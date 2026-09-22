<div class="border-border hover:border-border-strong flex items-start gap-4 rounded-sm border p-4.5 transition-colors">
    <div class="{{ match($item->status) { 'in_progress' => 'border-amber-400 text-amber-400', default => 'border-border-strong text-ink-faint' } }} flex size-9 shrink-0 items-center justify-center rounded-sm border">
        <flux:icon :name="$this->statusIcon($item->status)" class="size-4" />
    </div>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="font-classical text-[19px] leading-tight font-semibold {{ $item->status === 'completed' ? 'text-ink-muted line-through' : 'text-ink' }}">
                {{ $item->title }}
            </h3>
            <span class="chip-classical border-ink-faint text-ink-faint">{{ $this->typeLabel($item->type) }}</span>
        </div>

        @if($item->description)
            <p class="text-ink-muted mt-1.5 text-sm leading-[1.65]">{{ $item->description }}</p>
        @endif

        @if($item->target_date || $item->completed_date)
            <div class="meta-classical mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1">
                @if($item->target_date)
                    <span class="flex items-center gap-1 whitespace-nowrap">
                        <flux:icon name="calendar" class="size-3.5" />
                        <span class="tnum">{{ $item->target_date->format('M j, Y') }}</span>
                    </span>
                @endif
                @if($item->completed_date)
                    <span class="flex items-center gap-1 whitespace-nowrap">
                        <flux:icon name="check" class="size-3.5" />
                        <span class="tnum">{{ __('Completed') }} {{ $item->completed_date->format('M j, Y') }}</span>
                    </span>
                @endif
            </div>
        @endif
    </div>

    @if($canManage ?? false)
        <div class="shrink-0">
            <flux:dropdown position="bottom" align="end">
                <button type="button" class="btn-classical btn-classical-muted h-8 w-8 px-0" aria-label="{{ __('More actions') }}">
                    <flux:icon name="ellipsis-vertical" class="size-4" />
                </button>
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
