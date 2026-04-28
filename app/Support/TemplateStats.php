<?php

namespace App\Support;

readonly class TemplateStats
{
    public function __construct(
        public int $width,
        public int $height,
        public int $cellCount,
        public int $blockCount,
        public float $blockDensity,
        public int $whiteCount,
        public int $acrossWordCount,
        public int $downWordCount,
        public int $wordCount,
        public int $minWordLength,
        public int $maxWordLength,
        public float $avgWordLength,
        public bool $isRotationallySymmetric,
        public bool $isMirrorHorizontal,
        public bool $isMirrorVertical,
        public bool $isFullyChecked,
        public bool $isConnected,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'width' => $this->width,
            'height' => $this->height,
            'cell_count' => $this->cellCount,
            'block_count' => $this->blockCount,
            'block_density' => $this->blockDensity,
            'white_count' => $this->whiteCount,
            'across_word_count' => $this->acrossWordCount,
            'down_word_count' => $this->downWordCount,
            'word_count' => $this->wordCount,
            'min_word_length' => $this->minWordLength,
            'max_word_length' => $this->maxWordLength,
            'avg_word_length' => $this->avgWordLength,
            'is_rotationally_symmetric' => $this->isRotationallySymmetric,
            'is_mirror_horizontal' => $this->isMirrorHorizontal,
            'is_mirror_vertical' => $this->isMirrorVertical,
            'is_fully_checked' => $this->isFullyChecked,
            'is_connected' => $this->isConnected,
        ];
    }
}
