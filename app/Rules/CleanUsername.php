<?php

namespace App\Rules;

use App\Support\ProfanityFilter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class CleanUsername implements ValidationRule
{
    public function __construct(private readonly ProfanityFilter $filter) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if ($this->filter->contains($value)) {
            $fail(__('Please choose a different name — that one isn\'t allowed.'));
        }
    }
}
