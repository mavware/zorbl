<div class="border-line group flex items-start gap-4 rounded-xl border p-4">
    <div class="mt-0.5">
        <flux:icon :name="$this->statusIcon($item->status)" class="{{ match($item->status) { 'in_progress' => 'text-blue-500', 'completed' => 'text-green-500', default => 'text-zinc-500' } }} size-5" />
    </div>

    <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
            <flux:heading size="sm" class="{{ $item->status === 'completed' ? 'line-through opacity-60' : '' }}">
                {{ $item->title }}
            </flux:heading>
            <flux:badge size="sm" :color="$this->typeColor($item->type)">
                {{ $this->typeLabel($item->type) }}
            </flux:badge>
        </div>

        @if($item->description)
            <flux:text class="mt-1 text-sm">{{ $item->description }}</flux:text>
        @endif

        <div class="mt-2 flex items-center gap-3 text-xs text-zinc-500">
            @if($item->target_date)
                <span class="flex items-center gap-1">
                    <flux:icon name="calendar" class="size-3.5" />
                    {{ $item->target_date->format('M j, Y') }}
                </span>
            @endif
            @if($item->completed_date)
                <span class="flex items-center gap-1">
                    <flux:icon name="check" class="size-3.5" />
                    {{ __('Completed') }} {{ $item->completed_date->format('M j, Y') }}
                </span>
            @endif
        </div>
    </div>

    @if($canManage ?? false)
        <div class="shrink-0 opacity-0 transition-opacity group-hover:opacity-100">
            <flux:dropdown position="bottom" align="end">
                <flux:button variant="ghost" size="sm" icon="ellipsis-vertical" />
                <flux:menu>
                    <flux:menu.item icon="pencil" wire:click="openEditModal({{ $item->id }})">
                        {{ __('Edit') }}
                    </flux:menu.item>
                    <flux:menu.item icon="trash" variant="danger" wire:click="deleteItem({{ $item->id }})" wire:confirm="{{ __('Are you sure you want to delete this roadmap item?') }}">
                        {{ __('Delete') }}
                    </flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>
    @endif
</div>
