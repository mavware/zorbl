import { Passkeys, UserCancelledError } from '@laravel/passkeys';

const rememberMeChecked = () => {
    const box = document.querySelector('input[name="remember"]');

    return box instanceof HTMLInputElement ? box.checked : true;
};

const followRedirect = (response) => {
    if (response?.redirect) {
        window.location.href = response.redirect;
    }
};

/**
 * Sign-in page: an explicit "Sign in with a passkey" button plus browser
 * autofill (conditional mediation) anchored to the email field, which needs
 * `autocomplete="email webauthn"` for the picker to appear.
 */
export const passkeyLogin = () => ({
    supported: Passkeys.isSupported(),
    loading: false,
    error: null,

    init() {
        if (this.supported) {
            Passkeys.autofill({ remember: rememberMeChecked }).then(followRedirect).catch(() => {});
        }
    },

    async verify() {
        this.error = null;
        this.loading = true;

        try {
            Passkeys.cancel();
            followRedirect(await Passkeys.verify({ remember: rememberMeChecked }));
        } catch (e) {
            if (!(e instanceof UserCancelledError)) {
                this.error = e.message || 'Passkey sign-in failed.';
            }
        } finally {
            this.loading = false;
        }
    },
});

/**
 * Password-confirmation page: satisfy the "confirm your password" gate with a
 * passkey instead, using Fortify's confirmation endpoints.
 */
export const passkeyConfirm = () => ({
    supported: Passkeys.isSupported(),
    loading: false,
    error: null,

    async confirm() {
        this.error = null;
        this.loading = true;

        try {
            const response = await Passkeys.verify({
                routes: { options: '/passkeys/confirm/options', submit: '/passkeys/confirm' },
            });
            followRedirect(response);
        } catch (e) {
            if (!(e instanceof UserCancelledError)) {
                this.error = e.message || 'Passkey confirmation failed.';
            }
        } finally {
            this.loading = false;
        }
    },
});

/**
 * Security settings: register a new passkey for the signed-in user. Emits
 * `passkey-registered` so the Livewire list can refresh.
 */
export const passkeyManager = () => ({
    supported: Passkeys.isSupported(),
    registering: false,
    passkeyName: '',
    error: null,

    async register() {
        const name = this.passkeyName.trim();
        if (!name) return;

        this.error = null;
        this.registering = true;

        try {
            await Passkeys.register({ name });
            this.passkeyName = '';
            this.$dispatch('passkey-registered');
        } catch (e) {
            if (!(e instanceof UserCancelledError)) {
                this.error = e.message || 'Could not add this passkey.';
            }
        } finally {
            this.registering = false;
        }
    },
});
