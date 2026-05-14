<?php

namespace App\Console\Commands;

use App\Models\Achievement;
use App\Models\Contest;
use App\Models\ContestEntry;
use App\Models\Crossword;
use App\Models\CrosswordLike;
use App\Models\Follow;
use App\Models\PuzzleAttempt;
use App\Models\PuzzleComment;
use App\Models\User;
use Database\Seeders\Activity\AchievementsSeeder;
use Database\Seeders\Activity\AttemptsSeeder;
use Database\Seeders\Activity\ContestsSeeder;
use Database\Seeders\Activity\CrosswordsSeeder;
use Database\Seeders\Activity\SocialSeeder;
use Database\Seeders\Activity\UsersSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('seed:activity {--step= : Run a single step: users, crosswords, attempts, social, achievements, or contests}')]
#[Description('Seed initial user activity with detailed per-step instrumentation. Runs all steps in order, or a single step via --step=<name>.')]
class SeedActivity extends Command
{
    private const array STEPS = [
        'users' => UsersSeeder::class,
        'crosswords' => CrosswordsSeeder::class,
        'attempts' => AttemptsSeeder::class,
        'social' => SocialSeeder::class,
        'achievements' => AchievementsSeeder::class,
        'contests' => ContestsSeeder::class,
    ];

    public function handle(): int
    {
        $startedAt = microtime(true);

        $stepOption = $this->option('step');
        if ($stepOption !== null && ! isset(self::STEPS[$stepOption])) {
            $this->emit("Unknown step '{$stepOption}'. Valid steps: ".implode(', ', array_keys(self::STEPS)), 'error');

            return self::FAILURE;
        }

        $stepsToRun = $stepOption !== null
            ? [$stepOption => self::STEPS[$stepOption]]
            : self::STEPS;

        $this->printHeader($stepOption, count($stepsToRun));

        $initial = $this->snapshotCounts();
        $this->emitCounts('Initial state', $initial);

        $stepNumber = 0;
        $stepTotal = count($stepsToRun);
        $running = $initial;

        foreach ($stepsToRun as $name => $seederClass) {
            $stepNumber++;
            $exit = $this->runStep($stepNumber, $stepTotal, $name, $seederClass, $running);

            if ($exit !== self::SUCCESS) {
                $this->emit("Aborting after failed step '{$name}'. Remaining steps not run.", 'error');
                $this->printFooter($startedAt, $initial, $this->snapshotCounts());

                return $exit;
            }

            $running = $this->snapshotCounts();
        }

        $this->printFooter($startedAt, $initial, $running);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int>  $before
     */
    private function runStep(int $stepNumber, int $stepTotal, string $name, string $seederClass, array $before): int
    {
        $shortName = class_basename($seederClass);
        $this->emit('');
        $this->emit("───── Step {$stepNumber}/{$stepTotal}: {$name} ({$shortName}) ─────");

        $stepStart = microtime(true);
        $stepStartMemory = memory_get_usage(true);

        try {
            $seeder = app($seederClass);
            $seeder->setCommand($this);
            $seeder->run();
        } catch (Throwable $e) {
            $this->emit("{$shortName} threw: ".$e::class.': '.$e->getMessage(), 'error');
            $this->emit('  at '.$e->getFile().':'.$e->getLine(), 'error');
            $this->emit('Stack trace:', 'error');
            foreach (explode("\n", $e->getTraceAsString()) as $line) {
                $this->emit('  '.$line, 'error');
            }
            Log::error("[seed:activity] step '{$name}' failed", ['exception' => $e]);

            return self::FAILURE;
        }

        $elapsed = microtime(true) - $stepStart;
        $memoryUsed = memory_get_usage(true) - $stepStartMemory;
        $after = $this->snapshotCounts();

        $this->emit(sprintf(
            '%s completed in %.2fs. Memory delta: %s%s. Peak so far: %s.',
            $shortName,
            $elapsed,
            $memoryUsed >= 0 ? '+' : '-',
            $this->formatBytes(abs($memoryUsed)),
            $this->formatBytes(memory_get_peak_usage(true)),
        ));

        $this->emitDeltas($before, $after);

        return self::SUCCESS;
    }

    private function printHeader(?string $step, int $stepCount): void
    {
        $this->emit('========================================');
        $this->emit('seed:activity starting');
        $this->emit('  env:     '.app()->environment());
        $this->emit('  time:    '.now()->toDateTimeString().' ('.now()->getTimezone()->getName().')');
        $this->emit('  db:      '.config('database.default'));
        $this->emit('  driver:  '.DB::connection()->getDriverName());
        $this->emit('  php mem: '.$this->formatBytes(memory_get_usage(true)).' / limit '.ini_get('memory_limit'));
        $this->emit('  scope:   '.($step !== null ? "single step '{$step}'" : "all {$stepCount} steps in order"));
        $this->emit('========================================');
    }

    /**
     * @param  array<string, int>  $initial
     * @param  array<string, int>  $final
     */
    private function printFooter(float $startedAt, array $initial, array $final): void
    {
        $elapsed = microtime(true) - $startedAt;
        $this->emit('');
        $this->emit('========================================');
        $this->emitCounts('Final state', $final);
        $this->emit('Net change since start:');
        $this->emitDeltas($initial, $final, indent: '  ', omitUnchanged: true);
        $this->emit(sprintf('Total elapsed: %.2fs. Peak memory: %s.', $elapsed, $this->formatBytes(memory_get_peak_usage(true))));
        $this->emit('========================================');
    }

    /**
     * @return array<string, int>
     */
    private function snapshotCounts(): array
    {
        return [
            'users.total' => User::count(),
            'users.example' => User::where('email', 'like', '%@example.com')->count(),
            'users.solvers' => User::where('email', 'like', 'solver%@example.com')->count(),
            'users.constructors' => User::where('email', 'like', '%@example.com')
                ->where('email', 'not like', 'solver%@example.com')
                ->count(),
            'crosswords.total' => Crossword::count(),
            'crosswords.example' => Crossword::whereHas('user', fn ($q) => $q->where('email', 'like', '%@example.com'))->count(),
            'puzzle_attempts' => PuzzleAttempt::count(),
            'puzzle_comments' => PuzzleComment::count(),
            'crossword_likes' => CrosswordLike::count(),
            'follows' => Follow::count(),
            'achievements' => Achievement::count(),
            'contests' => Contest::count(),
            'contest_entries' => ContestEntry::count(),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function emitCounts(string $label, array $counts): void
    {
        $this->emit($label.':');
        foreach ($counts as $key => $value) {
            $this->emit(sprintf('  %-22s %d', $key.':', $value));
        }
    }

    /**
     * @param  array<string, int>  $before
     * @param  array<string, int>  $after
     */
    private function emitDeltas(array $before, array $after, string $indent = '  ', bool $omitUnchanged = true): void
    {
        $this->emit('Deltas:');
        $anyChanged = false;
        foreach ($after as $key => $value) {
            $delta = $value - ($before[$key] ?? 0);
            if ($omitUnchanged && $delta === 0) {
                continue;
            }
            $anyChanged = true;
            $arrow = $delta > 0 ? '+' : '';
            $this->emit(sprintf('%s%-22s %s%d', $indent, $key.':', $arrow, $delta));
        }
        if (! $anyChanged) {
            $this->emit($indent.'(no changes)');
        }
    }

    /**
     * Mirror a line to the console AND laravel.log so it surfaces in
     * Laravel Cloud's Logs tab regardless of how the command was invoked.
     */
    private function emit(string $message, string $level = 'info'): void
    {
        match ($level) {
            'error' => $this->error($message),
            'warning' => $this->warn($message),
            default => $this->line($message),
        };

        Log::log($level === 'warning' ? 'warning' : $level, '[seed:activity] '.$message);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.'B';
        }
        if ($bytes < 1024 * 1024) {
            return round($bytes / 1024, 1).'KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return round($bytes / (1024 * 1024), 1).'MB';
        }

        return round($bytes / (1024 * 1024 * 1024), 2).'GB';
    }
}
