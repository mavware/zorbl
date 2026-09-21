<?php

use App\Models\HelpArticle;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new
#[Title('Help Article')]
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

    public function render(): \Illuminate\View\View
    {
        // Unique <title> per article for search results. Layout appends app name.
        return $this->view()->title($this->articleTitle);
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

    <div class="space-y-6">
        <x-page-header :title="$articleTitle" :subtitle="$articleSummary !== '' ? $articleSummary : null">
            <x-slot:kicker>
                <nav class="flex items-center gap-2" aria-label="{{ __('Breadcrumb') }}">
                    <a href="{{ route('help.index') }}" wire:navigate class="hover:text-amber-300">{{ __('Help Center') }}</a>
                    <span aria-hidden="true">/</span>
                    <span>{{ $categoryLabel }}</span>
                </nav>
            </x-slot:kicker>
        </x-page-header>

        <article class="mx-auto w-full max-w-3xl space-y-10">
            <div class="prose-help text-ink space-y-4 [&_a]:text-amber-400 [&_a]:underline [&_code]:rounded [&_code]:bg-zinc-800 [&_code]:px-1 [&_code]:text-xs [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-ink [&_h3]:mt-6 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-ink [&_li]:my-1 [&_ol]:list-decimal [&_ol]:space-y-1 [&_ol]:pl-6 [&_p]:leading-relaxed [&_ul]:list-disc [&_ul]:space-y-1 [&_ul]:pl-6">
                {!! $renderedBody !!}
            </div>

            @if ($this->related->isNotEmpty())
                <section class="border-hairline border-t pt-8">
                    <h2 class="label-classical font-classical text-[12px] font-semibold text-amber-400 [--label-tracking:0.14em]">
                        {{ __('More in :category', ['category' => $categoryLabel]) }}
                    </h2>
                    <ul class="border-border divide-hairline mt-3 divide-y rounded-sm border">
                        @foreach ($this->related as $r)
                            <li>
                                <a
                                    href="{{ route('help.show', $r) }}"
                                    wire:navigate
                                    class="group flex items-center justify-between gap-4 px-[18px] py-3.5 transition-colors focus:outline-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-amber-400"
                                >
                                    <span class="font-classical text-ink group-hover:text-amber-300 text-[18px] leading-tight font-semibold transition-colors">{{ $r->title }}</span>
                                    <flux:icon name="chevron-right" class="text-ink-faint size-4 shrink-0 transition-colors group-hover:text-amber-400" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <div class="border-border text-ink-muted rounded-sm border p-5 text-sm">
                {{ __("Still stuck? Reach out and we'll help.") }}
                @auth
                    <a href="{{ route('support.create') }}" wire:navigate class="text-amber-400 hover:underline">{{ __('Open a support ticket →') }}</a>
                @else
                    <a href="{{ route('login') }}" class="text-amber-400 hover:underline">{{ __('Log in to contact support →') }}</a>
                @endauth
            </div>
        </article>
    </div>
</div>
