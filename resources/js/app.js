import { crosswordGrid } from './crossword-grid.js';
import { crosswordSolver } from './crossword-solver.js';
import { Passkeys, UserCancelledError } from '@laravel/passkeys';
import './pwa.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('crosswordGrid', crosswordGrid);
    Alpine.data('crosswordSolver', crosswordSolver);

    Alpine.data('passkeyLogin', () => ({
        supported: Passkeys.isSupported(),
        loading: false,
        error: null,
        async verify() {
            this.error = null;
            this.loading = true;
            try {
                const response = await Passkeys.verify();
                if (response.redirect) {
                    window.location.href = response.redirect;
                }
            } catch (e) {
                if (e instanceof UserCancelledError) return;
                this.error = e.message || 'Passkey authentication failed.';
            } finally {
                this.loading = false;
            }
        },
        async autofill() {
            if (!this.supported) return;
            try {
                const response = await Passkeys.autofill();
                if (response?.redirect) {
                    window.location.href = response.redirect;
                }
            } catch {
                // Autofill failures are silent
            }
        },
    }));

    Alpine.data('passkeyManager', () => ({
        supported: Passkeys.isSupported(),
        registering: false,
        passkeyName: '',
        error: null,
        async register() {
            if (!this.passkeyName.trim()) return;
            this.error = null;
            this.registering = true;
            try {
                await Passkeys.register({ name: this.passkeyName.trim() });
                this.passkeyName = '';
                this.$dispatch('passkey-registered');
            } catch (e) {
                if (e instanceof UserCancelledError) return;
                this.error = e.message || 'Failed to register passkey.';
            } finally {
                this.registering = false;
            }
        },
    }));
});

// Bridge Alpine `notify` events (dispatched by autofill, AI fill, and clue generation)
// to Flux toasts. Maps our internal `type` to Flux's `variant` vocabulary.
window.addEventListener('notify', (event) => {
    if (typeof window.Flux?.toast !== 'function') return;

    const { message, type } = event.detail ?? {};
    if (!message) return;

    const variant = type === 'error' ? 'danger' : (type ?? 'success');

    window.Flux.toast({ text: message, variant });
});
