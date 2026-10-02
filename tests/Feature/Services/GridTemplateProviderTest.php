<?php

use App\Models\Crossword;
use App\Models\Template;
use App\Services\GridTemplateProvider;
use Database\Factories\TemplateFactory;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

/**
 * @param  list<array{int, int}>  $blocks
 * @return array<int, array<int, int|string>>
 */
function providerGrid(int $size, array $blocks): array
{
    $grid = TemplateFactory::openGrid($size, $size);

    foreach ($blocks as [$row, $col]) {
        $grid[$row][$col] = '#';
    }

    return $grid;
}

test('returns stored templates for supported sizes and none where nothing is stored', function () {
    $provider = app(GridTemplateProvider::class);

    // Nothing is generated on the fly: a size with no stored templates has none.
    for ($size = 2; $size <= 35; $size++) {
        expect($provider->getTemplates($size, $size))
            ->toHaveCount(0, "Expected no templates for {$size}x{$size}");
    }

    Template::factory()->square(13)->count(2)->create();
    Template::factory()->square(13)->inactive()->create();
    Template::factory()->square(21)->count(7)->create();

    expect($provider->getTemplates(13, 13))->toHaveCount(2)
        ->and($provider->getTemplates(21, 21))->toHaveCount(5)
        ->and($provider->getTemplates(17, 17))->toHaveCount(0);
});

test('returns empty array for non-standard sizes', function () {
    $provider = app(GridTemplateProvider::class);

    expect($provider->getTemplates(2, 2))->toBe([])
        ->and($provider->getTemplates(36, 36))->toBe([])
        ->and($provider->getTemplates(15, 21))->toBe([]);
});

test('each template has correct dimensions', function () {
    $provider = app(GridTemplateProvider::class);

    foreach ([5, 13, 21, 35] as $n) {
        Template::factory()->square($n)->count(2)->create();

        $templates = $provider->getTemplates($n, $n);

        expect($templates)->toHaveCount(2);

        foreach ($templates as $template) {
            expect($template['grid'])->toHaveCount($n, "Template '{$template['name']}' has wrong height for {$n}x{$n}");

            foreach ($template['grid'] as $row) {
                expect($row)->toHaveCount($n, "Template '{$template['name']}' has wrong width for {$n}x{$n}");
            }
        }
    }
});

test('each template taken from a published crossword has 180-degree rotational symmetry', function () {
    $symmetric = providerGrid(13, [[0, 4], [12, 8]]);

    Crossword::factory()->published()->create(['width' => 13, 'height' => 13, 'grid' => $symmetric]);
    Crossword::factory()->published()->create(['width' => 13, 'height' => 13, 'grid' => providerGrid(13, [[0, 4]])]);

    $templates = app(GridTemplateProvider::class)->getTemplates(13, 13);

    expect($templates)->toHaveCount(1)
        ->and($templates[0]['grid'])->toBe($symmetric)
        ->and(GridTemplateProvider::hasRotationalSymmetry($templates[0]['grid'], 13, 13))->toBeTrue();
});

test('all words are at least 3 letters long in templates taken from published crosswords', function () {
    $valid = providerGrid(13, [[0, 4], [12, 8]]);

    Crossword::factory()->published()->create(['width' => 13, 'height' => 13, 'grid' => $valid]);
    // A block at (0, 1) leaves a one-letter word in the corner.
    Crossword::factory()->published()->create(['width' => 13, 'height' => 13, 'grid' => providerGrid(13, [[0, 1], [12, 11]])]);

    $templates = app(GridTemplateProvider::class)->getTemplates(13, 13);

    expect($templates)->toHaveCount(1)
        ->and($templates[0]['grid'])->toBe($valid)
        ->and(GridTemplateProvider::validateMinWordLength($templates[0]['grid'], 13, 13))->toBeTrue();
});

test('each template has a name', function () {
    Template::factory()->square(13)->count(2)->create();
    Crossword::factory()->published()->create(['width' => 13, 'height' => 13, 'grid' => providerGrid(13, [[0, 4], [12, 8]])]);

    $templates = app(GridTemplateProvider::class)->getTemplates(13, 13);

    expect($templates)->toHaveCount(3);

    foreach ($templates as $template) {
        expect($template)->toHaveKey('name')
            ->and($template['name'])->toBeString()->not->toBeEmpty();
    }
});
