<?php

use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\User;
use App\Policies\ContestPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->policy = new ContestPolicy;
});

test('viewAny allows guests', function () {
    expect($this->policy->viewAny(null))->toBeTrue();
});

test('viewAny allows authenticated users', function () {
    $user = User::factory()->create();

    expect($this->policy->viewAny($user))->toBeTrue();
});

test('view allows non-draft, non-archived contests for guests', function () {
    $contest = Contest::factory()->active()->create();

    expect($this->policy->view(null, $contest))->toBeTrue();
});

test('view denies draft contests', function () {
    $contest = Contest::factory()->draft()->create();

    expect($this->policy->view(null, $contest))->toBeFalse();
});

test('view denies archived contests', function () {
    $contest = Contest::factory()->create(['status' => 'archived']);

    expect($this->policy->view(null, $contest))->toBeFalse();
});

test('view allows ended contests', function () {
    $contest = Contest::factory()->ended()->create();

    expect($this->policy->view(null, $contest))->toBeTrue();
});

test('register allows for upcoming contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->upcoming()->create();

    expect($this->policy->register($user, $contest))->toBeTrue();
});

test('register allows for active contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    expect($this->policy->register($user, $contest))->toBeTrue();
});

test('register denies for ended contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->ended()->create();

    expect($this->policy->register($user, $contest))->toBeFalse();
});

test('register denies for draft contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->draft()->create();

    expect($this->policy->register($user, $contest))->toBeFalse();
});

test('solve allows registered users on active contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    ContestEntry::factory()->create(['contest_id' => $contest->id, 'user_id' => $user->id]);

    expect($this->policy->solve($user, $contest))->toBeTrue();
});

test('solve denies unregistered users on active contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    expect($this->policy->solve($user, $contest))->toBeFalse();
});

test('solve denies registered users on ended contests', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->ended()->create();
    ContestEntry::factory()->create(['contest_id' => $contest->id, 'user_id' => $user->id]);

    expect($this->policy->solve($user, $contest))->toBeFalse();
});

test('submitMeta allows registered users on active contests with attempts remaining', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create(['max_meta_attempts' => 5]);
    ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
        'meta_solved' => false,
        'meta_attempts_count' => 2,
    ]);

    expect($this->policy->submitMeta($user, $contest))->toBeTrue();
});

test('submitMeta denies when contest is not active', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->ended()->create();
    ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
        'meta_solved' => false,
        'meta_attempts_count' => 0,
    ]);

    expect($this->policy->submitMeta($user, $contest))->toBeFalse();
});

test('submitMeta denies unregistered users', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();

    expect($this->policy->submitMeta($user, $contest))->toBeFalse();
});

test('submitMeta denies when meta is already solved', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create();
    ContestEntry::factory()->metaSolved()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
    ]);

    expect($this->policy->submitMeta($user, $contest))->toBeFalse();
});

test('submitMeta denies when max attempts reached', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create(['max_meta_attempts' => 3]);
    ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
        'meta_solved' => false,
        'meta_attempts_count' => 3,
    ]);

    expect($this->policy->submitMeta($user, $contest))->toBeFalse();
});

test('submitMeta allows unlimited attempts when max_meta_attempts is zero', function () {
    $user = User::factory()->create();
    $contest = Contest::factory()->active()->create(['max_meta_attempts' => 0]);
    ContestEntry::factory()->create([
        'contest_id' => $contest->id,
        'user_id' => $user->id,
        'meta_solved' => false,
        'meta_attempts_count' => 100,
    ]);

    expect($this->policy->submitMeta($user, $contest))->toBeTrue();
});

test('viewLeaderboard allows for non-draft contests', function (string $status) {
    $contest = Contest::factory()->create(['status' => $status]);

    expect($this->policy->viewLeaderboard(null, $contest))->toBeTrue();
})->with(['active', 'upcoming', 'ended', 'archived']);

test('viewLeaderboard denies for draft contests', function () {
    $contest = Contest::factory()->draft()->create();

    expect($this->policy->viewLeaderboard(null, $contest))->toBeFalse();
});
