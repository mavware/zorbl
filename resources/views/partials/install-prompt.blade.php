{{--
    Dismissible install banner. Two flavors:
      - Chromium / Android: clicking the button calls the captured
        beforeinstallprompt event to trigger the native install dialog.
      - iOS Safari: shows a brief "Tap Share → Add to Home Screen" tooltip,
        since iOS has no programmatic install API.

    Hidden when:
      - The app is already running standalone (matchMedia or navigator.standalone)
      - The user dismissed the banner (localStorage zorbl_install_dismissed_at)
      - No install signal is available (not iOS, no beforeinstallprompt fired)
--}}
<div
    x-data="{
        ready: false,
        installable: false,
        installed: false,
        isIos: false,
        showIosTip: false,
        init() {
            const pwa = window.zorblPwa;
            if (!pwa) return;
            if (pwa.isStandalone) return;

            const dismissed = (() => {
                try {
                    const v = localStorage.getItem('zorbl_install_dismissed_at');
                    if (!v) return false;
                    // Re-show after 60 days.
                    return (Date.now() - parseInt(v, 10)) < 60 * 24 * 60 * 60 * 1000;
                } catch { return false; }
            })();
            if (dismissed) return;

            this.isIos = pwa.isIos;
            if (this.isIos) {
                this.installable = true;
                this.ready = true;
                return;
            }
            if (pwa.deferredPrompt) {
                this.installable = true;
                this.ready = true;
            }
            window.addEventListener('zorbl-install-available', () => {
                this.installable = true;
                this.ready = true;
            });
            window.addEventListener('zorbl-installed', () => {
                this.installed = true;
                this.ready = false;
            });
        },
        async install() {
            if (this.isIos) {
                this.showIosTip = true;
                return;
            }
            const accepted = await window.zorblPwa.promptInstall();
            if (accepted) {
                this.ready = false;
                this.installed = true;
            }
        },
        dismiss() {
            try { localStorage.setItem('zorbl_install_dismissed_at', String(Date.now())); } catch {}
            this.ready = false;
        },
    }"
    x-show="ready && installable && !installed"
    x-transition.opacity.duration.300ms
    x-cloak
    role="dialog"
    aria-labelledby="install-banner-title"
    class="fixed bottom-4 left-4 z-[55] w-[calc(100%-2rem)] max-w-sm rounded-xl border border-zinc-800 bg-zinc-950/95 p-4 shadow-2xl backdrop-blur-lg sm:bottom-6 sm:left-6"
>
    <div class="flex items-start gap-3">
        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <p id="install-banner-title" class="text-sm font-semibold text-zinc-100">
                {{ __('Install :app', ['app' => config('app.name')]) }}
            </p>
            <p class="mt-1 text-xs text-zinc-400">
                <span x-show="!isIos">{{ __('Add it to your home screen for one-tap access and a full-screen solving experience.') }}</span>
                <span x-show="isIos" x-cloak>{{ __('Tap Share, then "Add to Home Screen" to install Zorbl on your iPhone or iPad.') }}</span>
            </p>
            <div class="mt-3 flex items-center gap-2">
                <button
                    type="button"
                    x-on:click="install()"
                    class="inline-flex items-center justify-center rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-zinc-950 hover:bg-amber-400 transition"
                >
                    <span x-show="!isIos">{{ __('Install') }}</span>
                    <span x-show="isIos" x-cloak>{{ __('Show me how') }}</span>
                </button>
                <button
                    type="button"
                    x-on:click="dismiss()"
                    class="inline-flex items-center justify-center rounded-lg border border-zinc-700 px-3 py-1.5 text-xs font-semibold text-zinc-100 hover:border-zinc-500 hover:bg-zinc-800 transition"
                >
                    {{ __('Not now') }}
                </button>
            </div>

            {{-- iOS step-by-step tip --}}
            <div x-show="showIosTip" x-cloak x-transition class="mt-3 rounded-lg border border-zinc-800 bg-zinc-900 p-3 text-xs text-zinc-300">
                <ol class="list-decimal space-y-1 pl-4">
                    <li>{{ __('Tap the Share icon in Safari\'s toolbar.') }}</li>
                    <li>{{ __('Scroll and tap "Add to Home Screen".') }}</li>
                    <li>{{ __('Tap "Add" — Zorbl will appear on your home screen.') }}</li>
                </ol>
            </div>
        </div>
    </div>
</div>
