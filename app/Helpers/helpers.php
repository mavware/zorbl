<?php

if (! function_exists('copyright')) {
    function copyright($name): string
    {
        return filled($name) ? sprintf('© %s %s', now()->format('Y'), $name) : '';
    }
}

if (! function_exists('app_version')) {
    function app_version(): string
    {
        return config('app.version', '1.0.0');
    }
}
