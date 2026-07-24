@if(auth()->user()?->isAnonymous())
    <div class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-sm">
        <div class="text-amber-900 dark:text-amber-200">
            {{ __("You're building as a guest. Sign up to publish your puzzle and keep it forever.") }}
        </div>
        <a
            href="{{ route('register') }}"
            class="rounded-md bg-amber-500 px-3 py-1.5 text-xs font-semibold text-zinc-950 hover:bg-amber-400 transition"
        >
            {{ __('Sign up') }}
        </a>
    </div>
@endif
