<?php

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new
#[Title('Help Center')]
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

    <div class="space-y-6">
        <x-page-header
            :kicker="__('Help')"
            :title="__('Help Center')"
            :subtitle="__('Guides and answers for constructors and solvers.')"
        />

        <div class="mx-auto w-full max-w-3xl space-y-8">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search the help center…')"
                clearable
            />

            @php
                $grouped = $this->articlesByCategory;
                $categoryOrder = array_keys(\App\Models\HelpArticle::CATEGORIES);
                $totalArticles = collect($grouped)->sum(fn ($c) => $c->count());
            @endphp

            @if ($totalArticles === 0)
                <div class="border-border-strong flex flex-col items-center justify-center rounded-sm border border-dashed px-6 py-16 text-center">
                    <flux:icon name="question-mark-circle" class="text-ink-faint mb-4 size-10" />
                    <p class="text-ink-muted text-sm">
                        @if ($search !== '')
                            {{ __('No articles match ":term".', ['term' => $search]) }}
                        @else
                            {{ __('No help articles have been published yet.') }}
                        @endif
                    </p>
                </div>
            @else
                <div class="space-y-8">
                    @foreach ($categoryOrder as $key)
                        @php $articles = $grouped[$key] ?? null; @endphp
                        @if ($articles && $articles->isNotEmpty())
                            <section>
                                <h2 class="label-classical font-classical text-[12px] font-semibold text-amber-400 [--label-tracking:0.14em]">
                                    {{ \App\Models\HelpArticle::CATEGORIES[$key] }}
                                </h2>
                                <ul class="border-border divide-hairline mt-3 divide-y rounded-sm border">
                                    @foreach ($articles as $article)
                                        <li>
                                            <a
                                                href="{{ route('help.show', $article) }}"
                                                wire:navigate
                                                class="group flex items-start justify-between gap-4 px-[18px] py-3.5 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-amber-400"
                                            >
                                                <div class="min-w-0">
                                                    <p class="font-classical text-ink group-hover:text-amber-300 text-[18px] leading-tight font-semibold transition-colors">{{ $article->title }}</p>
                                                    @if ($article->summary)
                                                        <p class="text-ink-muted mt-1 text-sm">{{ $article->summary }}</p>
                                                    @endif
                                                </div>
                                                <flux:icon name="chevron-right" class="text-ink-faint mt-1 size-5 shrink-0 transition-colors group-hover:text-amber-400" />
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="border-border rounded-sm border p-6 text-center">
                <p class="text-ink-muted text-sm">
                    {{ __("Can't find what you're looking for?") }}
                </p>
                @auth
                    <a href="{{ route('support.create') }}" wire:navigate class="btn-classical btn-amber-outline mt-3">
                        {{ __('Open a support ticket') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-classical btn-classical-muted mt-3">
                        {{ __('Log in to contact support') }}
                    </a>
                @endauth
            </div>
        </div>
    </div>
</div>
