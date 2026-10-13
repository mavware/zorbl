<?php

use App\Http\Middleware\TrackLastSeen;
use App\Models\AiUsage;
use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\StripeWebhookLog;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Notifications\NewFollower;
use App\Services\AnonymousUserManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

describe('users:prune-anonymous', function () {
    test('keeps an old guest who was seen recently and removes an idle one', function () {
        $idle = app(AnonymousUserManager::class)->create();
        $idle->forceFill(['anonymous_created_at' => now()->subDays(60), 'last_seen_at' => now()->subDays(31)])->save();

        $active = app(AnonymousUserManager::class)->create();
        $active->forceFill(['anonymous_created_at' => now()->subDays(60), 'last_seen_at' => now()->subDays(2)])->save();

        $this->artisan('users:prune-anonymous')
            ->expectsOutput('Pruned 1 anonymous user(s) not seen for 30 day(s).')
            ->assertSuccessful();

        $this->assertModelMissing($idle);
        $this->assertModelExists($active);
    });

    test('falls back to the creation date for guests never stamped with a last sighting', function () {
        $untracked = app(AnonymousUserManager::class)->create();
        $untracked->forceFill(['anonymous_created_at' => now()->subDays(31), 'last_seen_at' => null])->save();

        $this->artisan('users:prune-anonymous')->assertSuccessful();

        $this->assertModelMissing($untracked);
    });

    test('removes the idle guest\'s attempts and refreshes the puzzle\'s cached solve stats', function () {
        $crossword = Crossword::factory()->published()->create();

        $idle = app(AnonymousUserManager::class)->create();
        $idle->forceFill(['last_seen_at' => now()->subDays(31)])->save();
        $idleAttempt = PuzzleAttempt::factory()->completed()->for($idle)->create(['crossword_id' => $crossword->id, 'solve_time_seconds' => 100]);

        PuzzleAttempt::factory()->completed()->for(User::factory()->create())->create(['crossword_id' => $crossword->id, 'solve_time_seconds' => 300]);

        expect($crossword->refresh()->cached_attempts_count)->toBe(2)
            ->and($crossword->cached_avg_solve_time)->toBe(200);

        $this->artisan('users:prune-anonymous')->assertSuccessful();

        $this->assertModelMissing($idleAttempt);

        expect($crossword->refresh()->cached_attempts_count)->toBe(1)
            ->and($crossword->cached_completed_count)->toBe(1)
            ->and($crossword->cached_avg_solve_time)->toBe(300);
    });
});

describe('TrackLastSeen middleware', function () {
    beforeEach(function () {
        Route::middleware(['web', 'auth'])->get('/_last-seen-probe', fn () => 'ok');
    });

    test('stamps last_seen_at on an authenticated request without touching updated_at', function () {
        $user = User::factory()->create(['last_seen_at' => null, 'updated_at' => '2026-01-01 00:00:00']);

        $this->actingAs($user)->get('/_last-seen-probe')->assertOk();

        $user->refresh();

        expect($user->last_seen_at)->not->toBeNull()
            ->and($user->last_seen_at->isCurrentMinute())->toBeTrue()
            ->and($user->updated_at->toDateString())->toBe('2026-01-01');
    });

    test('does not rewrite a sighting that is still fresh', function () {
        $recent = now()->subMinutes(TrackLastSeen::THROTTLE_MINUTES - 5)->startOfSecond();
        $user = User::factory()->create(['last_seen_at' => $recent]);

        $this->actingAs($user)->get('/_last-seen-probe')->assertOk();

        expect($user->refresh()->last_seen_at->equalTo($recent))->toBeTrue();
    });

    test('refreshes a sighting once the throttle window has passed', function () {
        $stale = now()->subMinutes(TrackLastSeen::THROTTLE_MINUTES + 5);
        $user = User::factory()->create(['last_seen_at' => $stale]);

        $this->actingAs($user)->get('/_last-seen-probe')->assertOk();

        expect($user->refresh()->last_seen_at->gt($stale))->toBeTrue();
    });

    test('ignores guests', function () {
        $this->get('/_last-seen-probe')->assertRedirect();
    });
});

describe('model:prune', function () {
    test('drops old log-style rows and keeps recent ones', function () {
        $oldStripe = StripeWebhookLog::factory()->create(['created_at' => now()->subDays(91)]);
        $recentStripe = StripeWebhookLog::factory()->create(['created_at' => now()->subDays(89)]);

        $oldDelivery = WebhookDelivery::factory()->create(['created_at' => now()->subDays(31)]);
        $recentDelivery = WebhookDelivery::factory()->create(['created_at' => now()->subDays(29)]);

        $oldUsage = AiUsage::factory()->create(['created_at' => now()->startOfMonth()->subMonth()->subDay()]);
        $lastMonthUsage = AiUsage::factory()->create(['created_at' => now()->startOfMonth()->subMonth()]);
        $thisMonthUsage = AiUsage::factory()->create(['created_at' => now()]);

        $this->artisan('model:prune')->assertSuccessful();

        $this->assertModelMissing($oldStripe);
        $this->assertModelExists($recentStripe);
        $this->assertModelMissing($oldDelivery);
        $this->assertModelExists($recentDelivery);
        $this->assertModelMissing($oldUsage);
        $this->assertModelExists($lastMonthUsage);
        $this->assertModelExists($thisMonthUsage);
    });
});

describe('notifications:prune', function () {
    test('deletes read notifications past the cutoff and keeps the rest', function () {
        $user = User::factory()->create();
        $follower = User::factory()->create();

        $user->notify(new NewFollower($follower));
        $user->notify(new NewFollower($follower));
        $user->notify(new NewFollower($follower));

        [$oldRead, $recentRead, $oldUnread] = $user->notifications()->get();
        $oldRead->forceFill(['read_at' => now()->subDays(91)])->save();
        $recentRead->forceFill(['read_at' => now()->subDays(10)])->save();
        $oldUnread->forceFill(['created_at' => now()->subDays(200)])->save();

        $this->artisan('notifications:prune')
            ->expectsOutput('Pruned 1 read notification(s) older than 90 day(s).')
            ->assertSuccessful();

        expect($user->notifications()->pluck('id')->all())
            ->toEqualCanonicalizing([$recentRead->id, $oldUnread->id]);
    });
});

describe('cache:prune-expired', function () {
    test('removes expired rows from the database cache table and keeps live ones', function () {
        config()->set('cache.default', 'database');

        DB::table('cache')->insert([
            ['key' => 'expired-key', 'value' => 's:1:"x";', 'expiration' => now()->subMinute()->getTimestamp()],
            ['key' => 'live-key', 'value' => 's:1:"x";', 'expiration' => now()->addHour()->getTimestamp()],
        ]);

        $this->artisan('cache:prune-expired')
            ->expectsOutput('Pruned 1 expired cache entry.')
            ->assertSuccessful();

        expect(DB::table('cache')->pluck('key')->all())->toBe(['live-key']);
    });

    test('is a no-op for non-database cache stores', function () {
        config()->set('cache.default', 'array');

        $this->artisan('cache:prune-expired')
            ->expectsOutput('Cache store [array] uses the array driver; nothing to prune.')
            ->assertSuccessful();
    });
});
