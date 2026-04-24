<?php

namespace App\Console\Commands;

use App\Models\Contest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('contests:publish-scheduled')]
#[Description('Transition draft contests with a reached publish_at time to upcoming status')]
class PublishScheduledContests extends Command
{
    public function handle(): int
    {
        $contests = Contest::where('status', 'draft')
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', now())
            ->get();

        if ($contests->isEmpty()) {
            $this->info('No contests to publish.');

            return self::SUCCESS;
        }

        foreach ($contests as $contest) {
            $contest->update(['status' => 'upcoming']);

            $this->info("Published contest: {$contest->title}");
        }

        $this->info("Published {$contests->count()} contest(s).");

        return self::SUCCESS;
    }
}
