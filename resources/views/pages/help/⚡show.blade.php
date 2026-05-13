<?php

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Help Article')]
#[Layout('layouts.public')]
class extends Component {
    #[Locked]
    public int $articleId;

    public string $articleTitle = '';
    public string $articleSummary = '';
    public string $articleSlug = '';
    public string $renderedBody = '';
    public string $category = '';
    public string $categoryLabel = '';

    public function mount(HelpArticle $article): void
    {
        abort_unless($article->is_published, 404);

        $this->articleId = $article->id;
        $this->articleTitle = $article->title;
        $this->articleSummary = (string) $article->summary;
        $this->articleSlug = $article->slug;
        $this->renderedBody = $article->rendered_body;
        $this->category = $article->category;
        $this->categoryLabel = $article->category_label;
    }

    /** @return Collection<int, HelpArticle> */
    #[Computed]
    public function related(): Collection
    {
        return HelpArticle::query()
            ->published()
            ->where('category', $this->category)
            ->where('id', '!=', $this->articleId)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->limit(5)
            ->get();
    }
}
?>

<div>
    @push('head_meta')
        @php
            $articleUrl = route('help.show', $articleSlug);
            $description = $articleSummary !== '' ? $articleSummary : __('A help article from :app.', ['app' => config('app.name')]);
            $articleJsonLd = [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                'headline' => $articleTitle,
                'description' => $description,
                'url' => $articleUrl,
                'inLanguage' => 'en',
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => config('app.name'),
                    'url' => url('/'),
                ],
            ];
        @endphp
        <link rel="canonical" href="{{ $articleUrl }}">
        <meta name="description" content="{{ $description }}">
        <meta property="og:type" content="article">
        <meta property="og:title" content="{{ $articleTitle.' — '.config('app.name') }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $articleUrl }}">
        <script type="application/ld+json">{!! json_encode($articleJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush

    <article class="mx-auto max-w-3xl py-8">
        <nav class="mb-6 flex items-center gap-2 text-sm text-zinc-500">
            <a href="{{ route('help.index') }}" wire:navigate class="hover:text-zinc-300">{{ __('Help Center') }}</a>
            <span aria-hidden="true">/</span>
            <span class="text-zinc-400">{{ $categoryLabel }}</span>
        </nav>

        <header class="mb-8 border-b border-zinc-800 pb-6">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $articleTitle }}</h1>
            @if ($articleSummary !== '')
                <p class="mt-3 text-lg text-zinc-400">{{ $articleSummary }}</p>
            @endif
        </header>

        <div class="prose-help space-y-4 text-zinc-300 [&_a]:text-amber-500 [&_a]:underline [&_code]:rounded [&_code]:bg-zinc-800 [&_code]:px-1 [&_code]:text-xs [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-zinc-100 [&_h3]:mt-6 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-zinc-100 [&_li]:my-1 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-6 [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6">
            {!! $renderedBody !!}
        </div>

        @if ($this->related->isNotEmpty())
            <section class="mt-16 border-t border-zinc-800 pt-8">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-zinc-500">
                    {{ __('More in :category', ['category' => $categoryLabel]) }}
                </h2>
                <ul class="mt-3 divide-y divide-zinc-800 rounded-xl border border-zinc-800 bg-zinc-900/40">
                    @foreach ($this->related as $r)
                        <li>
                            <a
                                href="{{ route('help.show', $r) }}"
                                wire:navigate
                                class="group flex items-center justify-between gap-4 p-4 transition hover:bg-zinc-900"
                            >
                                <span class="font-medium text-zinc-200 group-hover:text-amber-400">{{ $r->title }}</span>
                                <svg class="h-4 w-4 flex-shrink-0 text-zinc-600 group-hover:text-amber-500" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.168 10 7.23 6.29a.75.75 0 1 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                                </svg>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="mt-10 rounded-xl border border-zinc-800 bg-zinc-900/40 p-5 text-sm text-zinc-400">
            {{ __("Still stuck? Reach out and we'll help.") }}
            @auth
                <a href="{{ route('support.create') }}" wire:navigate class="text-amber-500 hover:underline">{{ __('Open a support ticket →') }}</a>
            @else
                <a href="{{ route('login') }}" class="text-amber-500 hover:underline">{{ __('Log in to contact support →') }}</a>
            @endauth
        </div>
    </article>
</div>
