<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use CrosswordBuilder\CrosswordIO\Crossword;
use CrosswordBuilder\CrosswordIO\Exceptions\ExportValidationException;
use CrosswordBuilder\CrosswordIO\Exceptions\IpuzImportException;
use CrosswordBuilder\CrosswordIO\Exceptions\JpzImportException;
use CrosswordBuilder\CrosswordIO\Exceptions\PdfImportException;
use CrosswordBuilder\CrosswordIO\Exceptions\PuzImportException;
use CrosswordBuilder\CrosswordIO\Exporters\IpuzExporter;
use CrosswordBuilder\CrosswordIO\Exporters\JpzExporter;
use CrosswordBuilder\CrosswordIO\Exporters\PuzExporter;
use CrosswordBuilder\CrosswordIO\ImportDetector;

new
#[Title('Puzzle Format Converter')]
#[Layout('layouts.public')]
class extends Component {
    use WithFileUploads;

    public $file;

    public string $targetFormat = 'ipuz';

    public string $error = '';

    public bool $fileLoaded = false;

    public string $detectedFormat = '';

    public string $puzzleTitle = '';

    public int $puzzleWidth = 0;

    public int $puzzleHeight = 0;

    public int $clueCount = 0;

    /** @var list<string> */
    public array $exportWarnings = [];

    public bool $showWarningModal = false;

    private ?Crossword $parsedCrossword = null;

    /** @var array<string, string> */
    private array $formatLabels = [
        'ipuz' => 'iPuz (.ipuz)',
        'puz' => 'Across Lite (.puz)',
        'jpz' => 'Crossword Compiler (.jpz)',
    ];

