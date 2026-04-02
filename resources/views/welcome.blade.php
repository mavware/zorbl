<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'Crossword Loft') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-950 text-zinc-100 antialiased">
        {{-- Navigation --}}
        <nav class="fixed top-0 inset-x-0 z-50 border-b border-zinc-800 bg-zinc-950/80 backdrop-blur-lg">
            <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-6">
                <a href="/" class="text-xl font-bold tracking-tight text-amber-500">Crossword Loft</a>
                <div class="flex items-center gap-4">
                    @auth
                        <a href="{{ route('crosswords.index') }}" class="text-sm text-zinc-400 hover:text-zinc-100 transition">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-sm text-zinc-400 hover:text-zinc-100 transition">Log in</a>
                        <a href="{{ route('register') }}" class="rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition">Sign up</a>
                    @endauth
                </div>
            </div>
        </nav>

        {{-- Hero --}}
        <section class="relative flex min-h-screen items-center justify-center overflow-hidden pt-16">
            <div class="absolute inset-0 bg-gradient-to-b from-amber-500/5 via-transparent to-transparent"></div>
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-amber-500/10 via-transparent to-transparent"></div>

            <div class="relative mx-auto max-w-6xl px-6 py-24 text-center">
                <h1 class="text-5xl font-bold tracking-tight sm:text-7xl">
                    Craft & Solve<br>
                    <span class="text-amber-500">Crosswords</span>
                </h1>
                <p class="mx-auto mt-6 max-w-2xl text-lg text-zinc-400 sm:text-xl">
                    Build puzzles with our visual grid editor, publish them for the world to solve, or dive into crosswords crafted by other constructors.
                </p>
                <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    @auth
                        <a href="{{ route('crosswords.index') }}" class="rounded-xl bg-amber-500 px-8 py-3.5 text-base font-semibold text-zinc-950 shadow-lg shadow-amber-500/20 hover:bg-amber-400 transition">
                            Create a Puzzle
                        </a>
                        <a href="{{ route('crosswords.solving') }}" class="rounded-xl border border-zinc-700 px-8 py-3.5 text-base font-semibold text-zinc-100 hover:border-zinc-500 hover:bg-zinc-800 transition">
                            Solve Puzzles
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="rounded-xl bg-amber-500 px-8 py-3.5 text-base font-semibold text-zinc-950 shadow-lg shadow-amber-500/20 hover:bg-amber-400 transition">
                            Start Creating
                        </a>
                        <a href="{{ route('login') }}" class="rounded-xl border border-zinc-700 px-8 py-3.5 text-base font-semibold text-zinc-100 hover:border-zinc-500 hover:bg-zinc-800 transition">
                            Sign In to Solve
                        </a>
                    @endauth
                </div>

                {{-- Mini crossword grid --}}
                <div class="mx-auto mt-16 max-w-sm" aria-hidden="true">
                    <div class="grid grid-cols-7 gap-0.5 rounded-xl border border-zinc-800 bg-zinc-900 p-3 shadow-2xl shadow-amber-500/5">
                        @php
                            $demoGrid = [
                                ['C','R','O','S','S','#','#'],
                                ['L','#','#','O','#','#','#'],
                                ['U','#','#','L','O','F','T'],
                                ['E','N','J','V','#','U','#'],
                                ['S','#','#','E','D','I','T'],
                                ['#','#','#','#','#','N','#'],
                                ['#','#','G','R','I','D','S'],
                            ];
                            $highlighted = [[0,0],[0,1],[0,2],[0,3],[0,4]];
                        @endphp
                        @foreach ($demoGrid as $r => $row)
                            @foreach ($row as $c => $cell)
                                @if ($cell === '#')
                                    <div class="aspect-square rounded-sm bg-zinc-950"></div>
                                @else
                                    @php $isHighlighted = collect($highlighted)->contains(fn($h) => $h[0] === $r && $h[1] === $c); @endphp
                                    <div class="flex aspect-square items-center justify-center rounded-sm text-xs font-bold {{ $isHighlighted ? 'bg-amber-500 text-zinc-950' : 'bg-zinc-800 text-zinc-300' }}">
                                        {{ $cell }}
                                    </div>
                                @endif
                            @endforeach
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-zinc-600">Interactive grid preview</p>
                </div>
            </div>
        </section>

        {{-- Features --}}
        <section class="relative border-t border-zinc-800 bg-zinc-950 py-24">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="text-center text-3xl font-bold tracking-tight sm:text-4xl">Everything you need to build & solve</h2>
                <p class="mx-auto mt-4 max-w-2xl text-center text-zinc-400">From construction to solving, Crossword Loft has the tools for every crossword enthusiast.</p>

                <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    {{-- Feature 1 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25a2.25 2.25 0 0 1-2.25-2.25v-2.25Z" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Visual Grid Editor</h3>
                        <p class="mt-2 text-sm text-zinc-400">Click to place blocks, type to fill letters. Rotational symmetry keeps your grid balanced automatically.</p>
                    </div>

                    {{-- Feature 2 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15.91 11.672a.375.375 0 0 1 0 .656l-5.603 3.113a.375.375 0 0 1-.557-.328V8.887c0-.286.307-.466.557-.327l5.603 3.112Z" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Solve & Compete</h3>
                        <p class="mt-2 text-sm text-zinc-400">Tackle puzzles from other constructors. Your progress saves automatically so you can pick up right where you left off.</p>
                    </div>

                    {{-- Feature 3 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Import & Export</h3>
                        <p class="mt-2 text-sm text-zinc-400">Import .ipuz files to get started fast. Support for standard and non-standard grid shapes, including diamonds and more.</p>
                    </div>

                    {{-- Feature 4 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Clue Library</h3>
                        <p class="mt-2 text-sm text-zinc-400">See what other constructors wrote for the same answer. Draw inspiration from the community's collective creativity.</p>
                    </div>

                    {{-- Feature 5 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 19.5 15-15m0 0H8.25m11.25 0v11.25" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Any Grid Shape</h3>
                        <p class="mt-2 text-sm text-zinc-400">Go beyond the classic square. Create diamond, irregular, and custom-shaped puzzles with void cell support.</p>
                    </div>

                    {{-- Feature 6 --}}
                    <div class="group rounded-2xl border border-zinc-800 bg-zinc-900/50 p-6 transition hover:border-amber-500/30 hover:bg-zinc-900">
                        <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.217 10.907a2.25 2.25 0 1 0 0 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186 9.566-5.314m-9.566 7.5 9.566 5.314m0 0a2.25 2.25 0 1 0 3.935 2.186 2.25 2.25 0 0 0-3.935-2.186Zm0-12.814a2.25 2.25 0 1 0 3.933-2.185 2.25 2.25 0 0 0-3.933 2.185Z" /></svg>
                        </div>
                        <h3 class="mt-4 text-lg font-semibold">Publish & Share</h3>
                        <p class="mt-2 text-sm text-zinc-400">When your puzzle is ready, publish it with one click. Other users can discover and solve your creation.</p>
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
                        <p class="mt-2 text-sm text-zinc-400">Set your dimensions, place blocks, and fill in your answers using the visual editor.</p>
                    </div>
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border-2 border-amber-500 text-xl font-bold text-amber-500">2</div>
                        <h3 class="mt-4 text-lg font-semibold">Write your clues</h3>
                        <p class="mt-2 text-sm text-zinc-400">Add clues for each answer. Browse the clue library for inspiration from other constructors.</p>
                    </div>
                    <div class="text-center">
                        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full border-2 border-amber-500 text-xl font-bold text-amber-500">3</div>
                        <h3 class="mt-4 text-lg font-semibold">Publish & solve</h3>
                        <p class="mt-2 text-sm text-zinc-400">Hit publish to share your puzzle. Then explore and solve crosswords from the community.</p>
                    </div>
                </div>
            </div>
        </section>

        {{-- CTA --}}
        <section class="border-t border-zinc-800 py-24">
            <div class="mx-auto max-w-2xl px-6 text-center">
                <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Ready to get started?</h2>
                <p class="mt-4 text-zinc-400">Join Crossword Loft and start crafting or solving puzzles today.</p>
                <div class="mt-8 flex flex-col items-center justify-center gap-4 sm:flex-row">
                    @auth
                        <a href="{{ route('crosswords.index') }}" class="rounded-xl bg-amber-500 px-8 py-3.5 text-base font-semibold text-zinc-950 shadow-lg shadow-amber-500/20 hover:bg-amber-400 transition">
                            Go to Dashboard
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="rounded-xl bg-amber-500 px-8 py-3.5 text-base font-semibold text-zinc-950 shadow-lg shadow-amber-500/20 hover:bg-amber-400 transition">
                            Create Free Account
                        </a>
                    @endauth
                </div>
            </div>
        </section>

        {{-- Footer --}}
        <footer class="border-t border-zinc-800 py-8">
            <div class="mx-auto max-w-6xl px-6 text-center text-sm text-zinc-600">
                &copy; {{ date('Y') }} {{ config('app.name', 'Crossword Loft') }}. All rights reserved.
            </div>
        </footer>
    </body>
</html>
