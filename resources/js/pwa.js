/*
 * Register the service worker and capture the `beforeinstallprompt` event so
 * we can surface a custom install button later. Stores the deferred prompt on
 * window.crosswordbuilderPwa so the Alpine install banner can call it on click.
 */

const isStandalone = () =>
    window.matchMedia?.('(display: standalone)').matches
    || window.navigator.standalone === true;

const isIos = () => {
    const ua = window.navigator.userAgent || '';
    return /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
};

window.crosswordbuilderPwa = {
    deferredPrompt: null,
    isStandalone: isStandalone(),
    isIos: isIos(),

    /**
     * Trigger the captured browser install prompt. Returns true if the user
     * accepted, false if they dismissed it or no prompt is available.
     */
    async promptInstall() {
        const prompt = this.deferredPrompt;
        if (!prompt) return false;
        this.deferredPrompt = null;
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            return choice?.outcome === 'accepted';
        } catch {
            return false;
        }
    },
};

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.crosswordbuilderPwa.deferredPrompt = e;
    window.dispatchEvent(new CustomEvent('crosswordbuilder-install-available'));
});

window.addEventListener('appinstalled', () => {
    window.crosswordbuilderPwa.deferredPrompt = null;
    window.crosswordbuilderPwa.isStandalone = true;
    window.dispatchEvent(new CustomEvent('crosswordbuilder-installed'));
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' }).catch(() => {});
    });
}
