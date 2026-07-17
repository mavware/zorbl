<?php

namespace App\Console\Commands;

use App\Support\SiteOgImageGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('og:default {--path=og-default.png : Path within public/ to write the PNG}')]
#[Description('Render the site-wide default 1200x630 Open Graph share image into public/')]
class GenerateDefaultOgImage extends Command
{
    public function handle(SiteOgImageGenerator $generator): int
    {
        $relative = ltrim((string) $this->option('path'), '/');
        $destination = public_path($relative);

        $bytes = $generator->render();

        if (@file_put_contents($destination, $bytes) === false) {
            $this->error("Could not write to {$destination}.");

            return self::FAILURE;
        }

        $this->info(sprintf('Wrote %s (%d KB).', $relative, (int) round(strlen($bytes) / 1024)));

        return self::SUCCESS;
    }
}
