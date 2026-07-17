<?php

use App\Models\Crossword;
use App\Models\User;
use App\Support\OgImageGenerator;
use App\Support\SiteOgImageGenerator;
use Illuminate\Support\Facades\Storage;

test('generator produces a 1200x630 PNG for a published puzzle', function () {
    $crossword = Crossword::factory()->published()->withBlocks()->create([
        'title' => 'Test Puzzle',
    ]);

    $bytes = app(OgImageGenerator::class)->render($crossword);

    expect($bytes)->toBeString()->not->toBe('');

    $image = imagecreatefromstring($bytes);
    expect($image)->not->toBeFalse();
    expect(imagesx($image))->toBe(1200);
    expect(imagesy($image))->toBe(630);
});

test('site default og generator produces a 1200x630 PNG', function () {
    $bytes = app(SiteOgImageGenerator::class)->render();

    expect($bytes)->toBeString()->not->toBe('');

    $image = imagecreatefromstring($bytes);
    expect($image)->not->toBeFalse();
    expect(imagesx($image))->toBe(1200);
    expect(imagesy($image))->toBe(630);
});

test('og:default command writes a PNG into public', function () {
    $relative = 'og-default-test-'.uniqid().'.png';
    $path = public_path($relative);

    try {
        $this->artisan('og:default', ['--path' => $relative])
            ->assertSuccessful();

        expect(file_exists($path))->toBeTrue();

        $image = imagecreatefromstring((string) file_get_contents($path));
        expect(imagesx($image))->toBe(1200)
            ->and(imagesy($image))->toBe(630);
    } finally {
        @unlink($path);
    }
});

test('the committed default og image exists and is 1200x630', function () {
    $path = public_path('og-default.png');

    expect(file_exists($path))->toBeTrue();

    $image = imagecreatefromstring((string) file_get_contents($path));
    expect(imagesx($image))->toBe(1200)
        ->and(imagesy($image))->toBe(630);
});

test('og image route returns a PNG for a published puzzle', function () {
    Storage::fake('local');
    $crossword = Crossword::factory()->published()->create();

    $response = $this->get(route('puzzles.og', $crossword));

    $response->assertOk()
        ->assertHeader('content-type', 'image/png');

    expect($response->getContent())->not->toBe('');
});

test('og image route 404s for an unpublished puzzle', function () {
    Storage::fake('local');
    $crossword = Crossword::factory()->create(['is_published' => false]);

    $this->get(route('puzzles.og', $crossword))->assertNotFound();
});

test('og image is cached after the first request', function () {
    Storage::fake('local');
    $crossword = Crossword::factory()->published()->create();

    $this->get(route('puzzles.og', $crossword))->assertOk();

    $files = Storage::disk('local')->files('og-images');
    expect($files)->toHaveCount(1)
        ->and($files[0])->toContain('crossword-'.$crossword->id);
});

test('og image route sets a long max-age and an ETag', function () {
    Storage::fake('local');
    $crossword = Crossword::factory()->published()->create();

    $response = $this->get(route('puzzles.og', $crossword));

    $response->assertOk();

    $cacheControl = $response->headers->get('cache-control');
    expect($cacheControl)->toContain('public')
        ->toContain('max-age=604800')
        ->toContain('stale-while-revalidate=2592000');

    expect($response->headers->get('etag'))->toMatch('/^"[a-f0-9]{12}"$/');
});

test('og image returns 304 when the client sends a matching If-None-Match', function () {
    Storage::fake('local');
    $crossword = Crossword::factory()->published()->create();

    $etag = $this->get(route('puzzles.og', $crossword))->headers->get('etag');

    $this->withHeaders(['If-None-Match' => $etag])
        ->get(route('puzzles.og', $crossword))
        ->assertStatus(304);
});

test('og image cache invalidates when the puzzle changes', function () {
    Storage::fake('local');
    $crossword = Crossword::factory()->published()->create(['title' => 'Original']);

    $this->get(route('puzzles.og', $crossword))->assertOk();
    $firstFiles = Storage::disk('local')->files('og-images');

    $crossword->update(['title' => 'Renamed', 'updated_at' => now()->addMinute()]);

    $this->get(route('puzzles.og', $crossword))->assertOk();
    $secondFiles = Storage::disk('local')->files('og-images');

    expect($secondFiles)->toHaveCount(2);
    expect($secondFiles)->not->toBe($firstFiles);
});

test('solve page injects og image meta for guests', function () {
    $owner = User::factory()->create(['name' => 'Ada Lovelace']);
    $crossword = Crossword::factory()->for($owner)->published()->create([
        'title' => 'Sharable Puzzle',
    ]);

    $response = $this->get(route('puzzles.solve', $crossword));

    $response->assertOk()
        ->assertSee(route('puzzles.og', $crossword), false)
        ->assertSee('og:image', false)
        ->assertSee('twitter:card', false)
        ->assertSee('summary_large_image', false)
        ->assertSee('Sharable Puzzle', false)
        ->assertSee('Ada Lovelace', false);
});
