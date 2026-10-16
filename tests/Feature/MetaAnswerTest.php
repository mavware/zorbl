<?php

use App\Models\Crossword;
use App\Models\PuzzleAttempt;
use App\Models\User;
use Livewire\Livewire;

test('solver sees meta answer prompt after completing a puzzle with meta answers', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES'])
        ->create();

    $this->actingAs($user);

    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('Meta Answer')
        ->assertSee('What is the hidden theme?');
});

test('solver does not see meta answer prompt on puzzles without meta answers', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSee('Meta Answer');
});

test('solver sees the meta answer beneath the puzzle before completing it and can answer straight away', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES'], reveal: true)
        ->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSee('Meta Answer')
        ->assertSee('What is the hidden theme?')
        ->assertSet('isSolved', false);

    // The card comes before the solved-only sections, so it sits right
    // under the grid and clues, and it carries no Submit button: the entry
    // checks itself once the last blank fills.
    $html = $component->html();
    expect($html)->toContain('data-state="open"')
        ->not->toContain('data-test="meta-answer-submit"')
        ->not->toContain('wire:key="share-section"');

    $component->set('metaAnswerSubmission', 'movies')
        ->call('submitMetaAnswer')
        ->assertSet('metaAnswerSubmitted', true)
        ->assertSet('metaAnswerCorrect', true);

    $attempt = PuzzleAttempt::where('user_id', $user->id)->where('crossword_id', $crossword->id)->first();
    expect($attempt->meta_answer)->toBe('movies')
        ->and((bool) $attempt->is_completed)->toBeFalse();
});

test('solver can submit a meta answer', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES'])
        ->create();

    $this->actingAs($user);

    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('metaAnswerSubmission', 'Movies')
        ->call('submitMetaAnswer')
        ->assertSet('metaAnswerSubmitted', true)
        ->assertSet('metaAnswerCorrect', true);

    $attempt = PuzzleAttempt::where('user_id', $user->id)
        ->where('crossword_id', $crossword->id)
        ->first();

    expect($attempt->meta_answer)->toBe('Movies');
});

test('incorrect meta answer is flagged when reveal is enabled', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES'], reveal: true)
        ->create();

    $this->actingAs($user);

    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('metaAnswerSubmission', 'WRONG')
        ->call('submitMetaAnswer')
        ->assertSet('metaAnswerSubmitted', true)
        ->assertSet('metaAnswerCorrect', false);
});

test('meta answer correctness is not revealed when reveal is disabled', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES'], reveal: false)
        ->create();

    $this->actingAs($user);

    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('metaAnswerSubmission', 'WRONG')
        ->call('submitMetaAnswer')
        ->assertSet('metaAnswerSubmitted', true)
        ->assertSet('metaAnswerCorrect', null);
});

test('meta answer matching is case-insensitive', function () {
    $crossword = Crossword::factory()
        ->withMetaAnswer('Theme?', ['MOVIES', 'Films'])
        ->create();

    expect($crossword->isMetaAnswerCorrect('movies'))->toBeTrue()
        ->and($crossword->isMetaAnswerCorrect('FILMS'))->toBeTrue()
        ->and($crossword->isMetaAnswerCorrect('  Movies  '))->toBeTrue()
        ->and($crossword->isMetaAnswerCorrect('wrong'))->toBeFalse();
});

test('hasMetaAnswer returns false when prompt or answers are missing', function () {
    $noPrompt = Crossword::factory()->create([
        'meta_answer_prompt' => null,
        'meta_answers' => ['MOVIES'],
    ]);

    $noAnswers = Crossword::factory()->create([
        'meta_answer_prompt' => 'What is the theme?',
        'meta_answers' => null,
    ]);

    $emptyAnswers = Crossword::factory()->create([
        'meta_answer_prompt' => 'What is the theme?',
        'meta_answers' => [],
    ]);

    $valid = Crossword::factory()->withMetaAnswer()->create();

    expect($noPrompt->hasMetaAnswer())->toBeFalse()
        ->and($noAnswers->hasMetaAnswer())->toBeFalse()
        ->and($emptyAnswers->hasMetaAnswer())->toBeFalse()
        ->and($valid->hasMetaAnswer())->toBeTrue();
});

