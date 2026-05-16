@if(session()->has(\App\Http\Controllers\ImpersonationController::SESSION_KEY))
    <div class="fixed top-0 inset-x-0 z-[100] bg-amber-500 text-zinc-900 shadow-md">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-2 text-sm">
            <div class="flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                </svg>
                <span>{{ __('Impersonating') }} <strong>{{ auth()->user()->name }}</strong>.</span>
            </div>
            <form method="POST" action="{{ route('impersonate.stop') }}">
                @csrf
                <button type="submit" class="rounded-md bg-zinc-900 px-3 py-1 text-xs font-semibold text-white transition-colors hover:bg-zinc-800">
                    {{ __('Leave impersonation') }}
                </button>
            </form>
        </div>
    </div>
    <div class="h-10" aria-hidden="true"></div>
@endif
