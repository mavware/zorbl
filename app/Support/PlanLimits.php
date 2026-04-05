<?php

namespace App\Support;

class PlanLimits
{
    public function __construct(
        private bool $isPro,
        private bool $isGrandfathered = false,
    ) {}

    public function maxPuzzles(): int
    {
        if ($this->isPro) {
            return PHP_INT_MAX;
        }

        return $this->isGrandfathered ? 10 : 5;
    }

    public function monthlyAiFills(): int
    {
        return $this->isPro ? 50 : 0;
    }

    public function monthlyAiClues(): int
    {
        return $this->isPro ? 50 : 0;
    }

    public function maxFavoriteLists(): int
    {
        return $this->isPro ? PHP_INT_MAX : 3;
    }

    public function canExportPuz(): bool
    {
        return $this->isPro;
    }

    public function canExportJpz(): bool
    {
        return $this->isPro;
    }

    public function canExportPdf(): bool
    {
        return $this->isPro;
    }

    public function apiRateLimit(): int
    {
        return $this->isPro ? 120 : 60;
    }

    public function isPro(): bool
    {
        return $this->isPro;
    }
}
