<?php

use App\Enums\TeamRole;
use App\Models\Crossword;
use App\Models\Team;
use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Team')] class extends Component {
    #[Locked]
    public int $teamId;

    public bool $showInviteModal = false;
    public bool $showAssignModal = false;
    public bool $showEditModal = false;

    #[Validate('required|email|max:255')]
    public string $inviteEmail = '';

    #[Validate('required|string|max:100')]
    public string $editName = '';

    #[Validate('nullable|string|max:500')]
    public string $editDescription = '';

    public function mount(Team $team): void
    {
        $this->authorize('view', $team);
        $this->teamId = $team->id;
    }

    #[Computed]
    public function team(): Team
    {
        return Team::with('owner')->findOrFail($this->teamId);
    }

    #[Computed]
    public function members()
    {
        return $this->team->members()
            ->withCount('crosswords')
            ->orderByRaw("CASE WHEN team_user.role = 'owner' THEN 0 ELSE 1 END")
            ->get();
    }

    #[Computed]
    public function crosswords()
    {
        return $this->team->crosswords()->with('user')->latest()->get();
    }

    #[Computed]
    public function assignablePuzzles()
    {
        return Auth::user()->crosswords()
            ->whereNull('team_id')
            ->orderBy('title')
            ->get(['id', 'title', 'width', 'height']);
    }

    public function isOwner(): bool
    {
        return $this->team->isOwner(Auth::user());
    }

    public function openEditModal(): void
    {
        $this->editName = $this->team->name;
        $this->editDescription = $this->team->description ?? '';
        $this->showEditModal = true;
    }

    public function updateTeam(): void
    {
        $this->authorize('update', $this->team);
        $this->validate([
            'editName' => 'required|string|max:100',
            'editDescription' => 'nullable|string|max:500',
        ]);

        $this->team->update([
            'name' => $this->editName,
            'description' => $this->editDescription ?: null,
        ]);

        $this->showEditModal = false;
        unset($this->team);

        Flux::toast(__('Team updated.'));
    }

    public function inviteMember(): void
    {
        $this->authorize('manageMember', $this->team);
        $this->validate(['inviteEmail' => 'required|email|max:255']);

        $user = User::where('email', $this->inviteEmail)->first();

        if (! $user) {
            $this->addError('inviteEmail', __('No user found with that email address.'));
            return;
        }

        if ($this->team->hasMember($user)) {
            $this->addError('inviteEmail', __('This user is already a member of the team.'));
            return;
        }

        $this->team->members()->attach($user->id, ['role' => TeamRole::Editor->value]);

        $this->reset('inviteEmail', 'showInviteModal');
        unset($this->members);

        Flux::toast(__(':name has been added to the team.', ['name' => $user->name]));
    }

    public function removeMember(int $userId): void
    {
        $this->authorize('manageMember', $this->team);

        if ($userId === $this->team->owner_id) {
            return;
        }

        $this->team->members()->detach($userId);
        $this->team->crosswords()->where('user_id', $userId)->update(['team_id' => null]);

        unset($this->members, $this->crosswords);

        Flux::toast(__('Member removed.'));
    }

    public function leaveTeam(): void
    {
        $team = $this->team;

        if ($team->isOwner(Auth::user())) {
            return;
        }

        $team->members()->detach(Auth::id());
        $team->crosswords()->where('user_id', Auth::id())->update(['team_id' => null]);

        $this->redirect(route('teams.index'), navigate: true);
    }

    public function assignPuzzle(int $crosswordId): void
    {
        $crossword = Auth::user()->crosswords()->findOrFail($crosswordId);
        $crossword->update(['team_id' => $this->teamId]);

        unset($this->crosswords, $this->assignablePuzzles);

        Flux::toast(__('Puzzle assigned to team.'));
    }

    public function unassignPuzzle(int $crosswordId): void
    {
        $crossword = $this->team->crosswords()->where('user_id', Auth::id())->findOrFail($crosswordId);
        $crossword->update(['team_id' => null]);

        unset($this->crosswords, $this->assignablePuzzles);

        Flux::toast(__('Puzzle removed from team.'));
    }
}; ?>

