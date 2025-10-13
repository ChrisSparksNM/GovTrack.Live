<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback
     */
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            
            // Check if user already exists with this Google ID
            $existingUser = User::where('google_id', $googleUser->getId())->first();
            
            if ($existingUser) {
                // User exists with Google ID, log them in
                Auth::login($existingUser);
                return redirect()->intended(route('dashboard'));
            }
            
            // Check if user exists with this email
            $existingEmailUser = User::where('email', $googleUser->getEmail())->first();
            
            if ($existingEmailUser) {
                // User exists with email but no Google ID, link the accounts
                $existingEmailUser->update([
                    'google_id' => $googleUser->getId()
                ]);
                
                Auth::login($existingEmailUser);
                return redirect()->intended(route('dashboard'));
            }
            
            // Create new user
            $newUser = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'password' => Hash::make(Str::random(24)), // Random password since they'll use Google
            ]);
            
            Auth::login($newUser);
            
            return redirect()->intended(route('dashboard'));
            
        } catch (\Exception $e) {
            return redirect()->route('login')->with('error', 'Google authentication failed. Please try again.');
        }
    }
}