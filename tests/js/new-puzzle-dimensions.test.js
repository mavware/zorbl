import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { DIMENSION_SYNC_MS, newPuzzleDimensions } from '../../resources/js/new-puzzle-dimensions.js';

/** A fake $wire whose $set resolves only when the test says the response arrived. */
function makeWire(values = { newWidth: 15, newHeight: 15 }) {
    const wire = { ...values, watchers: {}, pending: [] };
    wire.$watch = vi.fn((property, callback) => { wire.watchers[property] = callback; });
    wire.$set = vi.fn((property, value) => new Promise((resolve) => {
        wire.pending.push({ property, value, resolve });
    }));
    // Answer the oldest request: the server settled on `settled` (defaults to the sent value).
    wire.respond = async (settled = {}) => {
        const request = wire.pending.shift();
        wire[request.property] = request.value;
        Object.assign(wire, settled);
        request.resolve();
        // Let the awaiting push run its continuation (fake timers, so no setTimeout here).
        await Promise.resolve();
        await Promise.resolve();
    };
    return wire;
}

function makeDimensions(wire) {
    const dimensions = newPuzzleDimensions({ oddSizeTypes: ['diamond'] });
    dimensions.$wire = wire;
    dimensions.init();
    return dimensions;
}

describe('newPuzzleDimensions', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('starts from the server values and sends a field once the pause has passed', () => {
        const wire = makeWire();
        const dimensions = makeDimensions(wire);

        expect(dimensions.width).toBe(15);

        dimensions.width = 16;
        dimensions.onInput('width');
        dimensions.width = 17;
        dimensions.onInput('width');
        vi.advanceTimersByTime(DIMENSION_SYNC_MS - 1);
        expect(wire.$set).not.toHaveBeenCalled();

        vi.advanceTimersByTime(1);
        expect(wire.$set).toHaveBeenCalledTimes(1);
        expect(wire.$set).toHaveBeenCalledWith('newWidth', 17);
    });

    it('keeps a click made while a request is in flight, which the old binding lost', async () => {
        const wire = makeWire();
        const dimensions = makeDimensions(wire);

        dimensions.width = 16;
        dimensions.onInput('width');
        vi.advanceTimersByTime(DIMENSION_SYNC_MS);
        expect(wire.$set).toHaveBeenLastCalledWith('newWidth', 16);

        // The next click lands before the response; the response then arrives.
        dimensions.width = 17;
        dimensions.onInput('width');
        wire.watchers.newWidth(16);
        await wire.respond();

        expect(dimensions.width).toBe(17);

        vi.advanceTimersByTime(DIMENSION_SYNC_MS);
        await wire.respond();

        expect(wire.$set).toHaveBeenLastCalledWith('newWidth', 17);
        expect(dimensions.width).toBe(17);
        expect(dimensions.dirty.width).toBe(false);
    });

    it('adopts what the server decided once nothing is pending, such as a diamond stepping to an odd size', async () => {
        const wire = makeWire();
        const dimensions = makeDimensions(wire);

        dimensions.width = 16;
        dimensions.onInput('width');
        vi.advanceTimersByTime(DIMENSION_SYNC_MS);
        await wire.respond({ newWidth: 17, newHeight: 17 });

        expect(dimensions.width).toBe(17);
        expect(dimensions.height).toBe(17);
    });

    it('lets a server-side change through only where no edit is pending', () => {
        const wire = makeWire();
        const dimensions = makeDimensions(wire);

        dimensions.height = 9;
        dimensions.onInput('height');
        wire.watchers.newWidth(11);
        wire.watchers.newHeight(11);

        expect(dimensions.width).toBe(11);
        expect(dimensions.height).toBe(9);
    });

    it('steps by two only for puzzle types whose size must stay odd', () => {
        const wire = makeWire({ newWidth: 15, newHeight: 15, puzzleType: 'standard' });
        const dimensions = makeDimensions(wire);

        expect(dimensions.step()).toBe(1);
        wire.puzzleType = 'diamond';
        expect(dimensions.step()).toBe(2);
    });

    it('does not send a field cleared mid-edit and restores it on blur', async () => {
        const wire = makeWire();
        const dimensions = makeDimensions(wire);

        dimensions.width = '';
        dimensions.onInput('width');
        vi.advanceTimersByTime(DIMENSION_SYNC_MS);
        expect(wire.$set).not.toHaveBeenCalled();

        dimensions.settle('width');
        expect(dimensions.width).toBe(15);
        expect(dimensions.dirty.width).toBe(false);
    });
});
