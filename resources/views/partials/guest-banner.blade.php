@if(auth()->user()?->isAnonymous())
    <div class="mx-6 mb-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-sm lg:mx-8" data-full-bleed>
        <div class="text-amber-900 dark:text-amber-200">
            {{ __("You're building as a guest. Sign up to publish your puzzle and keep it forever.") }}
        </div>
        <a
            href="{{ route('register') }}"
            class="btn-amber-outline rounded-md px-3 py-1.5 text-xs font-semibold transition"
        >
            {{ __('Sign up') }}
        </a>
    </div>
@endif
