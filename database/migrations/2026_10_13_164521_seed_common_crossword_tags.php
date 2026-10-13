<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tags constructors commonly reach for: puzzle styles, difficulty, and
     * subject areas. Names already in the table are left untouched.
     *
     * @var list<string>
     */
    public const array TAGS = [
        // Style
        'Themed',
        'Themeless',
        'Cryptic',
        'Variety',
        'Rebus',
        'Meta',
        'Puns',
        'Wordplay',
        'Grid Art',
        'Mini',
        'Midi',
        'Logic',
        // Difficulty
        'Easy',
        'Medium',
        'Hard',
        // Subject
        'Pop Culture',
        'Sports',
        'Science',
        'History',
        'Movies',
        'Television',
        'Music',
        'Geography',
        'Food',
        'Literature',
        'Current Events',
        'Animals',
        'Nature',
        'Travel',
        'Art',
        'Technology',
        'Business',
        'Politics',
        'Holidays',
        'Seasonal',
        'Mythology',
        'Language',
        'Math',
        'Games',
        'Video Games',
        'Comics',
        'Theater',
        'Kids',
        'Education',
        'Religion',
        'Health',
        'Fashion',
    ];

    /**
     * Tests build the exact tags they need against an empty table, so the
     * seed data is only written outside the test environment.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        $now = now();

        DB::table('tags')->insertOrIgnore(array_map(fn (string $name): array => [
            'name' => $name,
            'slug' => Str::slug($name),
            'created_at' => $now,
            'updated_at' => $now,
        ], self::TAGS));
    }

    /**
     * Removes only the seeded tags nobody has used, so rolling back never
     * strips a tag from a puzzle or a user's blocked list.
     */
    public function down(): void
    {
        DB::table('tags')
            ->whereIn('slug', array_map(Str::slug(...), self::TAGS))
            ->whereNotExists(fn (Builder $query) => $query->from('crossword_tag')->whereColumn('crossword_tag.tag_id', 'tags.id'))
            ->whereNotExists(fn (Builder $query) => $query->from('blocked_tags')->whereColumn('blocked_tags.tag_id', 'tags.id'))
            ->delete();
    }
};
