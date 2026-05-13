<?php

namespace App\Support;

class ProfanityFilter
{
    /** @var array<int, string>|null */
    private ?array $words = null;

    private ?string $pattern = null;

    /**
     * Whole-word, case-insensitive match against the configured word list.
     */
    public function contains(?string $text): bool
    {
        if ($text === null || $text === '') {
            return false;
        }

        $pattern = $this->pattern();
        if ($pattern === null) {
            return false;
        }

        return preg_match($pattern, $text) === 1;
    }

    /**
     * True if any string in the array (and any nested values) contains a banned
     * term. Useful for checking arrays of clues without flattening yourself.
     *
     * @param  array<int|string, mixed>  $values
     */
    public function containsAny(array $values): bool
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                if ($this->containsAny($value)) {
                    return true;
                }

                continue;
            }
            if (is_string($value) && $this->contains($value)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int, string> */
    public function words(): array
    {
        if ($this->words === null) {
            /** @var array<int, string> $configured */
            $configured = config('profanity.words', []);
            $this->words = array_values(array_filter(array_map(
                fn ($w) => strtolower(trim((string) $w)),
                $configured,
            )));
        }

        return $this->words;
    }

    private function pattern(): ?string
    {
        if ($this->pattern !== null) {
            return $this->pattern === '' ? null : $this->pattern;
        }

        $words = $this->words();
        if ($words === []) {
            $this->pattern = '';

            return null;
        }

        $alternatives = array_map(fn ($w) => preg_quote($w, '/'), $words);
        // \b ensures we only match whole words to avoid the "Scunthorpe problem".
        $this->pattern = '/\b('.implode('|', $alternatives).')\b/iu';

        return $this->pattern;
    }
}