test('editor can save meta answer fields', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('metaAnswerPrompt', 'What is the hidden theme?')
        ->set('metaAnswer', 'MOVIES')
        ->set('metaAnswerReveal', false)
        ->call('saveMetadata');

    $crossword->refresh();

    expect($crossword->meta_answer_prompt)->toBe('What is the hidden theme?')
        ->and($crossword->meta_answers)->toBe(['MOVIES'])
        ->and($crossword->meta_answer_reveal)->toBeFalse();
});

test('editor stores the single meta answer trimmed, as it is typed, and clears it when emptied', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword]);

    // Autosaves like the other settings fields, with its own saved check.
    $component->set('metaAnswer', '  Hidden message  ')
        ->assertDispatched('setting-saved', property: 'metaAnswer');
    expect($crossword->fresh()->meta_answers)->toBe(['Hidden message']);

    // The preview shows the blanks exactly as solvers will see them.
    expect($component->html())->toContain('data-test="meta-answer-pattern-preview"')
        ->toContain('______ _______');

    $component->set('metaAnswer', '');
    expect($crossword->fresh()->meta_answers)->toBeNull();
    expect($component->html())->not->toContain('data-test="meta-answer-pattern-preview"');
});

test('editor shows the first answer of a puzzle saved with several and keeps the rest until it is edited', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('Theme?', ['MOVIES', 'Films'])
        ->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->assertSet('metaAnswer', 'MOVIES');

    // Saving another field leaves the stored list alone, so the alternates
    // still count for solvers.
    $component->set('title', 'Renamed');
    expect($crossword->fresh()->meta_answers)->toBe(['MOVIES', 'Films'])
        ->and($crossword->fresh()->isMetaAnswerCorrect('films'))->toBeTrue();

    // Editing the answer replaces the list with the single new answer.
    $component->set('metaAnswer', 'CINEMA');
    expect($crossword->fresh()->meta_answers)->toBe(['CINEMA'])
        ->and($crossword->fresh()->isMetaAnswerCorrect('films'))->toBeFalse();
});

test('editor clears meta answers when saving empty prompt', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer()
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.editor', ['crossword' => $crossword])
        ->set('metaAnswerPrompt', '')
        ->set('metaAnswer', '')
        ->call('saveMetadata');

    $crossword->refresh();

    expect($crossword->meta_answer_prompt)->toBeNull()
        ->and($crossword->meta_answers)->toBeNull();
});

test('the meta answer pattern blanks out letters and digits and keeps spaces and punctuation', function () {
    $crossword = Crossword::factory()->withMetaAnswer('Theme?', ["Rock 'n' Roll 2"])->create();

    expect($crossword->metaAnswer())->toBe("Rock 'n' Roll 2")
        ->and($crossword->metaAnswerPattern())->toBe("____ '_' ____ _")
        ->and(Crossword::metaAnswerPatternFor('Café-au-lait'))->toBe('____-__-____');

    $none = Crossword::factory()->create(['meta_answers' => null]);
    $blank = Crossword::factory()->create(['meta_answers' => ['  ']]);

    expect($none->metaAnswer())->toBeNull()
        ->and($none->metaAnswerPattern())->toBeNull()
        ->and($blank->metaAnswer())->toBeNull();
});

test('meta answer matching ignores spacing and punctuation', function () {
    $crossword = Crossword::factory()->withMetaAnswer('Theme?', ["Rock 'n' Roll"])->create();

    expect($crossword->isMetaAnswerCorrect('ROCKNROLL'))->toBeTrue()
        ->and($crossword->isMetaAnswerCorrect("rock 'n' roll"))->toBeTrue()
        ->and($crossword->isMetaAnswerCorrect('Rock n Roll'))->toBeTrue()
        ->and($crossword->isMetaAnswerCorrect('rock and roll'))->toBeFalse()
        ->and($crossword->isMetaAnswerCorrect("' - '"))->toBeFalse();
});

