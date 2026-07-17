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
#[Title('Free Crossword File Converter')]
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
    @php
        $converterDescription = __('Free online crossword file converter. Convert between .puz (Across Lite), .jpz (Crossword Compiler) and .ipuz files instantly in your browser — no account and no software to install.');

        // Single source of truth for the FAQ: rendered as visible <details>
        // below and mirrored into FAQPage structured data. Google requires the
        // schema Q&A to match the on-page content, so they share this array.
        $faqs = [
            [
                'q' => __('Is the crossword converter free?'),
                'a' => __('Yes. Converting puzzles is completely free, with no account, sign-up, or software download required.'),
            ],
            [
                'q' => __('Which crossword formats can I convert?'),
                'a' => __('You can convert between .puz (Across Lite), .jpz (Crossword Compiler) and .ipuz (the modern open standard). You can also import a crossword from a .pdf and export it to any of those formats.'),
            ],
            [
                'q' => __('Do I need to install anything?'),
                'a' => __('No. The converter runs on the web — upload your file, pick a target format, and download the converted puzzle. It works on any device with a browser.'),
            ],
            [
                'q' => __('Is my puzzle file kept private?'),
                'a' => __('Your file is processed only to perform the conversion and is never attached to an account, published, or shared. Uploads are temporary and cleared automatically.'),
            ],
            [
                'q' => __('What is the .ipuz format?'),
                'a' => __('.ipuz is a modern, JSON-based open standard for crossword puzzles. It is lossless and supports advanced features like shaded cells, bars, and rebus entries, which makes it a great archival format.'),
            ],
            [
                'q' => __('What is the difference between .puz and .jpz?'),
                'a' => __('.puz (Across Lite) is the classic binary format used by most major publications and solving apps, so it has the widest compatibility. .jpz (Crossword Compiler) is an XML-based format that supports richer grid features.'),
            ],
            [
                'q' => __('Will I lose anything when I convert?'),
                'a' => __('Conversions between full-featured formats are lossless. If your puzzle uses a feature the target format cannot represent, the converter warns you first and lets you decide whether to continue.'),
            ],
        ];
    @endphp

    <x-seo-meta
        title="Free Crossword File Converter"
        :canonical="route('tools.convert')"
        :description="$converterDescription"
    />

    @push('head_meta')
        @php
            $toolJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'SoftwareApplication',
                'name' => __('Crossword File Converter'),
                'url' => route('tools.convert'),
                'applicationCategory' => 'UtilitiesApplication',
                'operatingSystem' => 'Web browser',
                'description' => $converterDescription,
                'isAccessibleForFree' => true,
                'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'USD'],
                'featureList' => ['.puz (Across Lite)', '.jpz (Crossword Compiler)', '.ipuz', '.pdf import'],
                'publisher' => ['@type' => 'Organization', 'name' => config('app.name'), 'url' => url('/')],
            ];

            $faqJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => collect($faqs)->map(fn ($faq) => [
                    '@type' => 'Question',
                    'name' => $faq['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
                ])->all(),
            ];

            $breadcrumbJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => config('app.name'), 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => __('Crossword File Converter'), 'item' => route('tools.convert')],
                ],
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($toolJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/ld+json">{!! json_encode($breadcrumbJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <div class="mx-auto max-w-2xl py-8">
        <header class="text-center">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Crossword File Converter') }}</h1>
            <p class="mt-3 text-zinc-500">{{ __('Convert crossword puzzles between .puz, .jpz, and .ipuz — free, instant, and right in your browser. No account or software required.') }}</p>
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
        <h2 class="mt-10 text-xl font-bold tracking-tight">{{ __('Supported crossword formats') }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-3">
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

        {{-- How to convert --}}
        <h2 class="mt-10 text-xl font-bold tracking-tight">{{ __('How to convert a crossword file') }}</h2>
        <ol class="mt-4 space-y-3">
            <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-sm font-bold text-amber-500">1</span>
                <span class="text-sm text-zinc-400">{{ __('Upload your crossword file (.puz, .jpz, .ipuz, or .pdf).') }}</span>
            </li>
            <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-sm font-bold text-amber-500">2</span>
                <span class="text-sm text-zinc-400">{{ __('Choose the format you want to convert to.') }}</span>
            </li>
            <li class="flex gap-3">
                <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-500/15 text-sm font-bold text-amber-500">3</span>
                <span class="text-sm text-zinc-400">{{ __('Click Convert & Download — your converted puzzle saves straight to your device.') }}</span>
            </li>
        </ol>

        {{-- Supported conversions (targets specific "X to Y" searches) --}}
        <h2 class="mt-10 text-xl font-bold tracking-tight">{{ __('Popular conversions') }}</h2>
        <p class="mt-2 text-sm text-zinc-500">{{ __('The converter works in every direction between the major crossword formats:') }}</p>
        <ul class="mt-4 grid grid-cols-2 gap-2 text-sm text-zinc-400 sm:grid-cols-3">
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.puz to .ipuz') }}</li>
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.ipuz to .puz') }}</li>
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.puz to .jpz') }}</li>
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.jpz to .puz') }}</li>
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.jpz to .ipuz') }}</li>
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.ipuz to .jpz') }}</li>
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.pdf to .ipuz') }}</li>
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.pdf to .puz') }}</li>
            <li class="rounded-lg border border-zinc-800 bg-zinc-900/40 px-3 py-2">{{ __('.pdf to .jpz') }}</li>
        </ul>

        {{-- FAQ — mirrors the FAQPage structured data above --}}
        <h2 class="mt-10 text-xl font-bold tracking-tight">{{ __('Frequently asked questions') }}</h2>
        <div class="mt-4 divide-y divide-zinc-800 rounded-xl border border-zinc-800 bg-zinc-900/40">
            @foreach ($faqs as $faq)
                <details class="group p-5">
                    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-semibold text-zinc-100 marker:hidden">
                        {{ $faq['q'] }}
                        <svg class="size-4 shrink-0 text-zinc-500 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                    </summary>
                    <p class="mt-2 text-sm text-zinc-500">{{ $faq['a'] }}</p>
                </details>
            @endforeach
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
