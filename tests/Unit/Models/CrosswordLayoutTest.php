<?php

use App\Enums\CrosswordLayout;
use App\Models\Crossword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('layout is null by default', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    expect($crossword->fresh()->layout)->toBeNull();
});

test('layout is cast to CrosswordLayout enum when set', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'layout' => CrosswordLayout::GridCenterCluesStacked,
    ]);

    $fresh = $crossword->fresh();

    expect($fresh->layout)->toBe(CrosswordLayout::GridCenterCluesStacked)
        ->and($fresh->getAttributes()['layout'])->toBe(CrosswordLayout::GridCenterCluesStacked->value);
});

test('layout accepts the raw enum integer value via mass assignment', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create([
        'layout' => CrosswordLayout::CluesRight->value,
    ]);

    expect($crossword->fresh()->layout)->toBe(CrosswordLayout::CluesRight);
});

test('ordered() contains every CrosswordLayout case exactly once', function () {
    $orderedValues = array_map(fn ($c) => $c->value, CrosswordLayout::ordered());
    $caseValues = array_map(fn ($c) => $c->value, CrosswordLayout::cases());

    sort($orderedValues);
    sort($caseValues);

    expect($orderedValues)->toBe($caseValues);
});
