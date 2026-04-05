<?php

namespace App\Livewire\Concerns;

use App\Models\Crossword;
use App\Services\PdfExporter;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Zorbl\CrosswordIO\Exceptions\ExportValidationException;
use Zorbl\CrosswordIO\Exporters\IpuzExporter;
use Zorbl\CrosswordIO\Exporters\JpzExporter;
use Zorbl\CrosswordIO\Exporters\PuzExporter;

trait ExportsCrossword
{
    public bool $showExportWarning = false;

    public string $pendingExportFormat = '';

    /** @var list<string> */
    public array $exportWarnings = [];

    abstract protected function getExportableCrossword(): Crossword;

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

    public function attemptExport(string $format): mixed
    {
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

    public function exportPdf(): StreamedResponse
    {
        $gate = $this->getExportPlanGates()['pdf'] ?? null;
        if ($gate) {
            abort_unless(Auth::user()->planLimits()->{$gate}(), 403, 'Upgrade to Pro to export PDF files.');
        }

        $crossword = $this->getExportableCrossword();

        $exporter = app(PdfExporter::class);
        $pdf = $exporter->export($crossword, includeSolution: $this->getPdfIncludeSolution());
        $filename = str($crossword->title ?: 'crossword')->slug()->append('.pdf')->toString();

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }
}