<div class="mx-auto w-full max-w-4xl p-6">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-center gap-2 text-sm text-zinc-500">
                <a href="{{ route('teams.index') }}" wire:navigate class="hover:text-zinc-700 dark:hover:text-zinc-300">{{ __('Teams') }}</a>
                <span>/</span>
            </div>
            <div class="mt-2 flex items-center justify-between">
                <div>
                    <flux:heading size="xl">{{ $this->team->name }}</flux:heading>
                    @if ($this->team->description)
                        <flux:subheading class="mt-1">{{ $this->team->description }}</flux:subheading>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if ($this->isOwner())
                        <flux:button size="sm" variant="subtle" icon="pencil-square" wire:click="openEditModal">
                            {{ __('Edit') }}
                        </flux:button>
                    @else
                        <flux:button
                            size="sm"
                            variant="subtle"
                            icon="arrow-right-start-on-rectangle"
                            wire:click="leaveTeam"
                            wire:confirm="{{ __('Are you sure you want to leave this team?') }}"
                        >
                            {{ __('Leave') }}
                        </flux:button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Members Section --}}
        <div class="mb-8">
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Members') }}</flux:heading>
                @if ($this->isOwner())
                    <flux:button size="sm" variant="primary" icon="user-plus" wire:click="$set('showInviteModal', true)">
                        {{ __('Add member') }}
                    </flux:button>
                @endif
            </div>

            <div class="space-y-2">
                @foreach ($this->members as $member)
                    <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <div class="flex items-center gap-3">
                            <flux:avatar :name="$member->name" :initials="$member->initials()" size="sm" />
                            <div>
                                <div class="flex items-center gap-2">
                                    <flux:text class="font-medium">{{ $member->name }}</flux:text>
                                    <flux:badge size="sm" :color="$member->pivot->role === 'owner' ? 'amber' : 'zinc'">
                                        {{ TeamRole::from($member->pivot->role)->label() }}
                                    </flux:badge>
                                </div>
                                <flux:text class="text-xs text-zinc-500">{{ $member->email }}</flux:text>
                            </div>
                        </div>
                        @if ($this->isOwner() && $member->id !== $this->team->owner_id)
                            <flux:button
                                size="sm"
                                variant="subtle"
                                icon="x-mark"
                                wire:click="removeMember({{ $member->id }})"
                                wire:confirm="{{ __('Remove this member from the team?') }}"
                            />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Team Puzzles Section --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Team Puzzles') }}</flux:heading>
                <flux:button size="sm" variant="primary" icon="plus" wire:click="$set('showAssignModal', true)">
                    {{ __('Assign puzzle') }}
                </flux:button>
            </div>

            @if ($this->crosswords->isEmpty())
                <flux:callout>
                    <x-slot:heading>{{ __('No puzzles yet') }}</x-slot:heading>
                    {{ __('Assign one of your puzzles to this team so all members can collaborate on it.') }}
                </flux:callout>
            @else
                <div class="space-y-2">
                    @foreach ($this->crosswords as $crossword)
                        <div class="flex items-center justify-between rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('crosswords.editor', $crossword) }}" wire:navigate class="font-medium hover:underline">
                                        {{ $crossword->displayTitle() }}
                                    </a>
                                    @if ($crossword->is_published)
                                        <flux:badge color="green" size="sm">{{ __('Published') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ __('Draft') }}</flux:badge>
                                    @endif
                                </div>
                                <flux:text class="text-xs text-zinc-500">
                                    {{ $crossword->width }}×{{ $crossword->height }} &middot; {{ __('by :name', ['name' => $crossword->user->name]) }}
                                </flux:text>
                            </div>
                            @if ($crossword->user_id === Auth::id())
                                <flux:button
                                    size="sm"
                                    variant="subtle"
                                    icon="x-mark"
                                    wire:click="unassignPuzzle({{ $crossword->id }})"
                                    wire:confirm="{{ __('Remove this puzzle from the team?') }}"
                                />
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Invite Member Modal --}}
        <flux:modal wire:model="showInviteModal">
            <form wire:submit="inviteMember" class="space-y-4">
                <flux:heading>{{ __('Add a team member') }}</flux:heading>
                <flux:subheading>{{ __('Enter the email address of the constructor you want to add.') }}</flux:subheading>

                <flux:field>
                    <flux:label>{{ __('Email address') }}</flux:label>
                    <flux:input wire:model="inviteEmail" type="email" placeholder="constructor@example.com" required />
                    <flux:error name="inviteEmail" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showInviteModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Add member') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- Assign Puzzle Modal --}}
        <flux:modal wire:model="showAssignModal">
            <div class="space-y-4">
                <flux:heading>{{ __('Assign a puzzle') }}</flux:heading>
                <flux:subheading>{{ __('Choose one of your puzzles to share with this team.') }}</flux:subheading>

                @if ($this->assignablePuzzles->isEmpty())
                    <flux:text>{{ __('All your puzzles are already assigned to a team.') }}</flux:text>
                @else
                    <div class="max-h-64 space-y-2 overflow-y-auto">
                        @foreach ($this->assignablePuzzles as $puzzle)
                            <button
                                wire:click="assignPuzzle({{ $puzzle->id }})"
                                class="flex w-full items-center justify-between rounded-lg border border-zinc-200 p-3 text-left transition hover:border-zinc-300 dark:border-zinc-700 dark:hover:border-zinc-600"
                            >
                                <div>
                                    <flux:text class="font-medium">{{ $puzzle->displayTitle() }}</flux:text>
                                    <flux:text class="text-xs text-zinc-500">{{ $puzzle->width }}×{{ $puzzle->height }}</flux:text>
                                </div>
                                <flux:icon.plus class="size-4 text-zinc-400" />
                            </button>
                        @endforeach
                    </div>
                @endif

                <div class="flex justify-end">
                    <flux:button variant="ghost" wire:click="$set('showAssignModal', false)">{{ __('Done') }}</flux:button>
                </div>
            </div>
        </flux:modal>

        {{-- Edit Team Modal --}}
        <flux:modal wire:model="showEditModal">
            <form wire:submit="updateTeam" class="space-y-4">
                <flux:heading>{{ __('Edit team') }}</flux:heading>

                <flux:field>
                    <flux:label>{{ __('Team name') }}</flux:label>
                    <flux:input wire:model="editName" type="text" required />
                    <flux:error name="editName" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:textarea wire:model="editDescription" rows="2" />
                    <flux:error name="editDescription" />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    </div>
</div>
