<?php

use App\Models\ClueEntry;
use App\Models\ClueReport;

test('the command backfills quality issues for existing clues without touching timestamps', function () {
    $flagged = ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Cat nap spot']);
    $clean = ClueEntry::factory()->create(['answer' => 'DOG', 'clue' => 'Loyal pet']);

    ClueEntry::query()->update(['quality_issues' => null, 'updated_at' => '2026-01-01 00:00:00']);

    $this->artisan('clues:check-quality')
        ->expectsOutput('Deleted 0 duplicate clues.')
        ->expectsOutput('Checked 2 clues; 1 have quality issues.')
        ->assertSuccessful();

    expect($flagged->fresh()->quality_issues[0]['code'])->toBe('answer_in_clue')
        ->and($flagged->fresh()->updated_at->toDateString())->toBe('2026-01-01')
        ->and($clean->fresh()->quality_issues)->toBeNull();
});

test('the command deletes duplicate clues, keeping the oldest copy', function () {
    $original = ClueEntry::factory()->create(['answer' => 'OREO', 'clue' => 'Sandwich cookie', 'status' => ClueEntry::STATUS_PENDING]);
    $sameText = ClueEntry::factory()->create(['answer' => 'OREO', 'clue' => 'Sandwich cookie', 'status' => ClueEntry::STATUS_PENDING]);
    $differentCaseAndSpacing = ClueEntry::factory()->create(['answer' => 'OREO', 'clue' => '  sandwich COOKIE ', 'status' => ClueEntry::STATUS_PENDING]);

    $this->artisan('clues:check-quality')
        ->expectsOutput('Deleted 2 duplicate clues.')
        ->assertSuccessful();

    $this->assertModelExists($original);
    $this->assertModelMissing($sameText);
    $this->assertModelMissing($differentCaseAndSpacing);
});

test('the command keeps an approved copy over an older pending one', function () {
    $olderPending = ClueEntry::factory()->create(['answer' => 'OREO', 'clue' => 'Sandwich cookie', 'status' => ClueEntry::STATUS_PENDING]);
    $newerApproved = ClueEntry::factory()->create(['answer' => 'OREO', 'clue' => 'Sandwich cookie', 'status' => ClueEntry::STATUS_APPROVED]);

    $this->artisan('clues:check-quality')->assertSuccessful();

    $this->assertModelExists($newerApproved);
    $this->assertModelMissing($olderPending);
});

test('the command leaves the same clue for different answers alone', function () {
    $oreo = ClueEntry::factory()->create(['answer' => 'OREO', 'clue' => 'Sandwich cookie']);
    $hydrox = ClueEntry::factory()->create(['answer' => 'HYDROX', 'clue' => 'Sandwich cookie']);

    $this->artisan('clues:check-quality')
        ->expectsOutput('Deleted 0 duplicate clues.')
        ->assertSuccessful();

    $this->assertModelExists($oreo);
    $this->assertModelExists($hydrox);
});

test('deleting a duplicate removes its reports', function () {
    ClueEntry::factory()->create(['answer' => 'OREO', 'clue' => 'Sandwich cookie']);
    $duplicate = ClueEntry::factory()->create(['answer' => 'OREO', 'clue' => 'Sandwich cookie']);
    $report = ClueReport::factory()->create(['clue_entry_id' => $duplicate->id]);

    $this->artisan('clues:check-quality')->assertSuccessful();

    $this->assertModelMissing($report);
});

test('a dry run reports duplicates and issues without changing anything', function () {
    ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Cat nap spot']);
    $duplicate = ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Cat nap spot']);

    ClueEntry::query()->update(['quality_issues' => null]);

    $this->artisan('clues:check-quality', ['--dry-run' => true])
        ->expectsOutput('Would delete 1 duplicate clues.')
        ->expectsOutput('Checked 2 clues; 2 have quality issues.')
        ->assertSuccessful();

    $this->assertModelExists($duplicate);
    expect(ClueEntry::whereNotNull('quality_issues')->count())->toBe(0);
});
