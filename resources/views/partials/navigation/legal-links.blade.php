{{-- Terms / Privacy / Cookies / DMCA. The caller supplies the wrapper's layout
     classes via $class. --}}
@inject('navigation', 'App\Support\AppNavigation')

<div class="{{ $class ?? '' }} flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-600" data-legal-links>
    @foreach ($navigation->legal() as $item)
        <a href="{{ $item->href }}" wire:navigate
           class="hover:text-zinc-700 dark:hover:text-zinc-400">{{ $item->label }}</a>
    @endforeach
</div>
