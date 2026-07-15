@props(['keys', 'description'])

<div class="flex items-center justify-between gap-4">
    <span class="text-sm text-fg">{{ $description }}</span>
    <span class="flex shrink-0 items-center gap-1">
        @foreach(explode(' + ', $keys) as $key)
            <kbd class="inline-flex min-w-[1.5rem] items-center justify-center rounded bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">{{ trim($key) }}</kbd>
            @if(!$loop->last)
                <span class="text-xs text-zinc-400">+</span>
            @endif
        @endforeach
    </span>
</div>