    public function updatedFile(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'max:5120'],
        ]);

        $this->error = '';
        $this->fileLoaded = false;
        $this->exportWarnings = [];

        try {
            $crossword = $this->parseUploadedFile();
            $this->puzzleTitle = $crossword->title ?? __('Untitled');
            $this->puzzleWidth = $crossword->width;
            $this->puzzleHeight = $crossword->height;
            $this->clueCount = count($crossword->clues_across) + count($crossword->clues_down);
            $this->fileLoaded = true;

            $ext = strtolower($this->file->getClientOriginalExtension());
            $this->detectedFormat = match ($ext) {
                'puz' => 'Across Lite (.puz)',
                'jpz' => 'Crossword Compiler (.jpz)',
                'ipuz', 'json' => 'iPuz (.ipuz)',
                'pdf' => 'PDF (.pdf)',
                default => __('Unknown'),
            };

            if ($ext === 'ipuz' || $ext === 'json') {
                $this->targetFormat = 'puz';
            } elseif ($ext === 'puz') {
                $this->targetFormat = 'ipuz';
            } else {
                $this->targetFormat = 'ipuz';
            }
        } catch (IpuzImportException|PuzImportException|JpzImportException|PdfImportException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function convert(): mixed
    {
        if (! $this->file) {
            $this->error = __('Please upload a file first.');

            return null;
        }

        $this->error = '';
        $this->exportWarnings = [];

        try {
            $crossword = $this->parseUploadedFile();
        } catch (IpuzImportException|PuzImportException|JpzImportException|PdfImportException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        $exporter = match ($this->targetFormat) {
            'ipuz' => new IpuzExporter,
            'puz' => app(PuzExporter::class),
            'jpz' => app(JpzExporter::class),
            default => null,
        };

        if (! $exporter) {
            $this->error = __('Unsupported target format.');

            return null;
        }

        try {
            $exporter->validate($crossword);
        } catch (ExportValidationException $e) {
            $this->exportWarnings = array_map(fn ($f) => $f->label(), $e->unsupportedFeatures);
            $this->showWarningModal = true;

            return null;
        }

        return $this->runConversion($crossword, allowLossy: false);
    }

    public function confirmConvert(): mixed
    {
        $this->showWarningModal = false;
        $this->exportWarnings = [];

        try {
            $crossword = $this->parseUploadedFile();
        } catch (IpuzImportException|PuzImportException|JpzImportException|PdfImportException $e) {
            $this->error = $e->getMessage();

            return null;
        }

        return $this->runConversion($crossword, allowLossy: true);
    }

    public function cancelConvert(): void
    {
        $this->showWarningModal = false;
        $this->exportWarnings = [];
    }

    public function resetConverter(): void
    {
        $this->reset(['file', 'targetFormat', 'error', 'fileLoaded', 'detectedFormat', 'puzzleTitle', 'puzzleWidth', 'puzzleHeight', 'clueCount', 'exportWarnings', 'showWarningModal']);
        $this->targetFormat = 'ipuz';
    }

    private function parseUploadedFile(): Crossword
    {
        $contents = file_get_contents($this->file->getRealPath());
        $extension = $this->file->getClientOriginalExtension();
        $data = app(ImportDetector::class)->import($contents, $extension);

        return Crossword::fromArray($data);
    }

    private function runConversion(Crossword $crossword, bool $allowLossy): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $slug = str($crossword->title ?: 'crossword')->slug();

        return match ($this->targetFormat) {
            'ipuz' => $this->streamIpuz($crossword, $slug),
            'puz' => $this->streamPuz($crossword, $slug, $allowLossy),
            'jpz' => $this->streamJpz($crossword, $slug, $allowLossy),
        };
    }

    private function streamIpuz(Crossword $crossword, \Illuminate\Support\Stringable $slug): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $exporter = new IpuzExporter;
        $json = $exporter->toJson($crossword);

        return response()->streamDownload(function () use ($json): void {
            echo $json;
        }, $slug->append('.ipuz')->toString(), ['Content-Type' => 'application/json']);
    }

    private function streamPuz(Crossword $crossword, \Illuminate\Support\Stringable $slug, bool $allowLossy): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $binary = app(PuzExporter::class)->export($crossword, $allowLossy);

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $slug->append('.puz')->toString(), ['Content-Type' => 'application/octet-stream']);
    }

    private function streamJpz(Crossword $crossword, \Illuminate\Support\Stringable $slug, bool $allowLossy): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $compressed = app(JpzExporter::class)->export($crossword, $allowLossy);

        return response()->streamDownload(function () use ($compressed): void {
            echo $compressed;
        }, $slug->append('.jpz')->toString(), ['Content-Type' => 'application/octet-stream']);
    }
}
?>

