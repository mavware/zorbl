/**
 * The live PDF preview in the download modal.
 *
 * The preview is the real PDF: the server renders it with the settings
 * currently in the modal (saving none of them) and it is shown in an iframe
 * through a blob URL. It is rendered when the modal opens on the PDF format
 * (or the format is switched to PDF) and again whenever a setting changes, so
 * what the constructor sees is what Download saves.
 */

/** Pause after the last keystroke in the narrative before re-rendering. */
export const NARRATIVE_PREVIEW_MS = 600;

/** Pause that folds several settings changing in one response into one render. */
export const SETTING_PREVIEW_MS = 50;

/** Hides the browser PDF viewer's chrome and fits the page to the pane's width. */
const VIEWER_OPTIONS = '#toolbar=0&navpanes=0&view=FitH';

export function pdfPreview() {
    let timer = null;
    /** Bumped per render, so a render that settles after a newer one is dropped. */
    let sequence = 0;

    return {
        /** Blob URL of the PDF currently shown, or null before the first render. */
        url: null,
        loading: false,
        failed: false,

        init() {
            this.$wire.$watch('showPdfExportModal', (open) => {
                if (open) {
                    this.schedule(SETTING_PREVIEW_MS);
                } else {
                    this.clear();
                }
            });

            for (const setting of ['pdfOrientation', 'pdfLayout', 'pdfImage', 'pdfRemoveImage', 'pdfIncludeAnswerKey']) {
                this.$wire.$watch(setting, () => this.schedule(SETTING_PREVIEW_MS));
            }

            this.$wire.$watch('pdfNarrative', () => this.schedule(NARRATIVE_PREVIEW_MS));

            // Other formats have no preview; coming back to PDF renders it afresh.
            this.$wire.$watch('downloadFormat', () => this.schedule(SETTING_PREVIEW_MS));

            if (this.$wire.showPdfExportModal) {
                this.schedule(SETTING_PREVIEW_MS);
            }
        },

        destroy() {
            this.clear();
        },

        get frameSource() {
            return this.url ? this.url + VIEWER_OPTIONS : 'about:blank';
        },

        schedule(delay) {
            if (!this.$wire.showPdfExportModal || this.$wire.downloadFormat !== 'pdf') return;

            clearTimeout(timer);
            this.loading = true;
            timer = setTimeout(() => this.refresh(), delay);
        },

        async refresh() {
            const ticket = ++sequence;
            this.loading = true;

            let encoded = null;
            try {
                encoded = await this.$wire.pdfPreview();
            } catch {
                encoded = null;
            }

            if (ticket !== sequence) return;

            this.loading = false;
            this.failed = typeof encoded !== 'string';
            if (this.failed) return;

            const bytes = Uint8Array.from(atob(encoded), (character) => character.charCodeAt(0));
            this.show(URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' })));
        },

        show(url) {
            if (this.url) URL.revokeObjectURL(this.url);
            this.url = url;
        },

        clear() {
            clearTimeout(timer);
            sequence++;
            this.loading = false;
            this.failed = false;
            this.show(null);
        },
    };
}
