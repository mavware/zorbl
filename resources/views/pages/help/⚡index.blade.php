<?php

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Title('Help Center')]
#[Layout('layouts.public')]
class extends Component {
    #[Url(as: 'q')]
    public string $search = '';

    /**
     * @return array<string, Collection<int, HelpArticle>>
     */
    #[Computed]
    public function articlesByCategory(): array
    {
        $query = HelpArticle::query()
            ->published()
            ->orderBy('sort_order')
            ->orderBy('title');

        if ($this->search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $this->search).'%';
            $query->where(function ($q) use ($needle): void {
                $q->where('title', 'like', $needle)
                    ->orWhere('summary', 'like', $needle)
                    ->orWhere('body', 'like', $needle);
            });
        }

        return $query->get()->groupBy('category')->all();
    }
}
?>

<div>
    @push('head_meta')
        @php
            $faqs = \App\Models\HelpArticle::query()->published()->orderBy('sort_order')->limit(50)->get();
            $faqJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqs->map(fn ($a) => [
                    '@type' => 'Question',
                    'name' => $a->title,
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => strip_tags($a->rendered_body),
                    ],
                ])->all(),
            ];
        @endphp
        <link rel="canonical" href="{{ route('help.index') }}">
        <meta name="description" content="{{ __('Browse :app help articles, guides, and answers to common constructor and solver questions.', ['app' => config('app.name')]) }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ __('Help Center — :app', ['app' => config('app.name')]) }}">
        <meta property="og:url" content="{{ route('help.index') }}">
        @if (! $faqs->isEmpty())
            <script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endif
    @endpush

    <div class="mx-auto max-w-3xl py-8">
        <header class="text-center">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('Help Center') }}</h1>
            <p class="mt-3 text-zinc-500">{{ __('Guides and answers for constructors and solvers.') }}</p>
        </header>

        <div class="mt-8">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search the help center…')"
                clearable
            />
        </div>

        @php
            $grouped = $this->articlesByCategory;
            $categoryOrder = array_keys(\App\Models\HelpArticle::CATEGORIES);
            $totalArticles = collect($grouped)->sum(fn ($c) => $c->count());
        @endphp

        @if ($totalArticles === 0)
            <div class="mt-12 rounded-xl border border-zinc-800 bg-zinc-900/40 p-10 text-center">
                <p class="text-zinc-400">
                    @if ($search !== '')
                        {{ __('No articles match ":term".', ['term' => $search]) }}
                    @else
                        {{ __('No help articles have been published yet.') }}
                    @endif
                </p>
            </div>
        @else
            <div class="mt-10 space-y-10">
                @foreach ($categoryOrder as $key)
                    @php $articles = $grouped[$key] ?? null; @endphp
                    @if ($articles && $articles->isNotEmpty())
                        <section>
                            <h2 class="text-xs font-semibold uppercase tracking-wider text-amber-500">
                                {{ \App\Models\HelpArticle::CATEGORIES[$key] }}
                            </h2>
                            <ul class="mt-3 divide-y divide-zinc-800 rounded-xl border border-zinc-800 bg-zinc-900/40">
                                @foreach ($articles as $article)
                                    <li>
                                        <a
                                            href="{{ route('help.show', $article) }}"
                                            wire:navigate
                                            class="group flex items-start justify-between gap-4 p-5 transition hover:bg-zinc-900"
                                        >
                                            <div>
                                                <p class="font-medium text-zinc-100 group-hover:text-amber-400">{{ $article->title }}</p>
                                                @if ($article->summary)
                                                    <p class="mt-1 text-sm text-zinc-500">{{ $article->summary }}</p>
                                                @endif
                                            </div>
                                            <svg class="mt-1 h-5 w-5 flex-shrink-0 text-zinc-600 transition group-hover:text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                            </svg>
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endif
                @endforeach
            </div>
        @endif

        <div class="mt-16 rounded-xl border border-zinc-800 bg-zinc-900/40 p-6 text-center">
            <p class="text-sm text-zinc-400">
                {{ __("Can't find what you're looking for?") }}
            </p>
            @auth
                <a
                    href="{{ route('support.create') }}"
                    wire:navigate
                    class="mt-3 inline-flex items-center justify-center rounded-lg bg-amber-500 px-4 py-2 text-sm font-semibold text-zinc-950 hover:bg-amber-400 transition"
                >
                    {{ __('Open a support ticket') }}
                </a>
            @else
                <a
                    href="{{ route('login') }}"
                    class="mt-3 inline-flex items-center justify-center rounded-lg border border-zinc-700 px-4 py-2 text-sm font-semibold text-zinc-100 hover:border-zinc-500 hover:bg-zinc-800 transition"
                >
                    {{ __('Log in to contact support') }}
                </a>
            @endauth
        </div>
    </div>
</div>
