<?php

use App\Filament\Pages\Prompt;
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
