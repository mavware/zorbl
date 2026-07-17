@php
    use Illuminate\Support\Facades\Cache;

    $appName = config('app.name');
    // Keep under ~60 chars so Google/X/LinkedIn don't truncate it.
    $pageTitle = $appName.' — Build a crossword in 10 minutes';
    $tagline = 'Build crossword puzzles with a visual editor and publish them for solvers to enjoy. Free forever!';
    $ogImage = asset('og-default.png');
    $canonicalUrl = url('/');

    $stats = Cache::remember('marketing.welcome_stats', now()->addMinutes(15), function () {
        $puzzles = \App\Models\Crossword::where('is_published', true)->count();
        $constructors = \App\Models\User::has('crosswords')->count();
        $solvesWeek = \App\Models\PuzzleAttempt::where('is_completed', true)
            ->where('completed_at', '>=', now()->subWeek())
            ->count();

        return [
            'puzzles' => $puzzles,
            'constructors' => $constructors,
            'solves_week' => $solvesWeek,
        ];
    });

    // Credibility floor — hide any individual stat that's too small to be reassuring.
    $statFloor = 50;
    $visibleStats = collect([
        ['value' => $stats['puzzles'], 'label' => 'puzzles published'],
        ['value' => $stats['constructors'], 'label' => 'constructors'],
        ['value' => $stats['solves_week'], 'label' => 'solves this week'],
    ])->filter(fn ($s) => $s['value'] >= $statFloor)->values();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('favicon-16x16.png') }}">
        <link rel="manifest" href="{{ asset('site.webmanifest') }}">
        <meta name="theme-color" content="#0a0a0a">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $appName }}">
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $tagline }}">
        <link rel="canonical" href="{{ $canonicalUrl }}">

        {{-- Open Graph --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $appName }}">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $tagline }}">
        <meta property="og:url" content="{{ $canonicalUrl }}">
        <meta property="og:image" content="{{ $ogImage }}">

        {{-- Twitter card --}}
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $pageTitle }}">
        <meta name="twitter:description" content="{{ $tagline }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

        {{-- Structured data: Organization (branding), WebSite with SearchAction
             (sitelinks search box), and SiteNavigationElement for the key site
             sections we'd like Google to surface as sitelinks. --}}
        <script type="application/ld+json">{!! json_encode([
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => url('/').'#organization',
                    'name' => $appName,
                    'url' => url('/'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => asset('android-chrome-512x512.png'),
                        'width' => 512,
                        'height' => 512,
                    ],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => url('/').'#website',
                    'name' => $appName,
                    'url' => url('/'),
                    'description' => $tagline,
                    'publisher' => ['@id' => url('/').'#organization'],
                    'potentialAction' => [
                        '@type' => 'SearchAction',
                        'target' => [
                            '@type' => 'EntryPoint',
                            'urlTemplate' => route('puzzles.index').'?search={search_term_string}',
                        ],
                        'query-input' => 'required name=search_term_string',
                    ],
                ],
                [
                    '@type' => 'ItemList',
                    '@id' => url('/').'#sitenav',
                    'itemListElement' => collect([
                        ['name' => 'Build a Crossword', 'description' => 'Create your own crossword with a free visual editor.', 'url' => route('register')],
                        ['name' => 'Puzzle of the Day', 'description' => 'Solve today\'s featured crossword and browse past dailies.', 'url' => route('puzzles.daily-history')],
                        ['name' => 'Newest Puzzles', 'description' => 'The latest crosswords published by the community.', 'url' => route('puzzles.index')],
                        ['name' => 'Trending Puzzles', 'description' => 'The most-played crosswords right now.', 'url' => route('puzzles.index', ['sortBy' => 'most_played'])],
                    ])->map(fn ($item, $i) => [
                        '@type' => 'SiteNavigationElement',
                        'position' => $i + 1,
                        ...$item,
                    ])->values()->all(),
                ],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @fluxAppearance
    </head>
    <body class="bg-zinc-950 text-zinc-100 antialiased">
        {{-- Navigation --}}
        <nav class="fixed top-0 inset-x-0 z-50 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur-lg">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <a href="/" class="text-xl font-bold tracking-tight text-amber-500">{{ $appName }}</a>
                <div class="flex items-center gap-4">
{{--                    <a href="{{ route('puzzles.index') }}" class="text-sm text-zinc-500 hover:text-zinc-100 transition">Browse Puzzles</a>--}}
                    @auth
                        <a href="{{ route('crosswords.index') }}" class="text-sm text-zinc-500 hover:text-zinc-100 transition">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-zinc-500 hover:text-zinc-100 transition">Log in</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition">Sign up</a>
                    @endauth
                </div>
            </div>
        </nav>

        {{-- Hero --}}
        <section class="relative flex min-h-screen items-center justify-center overflow-hidden pt-16">
            <div class="absolute inset-0 bg-gradient-to-b from-amber-500/5 via-transparent to-transparent"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-amber-500/10 via-transparent to-transparent"></div>

            <div
                class="relative mx-auto max-w-6xl px-6 py-20 text-center"
                x-data="{ tab: 'build', showSignup: false }"
                x-on:show-signup-prompt.window="showSignup = true"
            >
                {{-- Solve / Build Toggle --}}
                <div class="relative mx-auto mb-10 inline-grid grid-cols-2 rounded-full border border-zinc-800 bg-zinc-900/60 p-1">
                    {{-- Sliding highlight pill --}}
                    <div
                        class="absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-full bg-amber-500 shadow-lg shadow-amber-500/20 transition-transform duration-300 ease-out"
                        :class="tab === 'solve' && 'translate-x-full'"
                        aria-hidden="true"
                    ></div>
                    <button
                        type="button"
                        @click="tab = 'build'"
                        :class="tab === 'build' ? 'text-zinc-950' : 'text-zinc-400 hover:text-zinc-100 cursor-pointer'"
                        class="flex relative rounded-full px-8 py-2.5 text-base font-semibold transition"
                    >
                        <flux:icon.wrench-screwdriver class="mr-2" /> Build
                    </button>
                    <button
                        type="button"
                        @click="tab = 'solve'"
                        :class="tab === 'solve' ? 'text-zinc-950' : 'text-zinc-400 hover:text-zinc-100 cursor-pointer'"
                        class="flex relative rounded-full px-8 py-2.5 text-base font-semibold transition"
                    >
                        <flux:icon.play class="mr-2" /> Solve
                    </button>

                </div>

                {{-- Solve panel --}}
                <div x-show="tab === 'solve'" x-cloak>
                    <p class="font-bold tracking-tight text-white text-3xl sm:text-5xl">
                        Thousands of puzzles. <span class="text-amber-500">One click away.</span>
                    </p>
                    <div class="mx-auto mt-10 w-full max-w-7xl rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 shadow-2xl shadow-amber-500/5 sm:p-8">
                        <livewire:puzzle-discovery :limit="7" />
                    </div>
                </div>

                {{-- Signup Prompt Modal (for guests trying to solve a second puzzle) --}}
                <template x-teleport="body">
                    <div
                        x-show="showSignup"
                        x-cloak
                        x-on:keydown.escape.window="showSignup = false"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                        x-on:click.self="showSignup = false"
                    >
                        <div class="mx-4 w-full max-w-md rounded-2xl bg-zinc-900 p-8 text-center shadow-xl ring-1 ring-zinc-800" x-on:click.stop>
                            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-500/10">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-8 text-amber-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/>
                                    <path d="m9 15 2 2 4-4"/>
                                </svg>
                            </div>
                            <h3 class="text-xl font-bold text-zinc-100">{{ __('Ready for more puzzles?') }}</h3>
                            <p class="mt-2 text-sm text-zinc-400">
                                {{ __('Create a free account to solve unlimited puzzles, save your progress across devices, and track your stats.') }}
                            </p>
                            <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                                <a href="{{ route('register') }}" class="rounded-xl bg-amber-500 px-6 py-2.5 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition">
                                    {{ __('Create Free Account') }}
                                </a>
                                <a href="{{ route('login') }}" class="rounded-xl border border-zinc-700 px-6 py-2.5 text-sm font-semibold text-zinc-300 hover:bg-zinc-800 transition">
                                    {{ __('Log In') }}
                                </a>
                            </div>
                            <button x-on:click="showSignup = false" class="mt-4 text-xs text-zinc-500 hover:text-zinc-300">
                                {{ __('Maybe later') }}
                            </button>
                        </div>
                    </div>
                </template>

                {{-- Build panel --}}
                <div x-show="tab === 'build'" x-cloak style="display: none">
                    <p class="font-bold tracking-tight text-white text-3xl sm:text-5xl">
                        Start with a shape. <span class="text-amber-500">Build from there.</span>
                    </p>
                    <div class="mx-auto mt-10 w-full max-w-2xl rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 shadow-2xl shadow-amber-500/5 sm:p-8">
                        <livewire:welcome-builder />
                    </div>
                </div>
            </div>
        </section>

        {{-- Live trust strip (only renders if at least one stat clears the credibility floor) --}}
        @if ($visibleStats->isNotEmpty())
            <section class="border-t border-zinc-800 bg-zinc-950/60 py-10">
                <div class="mx-auto max-w-5xl px-6">
                    @php
                        $statsCols = match ($visibleStats->count()) {
                            1 => 'sm:grid-cols-1',
                            2 => 'sm:grid-cols-2',
                            default => 'sm:grid-cols-3',
                        };
                    @endphp
                    <dl class="grid gap-8 text-center {{ $statsCols }}">
                        @foreach ($visibleStats as $stat)
                            <div>
                                <dt class="text-xs uppercase tracking-wider text-zinc-600">{{ $stat['label'] }}</dt>
                                <dd class="mt-2 text-3xl font-bold text-amber-500">{{ number_format($stat['value']) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            </section>
        @endif

        {{-- Features --}}
        <section class="relative border-t border-zinc-800 bg-zinc-950 py-24">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="text-center text-3xl font-bold tracking-tight sm:text-4xl">A toolkit built by puzzle people, for puzzle people</h2>
                <p class="mx-auto mt-4 max-w-2xl text-center text-zinc-500">Everything you need to take a puzzle from idea to inbox — and everything a solver needs to keep coming back.</p>

                <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Feature 1 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Symmetry on autopilot</h3>
                        <p class="mt-2 text-sm text-zinc-500">Click to drop a block, type to fill a letter. Rotational symmetry mirrors your blocks automatically so the grid always looks the part.</p>
                    </div>

                    {{-- Feature 2 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Pick up where you left off</h3>
                        <p class="mt-2 text-sm text-zinc-500">Solve any puzzle in the catalog and your progress saves automatically — across devices, between tabs, no logins lost.</p>
                    </div>

                    {{-- Feature 3 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Bring your own .ipuz</h3>
                        <p class="mt-2 text-sm text-zinc-500">Already constructing? Import .ipuz files from your favorite tool and keep working. Export anytime — your puzzles stay yours.</p>
                    </div>

                    {{-- Feature 4 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Never stare at a blank clue again</h3>
                        <p class="mt-2 text-sm text-zinc-500">See how other constructors clued the same answer. The clue library turns the community's collective wit into your inspiration.</p>
                    </div>

                    {{-- Feature 5 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Squares, diamonds, anything</h3>
                        <p class="mt-2 text-sm text-zinc-500">Go beyond the classic 15x15. Build minis, themelesses, diamonds, irregular shapes — with full void-cell support for the wild ideas.</p>
                    </div>

                    {{-- Feature 6 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">One-click publish</h3>
                        <p class="mt-2 text-sm text-zinc-500">When the grid is solid and the clues sing, ship it. Your puzzle goes live for solvers around the world to find and finish.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- How it works --}}
        <section class="border-t border-zinc-800 py-24">
            <div class="mx-auto max-w-4xl px-6">
                <h2 class="text-center text-3xl font-bold tracking-tight sm:text-4xl">How it works</h2>
                <div class="mt-16 grid gap-12 sm:grid-cols-3">
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border-2 border-amber-500 text-xl font-bold text-amber-500">1</div>
                        <h3 class="mt-4 text-lg font-semibold">Design your grid</h3>
                        <p class="mt-2 text-sm text-zinc-500">Pick a size, place blocks, and fill in answers with the visual editor. Symmetry happens automatically.</p>
                    </div>
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border-2 border-amber-500 text-xl font-bold text-amber-500">2</div>
                        <h3 class="mt-4 text-lg font-semibold">Write your clues</h3>
                        <p class="mt-2 text-sm text-zinc-500">Sharpen each clue. Browse the community library when you need a spark of inspiration.</p>
                    </div>
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border-2 border-amber-500 text-xl font-bold text-amber-500">3</div>
                        <h3 class="mt-4 text-lg font-semibold">Publish & solve</h3>
                        <p class="mt-2 text-sm text-zinc-500">Hit publish and your puzzle is live. Then unwind with crosswords from constructors around the world.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- FAQ --}}
        <section class="border-t border-zinc-800 bg-zinc-950 py-24">
            <div class="mx-auto max-w-3xl px-6">
                <h2 class="text-center text-3xl font-bold tracking-tight sm:text-4xl">Questions, answered</h2>
                <div class="mt-12 divide-y divide-zinc-800 rounded-2xl border border-zinc-800 bg-zinc-900/40">
                    <details class="group p-6">
                        <summary class="flex cursor-pointer items-center justify-between text-base font-semibold text-zinc-100 marker:hidden list-none">
                            Do I need crossword construction experience?
                            <svg class="h-5 w-5 shrink-0 text-zinc-600 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>
                        <p class="mt-3 text-sm text-zinc-500">No. The visual editor handles symmetry, numbering, and the boring bookkeeping. If you can spell, you can build a mini in an afternoon.</p>
                    </details>
                    <details class="group p-6">
                        <summary class="flex cursor-pointer items-center justify-between text-base font-semibold text-zinc-100 marker:hidden list-none">
                            Is it really free?
                            <svg class="h-5 w-5 shrink-0 text-zinc-600 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>
                        <p class="mt-3 text-sm text-zinc-500">Yes. Building, publishing, and solving are free with no credit card required. An optional Pro plan adds AI-assisted grid filling and clue suggestions for constructors who want a head start.</p>
                    </details>
                    <details class="group p-6">
                        <summary class="flex cursor-pointer items-center justify-between text-base font-semibold text-zinc-100 marker:hidden list-none">
                            Can I import puzzles I&rsquo;ve already built elsewhere?
                            <svg class="h-5 w-5 shrink-0 text-zinc-600 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>
                        <p class="mt-3 text-sm text-zinc-500">Yes. Import standard <code class="rounded bg-zinc-800 px-1 text-xs text-amber-400">.ipuz</code> files from any tool that exports them and keep working. Export back out anytime.</p>
                    </details>
                    <details class="group p-6">
                        <summary class="flex cursor-pointer items-center justify-between text-base font-semibold text-zinc-100 marker:hidden list-none">
                            What grid sizes and shapes are supported?
                            <svg class="h-5 w-5 shrink-0 text-zinc-600 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>
                        <p class="mt-3 text-sm text-zinc-500">Anything from 4x4 minis up through Sunday-sized 21x21 grids. Diamonds, asymmetric layouts, and void cells are all first-class citizens.</p>
                    </details>
                    <details class="group p-6">
                        <summary class="flex cursor-pointer items-center justify-between text-base font-semibold text-zinc-100 marker:hidden list-none">
                            How is this different from solving on the NYT?
                            <svg class="h-5 w-5 shrink-0 text-zinc-600 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>
                        <p class="mt-3 text-sm text-zinc-500">{{ $appName }} is a place to <em>build</em> as well as solve, with puzzles from independent constructors you won&rsquo;t find on big-paper sites. Think of it as the indie venue for crossword craft.</p>
                    </details>
                    <details class="group p-6">
                        <summary class="flex cursor-pointer items-center justify-between text-base font-semibold text-zinc-100 marker:hidden list-none">
                            Can I run a contest with my puzzles?
                            <svg class="h-5 w-5 shrink-0 text-zinc-600 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
                        </summary>
                        <p class="mt-3 text-sm text-zinc-500">Yes. Contests let you bundle puzzles, set a window, and see a live leaderboard as solvers race to the finish.</p>
                    </details>
                </div>
            </div>
        </section>

        {{-- CTA --}}
        <section class="border-t border-zinc-800 py-24">
            <div class="mx-auto max-w-2xl px-6 text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Build your first puzzle in under 10 minutes</h2>
                <p class="mt-4 text-zinc-500">Join {{ $appName }} and start crafting or solving today.</p>
                <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    @auth
                        <a href="{{ route('crosswords.index') }}" class="rounded-xl bg-amber-500 px-8 py-3.5 text-base font-semibold text-zinc-950 shadow-lg shadow-amber-500/20 hover:bg-amber-400 transition">
                            Go to dashboard
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="rounded-xl bg-amber-500 px-8 py-3.5 text-base font-semibold text-zinc-950 shadow-lg shadow-amber-500/20 hover:bg-amber-400 transition">
                            Create your free account
                        </a>
                        <a href="{{ route('puzzles.index') }}" class="rounded-xl border border-zinc-700 px-8 py-3.5 text-base font-semibold text-zinc-100 hover:border-zinc-500 hover:bg-zinc-800 transition">
                            Browse puzzles
                        </a>
                    @endauth
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <footer class="border-t border-zinc-800 py-8">
            <div class="mx-auto flex max-w-6xl flex-col items-center gap-3 px-6 text-center text-sm text-zinc-700 sm:flex-row sm:justify-between">
                <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
                <div class="flex flex-wrap items-center justify-center gap-x-5 gap-y-2">
                    <a href="{{ route('register') }}" class="text-zinc-500 hover:text-zinc-300">Build a Crossword</a>
                    <a href="{{ route('puzzles.daily-history') }}" class="text-zinc-500 hover:text-zinc-300">Puzzle of the Day</a>
                    <a href="{{ route('puzzles.index') }}" class="text-zinc-500 hover:text-zinc-300">Newest Puzzles</a>
                    <a href="{{ route('puzzles.index', ['sortBy' => 'most_played']) }}" class="text-zinc-500 hover:text-zinc-300">Trending Puzzles</a>
                    <a href="{{ route('help.index') }}" class="text-zinc-500 hover:text-zinc-300">Help</a>
                    <a href="{{ route('roadmap.index') }}" class="text-zinc-500 hover:text-zinc-300">Roadmap</a>
                    <a href="{{ route('legal.terms') }}" class="text-zinc-500 hover:text-zinc-300">Terms</a>
                    <a href="{{ route('legal.privacy') }}" class="text-zinc-500 hover:text-zinc-300">Privacy</a>
                    <a href="{{ route('legal.cookies') }}" class="text-zinc-500 hover:text-zinc-300">Cookies</a>
                    <a href="{{ route('legal.dmca') }}" class="text-zinc-500 hover:text-zinc-300">DMCA</a>
                </div>
            </div>
        </footer>

        @include('partials.install-prompt')

        @fluxScripts
    </body>
</html>
