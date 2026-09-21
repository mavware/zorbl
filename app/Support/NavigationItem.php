<?php

namespace App\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * A single link in the app navigation, rendered by whichever chrome (sidebar
 * or top bar) the app is configured to use.
 */
final readonly class NavigationItem
{
    public function __construct(
        public string $label,
        public string $icon,
        public string $href,
        public bool $current = false,
        public bool $navigate = true,
    ) {}

    /**
     * Extra attributes for the rendered link: wire:navigate for in-app pages,
     * nothing for links that leave the Livewire app (e.g. the admin panel).
     */
    public function attributes(): ComponentAttributeBag
    {
        return new ComponentAttributeBag($this->navigate ? ['wire:navigate' => true] : []);
    }
}
