<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Teams')] class extends Component {
    public bool $showCreateModal = false;

    #[Validate('required|string|max:100')]
    public string $name = '';

    #[Validate('nullable|string|max:500')]
    public string $description = '';

    #[Computed]
    public function teams()
    {
        return Auth::user()->teams()->withCount('members', 'crosswords')->latest()->get();
    }

    public function createTeam(): void
    {
        $this->validate();

        $team = Team::create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'owner_id' => Auth::id(),
        ]);

        $team->members()->attach(Auth::id(), ['role' => TeamRole::Owner->value]);

        $this->reset('name', 'description', 'showCreateModal');
        unset($this->teams);

        Flux\Flux::toast(__('Team created.'));
    }

    public function deleteTeam(int $teamId): void
    {
        $team = Team::findOrFail($teamId);
        $this->authorize('delete', $team);

        $team->crosswords()->update(['team_id' => null]);
        $team->delete();

        unset($this->teams);

        Flux\Flux::toast(__('Team deleted.'));
    }
}; ?>

<div class="mx-auto w-full max-w-4xl p-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <flux:heading size="xl">{{ __('Teams') }}</flux:heading>
                <flux:subheading>{{ __('Collaborate on puzzles with other constructors.') }}</flux:subheading>
            </div>
            <flux:button variant="primary" icon="plus" wire:click="$set('showCreateModal', true)">
                {{ __('Create team') }}
            </flux:button>
        </div>

        @if ($this->teams->isEmpty())
            <flux:callout>
                <x-slot:heading>{{ __('No teams yet') }}</x-slot:heading>
                {{ __('Create a team to start collaborating with other constructors on puzzles.') }}
            </flux:callout>
        @else
            <div class="space-y-3">
                @foreach ($this->teams as $team)
                    <a href="{{ route('teams.show', $team) }}" wire:navigate class="block">
                        <div class="rounded-lg border border-zinc-200 p-4 transition hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <flux:heading>{{ $team->name }}</flux:heading>
                                        @if ($team->isOwner(Auth::user()))
                                            <flux:badge color="amber" size="sm">{{ __('Owner') }}</flux:badge>
                                        @else
                                            <flux:badge size="sm">{{ __('Member') }}</flux:badge>
                                        @endif
                                    </div>
                                    @if ($team->description)
                                        <flux:text class="mt-1">{{ $team->description }}</flux:text>
                                    @endif
                                    <div class="mt-2 flex gap-4">
                                        <flux:text class="text-sm text-zinc-500">
                                            {{ trans_choice(':count member|:count members', $team->members_count) }}
                                        </flux:text>
                                        <flux:text class="text-sm text-zinc-500">
                                            {{ trans_choice(':count puzzle|:count puzzles', $team->crosswords_count) }}
                                        </flux:text>
                                    </div>
                                </div>
                                @if ($team->isOwner(Auth::user()))
                                    <div class="flex items-center" wire:click.prevent.stop>
                                        <flux:button
                                            size="sm"
                                            variant="subtle"
                                            icon="trash"
                                            wire:click="deleteTeam({{ $team->id }})"
                                            wire:confirm="{{ __('Are you sure? All puzzles will be unassigned from this team.') }}"
                                        />
                                    </div>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- Create Team Modal --}}
        <flux:modal wire:model="showCreateModal">
            <form wire:submit="createTeam" class="space-y-4">
                <flux:heading>{{ __('Create a team') }}</flux:heading>
                <flux:subheading>{{ __('Teams let you collaborate with other constructors on puzzles.') }}</flux:subheading>

                <flux:field>
                    <flux:label>{{ __('Team name') }}</flux:label>
                    <flux:input wire:model="name" type="text" placeholder="{{ __('e.g. Sunday Solvers') }}" required />
                    <flux:error name="name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="description" :placeholder="__('What does this team do?')" rows="2" />
                    <flux:error name="description" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showCreateModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</div>
