// Debounced autosave helper used by both the editor and solver Alpine
// components. The host owns the dirty flag (`isDirty`) and the indicator
// state (`saving`, `showSaved`); this factory just sequences debounce,
// flush, and the post-save acknowledgement timer.

const DEFAULT_DEBOUNCE_MS = 3000;
const DEFAULT_SAVED_FEEDBACK_MS = 2000;

/**
 * @param {Object} args
 * @param {() => Promise<void>} args.save           Async save action.
 * @param {() => boolean} args.isDirty              Read current dirty state.
 * @param {(dirty: boolean) => void} args.setDirty  Mutate dirty state.
 * @param {(saving: boolean) => void} args.setSaving
 * @param {(showSaved: boolean) => void} [args.setShowSaved]
 * @param {number} [args.debounceMs]
 * @param {number} [args.savedFeedbackMs]
 */
export function createAutosave({
    save,
    isDirty,
    setDirty,
    setSaving,
    setShowSaved,
    debounceMs = DEFAULT_DEBOUNCE_MS,
    savedFeedbackMs = DEFAULT_SAVED_FEEDBACK_MS,
}) {
    let saveTimer = null;
    let savedTimer = null;
    let inFlight = false;

    function scheduleSave() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(() => {
            saveTimer = null;
            flush();
        }, debounceMs);
    }

    async function flush() {
        if (!isDirty() || inFlight) return;

        inFlight = true;
        setSaving(true);
        setShowSaved?.(false);
        setDirty(false);

        try {
            await save();
        } catch (e) {
            // Re-mark so the next debounce / flush will retry.
            setDirty(true);
        } finally {
            inFlight = false;
            setSaving(false);
            if (isDirty()) scheduleSave();
        }
    }

    function acknowledge() {
        setSaving(false);
        setShowSaved?.(true);
        clearTimeout(savedTimer);
        savedTimer = setTimeout(() => {
            savedTimer = null;
            setShowSaved?.(false);
        }, savedFeedbackMs);
    }

    function destroy() {
        clearTimeout(saveTimer);
        clearTimeout(savedTimer);
        saveTimer = null;
        savedTimer = null;
    }

    return {
        scheduleSave,
        flush,
        acknowledge,
        destroy,
        isInFlight: () => inFlight,
    };
}

// Deep-clone a plain JSON-serialisable value for sending to Livewire.
// We deliberately use JSON round-trip (not structuredClone) because Alpine's
// reactive Proxy wrapping can trip structuredClone with "[object Array] could
// not be cloned" — and the payload is JSON-encoded by Livewire downstream
// anyway, so this is the format we have to support.
export function cloneForWire(value) {
    return JSON.parse(JSON.stringify(value));
}
