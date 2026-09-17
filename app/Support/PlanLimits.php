<?php

namespace App\Support;

class PlanLimits
{
    public function __construct(
        private bool $isPro,
        private bool $isGrandfathered = false,
        private bool $isAnonymous = false,
    ) {}

    public function maxPuzzles(): int
    {
        if ($this->isAnonymous) {
            return 1;
        }

        return PHP_INT_MAX;
    }

    public function monthlyAiFills(): int
    {
        if ($this->isAnonymous) {
            return 0;
        }

        return $this->isPro ? 50 : 0;
    }

    public function monthlyAiClues(): int
    {
        if ($this->isAnonymous) {
            return 0;
        }

        return $this->isPro ? 50 : 0;
    }

    public function maxFavoriteLists(): int
    {
        if ($this->isAnonymous) {
            return 0;
        }

        return PHP_INT_MAX;
    }

    public function canExportPuz(): bool
    {
        return ! $this->isAnonymous;
    }

    public function canExportJpz(): bool
    {
        return ! $this->isAnonymous;
    }

    public function canExportPdf(): bool
    {
        return ! $this->isAnonymous;
    }

    public function apiRateLimit(): int
    {
        if ($this->isAnonymous) {
            return 30;
        }

        return $this->isPro ? 120 : 60;
    }

    public function isPro(): bool
    {
        return $this->isPro;
    }

    public function isAnonymous(): bool
    {
        return $this->isAnonymous;
    }
}
