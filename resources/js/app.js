import { crosswordGrid } from './crossword-grid.js';
import { crosswordSolver } from './crossword-solver.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('crosswordGrid', crosswordGrid);
    Alpine.data('crosswordSolver', crosswordSolver);
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
