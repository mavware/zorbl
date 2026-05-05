@props(['rank'])

@if($rank <= 3)
    <span @class([
        'inline-flex size-7 items-center justify-center rounded-full text-sm font-bold text-white',
        'bg-amber-500' => $rank === 1,
        'bg-zinc-400' => $rank === 2,
        'bg-amber-700' => $rank === 3,
    ])>{{ $rank }}</span>
@else
    <span class="px-2 text-sm text-zinc-600">{{ $rank }}</span>
@endif
