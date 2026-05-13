<?php

use App\Models\HelpArticle;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

test('help center index renders for guests with seeded categories', function () {
    HelpArticle::factory()->create([
        'category' => 'getting-started',
        'title' => 'What is Zorbl?',
        'slug' => 'what-is-zorbl',
    ]);
    HelpArticle::factory()->create([
        'category' => 'solving',
        'title' => 'How do contests work?',
        'slug' => 'how-contests-work',
    ]);

    $this->get(route('help.index'))
        ->assertOk()
        ->assertSee('Help Center')
        ->assertSee('What is Zorbl?')
        ->assertSee('How do contests work?')
        ->assertSee(HelpArticle::CATEGORIES['getting-started'])
        ->assertSee(HelpArticle::CATEGORIES['solving']);
});

test('help center index hides draft articles', function () {
    HelpArticle::factory()->create(['title' => 'Visible']);
    HelpArticle::factory()->draft()->create(['title' => 'Hidden draft']);

    $this->get(route('help.index'))
        ->assertOk()
        ->assertSee('Visible')
        ->assertDontSee('Hidden draft');
});

test('help center search filters articles by query string', function () {
    HelpArticle::factory()->create(['title' => 'Importing puzzles', 'slug' => 'importing-puzzles']);
    HelpArticle::factory()->create(['title' => 'Solving on mobile', 'slug' => 'solving-on-mobile']);

    Livewire\Livewire::test('pages::help.index')
        ->set('search', 'import')
        ->assertSee('Importing puzzles')
        ->assertDontSee('Solving on mobile');
});

test('individual article page renders rendered markdown', function () {
    $article = HelpArticle::factory()->create([
        'title' => 'Markdown lives',
        'slug' => 'markdown-lives',
        'category' => 'constructing',
        'body' => "## Heading\n\nBody with **bold** word.",
    ]);

    $this->get(route('help.show', $article))
        ->assertOk()
        ->assertSee('Markdown lives')
        ->assertSee('<h2>Heading</h2>', false)
        ->assertSee('<strong>bold</strong>', false);
});

test('draft article returns 404', function () {
    $article = HelpArticle::factory()->draft()->create();

    $this->get(route('help.show', $article))->assertNotFound();
});

test('help center index emits FAQPage json-ld', function () {
    HelpArticle::factory()->create(['title' => 'Q: how does pricing work?', 'body' => 'It is free.']);

    $html = $this->get(route('help.index'))->getContent();

    expect($html)
        ->toContain('application/ld+json')
        ->toContain('"@type":"FAQPage"')
        ->toContain('Q: how does pricing work?');
});

test('article page emits Article json-ld', function () {
    $article = HelpArticle::factory()->create([
        'title' => 'Indexable article',
        'slug' => 'indexable-article',
        'summary' => 'A summary used in description.',
    ]);

    $html = $this->get(route('help.show', $article))->getContent();

    expect($html)
        ->toContain('"@type":"Article"')
        ->toContain('Indexable article')
        ->toContain('A summary used in description.');
});

test('sitemap includes published help articles and skips drafts', function () {
    Cache::forget('sitemap.xml');
    $visible = HelpArticle::factory()->create(['slug' => 'visible-help']);
    $draft = HelpArticle::factory()->draft()->create(['slug' => 'hidden-help']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)
        ->toContain(route('help.index'))
        ->toContain(route('help.show', $visible))
        ->not->toContain(route('help.show', $draft));
});

test('publishing a help article busts the sitemap cache', function () {
    $this->get('/sitemap.xml');
    expect(Cache::has('sitemap.xml'))->toBeTrue();

    HelpArticle::factory()->draft()->create()->update(['is_published' => true]);

    expect(Cache::has('sitemap.xml'))->toBeFalse();
});

test('admin can access the help articles resource', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/help-articles')
        ->assertOk();
});

test('non-admins cannot access the help articles admin resource', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/help-articles')
        ->assertForbidden();
});
