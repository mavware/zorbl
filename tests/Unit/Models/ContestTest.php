<?php

use App\Models\Contest;
use Tests\TestCase;

uses(TestCase::class);

test('isActive returns true when status is active and within time window', function () {
    $contest = new Contest([
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(6),
    ]);

    expect($contest->isActive())->toBeTrue();
});

test('isActive returns false when contest has ended', function () {
    $contest = new Contest([
        'status' => 'ended',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subDay(),
    ]);

    expect($contest->isActive())->toBeFalse();
});

test('isUpcoming returns true for upcoming contest', function () {
    $contest = new Contest([
        'status' => 'upcoming',
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(9),
    ]);

    expect($contest->isUpcoming())->toBeTrue();
});

test('isUpcoming returns false for active contest within window', function () {
    $contest = new Contest([
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(6),
    ]);

    expect($contest->isUpcoming())->toBeFalse();
});

test('hasEnded returns true for ended contest', function () {
    $contest = new Contest([
        'status' => 'ended',
        'starts_at' => now()->subDays(8),
        'ends_at' => now()->subDay(),
    ]);

    expect($contest->hasEnded())->toBeTrue();
});

test('hasEnded returns false for active contest', function () {
    $contest = new Contest([
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(6),
    ]);

    expect($contest->hasEnded())->toBeFalse();
});

test('checkMetaAnswer normalizes and compares correctly', function () {
    $contest = new Contest(['meta_answer' => 'HELLO WORLD']);

    expect($contest->checkMetaAnswer('helloworld'))->toBeTrue()
        ->and($contest->checkMetaAnswer('HELLO WORLD'))->toBeTrue()
        ->and($contest->checkMetaAnswer('hello-world'))->toBeTrue()
        ->and($contest->checkMetaAnswer('hello world!'))->toBeTrue()
        ->and($contest->checkMetaAnswer('goodbye'))->toBeFalse();
});

test('checkMetaAnswer strips non-alpha characters', function () {
    $contest = new Contest(['meta_answer' => 'TEST123']);

    expect($contest->checkMetaAnswer('test'))->toBeTrue()
        ->and($contest->checkMetaAnswer('TEST 123'))->toBeTrue();
});
