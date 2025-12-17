<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Models\User;
use App\Models\UserPlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;

class SocialAuthController extends Controller
{
    public function redirectToProvider(Request $request, $provider)
    {
        // Store extension flag in session for callback
        if ($request->has('extension')) {
            session(['oauth_from_extension' => true]);
        }

        if ($provider === 'google') {
            // Include YouTube scopes for comment moderation
            return Socialite::driver($provider)
                ->scopes(config('services.youtube.scopes'))
                ->with([
                    'access_type' => 'offline',      // Get refresh_token
                    'prompt' => 'consent',           // Force consent screen to get refresh_token
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

            // If Google login, also save YouTube connection
            if ($provider === 'google') {
                $this->saveYouTubeConnection($user, $socialUser);
            }

            // Check if request came from extension
            if (session('oauth_from_extension')) {
                session()->forget('oauth_from_extension');

                // Generate JWT token for extension
                $token = JWTAuth::fromUser($user);

                // Redirect to API endpoint with token in URL fragment (more secure)
                return redirect("/api/auth/extension-callback#token={$token}");
            }

            Auth::login($user);

            return redirect()->route('dashboard')->with('success', 'Login successful! YouTube connected.');
        } catch (\Exception $e) {
            \Log::error('OAuth error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
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

    public function logout()
    {
        Auth::logout();

        return redirect('/')->with('success', 'Logout successful!');
    }
}
