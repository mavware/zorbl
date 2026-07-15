<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Services\AnonymousUserManager;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * If the request comes from an anonymous "guest builder" session
     * (crosswordbuilder_anon cookie), the anonymous user row is upgraded in place so
     * that puzzles already created during the guest session stay attached.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            // Registration intentionally skips the "confirm password" double-entry —
            // password reset and the in-settings password change still require it
            // because a typo there locks the user out.
            'password' => $this->passwordRules(confirmed: false),
        ])->validate();

        $anonManager = app(AnonymousUserManager::class);
        $anon = $anonManager->findForRequest(request());

        if ($anon !== null) {
            $anon->forceFill([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => Hash::make($input['password']),
                'is_anonymous' => false,
                'anonymous_token' => null,
                'converted_at' => now(),
            ])->save();

            $anonManager->forgetCookie();

            return $anon->fresh();
        }

        return User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => $input['password'],
        ]);
    }
}
