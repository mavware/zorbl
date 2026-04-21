<?php

use App\Models\RoadmapItem;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Roadmap')] class extends Component {
    #[Url]
    public string $filter = 'all';

    public bool $showAddModal = false;
    public string $newTitle = '';
    public string $newDescription = '';
    public string $newType = 'feature';
    public string $newTargetDate = '';

    public bool $showEditModal = false;
    public ?int $editingItemId = null;
    public string $editTitle = '';
    public string $editDescription = '';
    public string $editType = 'feature';
    public string $editStatus = 'planned';
    public string $editTargetDate = '';

    #[Computed]
    public function canManage(): bool
    {
        return Gate::allows('create', RoadmapItem::class);
    }

    /** @return array<string, \Illuminate\Support\Collection<int, RoadmapItem>> */
    #[Computed]
    public function groupedItems(): array
    {
        $query = RoadmapItem::query();

        if ($this->filter !== 'all') {
            $query->where('type', $this->filter);
        }

        $items = $query->orderBy('sort_order')->orderByDesc('created_at')->get();

        return [
            'in_progress' => $items->where('status', 'in_progress')->values(),
            'planned' => $items->where('status', 'planned')->values(),
            'completed' => $items->where('status', 'completed')->values(),
        ];
    }

    public function addItem(): void
    {
        Gate::authorize('create', RoadmapItem::class);

        $this->validate([
            'newTitle' => ['required', 'string', 'min:3', 'max:255'],
            'newDescription' => ['nullable', 'string', 'max:2000'],
            'newType' => ['required', 'in:feature,fix,improvement'],
            'newTargetDate' => ['nullable', 'date'],
        ]);

        RoadmapItem::create([
            'title' => trim($this->newTitle),
            'description' => trim($this->newDescription) ?: null,
            'type' => $this->newType,
            'status' => 'planned',
            'target_date' => $this->newTargetDate ?: null,
        ]);

        $this->reset('newTitle', 'newDescription', 'newType', 'newTargetDate', 'showAddModal');
        $this->newType = 'feature';
        unset($this->groupedItems);
    }

    public function openEditModal(int $id): void
    {
        $item = RoadmapItem::findOrFail($id);

        Gate::authorize('update', $item);

        $this->editingItemId = $item->id;
        $this->editTitle = $item->title;
        $this->editDescription = $item->description ?? '';
        $this->editType = $item->type;
        $this->editStatus = $item->status;
        $this->editTargetDate = $item->target_date?->format('Y-m-d') ?? '';
        $this->showEditModal = true;
    }

    public function saveEdit(): void
    {
        $item = RoadmapItem::findOrFail($this->editingItemId);

        Gate::authorize('update', $item);

        $this->validate([
            'editTitle' => ['required', 'string', 'min:3', 'max:255'],
            'editDescription' => ['nullable', 'string', 'max:2000'],
            'editType' => ['required', 'in:feature,fix,improvement'],
            'editStatus' => ['required', 'in:planned,in_progress,completed'],
            'editTargetDate' => ['nullable', 'date'],
        ]);

        $item->update([
            'title' => trim($this->editTitle),
            'description' => trim($this->editDescription) ?: null,
            'type' => $this->editType,
            'status' => $this->editStatus,
            'target_date' => $this->editTargetDate ?: null,
            'completed_date' => $this->editStatus === 'completed' && ! $item->completed_date ? now() : ($this->editStatus !== 'completed' ? null : $item->completed_date),
        ]);

        $this->showEditModal = false;
        $this->editingItemId = null;
        unset($this->groupedItems);
    }

    public function deleteItem(int $id): void
    {
        $item = RoadmapItem::findOrFail($id);

        Gate::authorize('delete', $item);

        $item->delete();
        unset($this->groupedItems);
    }

    public function typeColor(string $type): string
    {
        return match ($type) {
            'feature' => 'indigo',
            'fix' => 'red',
            'improvement' => 'amber',
            default => 'zinc',
        };
    }

    public function typeLabel(string $type): string
    {
        return match ($type) {
            'feature' => __('Feature'),
            'fix' => __('Fix'),
            'improvement' => __('Improvement'),
            default => $type,
        };
    }

    public function statusIcon(string $status): string
    {
        return match ($status) {
            'in_progress' => 'arrow-path',
            'planned' => 'clock',
            'completed' => 'check-circle',
            default => 'question-mark-circle',
        };
    }
}
?>

