<?php

use App\Models\Tag;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds the default crossword tags', function () {
    $this->seed(TagSeeder::class);

    expect(Tag::count())->toBe(10);

    $expectedTags = [
        'Pop Culture',
        'Sports',
        'Science',
        'History',
        'Movies',
        'Music',
        'Geography',
        'Food',
        'Literature',
        'Current Events',
    ];

    foreach ($expectedTags as $name) {
        expect(Tag::where('name', $name)->exists())->toBeTrue();
    }
});

it('is idempotent when run multiple times', function () {
    $this->seed(TagSeeder::class);
    $this->seed(TagSeeder::class);

    expect(Tag::count())->toBe(10);
});
