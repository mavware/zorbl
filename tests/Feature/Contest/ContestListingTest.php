<?php

use App\Models\Contest;
use App\Models\User;

test('contest index page shows active contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create(['title' => 'Active Contest']);

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertOk()
        ->assertSee('Active Contest');
});

test('contest index page shows upcoming contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->upcoming()->create(['title' => 'Upcoming Contest']);

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertOk()
        ->assertSee('Upcoming Contest');
});

test('contest index page shows past contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->ended()->create(['title' => 'Past Contest']);

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertOk()
        ->assertSee('Past Contest');
});

test('contest index page hides draft contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->draft()->create(['title' => 'Draft Contest']);

    $this->actingAs($user)
        ->get(route('contests.index'))
        ->assertOk()
        ->assertDontSee('Draft Contest');
});
