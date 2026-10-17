/**
 * The New Puzzle modal's width and height inputs.
 *
 * These used to be bound straight to Livewire with a debounce. That lost
 * clicks on the number spinners: the second click landed while the first
 * update's request was in flight, and when that response morphed the modal
 * it wrote the server's value back into the input before the debounced
 * send had read it, so the click vanished.
 *
 * Here the inputs are Alpine's: the typed value lives in `width` and
 * `height`, nothing the server sends can overwrite a field with an edit
 * pending, and each field is pushed to Livewire once the solver pauses.
 * When a push settles, fields with no newer edit adopt what the server
 * decided, which is how a square puzzle type copies the width into the
 * height and a diamond steps an even size to the next odd one.
 */

/** Pause after the last change before a field is sent to the server. */
export const DIMENSION_SYNC_MS = 400;

const PROPERTIES = { width: 'newWidth', height: 'newHeight' };

/**
 * @param {{ oddSizeTypes?: string[] }} options  Puzzle types whose size must be odd, so their spinner steps by two
 */
export function newPuzzleDimensions({ oddSizeTypes = [] } = {}) {
    const timers = {};

    return {
        width: null,
        height: null,
        oddSizeTypes,
        /** Fields edited since the server last confirmed them. */
        dirty: { width: false, height: false },
        /** Bumped per edit, so a push that settles after a newer edit never overrides it. */
        sequence: { width: 0, height: 0 },

        init() {
            this.width = this.$wire.newWidth;
            this.height = this.$wire.newHeight;

            // Changes the server makes on its own, such as a puzzle type switch
            // forcing a square, reach the inputs unless an edit is pending there.
            for (const [field, property] of Object.entries(PROPERTIES)) {
                this.$wire.$watch(property, (value) => {
                    if (!this.dirty[field]) this[field] = value;
                });
            }
        },

        /** The spinner step for the current puzzle type: two where the size must stay odd. */
        step() {
            return this.oddSizeTypes.includes(this.$wire.puzzleType) ? 2 : 1;
        },

        /** Every keystroke or spinner click: mark the field and restart its pause. */
        onInput(field) {
            this.dirty[field] = true;
            this.sequence[field]++;
            clearTimeout(timers[field]);
            timers[field] = setTimeout(() => this.push(field), DIMENSION_SYNC_MS);
        },

        async push(field) {
            const value = this[field];

            // A field cleared mid-edit waits for a number.
            if (!Number.isInteger(value)) return;

            const sequence = this.sequence[field];
            await this.$wire.$set(PROPERTIES[field], value);

            // Edited again meanwhile, sent or not: that edit's own push settles things.
            if (sequence !== this.sequence[field]) return;

            this.dirty[field] = false;
            this.adopt();
        },

        /** Take the server's settled values wherever no edit is pending. */
        adopt() {
            for (const [field, property] of Object.entries(PROPERTIES)) {
                if (!this.dirty[field]) this[field] = this.$wire[property];
            }
        },

        /** Leaving a field blank or with junk falls back to the server's value. */
        settle(field) {
            if (!Number.isInteger(this[field])) {
                this.dirty[field] = false;
                this[field] = this.$wire[PROPERTIES[field]];
            }
        },
    };
}
