<?php

namespace App\Livewire\Concerns;

use App\Enums\PdfLayout;
use App\Models\Crossword;
use App\Services\PdfExporter;
use CrosswordBuilder\CrosswordIO\Exceptions\ExportValidationException;
use CrosswordBuilder\CrosswordIO\Exporters\IpuzExporter;
use CrosswordBuilder\CrosswordIO\Exporters\JpzExporter;
use CrosswordBuilder\CrosswordIO\Exporters\PuzExporter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Renderless;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ExportsCrossword
{
    public bool $showExportWarning = false;

    public string $pendingExportFormat = '';

    /** @var list<string> */
    public array $exportWarnings = [];

    /** Whether the download modal is open: the format choice plus, for a PDF, its settings and preview. */
    public bool $showPdfExportModal = false;

    /** The format picked in the download modal: 'pdf', 'ipuz', 'puz' or 'jpz'. */
    public string $downloadFormat = 'pdf';

    public string $pdfOrientation = 'portrait';

    /** A PdfLayout value: where the grid sits and how the clues are arranged around it. */
    public string $pdfLayout = PdfLayout::GridBottomRight->value; // PdfLayout::default()

    public string $pdfNarrative = '';

    /** @var TemporaryUploadedFile|null */
    public $pdfImage = null;

    public bool $pdfRemoveImage = false;

    /** Whether the exporter asked for the answer key on its own page at the end of the PDF. */
    public bool $pdfIncludeAnswerKey = false;

    abstract protected function getExportableCrossword(): Crossword;

    /**
     * Whether this page may put the answer key in a PDF at all. Builder
     * pages allow it; pages that export for a solver do not, whatever
     * `pdfIncludeAnswerKey` is set to.
     */
    protected function getPdfIncludeSolution(): bool
    {
        return false;
    }

    /**
     * @return array<string, string|null>
     */
    protected function getExportPlanGates(): array
    {
        return [
            'puz' => null,
            'jpz' => null,
            'pdf' => null,
        ];
    }

    /**
     * Open the download modal, where the format is chosen. It starts on PDF,
     * or on .ipuz for someone whose plan does not include PDFs.
     */
    public function openDownloadModal(): void
    {
        $this->resetPdfSettings();
        $this->pdfNarrative = $this->getExportableCrossword()->pdf_narrative ?? '';
        $this->downloadFormat = $this->canDownloadFormat('pdf') ? 'pdf' : 'ipuz';
        $this->showPdfExportModal = true;
    }

    /**
     * Download the puzzle in the format picked in the download modal.
     */
    public function confirmDownload(): mixed
    {
        $format = in_array($this->downloadFormat, ['pdf', 'ipuz', 'puz', 'jpz'], true) ? $this->downloadFormat : 'pdf';

        if ($format === 'pdf' && $this->canDownloadFormat('pdf')) {
            return $this->confirmPdfExport();
        }

        $this->cancelPdfExport();

        return $this->attemptExport($format);
    }

    /**
     * Whether the signed-in user's plan includes downloads in this format.
     */
    public function canDownloadFormat(string $format): bool
    {
        $gate = $this->getExportPlanGates()[$format] ?? null;

        return ! $gate || Auth::user()->planLimits()->{$gate}();
    }

    public function attemptExport(string $format): mixed
    {
        $gate = $this->getExportPlanGates()[$format] ?? null;
        if ($gate && ! Auth::user()->planLimits()->{$gate}()) {
            $this->onExportUpgradeRequired($format);

            return null;
        }

        if ($format === 'pdf') {
            $this->choosePdfExport();

            return null;
        }

        $crossword = $this->getExportableCrossword();

        $exporter = match ($format) {
            'ipuz' => new IpuzExporter,
            'puz' => app(PuzExporter::class),
            'jpz' => app(JpzExporter::class),
            default => null,
        };

        if (! $exporter) {
            return null;
        }

        try {
            $exporter->validate($crossword->toCrosswordIO());
        } catch (ExportValidationException $e) {
            $this->exportWarnings = array_map(fn ($f) => $f->label(), $e->unsupportedFeatures);
            $this->pendingExportFormat = $format;
            $this->showExportWarning = true;

            return null;
        }

        return $this->runExport($format, allowLossyExport: false);
    }

    protected function onExportUpgradeRequired(string $format): void
    {
        abort(403, "Upgrade to Pro to export .{$format} files.");
    }

    public function confirmExport(): mixed
    {
        $this->showExportWarning = false;
        $format = $this->pendingExportFormat;
        $this->pendingExportFormat = '';
        $this->exportWarnings = [];

        return $this->runExport($format, allowLossyExport: true);
    }

    public function cancelExport(): void
    {
        $this->showExportWarning = false;
        $this->pendingExportFormat = '';
        $this->exportWarnings = [];
    }

    private function runExport(string $format, bool $allowLossyExport): mixed
    {
        return match ($format) {
            'ipuz' => $this->exportIpuz(),
            'puz' => $this->exportPuz($allowLossyExport),
            'jpz' => $this->exportJpz($allowLossyExport),
            default => null,
        };
    }

    public function exportIpuz(): StreamedResponse
    {
        $crossword = $this->getExportableCrossword();

        $exporter = new IpuzExporter;
        $json = $exporter->toJson($crossword->toCrosswordIO());
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.ipuz')->toString();

        return response()->streamDownload(function () use ($json) {
            echo $json;
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function exportPuz(bool $allowLossyExport = false): StreamedResponse
    {
        $gate = $this->getExportPlanGates()['puz'] ?? null;
        if ($gate) {
            abort_unless(Auth::user()->planLimits()->{$gate}(), 403, 'Upgrade to Pro to export .puz files.');
        }

        $crossword = $this->getExportableCrossword();

        $exporter = app(PuzExporter::class);
        $binary = $exporter->export($crossword->toCrosswordIO(), $allowLossyExport);
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.puz')->toString();

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/octet-stream']);
    }

    public function exportJpz(bool $allowLossyExport = false): StreamedResponse
    {
        $gate = $this->getExportPlanGates()['jpz'] ?? null;
        if ($gate) {
            abort_unless(Auth::user()->planLimits()->{$gate}(), 403, 'Upgrade to Pro to export .jpz files.');
        }

        $crossword = $this->getExportableCrossword();

        $exporter = app(JpzExporter::class);
        $compressed = $exporter->export($crossword->toCrosswordIO(), $allowLossyExport);
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.jpz')->toString();

        return response()->streamDownload(function () use ($compressed) {
            echo $compressed;
        }, $filename, ['Content-Type' => 'application/octet-stream']);
    }

    public function choosePdfExport(): void
    {
        $gate = $this->getExportPlanGates()['pdf'] ?? null;
        if ($gate && ! Auth::user()->planLimits()->{$gate}()) {
            $this->onExportUpgradeRequired('pdf');

            return;
        }

        $this->resetPdfSettings();
        $this->pdfNarrative = $this->getExportableCrossword()->pdf_narrative ?? '';
        $this->downloadFormat = 'pdf';
        $this->showPdfExportModal = true;
    }

    public function confirmPdfExport(): ?StreamedResponse
    {
        if ($this->pdfImage instanceof TemporaryUploadedFile) {
            $this->validate($this->pdfImageRules());
        }

        $this->showPdfExportModal = false;

        $crossword = $this->getExportableCrossword();

        $narrative = trim($this->pdfNarrative);
        if ($crossword->pdf_narrative !== ($narrative ?: null)) {
            $crossword->update(['pdf_narrative' => $narrative ?: null]);
        }

        if ($this->pdfRemoveImage && $crossword->pdf_image) {
            Storage::disk('public')->delete($crossword->pdf_image);
            $crossword->update(['pdf_image' => null]);
        } elseif ($this->pdfImage instanceof TemporaryUploadedFile) {
            if ($crossword->pdf_image) {
                Storage::disk('public')->delete($crossword->pdf_image);
            }
            $path = $this->pdfImage->store('pdf-images', 'public');
            $crossword->update(['pdf_image' => $path]);
        }

        $this->pdfImage = null;
        $this->pdfRemoveImage = false;

        return $this->exportPdf();
    }

    public function cancelPdfExport(): void
    {
        $this->showPdfExportModal = false;
        $this->downloadFormat = 'pdf';
        $this->resetPdfSettings();
    }

    private function resetPdfSettings(): void
    {
        $this->pdfOrientation = 'portrait';
        $this->pdfLayout = PdfLayout::default()->value;
        $this->pdfNarrative = '';
        $this->pdfImage = null;
        $this->pdfRemoveImage = false;
        $this->pdfIncludeAnswerKey = false;
    }

    /**
     * Reject an unusable header image as soon as it is uploaded, so the
     * problem shows beside the field instead of only at export time.
     */
    public function updatedPdfImage(): void
    {
        $this->validate($this->pdfImageRules());
    }

    /**
     * Render the PDF as it would be exported with the settings currently in
     * the export modal, without saving any of them.
     *
     * @return string|null The base64-encoded PDF, or null when the download modal is not open on a PDF the user may download.
     */
    #[Renderless]
    public function pdfPreview(): ?string
    {
        if (! $this->showPdfExportModal || $this->downloadFormat !== 'pdf' || ! $this->canDownloadFormat('pdf')) {
            return null;
        }

        $crossword = $this->getExportableCrossword();

        return base64_encode($this->renderPdf($crossword, $this->pendingPdfImagePath($crossword)));
    }

    public function exportPdf(): StreamedResponse
    {
        $crossword = $this->getExportableCrossword();

        $imagePath = $crossword->pdf_image ? Storage::disk('public')->path($crossword->pdf_image) : null;

        $pdf = $this->renderPdf($crossword, $imagePath);
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.pdf')->toString();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    /**
     * @return string The raw PDF binary content.
     */
    private function renderPdf(Crossword $crossword, ?string $imagePath): string
    {
        $gate = $this->getExportPlanGates()['pdf'] ?? null;
        if ($gate) {
            abort_unless(Auth::user()->planLimits()->{$gate}(), 403, 'Upgrade to Pro to export PDF files.');
        }

        $orientation = in_array($this->pdfOrientation, ['portrait', 'landscape']) ? $this->pdfOrientation : 'portrait';
        $narrative = trim($this->pdfNarrative);

        return app(PdfExporter::class)->export(
            $crossword,
            includeSolution: $this->pdfIncludeAnswerKey && $this->getPdfIncludeSolution(),
            orientation: $orientation,
            narrative: $narrative ?: null,
            imagePath: $imagePath,
            layout: PdfLayout::tryFrom($this->pdfLayout) ?? PdfLayout::Standard,
        );
    }

    /**
     * The header image the export would use if confirmed now: a valid new
     * upload, otherwise the saved image unless it is marked for removal.
     */
    private function pendingPdfImagePath(Crossword $crossword): ?string
    {
        if ($this->pdfRemoveImage) {
            return null;
        }

        if ($this->pdfImage instanceof TemporaryUploadedFile
            && Validator::make(['pdfImage' => $this->pdfImage], $this->pdfImageRules())->passes()) {
            return $this->pdfImage->getRealPath();
        }

        return $crossword->pdf_image ? Storage::disk('public')->path($crossword->pdf_image) : null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function pdfImageRules(): array
    {
        return ['pdfImage' => ['nullable', 'image', 'max:2048']];
    }
}
