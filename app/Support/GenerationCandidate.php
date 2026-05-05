<?php

namespace App\Support;

readonly class GenerationCandidate
{
    /**
     * @param  array<int, array<int, int|string>>  $grid
     * @param  list<string>  $strengths
     * @param  list<string>  $compromises
     * @param  list<string>  $validationErrors
     */
    public function __construct(
        public string $name,
        public int $width,
        public int $height,
        public array $grid,
        public string $philosophy,
        public array $strengths,
        public array $compromises,
        public ?string $bestFor,
        public ?string $avoidWhen,
        public ?TemplateStats $stats,
        public array $validationErrors,
        public ?int $savedTemplateId = null,
    ) {}

    public function isValid(): bool
    {
        return $this->validationErrors === [];
    }
}
