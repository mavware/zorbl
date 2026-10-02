<?php

namespace Database\Seeders;

use App\Models\Template;
use App\Services\TemplateTagger;
use Illuminate\Database\Seeder;

class TemplateTagSeeder extends Seeder
{
    /**
     * Apply a first-pass set of TemplateStyle tags to each template based
     * on its computed stats. Skips templates that already have any tags so
     * subsequent manual edits aren't overwritten.
     */
    public function run(): void
    {
        $tagger = app(TemplateTagger::class);

        Template::query()->get()->each(fn (Template $template) => $tagger->tag($template));
    }
}
