<?php

namespace App\Support\Concerns;

trait FormatsTime
{
    /**
     * Format a duration in seconds as a human-readable string (e.g. "5:32" or "1:02:15").
     */
    public function formatTime(?int $seconds): string
    {
        if ($seconds === null) {
            return "\u{2014}";
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }
}
