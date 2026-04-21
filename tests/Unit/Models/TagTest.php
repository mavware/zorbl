<?php

use App\Models\Tag;
use Illuminate\Support\Str;

test('tag auto-generates slug from name', function () {
    $tag = new Tag(['name' => 'Pop Culture']);

    expect(Str::slug($tag->name))->toBe('pop-culture');
});

test('factory produces valid tags', function () {
    expect(Tag::factory()->make())
        ->name->not->toBeEmpty();
});
