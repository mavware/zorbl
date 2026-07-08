<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @param  bool  $confirmed  Whether to require the password confirmation field.
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(bool $confirmed = true): array
    {
        $rules = ['required', 'string', Password::default()];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
