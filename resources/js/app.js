import { collapsibleGrid } from './collapsible-grid.js';
import { crosswordGrid } from './crossword-grid.js';
import { crosswordSolver } from './crossword-solver.js';
import { passkeyConfirm, passkeyLogin, passkeyManager } from './passkeys.js';
import './pwa.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('collapsibleGrid', collapsibleGrid);
    Alpine.data('crosswordGrid', crosswordGrid);
    Alpine.data('crosswordSolver', crosswordSolver);
    Alpine.data('passkeyLogin', passkeyLogin);
    Alpine.data('passkeyConfirm', passkeyConfirm);
    Alpine.data('passkeyManager', passkeyManager);
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
