<?php

use App\Models\ClueEntry;

test('the command backfills quality issues for existing clues without touching timestamps', function () {
    $flagged = ClueEntry::factory()->create(['answer' => 'CAT', 'clue' => 'Cat nap spot']);
    $clean = ClueEntry::factory()->create(['answer' => 'DOG', 'clue' => 'Loyal pet']);

    ClueEntry::query()->update(['quality_issues' => null, 'updated_at' => '2026-01-01 00:00:00']);

    $this->artisan('clues:check-quality')
        ->expectsOutput('Checked 2 clues; 1 have quality issues.')
        ->assertSuccessful();

    expect($flagged->fresh()->quality_issues[0]['code'])->toBe('answer_in_clue')
        ->and($flagged->fresh()->updated_at->toDateString())->toBe('2026-01-01')
        ->and($clean->fresh()->quality_issues)->toBeNull();
});
