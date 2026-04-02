import { crosswordGrid } from './crossword-grid.js';
import { crosswordSolver } from './crossword-solver.js';

document.addEventListener('alpine:init', () => {
    Alpine.data('crosswordGrid', crosswordGrid);
    Alpine.data('crosswordSolver', crosswordSolver);
});
