{{-- The strip runs the full window width; its content sits in the same
     centred column as the page roots below it (app.css). --}}
@if(auth()->user()?->isAnonymous())
    <div class="mb-3 border-y border-amber-500/30 bg-amber-500/10 px-6 py-2.5 text-sm lg:px-8" data-full-bleed data-banner>
        <div class="mx-auto flex w-full max-w-7xl flex-wrap items-center justify-between gap-3" data-banner-content>
            <div class="text-amber-900 dark:text-amber-200">
                {{ __("You're building as a guest. Sign in to keep your puzzle forever.") }}
            </div>
            <a
                href="{{ route('register') }}"
                class="btn-amber-outline rounded-md px-3 py-1.5 text-xs font-semibold transition"
            >
                {{ __('Sign in') }}
            </a>
        </div>
    </div>
@endif
