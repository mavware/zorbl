<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class GoogleController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (InvalidStateException) {
            return redirect()->route('login')
                ->with('status', __('Google authentication failed. Please try again.'));
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            Auth::login($user, remember: true);

            return redirect()->intended(config('fortify.home'));
        }

        $existingUser = User::where('email', $googleUser->getEmail())->first();

        if ($existingUser) {
            $existingUser->update(['google_id' => $googleUser->getId()]);
            Auth::login($existingUser, remember: true);

            return redirect()->intended(config('fortify.home'));
        }

        $user = User::create([
            'name' => $googleUser->getName(),
            'email' => $googleUser->getEmail(),
            'google_id' => $googleUser->getId(),
            'password' => Str::random(32),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        Auth::login($user, remember: true);

        return redirect()->intended(config('fortify.home'));
    }
}
