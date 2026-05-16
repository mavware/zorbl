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

test('solver does not see meta answer prompt before completing the puzzle', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()
        ->for($user)
        ->withMetaAnswer('What is the hidden theme?', ['MOVIES'])
        ->create();

    $this->actingAs($user);

    Livewire::test('pages::crosswords.solver', ['crossword' => $crossword])
        ->assertDontSee('Meta Answer');
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
        ->set('metaAnswers', ['MOVIES', 'Films'])
        ->set('metaAnswerReveal', false)
        ->call('saveMetadata');

    $crossword->refresh();

    expect($crossword->meta_answer_prompt)->toBe('What is the hidden theme?')
        ->and($crossword->meta_answers)->toBe(['MOVIES', 'Films'])
        ->and($crossword->meta_answer_reveal)->toBeFalse();
});

test('editor can add and remove meta answers', function () {
    $user = User::factory()->create();
    $crossword = Crossword::factory()->for($user)->create();

    $this->actingAs($user);

    $component = Livewire::test('pages::crosswords.editor', ['crossword' => $crossword]);

    $component->set('newMetaAnswer', 'MOVIES')
        ->call('addMetaAnswer')
        ->assertSet('metaAnswers', ['MOVIES'])
        ->assertSet('newMetaAnswer', '');

    $component->set('newMetaAnswer', 'Films')
        ->call('addMetaAnswer')
        ->assertSet('metaAnswers', ['MOVIES', 'Films']);

    $component->call('removeMetaAnswer', 0)
        ->assertSet('metaAnswers', ['Films']);
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
        ->set('metaAnswers', [])
        ->call('saveMetadata');

    $crossword->refresh();

    expect($crossword->meta_answer_prompt)->toBeNull()
        ->and($crossword->meta_answers)->toBeNull();
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