<div>
    @push('head_meta')
        <link rel="canonical" href="{{ route('tools.convert') }}">
        <meta name="description" content="{{ __('Free crossword puzzle format converter. Convert between .puz, .jpz, and .ipuz formats instantly — no account required.') }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ __('Puzzle Format Converter — :app', ['app' => config('app.name')]) }}">
        <meta property="og:url" content="{{ route('tools.convert') }}">
    @endpush

    <div class="mx-auto max-w-2xl py-8">
        <header class="text-center">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Puzzle Format Converter') }}</h1>
            <p class="mt-3 text-zinc-500">{{ __('Convert crossword puzzles between popular file formats. Free, instant, no account required.') }}</p>
        </header>

        <div class="mt-10 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6 sm:p-8">
            {{-- Upload area --}}
            @if (! $fileLoaded)
                <div class="space-y-4">
                    <flux:heading size="lg">{{ __('Upload a puzzle file') }}</flux:heading>
                    <flux:text class="text-zinc-400">
                        {{ __('Supported formats: .puz (Across Lite), .jpz (Crossword Compiler), .ipuz / .json (iPuz), .pdf') }}
                    </flux:text>

                    <div>
                        <flux:input type="file" wire:model="file" accept=".ipuz,.json,.puz,.jpz,.pdf" />
                        <flux:error name="file" />
                    </div>

                    <div wire:loading wire:target="file" class="flex items-center gap-2 text-sm text-zinc-400">
                        <flux:icon.arrow-path class="h-4 w-4 animate-spin" />
                        {{ __('Reading file…') }}
                    </div>

                    @if ($error)
                        <div class="rounded-lg border border-red-800 bg-red-950/50 p-4">
                            <flux:text class="text-red-400">{{ $error }}</flux:text>
                        </div>
                    @endif
                </div>
            @else
                {{-- File loaded — show preview and format picker --}}
                <div class="space-y-6">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <flux:heading size="lg">{{ $puzzleTitle }}</flux:heading>
                            <flux:text class="mt-1 text-zinc-400">
                                {{ $detectedFormat }} · {{ $puzzleWidth }}×{{ $puzzleHeight }} · {{ trans_choice(':count clue|:count clues', $clueCount) }}
                            </flux:text>
                        </div>
                        <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="resetConverter">
                            {{ __('Clear') }}
                        </flux:button>
                    </div>

                    <flux:separator />

                    <div class="space-y-3">
                        <flux:heading>{{ __('Convert to') }}</flux:heading>
                        <flux:radio.group wire:model="targetFormat" variant="cards" class="flex-col sm:flex-row">
                            <flux:radio value="ipuz" label="iPuz (.ipuz)" description="{{ __('Modern, lossless format') }}" />
                            <flux:radio value="puz" label="Across Lite (.puz)" description="{{ __('Widely supported classic format') }}" />
                            <flux:radio value="jpz" label="Crossword Compiler (.jpz)" description="{{ __('XML-based format') }}" />
                        </flux:radio.group>
                    </div>

                    @if ($error)
                        <div class="rounded-lg border border-red-800 bg-red-950/50 p-4">
                            <flux:text class="text-red-400">{{ $error }}</flux:text>
                        </div>
                    @endif

                    <flux:button variant="primary" wire:click="convert" wire:loading.attr="disabled" class="w-full">
                        <span wire:loading.remove wire:target="convert">{{ __('Convert & Download') }}</span>
                        <span wire:loading wire:target="convert">{{ __('Converting…') }}</span>
                    </flux:button>
                </div>
            @endif
        </div>

        {{-- Supported formats info --}}
        <div class="mt-8 grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
                <p class="font-semibold text-zinc-100">.puz</p>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Across Lite format. The most widely supported crossword format, used by major publications.') }}</p>
            </div>
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
                <p class="font-semibold text-zinc-100">.jpz</p>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Crossword Compiler format. XML-based format with support for advanced grid features.') }}</p>
            </div>
            <div class="rounded-xl border border-zinc-800 bg-zinc-900/40 p-5">
                <p class="font-semibold text-zinc-100">.ipuz</p>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Modern open standard. JSON-based, lossless, supports all puzzle features.') }}</p>
            </div>
        </div>

        {{-- CTA for logged-in features --}}
        <div class="mt-8 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6 text-center">
            <p class="text-sm text-zinc-400">
                {{ __('Want to build and publish your own crossword puzzles?') }}
            </p>
            @auth
                <a
                    href="{{ route('crosswords.index') }}"
                    wire:navigate
                    class="mt-3 inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition"
                >
                    {{ __('Go to My Puzzles') }}
                </a>
            @else
                <a
                    href="{{ route('register') }}"
                    class="mt-3 inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition"
                >
                    {{ __('Sign up free') }}
                </a>
            @endauth
        </div>
    </div>

    {{-- Lossy export warning modal --}}
    <flux:modal wire:model.self="showWarningModal" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Some features may be lost') }}</flux:heading>
            <flux:text class="text-zinc-400">
                {{ __('The target format does not support all features in this puzzle. The following will be lost or simplified:') }}
            </flux:text>
            <ul class="list-inside list-disc space-y-1 text-sm text-amber-400">
                @foreach ($exportWarnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
            <div class="flex gap-3 pt-2">
                <flux:button variant="primary" wire:click="confirmConvert" class="flex-1">
                    {{ __('Convert anyway') }}
                </flux:button>
                <flux:button variant="ghost" wire:click="cancelConvert" class="flex-1">
                    {{ __('Cancel') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
