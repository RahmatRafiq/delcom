<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use App\Services\InstagramService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirectToProvider($provider)
    {
        if ($provider === 'google') {
            // Include YouTube scopes for comment moderation
            return Socialite::driver($provider)
                ->scopes(config('services.youtube.scopes'))
                ->with([
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                ])
                ->redirect();
        }

        if ($provider === 'facebook') {
            // Include Instagram permissions
            return Socialite::driver($provider)
                ->scopes(config('services.facebook.scopes'))
                ->with([
                    'auth_type' => 'rerequest',
                ])
                ->redirect();
        }

        return Socialite::driver($provider)->redirect();
    }

    public function handleProviderCallback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
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
                    'access_token' => $pageAccessToken, // Use page token for IG API
                    'token_expires_at' => now()->addDays(60), // FB long-lived tokens ~60 days
                    'scopes' => config('services.facebook.scopes'),
                    'is_active' => true,
                    'auto_moderation_enabled' => false,
                    'scan_frequency_minutes' => 60,
                    'metadata' => [
                        'facebook_page_id' => $page['id'],
                        'facebook_page_name' => $page['name'],
                        'ig_followers' => $igAccount['followers_count'] ?? 0,
                    ],
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
