<?php

namespace App\Console\Commands;

use App\Models\Crossword;
use App\Models\DailyPuzzle;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

#[Signature('daily-puzzles:bulk-schedule {from : Start date (Y-m-d)} {to : End date (Y-m-d)} {--tag= : Only pick puzzles with this tag slug} {--overwrite : Replace existing daily puzzle assignments in the range}')]
#[Description('Bulk-schedule daily puzzles for a date range from the pool of eligible published puzzles')]
class BulkScheduleDailyPuzzles extends Command
{
    public function handle(): int
    {
        $from = Carbon::parse($this->argument('from'));
        $to = Carbon::parse($this->argument('to'));

        if ($to->lt($from)) {
            $this->error('The "to" date must be on or after the "from" date.');

            return self::FAILURE;
        }

        $overwrite = $this->option('overwrite');
        $tagSlug = $this->option('tag');

        $candidates = Crossword::query()
            ->where('is_published', true)
            ->whereNotNull('title')
            ->where('title', '!=', '')
            ->whereHas('attempts', fn (Builder $q) => $q->where('is_completed', true))
            ->when($tagSlug, fn (Builder $q) => $q->whereHas(
                'tags',
                fn (Builder $tq) => $tq->where('slug', $tagSlug),
            ))
            ->pluck('id');

        if ($candidates->isEmpty()) {
            $this->error('No eligible published puzzles found.');

            return self::FAILURE;
        }

        $existingDates = DailyPuzzle::query()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->flip();

        $scheduled = 0;
        $skipped = 0;
        $replaced = 0;
        $usedIds = collect();

        $current = $from->copy();
        while ($current->lte($to)) {
            $dateString = $current->toDateString();

            if ($existingDates->has($dateString) && ! $overwrite) {
                $this->line("  Skipped {$dateString} (already assigned)");
                $skipped++;
                $current->addDay();

                continue;
            }

            $seed = crc32($dateString);
            $available = $candidates->diff($usedIds);

            if ($available->isEmpty()) {
                $available = $candidates;
                $usedIds = collect();
            }

            $index = abs($seed) % $available->count();
            $crosswordId = $available->values()->get($index);
            $usedIds->push($crosswordId);

            if ($existingDates->has($dateString)) {
                DailyPuzzle::query()
                    ->where('date', $dateString)
                    ->update(['crossword_id' => $crosswordId]);
                $replaced++;
            } else {
                DailyPuzzle::create([
                    'date' => $dateString,
                    'crossword_id' => $crosswordId,
                ]);
                $scheduled++;
            }

            $this->line("  Scheduled {$dateString} → puzzle #{$crosswordId}");

            $current->addDay();
        }

        $this->newLine();
        $this->info("Done. Scheduled: {$scheduled}, Replaced: {$replaced}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
