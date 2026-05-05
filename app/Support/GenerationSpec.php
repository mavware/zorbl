<?php

namespace App\Support;

use App\Enums\TemplateStyle;

readonly class GenerationSpec
{
    /**
     * @param  list<TemplateStyle>  $styleTags
     * @param  list<string>  $seedEntries
     */
    public function __construct(
        public int $width,
        public int $height,
        public array $styleTags = [],
        public ?string $philosophyHint = null,
        public array $seedEntries = [],
        public int $candidateCount = 3,
    ) {}

    public function describe(): string
    {
        $parts = [sprintf('%dx%d', $this->width, $this->height)];

        if ($this->styleTags !== []) {
            $parts[] = 'tags: '.implode(', ', array_map(fn (TemplateStyle $t) => $t->value, $this->styleTags));
        }

        if (filled($this->philosophyHint)) {
            $parts[] = 'philosophy: '.$this->philosophyHint;
        }

        if ($this->seedEntries !== []) {
            $parts[] = 'seed entries: '.implode(', ', $this->seedEntries);
        }

        return implode(' · ', $parts);
    }
}
