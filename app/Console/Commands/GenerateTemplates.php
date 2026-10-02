<?php

namespace App\Console\Commands;

use App\Enums\TemplateGeneratorStyle;
use App\Services\ProceduralTemplateGenerator;
use App\Services\TemplateDraftSaver;
use App\Services\TemplateFillChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('templates:generate
    {size : Square grid size, 5 to 27}
    {--style=standard : standard, themed or themeless}
    {--count=3 : Number of templates to generate}
    {--seed= : Seed for reproducible output}
    {--theme-lengths= : Themed only: comma-separated slot lengths from the top row down}
    {--word=* : A word the grid must have a slot for; repeat the option for several, add :down for a down word}
    {--dry-run : Print the templates without saving them}
    {--fill-check : Try to fill each template from the word list}
    {--fill-timeout=20 : Seconds allowed per fill check}')]
#[Description('Generate crossword templates procedurally and save them as inactive drafts')]
class GenerateTemplates extends Command
{
    public function handle(
        ProceduralTemplateGenerator $generator,
        TemplateDraftSaver $drafts,
        TemplateFillChecker $fillChecker,
    ): int {
        $style = TemplateGeneratorStyle::tryFrom((string) $this->option('style'));

        if ($style === null) {
            $this->error('Unknown style. Use standard, themed or themeless.');

            return self::FAILURE;
        }

        $size = (int) $this->argument('size');
        $seed = $this->option('seed') !== null ? (int) $this->option('seed') : null;
        $themeLengths = array_map('intval', array_filter(explode(',', (string) $this->option('theme-lengths')), 'strlen'));

        $seedWords = array_map(function (string $option): array {
            [$word, $direction] = array_pad(explode(':', $option, 2), 2, 'across');

            return ['word' => $word, 'direction' => $direction];
        }, (array) $this->option('word'));

        try {
            $candidates = $generator->generate($size, $style, max(1, (int) $this->option('count')), $seed, $themeLengths, seedWords: $seedWords);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($candidates === []) {
            $this->warn('No valid template was found. Try another seed.');

            return self::FAILURE;
        }

        foreach ($candidates as $candidate) {
            $this->newLine();
            $this->line("<info>{$candidate->name}</info>");
            $this->line($candidate->philosophy);

            foreach ($candidate->grid as $row) {
                $this->line(implode(' ', array_map(fn (int|string $cell): string => $cell === '#' ? '#' : '.', $row)));
            }

            foreach ($candidate->strengths as $strength) {
                $this->line("  + {$strength}");
            }
            foreach ($candidate->compromises as $compromise) {
                $this->line("  - {$compromise}");
            }
            if ($seedWords !== []) {
                $this->line("  {$candidate->bestFor}");
            }

            if ($this->option('fill-check')) {
                $check = $fillChecker->check($candidate->grid, $candidate->width, $candidate->height, timeout: (int) $this->option('fill-timeout'));
                $this->line('  Fill check: '.$check['message']);
            }
        }

        $this->newLine();

        if ($this->option('dry-run')) {
            $this->info(sprintf('Generated %d template(s). Dry run: nothing saved.', count($candidates)));

            return self::SUCCESS;
        }

        $ids = $drafts->saveAsDrafts($candidates);
        $this->info(sprintf('Saved %d inactive draft(s). Review and activate them in the admin panel.', count($ids)));

        return self::SUCCESS;
    }
}
