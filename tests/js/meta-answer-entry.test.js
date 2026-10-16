import { describe, expect, it, vi } from 'vitest';
import {
    groupMetaAnswerWords,
    mergeMetaAnswerLetters,
    metaAnswerEntry,
    parseMetaAnswerPattern,
    sanitizeMetaAnswerLetters,
} from '../../resources/js/meta-answer-entry.js';

function makeEntry(options) {
    const entry = metaAnswerEntry(options);
    entry.$refs = { input: { focus: vi.fn() } };
    entry.$wire = { metaAnswerSubmission: '', submitMetaAnswer: vi.fn(async () => {}) };
    return entry;
}

describe('parseMetaAnswerPattern', () => {
    it('turns blanks into slots and keeps spaces and punctuation as gaps and literals', () => {
        expect(parseMetaAnswerPattern("__ '_' ___")).toEqual([
            { type: 'slot', char: '_', index: 0 },
            { type: 'slot', char: '_', index: 1 },
            { type: 'gap', char: ' ', index: 2 },
            { type: 'literal', char: "'", index: 2 },
            { type: 'slot', char: '_', index: 2 },
            { type: 'literal', char: "'", index: 3 },
            { type: 'gap', char: ' ', index: 3 },
            { type: 'slot', char: '_', index: 3 },
            { type: 'slot', char: '_', index: 4 },
            { type: 'slot', char: '_', index: 5 },
        ]);
    });
});

describe('groupMetaAnswerWords', () => {
    it('splits the pieces into words at the gaps and drops the gaps', () => {
        const words = groupMetaAnswerWords(parseMetaAnswerPattern("__ '_'  ___"));

        expect(words.map((word) => word.map((piece) => piece.char).join(''))).toEqual(['__', "'_'", '___']);
        expect(words[2][0].index).toBe(3);
    });
});

describe('sanitizeMetaAnswerLetters', () => {
    it('keeps only letters and digits, upper-cased, cut to the slot count', () => {
        expect(sanitizeMetaAnswerLetters('ro-ck n 42', 6)).toBe('ROCKN4');
        expect(sanitizeMetaAnswerLetters('', 6)).toBe('');
        expect(sanitizeMetaAnswerLetters('café', 4)).toBe('CAFÉ');
    });
});

describe('mergeMetaAnswerLetters', () => {
    it('pours letters into the pattern and leaves unfilled slots empty', () => {
        expect(mergeMetaAnswerLetters('____ ____', 'ROCKROLL')).toBe('ROCK ROLL');
        expect(mergeMetaAnswerLetters("_'_", 'A')).toBe("A'");
    });
});

describe('metaAnswerEntry', () => {
    it('counts slots, seeds from a previous submission and reports completion', () => {
        const entry = makeEntry({ pattern: '______ _____', initial: 'hidden mess' });

        expect(entry.slotCount).toBe(11);
        expect(entry.words).toHaveLength(2);
        expect(entry.letters).toBe('HIDDENMESS');
        expect(entry.isComplete).toBe(false);
        expect(entry.activeIndex).toBe(10);
        expect(entry.letterAt(0)).toBe('H');
        expect(entry.letterAt(10)).toBe('');
    });

    it('mirrors the hidden input into the blanks and rewrites it when it held junk', () => {
        const entry = makeEntry({ pattern: '___' });
        const target = { value: 'a b-c d' };

        entry.onInput({ target });

        expect(entry.letters).toBe('ABC');
        expect(target.value).toBe('ABC');
        expect(entry.isComplete).toBe(true);
    });

    it('checks the answer the moment the last blank fills, poured back into the pattern', async () => {
        const entry = makeEntry({ pattern: "____ '_' ____" });

        entry.onInput({ target: { value: 'rocknrol' } });
        expect(entry.$wire.submitMetaAnswer).not.toHaveBeenCalled();

        entry.onInput({ target: { value: 'rocknroll' } });
        await Promise.resolve();

        expect(entry.$wire.metaAnswerSubmission).toBe("ROCK 'N' ROLL");
        expect(entry.$wire.submitMetaAnswer).toHaveBeenCalledTimes(1);
    });

    it('refuses to submit an incomplete answer', async () => {
        const entry = makeEntry({ pattern: '_____' });
        entry.onInput({ target: { value: 'abc' } });

        await entry.submit();

        expect(entry.$wire.submitMetaAnswer).not.toHaveBeenCalled();
    });

    it('never re-sends a full row that has not changed, but checks again once it has', async () => {
        const entry = makeEntry({ pattern: '___', initial: 'abc', submitted: 'ABC' });
        expect(entry.letters).toBe('ABC');

        await entry.submit();
        entry.onInput({ target: { value: 'ABC' } });
        await Promise.resolve();
        expect(entry.$wire.submitMetaAnswer).not.toHaveBeenCalled();

        entry.onInput({ target: { value: 'AB' } });
        entry.onInput({ target: { value: 'ABD' } });
        await Promise.resolve();

        expect(entry.$wire.metaAnswerSubmission).toBe('ABD');
        expect(entry.$wire.submitMetaAnswer).toHaveBeenCalledTimes(1);
    });

    it('ignores typing, focus and submit once locked', async () => {
        const entry = makeEntry({ pattern: '___', initial: 'ABC', locked: true });

        entry.onInput({ target: { value: 'XYZ' } });
        entry.focusInput();
        await entry.submit();

        expect(entry.letters).toBe('ABC');
        expect(entry.$refs.input.focus).not.toHaveBeenCalled();
        expect(entry.$wire.submitMetaAnswer).not.toHaveBeenCalled();
    });

    it('takes focus on init only when asked, as after a wrong answer', () => {
        const retry = makeEntry({ pattern: '___', initial: 'ABC', submitted: 'ABC', autofocus: true });
        retry.$nextTick = (fn) => fn();
        retry.init();
        expect(retry.$refs.input.focus).toHaveBeenCalledTimes(1);

        const open = makeEntry({ pattern: '___' });
        open.$nextTick = (fn) => fn();
        open.init();
        expect(open.$refs.input.focus).not.toHaveBeenCalled();
    });

    it('clicking the blanks focuses the hidden input', () => {
        const entry = makeEntry({ pattern: '___' });

        entry.focusInput();

        expect(entry.$refs.input.focus).toHaveBeenCalledTimes(1);
    });
});