<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <flux:heading size="xl">{{ __('Roadmap') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Upcoming features, fixes, and improvements.') }}</flux:text>
        </div>

        @if($this->canManage)
            <flux:button variant="primary" icon="plus" wire:click="$set('showAddModal', true)">
                {{ __('Add Item') }}
            </flux:button>
        @endif
    </div>

    {{-- Type Filter --}}
    <div class="flex gap-2">
        <flux:select wire:model.live="filter" class="w-44">
            <flux:select.option value="all">{{ __('All Types') }}</flux:select.option>
            <flux:select.option value="feature">{{ __('Features') }}</flux:select.option>
            <flux:select.option value="fix">{{ __('Fixes') }}</flux:select.option>
            <flux:select.option value="improvement">{{ __('Improvements') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- In Progress --}}
    @if($this->groupedItems['in_progress']->isNotEmpty())
        <section>
            <div class="mb-3 flex items-center gap-2">
                <flux:icon name="arrow-path" class="size-5 text-blue-500" />
                <flux:heading size="lg">{{ __('In Progress') }}</flux:heading>
                <flux:badge size="sm">{{ $this->groupedItems['in_progress']->count() }}</flux:badge>
            </div>
            <div class="grid gap-3">
                @foreach($this->groupedItems['in_progress'] as $item)
                    @include('pages.roadmap._item', ['item' => $item, 'canManage' => $this->canManage])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Planned --}}
    @if($this->groupedItems['planned']->isNotEmpty())
        <section>
            <div class="mb-3 flex items-center gap-2">
                <flux:icon name="clock" class="size-5 text-zinc-400" />
                <flux:heading size="lg">{{ __('Planned') }}</flux:heading>
                <flux:badge size="sm">{{ $this->groupedItems['planned']->count() }}</flux:badge>
            </div>
            <div class="grid gap-3">
                @foreach($this->groupedItems['planned'] as $item)
                    @include('pages.roadmap._item', ['item' => $item, 'canManage' => $this->canManage])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Completed --}}
    @if($this->groupedItems['completed']->isNotEmpty())
        <section>
            <div class="mb-3 flex items-center gap-2">
                <flux:icon name="check-circle" class="size-5 text-green-500" />
                <flux:heading size="lg">{{ __('Completed') }}</flux:heading>
                <flux:badge size="sm">{{ $this->groupedItems['completed']->count() }}</flux:badge>
            </div>
            <div class="grid gap-3">
                @foreach($this->groupedItems['completed'] as $item)
                    @include('pages.roadmap._item', ['item' => $item, 'canManage' => $this->canManage])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Empty State --}}
    @if($this->groupedItems['in_progress']->isEmpty() && $this->groupedItems['planned']->isEmpty() && $this->groupedItems['completed']->isEmpty())
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-zinc-300 py-16 dark:border-zinc-600">
            <flux:icon name="map" class="mb-4 size-12 text-zinc-400" />
            <flux:heading size="lg" class="mb-2">{{ __('No roadmap items yet') }}</flux:heading>
            <flux:text>{{ __('Add features, fixes, and improvements to share what\'s coming next.') }}</flux:text>
        </div>
    @endif

    {{-- Add Item Modal --}}
    <flux:modal wire:model="showAddModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Add Roadmap Item') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="newTitle" placeholder="{{ __('e.g. Dark mode support') }}" />
                <flux:error name="newTitle" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="newDescription" placeholder="{{ __('Describe the feature or fix...') }}" rows="3" />
                <flux:error name="newDescription" />
            </flux:field>

            <div class="grid grid-cols-2 gap-4">
                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <flux:select wire:model="newType">
                        <flux:select.option value="feature">{{ __('Feature') }}</flux:select.option>
                        <flux:select.option value="fix">{{ __('Fix') }}</flux:select.option>
                        <flux:select.option value="improvement">{{ __('Improvement') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="newType" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Target Date') }} <span class="text-zinc-400">({{ __('optional') }})</span></flux:label>
                    <flux:input type="date" wire:model="newTargetDate" />
                    <flux:error name="newTargetDate" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showAddModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="addItem">{{ __('Add') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    {{-- Edit Item Modal --}}
    <flux:modal wire:model="showEditModal">
        <div class="space-y-6">
            <flux:heading size="lg">{{ __('Edit Roadmap Item') }}</flux:heading>

            <flux:field>
                <flux:label>{{ __('Title') }}</flux:label>
                <flux:input wire:model="editTitle" />
                <flux:error name="editTitle" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea wire:model="editDescription" rows="3" />
                <flux:error name="editDescription" />
            </flux:field>

            <div class="grid grid-cols-3 gap-4">
                <flux:field>
                    <flux:label>{{ __('Type') }}</flux:label>
                    <flux:select wire:model="editType">
                        <flux:select.option value="feature">{{ __('Feature') }}</flux:select.option>
                        <flux:select.option value="fix">{{ __('Fix') }}</flux:select.option>
                        <flux:select.option value="improvement">{{ __('Improvement') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="editType" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Status') }}</flux:label>
                    <flux:select wire:model="editStatus">
                        <flux:select.option value="planned">{{ __('Planned') }}</flux:select.option>
                        <flux:select.option value="in_progress">{{ __('In Progress') }}</flux:select.option>
                        <flux:select.option value="completed">{{ __('Completed') }}</flux:select.option>
                    </flux:select>
                    <flux:error name="editStatus" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Target Date') }}</flux:label>
                    <flux:input type="date" wire:model="editTargetDate" />
                    <flux:error name="editTargetDate" />
                </flux:field>
            </div>

            <div class="flex justify-end gap-2">
                <flux:button wire:click="$set('showEditModal', false)">{{ __('Cancel') }}</flux:button>
                <flux:button variant="primary" wire:click="saveEdit">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
