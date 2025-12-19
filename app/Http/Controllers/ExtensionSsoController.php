<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Tymon\JWTAuth\Facades\JWTAuth;

class ExtensionSsoController extends Controller
{
    /**
     * Show the extension authorization page.
     * User must be logged in via web session.
     */
    public function authorize(Request $request)
    {
        $user = Auth::user();

        return Inertia::render('Extension/Authorize', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Generate JWT token for extension.
     * This is called from the authorization page after user confirms.
     */
    public function generateToken(Request $request)
    {
        $user = Auth::user();

        // Generate JWT token with extended TTL for extension
        $token = JWTAuth::customClaims([
            'source' => 'extension',
            'ext_version' => $request->input('version', '1.0.0'),
        ])->fromUser($user);

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'plan' => $user->getCurrentPlan()?->name ?? 'Free',
            ],
        ]);
    }

    /**
     * Callback page that receives the token and passes it to extension.
     * This uses postMessage for secure cross-origin communication.
     */
    public function callback(Request $request)
    {
        $user = Auth::user();

        // Generate JWT token
        $token = JWTAuth::customClaims([
            'source' => 'extension',
        ])->fromUser($user);

        // Return a simple HTML page that sends token to extension via postMessage
        return response()->view('extension.callback', [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
