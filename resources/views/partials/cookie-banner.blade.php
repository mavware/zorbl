@php
    /** @var \App\Support\CookieConsentManager $cookieManager */
    $cookieManager = app(\App\Support\CookieConsentManager::class);
@endphp

@if ($cookieManager->shouldShowBanner(request()))
    <div
        x-data="{
            visible: true,
            submitting: false,
            async choose(choice) {
                if (this.submitting) return;
                this.submitting = true;
                try {
                    const res = await fetch(@js(route('cookie-consent.store')), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]')?.content ?? '',
                        },
                        body: JSON.stringify({ choice }),
                    });
                    if (res.ok) {
                        this.visible = false;
                    }
                } catch (e) {
                    // Fail closed — leave the banner up so the user can retry.
                } finally {
                    this.submitting = false;
                }
            },
        }"
        x-show="visible"
        x-transition.opacity.duration.300ms
        x-cloak
        role="dialog"
        aria-labelledby="cookie-banner-title"
        aria-describedby="cookie-banner-body"
        class="fixed bottom-4 right-4 z-[60] w-[calc(100%-2rem)] max-w-sm rounded-xl border border-zinc-800 bg-zinc-950/95 p-4 shadow-2xl backdrop-blur-lg sm:bottom-6 sm:right-6"
    >
        <p id="cookie-banner-title" class="text-sm font-semibold text-zinc-100">{{ __('We use cookies') }}</p>
        <p id="cookie-banner-body" class="mt-1 text-xs text-zinc-400">
            {{ __('Strictly necessary cookies keep you signed in. We don\'t use marketing cookies, and analytics are off unless you opt in.') }}
            <a href="{{ route('legal.cookies') }}" class="text-amber-500 hover:underline">{{ __('Learn more') }}</a>
        </p>
        <div class="mt-3 flex items-center gap-2">
            <button
                type="button"
                @click="choose('{{ \App\Models\CookieConsent::CHOICE_REJECT_NON_ESSENTIAL }}')"
                :disabled="submitting"
                class="inline-flex flex-1 items-center justify-center rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-semibold text-zinc-100 hover:border-zinc-500 hover:bg-zinc-800 transition disabled:opacity-50"
            >
                {{ __('Reject') }}
            </button>
            <button
                type="button"
                @click="choose('{{ \App\Models\CookieConsent::CHOICE_ACCEPT_ALL }}')"
                :disabled="submitting"
                class="inline-flex flex-1 items-center justify-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-zinc-950 hover:bg-amber-400 transition disabled:opacity-50"
            >
                {{ __('Accept all') }}
            </button>
        </div>
    </div>
@endif
