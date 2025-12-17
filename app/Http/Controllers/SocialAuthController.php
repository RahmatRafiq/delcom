<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\Platforms\Instagram\InstagramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;

class SocialAuthController extends Controller
{
    public function redirectToProvider(Request $request, $provider)
    {
        $hasExtensionParam = $request->has('extension');
        Log::info('OAuth redirect started', [
            'provider' => $provider,
            'has_extension_param' => $hasExtensionParam,
            'all_params' => $request->all(),
        ]);

        // Build the OAuth redirect
        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            $driver = $driver
                ->scopes(config('services.youtube.scopes'))
                ->with([
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                ]);
        }

        if ($provider === 'facebook') {
            $driver = $driver
                ->scopes(config('services.facebook.scopes'))
                ->with([
                    'auth_type' => 'rerequest',
                ]);
        }

        // If from extension, store in cache with unique state
        if ($hasExtensionParam) {
            $state = Str::random(40);
            Cache::put("oauth_extension_state:{$state}", true, now()->addMinutes(10));
            Log::info('Extension OAuth: storing state in cache', ['state' => $state]);

            // Add state to OAuth request
            $driver = $driver->with(['state' => $state]);
        }

        return $driver->redirect();
    }

    public function handleProviderCallback(Request $request, $provider)
    {
        try {
            // Get state from request to check if from extension
            $state = $request->get('state');
            $isFromExtension = $state && Cache::pull("oauth_extension_state:{$state}");

            Log::info('OAuth callback received', [
                'provider' => $provider,
                'state' => $state,
                'is_from_extension' => $isFromExtension,
            ]);

            // Use stateless() since we're managing state ourselves for extension
            $driver = Socialite::driver($provider);
            if ($isFromExtension) {
                $driver = $driver->stateless();
            }

            $socialUser = $driver->user();
            $user = User::where('email', $socialUser->getEmail())->first();

            if (! $user) {
                $user = User::create([
                    'name' => $socialUser->getName(),
                    'email' => $socialUser->getEmail(),
                    'provider' => $provider,
                    'provider_id' => $socialUser->getId(),
                    'password' => Hash::make(uniqid()),
                ]);

                // Assign default role for new users
                $user->assignRole('user');
            }

            $message = 'Login successful!';

            // Save platform connections based on provider
            if ($provider === 'google') {
                $this->saveYouTubeConnection($user, $socialUser);
                $message = 'Login successful! YouTube connected.';
            }

            if ($provider === 'facebook') {
                $igConnected = $this->saveInstagramConnection($user, $socialUser);
                $message = $igConnected
                    ? 'Login successful! Instagram connected.'
                    : 'Login successful! No Instagram Business account found.';
            }

            Log::info('OAuth user authenticated', [
                'user_email' => $user->email,
                'is_from_extension' => $isFromExtension,
            ]);

            if ($isFromExtension) {
                // Generate JWT token for extension
                $token = JWTAuth::fromUser($user);

                Log::info('Redirecting to extension callback', [
                    'token_length' => \strlen($token),
                ]);

                // Redirect to API endpoint with token in URL fragment
                return redirect("/api/auth/extension-callback#token={$token}");
            }

            Auth::login($user);

            return redirect()->route('dashboard')->with('success', $message);
        } catch (\Exception $e) {
            Log::error('OAuth error', [
                'provider' => $provider,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return redirect('/')->with('error', 'An error occurred during login: '.$e->getMessage());
        }
    }

    /**
     * Save YouTube connection from Google OAuth
     */
    private function saveYouTubeConnection(User $user, $socialUser): void
    {
        $platform = Platform::where('name', 'youtube')->first();

        if (! $platform) {
            return;
        }

        // Get tokens from social user
        $accessToken = $socialUser->token;
        $refreshToken = $socialUser->refreshToken;
        $expiresIn = $socialUser->expiresIn;

        // Fetch YouTube channel info
        $channelInfo = $this->fetchYouTubeChannelInfo($accessToken);

        if (! $channelInfo) {
            // User may not have a YouTube channel
            return;
        }

        // Save or update user platform connection
        UserPlatform::updateOrCreate(
            [
                'user_id' => $user->id,
                'platform_id' => $platform->id,
            ],
            [
                'platform_user_id' => $channelInfo['channel_id'],
                'platform_username' => $channelInfo['channel_title'],
                'platform_channel_id' => $channelInfo['channel_id'],
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
                'scopes' => config('services.youtube.scopes'),
                'is_active' => true,
                'auto_moderation_enabled' => false,
                'scan_frequency_minutes' => 60,
            ]
        );
    }

    /**
     * Fetch YouTube channel info using access token
     */
    private function fetchYouTubeChannelInfo(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/youtube/v3/channels', [
                    'part' => 'snippet',
                    'mine' => 'true',
                ]);

            if ($response->successful()) {
                $data = $response->json();

                if (! empty($data['items'])) {
                    $channel = $data['items'][0];

                    return [
                        'channel_id' => $channel['id'],
                        'channel_title' => $channel['snippet']['title'],
                    ];
                }
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Save Instagram connection from Facebook OAuth
     */
    private function saveInstagramConnection(User $user, $socialUser): bool
    {
        $platform = Platform::where('name', 'instagram')->first();

        if (! $platform) {
            Log::warning('Instagram platform not found in database');

            return false;
        }

        $accessToken = $socialUser->token;

        // Get Facebook Pages with linked Instagram accounts
        $pages = InstagramService::getFacebookPages($accessToken);

        if (empty($pages)) {
            Log::info('No Facebook Pages found for user', ['user_id' => $user->id]);

            return false;
        }

        $connected = false;

        foreach ($pages as $page) {
            // Check if page has Instagram Business account
            if (! isset($page['instagram_business_account'])) {
                continue;
            }

            $igAccount = $page['instagram_business_account'];
            $pageAccessToken = $page['access_token']; // Page-specific token

            // Save Instagram connection
            UserPlatform::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'platform_id' => $platform->id,
                    'platform_user_id' => $igAccount['id'],
                ],
                [
                    'platform_username' => $igAccount['username'] ?? $igAccount['name'] ?? 'Unknown',
                    'connection_method' => 'api',
                    'access_token' => $pageAccessToken,
                    'token_expires_at' => now()->addDays(60),
                    'scopes' => config('services.facebook.scopes'),
                    'is_active' => true,
                    'auto_moderation_enabled' => false,
                    'scan_frequency_minutes' => 60,
                ]
            );

            Log::info('Instagram account connected', [
                'user_id' => $user->id,
                'ig_username' => $igAccount['username'] ?? 'unknown',
                'ig_id' => $igAccount['id'],
            ]);

            $connected = true;
        }

        return $connected;
    }

    public function logout()
    {
        Auth::logout();

        return redirect('/')->with('success', 'Logout successful!');
    }
}
