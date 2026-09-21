<?php

use Livewire\Component;
use Livewire\Attributes\Title;

new #[Title('Appearance settings')] class extends Component {
    //
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Appearance settings') }}</h2>

    <x-pages::settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div x-data class="border-border-strong divide-hairline inline-flex h-10 divide-x overflow-hidden rounded-sm border" role="radiogroup" aria-label="{{ __('Appearance') }}">
            @foreach ([['value' => 'light', 'icon' => 'sun', 'label' => __('Light')], ['value' => 'dark', 'icon' => 'moon', 'label' => __('Dark')], ['value' => 'system', 'icon' => 'computer-desktop', 'label' => __('System')]] as $option)
                <label class="cursor-pointer">
                    <input type="radio" name="appearance" value="{{ $option['value'] }}" x-model="$flux.appearance" class="peer sr-only" />
                    <span class="font-classical text-ink-muted hover:text-ink peer-checked:bg-amber-400/10 peer-checked:text-amber-400 peer-focus-visible:outline-2 peer-focus-visible:-outline-offset-2 peer-focus-visible:outline-amber-400 flex h-full items-center gap-2 px-3.5 text-[15px] font-medium transition-colors">
                        <flux:icon :name="$option['icon']" class="size-4" />
                        {{ $option['label'] }}
                    </span>
                </label>
            @endforeach
        </div>
    </x-pages::settings.layout>
</section>
