<?php

if (! function_exists('copyright')) {
    function copyright($name): string
    {
        return filled($name) ? sprintf('© %s %s', now()->format('Y'), $name) : '';
    }
}
