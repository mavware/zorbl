@props(['stats'])

<div class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-3 lg:grid-cols-6 text-sm">
    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Blocks</div>
        <div class="font-semibold text-gray-950 dark:text-white">
            {{ $stats->blockCount }}
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                ({{ number_format($stats->blockDensity * 100, 1) }}%)
            </span>
        </div>
    </div>

    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Words</div>
        <div class="font-semibold text-gray-950 dark:text-white">
            {{ $stats->wordCount }}
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                ({{ $stats->acrossWordCount }}A / {{ $stats->downWordCount }}D)
            </span>
        </div>
    </div>

    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Length</div>
        <div class="font-semibold text-gray-950 dark:text-white">
            {{ $stats->minWordLength }}–{{ $stats->maxWordLength }}
            <span class="text-xs font-normal text-gray-500 dark:text-gray-400">
                (avg {{ number_format($stats->avgWordLength, 1) }})
            </span>
        </div>
    </div>

    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Symmetry</div>
        <div class="font-semibold text-gray-950 dark:text-white">
            @php
                $sym = [];
                if ($stats->isRotationallySymmetric) { $sym[] = 'Rotational'; }
                if ($stats->isMirrorHorizontal) { $sym[] = 'Mirror-H'; }
                if ($stats->isMirrorVertical) { $sym[] = 'Mirror-V'; }
            @endphp
            {{ empty($sym) ? 'Asymmetric' : implode(' · ', $sym) }}
        </div>
    </div>

    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Connected</div>
        <div class="font-semibold {{ $stats->isConnected ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
            {{ $stats->isConnected ? 'Yes' : 'No' }}
        </div>
    </div>

    <div>
        <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Fully checked</div>
        <div class="font-semibold {{ $stats->isFullyChecked ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
            {{ $stats->isFullyChecked ? 'Yes' : 'No' }}
        </div>
    </div>
</div>
