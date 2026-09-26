import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { crosswordGrid } from '../../resources/js/crossword-grid.js';

function makeGrid(checkClueQuality) {
    const inst = crosswordGrid({
        width: 3,
        height: 1,
        grid: [[1, 0, 0]],
        solution: [['C', 'A', 'T']],
        styles: {},
        cluesAcross: [{ number: 1, clue: 'Cat nap spot' }],
        cluesDown: [],
        minAnswerLength: 2,
        prefilled: null,
        gridLocked: false,
        puzzleType: 'classic',
    });
    inst.$watch = () => {};
    inst.$nextTick = () => {};
    inst.$wire = { checkClueQuality };
    return inst;
}

describe('clue quality checks', () => {
    beforeEach(() => { vi.useFakeTimers(); });
    afterEach(() => { vi.useRealTimers(); });

    it('sends each clue with its current answer and stores the issues by slot', async () => {
        const issue = { code: 'answer_in_clue', severity: 'error', message: 'Clue contains the answer' };
        const check = vi.fn().mockResolvedValue({ 'across-1': [issue] });
        const inst = makeGrid(check);

        await inst.checkClueQuality();

        expect(check).toHaveBeenCalledWith([
            { direction: 'across', number: 1, clue: 'Cat nap spot', answer: 'CAT' },
        ]);
        expect(inst.clueQuality(inst.cluesAcross[0], 'across')).toEqual([issue]);
        expect(inst.clueQualityIcon(inst.cluesAcross[0], 'across')).toBe('error');
        expect(inst.clueQualityTooltip(inst.cluesAcross[0], 'across')).toBe('Clue contains the answer');
    });

    it('shows a warning icon when every issue is a warning', async () => {
        const inst = makeGrid(vi.fn().mockResolvedValue({
            'across-1': [{ code: 'cross_reference', severity: 'warning', message: 'Depends on another clue (2-Down)' }],
        }));

        await inst.checkClueQuality();

        expect(inst.clueQualityIcon(inst.cluesAcross[0], 'across')).toBe('warning');
    });

    it('debounces rapid edits into a single check', async () => {
        const check = vi.fn().mockResolvedValue({});
        const inst = makeGrid(check);

        inst.scheduleClueQualityCheck();
        inst.scheduleClueQualityCheck();
        inst.scheduleClueQualityCheck();
        await vi.runAllTimersAsync();

        expect(check).toHaveBeenCalledTimes(1);
    });

    it('ignores a slower response that arrives after a newer one', async () => {
        let resolveFirst;
        const check = vi.fn()
            .mockImplementationOnce(() => new Promise((r) => { resolveFirst = r; }))
            .mockResolvedValueOnce({});
        const inst = makeGrid(check);

        const first = inst.checkClueQuality();
        await inst.checkClueQuality();
        resolveFirst({ 'across-1': [{ code: 'too_short', severity: 'warning', message: 'stale' }] });
        await first;

        expect(inst.clueIssues).toEqual({});
    });

    it('hides issues for a clue that has since been cleared', async () => {
        const inst = makeGrid(vi.fn().mockResolvedValue({
            'across-1': [{ code: 'too_short', severity: 'warning', message: 'Clue may be too short' }],
        }));
        await inst.checkClueQuality();

        inst.cluesAcross[0].clue = '';

        expect(inst.clueQualityIcon(inst.cluesAcross[0], 'across')).toBe('');
    });
});
