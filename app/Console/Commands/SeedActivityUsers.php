<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\Activity\UsersSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('seed:activity:users')]
#[Description('Run UsersSeeder with detailed instrumentation. Mirrors all output to laravel.log so it surfaces in Laravel Cloud Logs.')]
class SeedActivityUsers extends Command
{
    public function handle(): int
    {
        $startedAt = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->emit('========================================');
        $this->emit('seed:activity:users starting');
        $this->emit('  env:     '.app()->environment());
        $this->emit('  time:    '.now()->toDateTimeString().' ('.now()->getTimezone()->getName().')');
        $this->emit('  db:      '.config('database.default'));
        $this->emit('  driver:  '.DB::connection()->getDriverName());
        $this->emit('  php mem: '.$this->formatBytes($startMemory).' / limit '.ini_get('memory_limit'));
        $this->emit('========================================');

        $before = $this->snapshotUserCounts();
        $this->emitCounts('Pre-flight user counts', $before);

        try {
            $this->emit('Invoking UsersSeeder::run()...');
            $stepStart = microtime(true);

            $seeder = app(UsersSeeder::class);
            $seeder->setCommand($this);
            $seeder->run();

            $stepElapsed = microtime(true) - $stepStart;
            $this->emit(sprintf('UsersSeeder::run() returned cleanly in %.2fs.', $stepElapsed));
        } catch (Throwable $e) {
            $this->emit('UsersSeeder threw: '.$e::class.': '.$e->getMessage(), 'error');
            $this->emit('  in '.$e->getFile().':'.$e->getLine(), 'error');
            $this->emit('Stack trace:', 'error');
            foreach (explode("\n", $e->getTraceAsString()) as $line) {
                $this->emit('  '.$line, 'error');
            }
            Log::error('[seed:activity:users] failed', ['exception' => $e]);

            return self::FAILURE;
        }

        $after = $this->snapshotUserCounts();
        $this->emitCounts('Post-flight user counts', $after);

        $this->emit('Deltas:');
        foreach ($after as $key => $value) {
            $delta = $value - ($before[$key] ?? 0);
            $arrow = $delta > 0 ? '+' : '';
            $this->emit(sprintf('  %-22s %s%d', $key.':', $arrow, $delta));
        }

        $elapsed = microtime(true) - $startedAt;
        $peakMemory = memory_get_peak_usage(true);
        $this->emit('========================================');
        $this->emit(sprintf('Done in %.2fs. Peak memory: %s.', $elapsed, $this->formatBytes($peakMemory)));
        $this->emit('========================================');

        return self::SUCCESS;
    }

    /**
     * Mirror a line to the console AND the Laravel log so it shows up in
     * Laravel Cloud's Logs tab regardless of how the command was invoked.
     */
    private function emit(string $message, string $level = 'info'): void
    {
        match ($level) {
            'error' => $this->error($message),
            'warning' => $this->warn($message),
            default => $this->line($message),
        };

        Log::log($level === 'warning' ? 'warning' : $level, '[seed:activity:users] '.$message);
    }

    /**
     * @return array<string, int>
     */
    private function snapshotUserCounts(): array
    {
        return [
            'total_users' => User::count(),
            'example_com_users' => User::where('email', 'like', '%@example.com')->count(),
            'solver_users' => User::where('email', 'like', 'solver%@example.com')->count(),
            'constructor_users' => User::where('email', 'like', '%@example.com')
                ->where('email', 'not like', 'solver%@example.com')
                ->count(),
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
