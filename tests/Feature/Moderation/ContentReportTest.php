<?php

use App\Filament\Resources\ContentReports\Pages\EditContentReport;
use App\Models\ContentReport;
use App\Models\Crossword;
use App\Models\PuzzleComment;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

test('guest visiting the report button is redirected to login when opening', function () {
    $crossword = Crossword::factory()->published()->create();

    Livewire::test('report-button', ['type' => 'puzzle', 'reportableId' => $crossword->id])
        ->call('open')
        ->assertRedirect(route('login'));
});

test('authenticated user files a report against a puzzle', function () {
    $reporter = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Livewire::actingAs($reporter)
        ->test('report-button', ['type' => 'puzzle', 'reportableId' => $crossword->id])
        ->call('open')
        ->set('reason', 'spam')
        ->set('details', 'Looks like an ad.')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSet('submitted', true);

    $report = ContentReport::query()->first();
    expect($report)->not->toBeNull()
        ->and($report->reporter_id)->toBe($reporter->id)
        ->and($report->reportable_type)->toBe(Crossword::class)
        ->and($report->reportable_id)->toBe($crossword->id)
        ->and($report->reason)->toBe('spam')
        ->and($report->details)->toBe('Looks like an ad.')
        ->and($report->status)->toBe(ContentReport::STATUS_PENDING);
});

test('reports support comments and profiles polymorphically', function () {
    $reporter = User::factory()->create();
    $constructor = User::factory()->create();
    $crossword = Crossword::factory()->for($constructor)->published()->create();
    $comment = PuzzleComment::factory()->create([
        'user_id' => $constructor->id,
        'crossword_id' => $crossword->id,
    ]);

    Livewire::actingAs($reporter)
        ->test('report-button', ['type' => 'comment', 'reportableId' => $comment->id])
        ->call('open')
        ->set('reason', 'harassment')
        ->call('submit');

    Livewire::actingAs($reporter)
        ->test('report-button', ['type' => 'profile', 'reportableId' => $constructor->id])
        ->call('open')
        ->set('reason', 'inappropriate')
        ->call('submit');

    expect(ContentReport::query()->where('reportable_type', PuzzleComment::class)->count())->toBe(1);
    expect(ContentReport::query()->where('reportable_type', User::class)->count())->toBe(1);
});

test('a user cannot file two reports against the same content', function () {
    $reporter = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Livewire::actingAs($reporter)
        ->test('report-button', ['type' => 'puzzle', 'reportableId' => $crossword->id])
        ->call('open')
        ->set('reason', 'spam')
        ->call('submit');

    Livewire::actingAs($reporter)
        ->test('report-button', ['type' => 'puzzle', 'reportableId' => $crossword->id])
        ->call('open')
        ->set('reason', 'misinformation')
        ->call('submit');

    expect(ContentReport::query()->count())->toBe(1);
});

test('different users can each report the same content once', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    foreach ([$a, $b] as $user) {
        Livewire::actingAs($user)
            ->test('report-button', ['type' => 'puzzle', 'reportableId' => $crossword->id])
            ->call('open')
            ->set('reason', 'spam')
            ->call('submit');
    }

    expect(ContentReport::query()->count())->toBe(2);
});

test('report submission requires a reason', function () {
    $reporter = User::factory()->create();
    $crossword = Crossword::factory()->published()->create();

    Livewire::actingAs($reporter)
        ->test('report-button', ['type' => 'puzzle', 'reportableId' => $crossword->id])
        ->call('open')
        ->call('submit')
        ->assertHasErrors(['reason']);

    expect(ContentReport::query()->count())->toBe(0);
});

test('submitting against a non-existent reportable does not create a report', function () {
    $reporter = User::factory()->create();

    try {
        Livewire::actingAs($reporter)
            ->test('report-button', ['type' => 'puzzle', 'reportableId' => 999999])
            ->call('open')
            ->set('reason', 'spam')
            ->call('submit');
    } catch (Throwable $e) {
        // abort(404) bubbles out of the Livewire test runner as a generic throwable;
        // we only care that nothing made it to the database.
    }

    expect(ContentReport::query()->count())->toBe(0);
});

test('admin can access the content reports queue', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get('/admin/content-reports')
        ->assertOk();
});

test('non-admins cannot access the moderation queue', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin/content-reports')
        ->assertForbidden();
});

test('updating a pending report stamps reviewer and time', function () {
    Role::findOrCreate('Admin', 'web');
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $report = ContentReport::factory()->create([
        'status' => ContentReport::STATUS_PENDING,
    ]);

    Livewire::actingAs($admin)
        ->test(EditContentReport::class, ['record' => $report->id])
        ->fillForm([
            'status' => ContentReport::STATUS_DISMISSED,
            'resolution_note' => 'Not a violation.',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $report->refresh();

    expect($report->status)->toBe(ContentReport::STATUS_DISMISSED)
        ->and($report->reviewed_by)->toBe($admin->id)
        ->and($report->reviewed_at)->not->toBeNull()
        ->and($report->resolution_note)->toBe('Not a violation.');
});
