<?php

use App\Filament\Pages\Prompt;
use App\Models\Crossword;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('Admin', 'web');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('Admin');
    $this->actingAs($this->admin);
});

test('admin can load the prompt page', function (): void {
    Livewire::test(Prompt::class)->assertSuccessful();
});

test('submitting a prompt feeds it to the theme builder and stores the result', function (): void {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [[
                'type' => 'tool_use',
                'id' => 'toolu_theme',
                'name' => 'submit_theme_entries',
                'input' => [
                    'entries' => [
                        ['entry' => 'SEA CHANGE', 'length' => 9, 'explanation' => 'Hidden body of water.'],
                    ],
                    'assumptions' => '',
                ],
            ]],
        ]),
    ]);

    Livewire::test(Prompt::class)
        ->fillForm(['prompt' => 'Bodies of water hidden in phrases'])
        ->call('submit')
        ->assertSet('result.success', true)
        ->assertSet('result.entries.0.entry', 'SEA CHANGE');

    Http::assertSent(fn ($request) => str_contains(
        $request['messages'][0]['content'] ?? '',
        'Bodies of water hidden in phrases',
    ));
});

test('validation blocks submitting an empty prompt', function (): void {
    Http::fake();

    Livewire::test(Prompt::class)
        ->fillForm(['prompt' => ''])
        ->call('submit')
        ->assertHasFormErrors(['prompt' => 'required']);

    Http::assertNothingSent();
});

test('building a puzzle places the words into a fitting template and redirects to the editor', function (): void {
    Template::factory()->square(15)->create();

    $word = str_repeat('A', 15);

    Livewire::test(Prompt::class)
        ->set('wordsData', ['words' => [$word]])
        ->call('buildPuzzle')
        ->assertRedirect();

    $crossword = Crossword::where('user_id', $this->admin->id)->latest('id')->first();

    expect($crossword)->not->toBeNull()
        ->and($crossword->width)->toBe(15)
        ->and($crossword->height)->toBe(15);

    // The placed word occupies a full across row of the saved solution.
    $rows = array_map(fn (array $row): string => implode('', $row), $crossword->solution);
    expect($rows)->toContain($word);
});

test('building a puzzle handles the repeater keyed-item state shape', function (): void {
    Template::factory()->square(15)->create();

    $word = str_repeat('A', 15);

    // The live simple-repeater state keys each item by a UUID and nests the
    // value under the inner field name, e.g. ['uuid' => ['word' => 'AAA...']].
    Livewire::test(Prompt::class)
        ->set('wordsData', ['words' => ['0e1f' => ['word' => $word]]])
        ->call('buildPuzzle')
        ->assertRedirect();

    $crossword = Crossword::where('user_id', $this->admin->id)->latest('id')->first();
    $rows = array_map(fn (array $row): string => implode('', $row), $crossword->solution);
    expect($rows)->toContain($word);
});

test('building a puzzle warns when no template fits the words', function (): void {
    Template::factory()->square(15)->create(); // open grid: only 15-length slots

    Livewire::test(Prompt::class)
        ->set('wordsData', ['words' => ['SHORT']])
        ->call('buildPuzzle')
        ->assertNotified();

    expect(Crossword::where('user_id', $this->admin->id)->count())->toBe(0);
});

test('building a puzzle warns when no words are supplied', function (): void {
    Livewire::test(Prompt::class)
        ->set('wordsData', ['words' => []])
        ->call('buildPuzzle')
        ->assertNotified();

    expect(Crossword::where('user_id', $this->admin->id)->count())->toBe(0);
});
