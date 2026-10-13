<?php

use App\Models\Crossword;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;

/**
 * The migration skips the test environment so other tests start from an empty
 * tags table; run it here as if outside tests.
 */
function runCommonTagsMigration(string $direction): void
{
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_10_13_164521_seed_common_crossword_tags.php');

    app()->instance('env', 'production');

    try {
        $migration->{$direction}();
    } finally {
        app()->instance('env', 'testing');
    }
}

test('the migration is skipped in the test environment', function () {
    $migration = require database_path('migrations/2026_10_13_164521_seed_common_crossword_tags.php');
    $migration->up();

    expect(Tag::count())->toBe(0);
});

test('it adds the common tags with slugs and leaves existing ones alone', function () {
    $existing = Tag::create(['name' => 'Sports', 'slug' => 'sports']);

    runCommonTagsMigration('up');

    $tags = (require database_path('migrations/2026_10_13_164521_seed_common_crossword_tags.php'))::TAGS;

    expect(Tag::count())->toBe(count($tags))
        ->and(Tag::where('name', 'Sports')->value('id'))->toBe($existing->id)
        ->and(Tag::where('slug', 'pop-culture')->value('name'))->toBe('Pop Culture')
        ->and(Tag::where('slug', 'grid-art')->exists())->toBeTrue()
        ->and(Tag::where('slug', 'video-games')->exists())->toBeTrue()
        ->and(Tag::pluck('slug')->unique())->toHaveCount(count($tags));
});

test('running it twice adds nothing', function () {
    runCommonTagsMigration('up');
    $count = Tag::count();

    runCommonTagsMigration('up');

    expect(Tag::count())->toBe($count);
});

test('rolling back removes only seeded tags nobody has used', function () {
    runCommonTagsMigration('up');
    $custom = Tag::create(['name' => 'Zebras']);
    $onPuzzle = Tag::where('slug', 'cryptic')->firstOrFail();
    $blocked = Tag::where('slug', 'politics')->firstOrFail();
    Crossword::factory()->create()->tags()->attach($onPuzzle);
    User::factory()->create()->blockedTags()->attach($blocked);

    runCommonTagsMigration('down');

    expect(Tag::orderBy('name')->pluck('name')->all())->toBe(['Cryptic', 'Politics', 'Zebras'])
        ->and($custom->fresh())->not->toBeNull();
});