test('solver renders the meta answer as blanks shaped like the answer, never the answer itself', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['SILENT FILMS'])
        ->create();

    $this->actingAs($user);

    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    $html = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('metaAnswerPattern', '______ _____')
        ->html();

    expect($html)->toContain('data-test="meta-answer-section"')
        ->toContain('data-state="open"')
        ->toContain('data-pattern="______ _____"')
        ->toContain('data-test="meta-answer-input"')
        ->not->toContain('SILENT FILMS')
        ->not->toContain('SILENT');
});

test('solver locks the blanks on a correct answer and keeps a wrong one in place to fix', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('Theme?', ['MOVIES'], reveal: true)
        ->create();

    $this->actingAs($user);

    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'completed_at' => now(),
    ]);

    $component = Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->set('metaAnswerSubmission', 'WRONGS')
        ->call('submitMetaAnswer');

    // The wrong letters stay in the blanks, in red, so the solver can back
    // up and change some rather than start over.
    expect($component->html())->toContain('data-state="retry"')
        ->toContain('Not quite!')
        ->toContain('data-test="meta-answer-input"')
        ->toContain("initial: 'WRONGS', submitted: 'WRONGS', autofocus: true")
        ->toContain('border-red-400');

    $component->set('metaAnswerSubmission', 'movies')->call('submitMetaAnswer');

    expect($component->html())->toContain('data-state="done"')
        ->toContain('Correct!')
        ->toContain('data-locked')
        ->not->toContain('data-test="meta-answer-input"');
});

test('guests see the meta answer blanks on the public solve page and can check an answer without it being stored', function () {
    $crossword = Crossword::factory()
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES'], reveal: true)
        ->create(['is_published' => true]);

    $component = Livewire::test('pages::puzzles.solve', ['crossword' => $crossword])
        ->assertSet('hasMetaAnswer', true)
        ->assertSet('metaAnswerPattern', '______');

    // Shown while solving, not behind the client-side solved flag.
    expect($component->html())->toContain('data-test="meta-answer-section"')
        ->not->toContain('x-show="solved"')
        ->toContain('data-pattern="______"')
        ->not->toContain('MOVIES');

    $component->set('metaAnswerSubmission', 'wrongs')
        ->call('submitMetaAnswer')
        ->assertSet('metaAnswerSubmitted', true)
        ->assertSet('metaAnswerCorrect', false)
        ->set('metaAnswerSubmission', 'Movies')
        ->call('submitMetaAnswer')
        ->assertSet('metaAnswerCorrect', true);

    expect(PuzzleAttempt::count())->toBe(0);
});

test('guests get no verdict when the puzzle does not reveal correctness', function () {
    $crossword = Crossword::factory()
        ->withMetaAnswer('Theme?', ['MOVIES'], reveal: false)
        ->create(['is_published' => true]);

    Livewire::test('pages::puzzles.solve', ['crossword' => $crossword])
        ->set('metaAnswerSubmission', 'anything')
        ->call('submitMetaAnswer')
        ->assertSet('metaAnswerSubmitted', true)
        ->assertSet('metaAnswerCorrect', null);
});

test('the public solve page has no meta answer card for puzzles without one', function () {
    $crossword = Crossword::factory()->create(['is_published' => true]);

    Livewire::test('pages::puzzles.solve', ['crossword' => $crossword])
        ->assertSet('hasMetaAnswer', false)
        ->assertDontSee('data-test="meta-answer-section"', false);
});

test('previously submitted meta answer is loaded on solver mount', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the theme?', ['MOVIES'])
        ->create();

    PuzzleAttempt::factory()->create([
        'user_id' => $user->id,
        'crossword_id' => $crossword->id,
        'is_completed' => true,
        'completed_at' => now(),
        'meta_answer' => 'Movies',
    ]);

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertSet('metaAnswerSubmitted', true)
        ->assertSet('metaAnswerSubmission', 'Movies')
        ->assertSet('metaAnswerCorrect', true);
});
