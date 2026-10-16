/**
 * The meta answer entry: the row of blanks a solver types the puzzle's final
 * answer into. The server hands over only the answer's shape (its pattern:
 * `_` per letter or digit, spaces and punctuation kept, e.g. "____ '_' ____"),
 * so the page never holds the answer itself.
 *
 * One visually hidden input collects the keystrokes; the blanks are drawn
 * from its sanitised value, like a one-time-code field. There is no Submit
 * button: the moment the last blank fills, the answer is checked. Checking
 * rebuilds the full string by pouring the typed letters back into the
 * pattern, so the server receives "ROCK 'N' ROLL", not "ROCKNROLL". A
 * wrong answer comes back with its letters still in place (`initial` and
 * `submitted` both set), so the solver backs up, changes some, and the
 * entry checks again once it is full and different.
 */

/** Is this character one the solver has to supply? */
export function isMetaAnswerSlot(char) {
    return /[\p{L}\p{N}]/u.test(char);
}

/**
 * Split a pattern into renderable pieces.
 *
 * @returns {Array<{type: 'slot'|'gap'|'literal', char: string, index: number}>}
 *   `index` counts slots only; gaps and literals carry the slot index that follows them.
 */
export function parseMetaAnswerPattern(pattern) {
    const pieces = [];
    let index = 0;

    for (const char of String(pattern ?? '')) {
        if (char === '_') {
            pieces.push({ type: 'slot', char: '_', index: index++ });
        } else if (char === ' ') {
            pieces.push({ type: 'gap', char: ' ', index });
        } else {
            pieces.push({ type: 'literal', char, index });
        }
    }

    return pieces;
}

/**
 * Group the pieces into words at the gaps, so the page can wrap between
 * words rather than in the middle of one. Gaps themselves are dropped.
 *
 * @returns {Array<Array<{type: 'slot'|'literal', char: string, index: number}>>}
 */
export function groupMetaAnswerWords(pieces) {
    const words = [[]];

    for (const piece of pieces) {
        if (piece.type === 'gap') {
            if (words[words.length - 1].length > 0) words.push([]);
            continue;
        }

        words[words.length - 1].push(piece);
    }

    return words.filter((word) => word.length > 0);
}

/** Keep only letters and digits, upper-cased, cut to the slot count. */
export function sanitizeMetaAnswerLetters(raw, slotCount) {
    const letters = Array.from(String(raw ?? '')).filter(isMetaAnswerSlot).join('').toUpperCase();

    return Array.from(letters).slice(0, slotCount).join('');
}

/** Pour typed letters back into the pattern; unfilled slots become empty. */
export function mergeMetaAnswerLetters(pattern, letters) {
    const chars = Array.from(letters);
    let next = 0;

    return Array.from(String(pattern ?? '')).map((char) => {
        if (char !== '_') return char;

        return chars[next++] ?? '';
    }).join('');
}

export function metaAnswerEntry({ pattern, initial = '', locked = false, submitted = null, autofocus = false }) {
    const pieces = parseMetaAnswerPattern(pattern);
    const slotCount = pieces.filter((piece) => piece.type === 'slot').length;

    return {
        pattern,
        pieces,
        words: groupMetaAnswerWords(pieces),
        slotCount,
        locked,
        letters: sanitizeMetaAnswerLetters(initial, slotCount),
        /** The last answer sent, so a full row is never re-sent unchanged. */
        submittedValue: submitted === null ? null : mergeMetaAnswerLetters(pattern, sanitizeMetaAnswerLetters(submitted, slotCount)),
        focused: false,
        submitting: false,

        /**
         * A card swapped in after a wrong answer takes the keyboard straight
         * back, so the solver can fix a letter without clicking again. The
         * open card never grabs focus: the grid keeps it while solving.
         */
        init() {
            if (autofocus && !locked) {
                this.$nextTick(() => this.focusInput());
            }
        },

        get isComplete() {
            return this.slotCount > 0 && this.letters.length === this.slotCount;
        },

        /** The slot the next keystroke lands in, for the caret styling. */
        get activeIndex() {
            return this.letters.length;
        },

        letterAt(index) {
            return Array.from(this.letters)[index] ?? '';
        },

        /** The full answer string to send: letters poured into the pattern. */
        value() {
            return mergeMetaAnswerLetters(this.pattern, this.letters);
        },

        /**
         * Mirror the hidden input into the blanks after every keystroke, and
         * check the answer as soon as the last blank fills.
         */
        onInput(event) {
            if (this.locked) return;

            this.letters = sanitizeMetaAnswerLetters(event.target.value, this.slotCount);
            if (event.target.value !== this.letters) {
                event.target.value = this.letters;
            }

            if (this.isComplete) {
                this.submit();
            }
        },

        focusInput() {
            if (this.locked) return;

            this.$refs.input?.focus();
        },

        async submit() {
            if (this.locked || !this.isComplete || this.submitting) return;

            const value = this.value();
            if (value === this.submittedValue) return;

            this.submitting = true;
            this.submittedValue = value;
            try {
                this.$wire.metaAnswerSubmission = value;
                await this.$wire.submitMetaAnswer();
            } finally {
                this.submitting = false;
            }
        },
    };
}
