<?php

namespace Zorbl\CrosswordIO;

class Crossword
{
    /**
     * @param  array<int, array<int, mixed>>  $grid  Numbered grid cells (int|'#'|null|0)
     * @param  array<int, array<int, string|null>>  $solution  Solution letters (string|'#'|null|'')
     * @param  array<int, array{number: int, clue: string}>  $clues_across
     * @param  array<int, array{number: int, clue: string}>  $clues_down
     * @param  array<string, array{shapebg?: string, bars?: list<string>}>|null  $styles  Sparse cell styles keyed by "row,col"
     * @param  array<string, mixed>|null  $metadata  Additional format-specific metadata
     */
    public function __construct(
        public readonly int $width,
        public readonly int $height,
        public readonly array $grid,
        public readonly array $solution,
        public readonly array $clues_across,
        public readonly array $clues_down,
        public readonly ?string $title = null,
        public readonly ?string $author = null,
        public readonly ?string $copyright = null,
        public readonly ?string $notes = null,
        public readonly string $kind = 'https://ipuz.org/crossword#1',
        public readonly ?array $styles = null,
        public readonly ?array $metadata = null,
        public readonly ?array $prefilled = null,
    ) {}

    /**
     * Create a Crossword from an importer result array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            width: $data['width'],
            height: $data['height'],
            grid: $data['grid'],
            solution: $data['solution'],
            clues_across: $data['clues_across'],
            clues_down: $data['clues_down'],
            title: $data['title'] ?? null,
            author: $data['author'] ?? null,
            copyright: $data['copyright'] ?? null,
            notes: $data['notes'] ?? null,
            kind: $data['kind'] ?? 'https://ipuz.org/crossword#1',
            styles: $data['styles'] ?? null,
            metadata: $data['metadata'] ?? null,
            prefilled: $data['prefilled'] ?? null,
        );
    }

    public function hasVoidCells(): bool
    {
        foreach ($this->solution as $row) {
            foreach ($row as $cell) {
                if ($cell === null) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasBars(): bool
    {
        foreach ($this->styles ?? [] as $style) {
            if (! empty($style['bars'])) {
                return true;
            }
        }

        return false;
    }

    public function hasNonLatin1Content(): bool
    {
        $pattern = '/[^\x00-\xFF]/u';

        foreach ($this->solution as $row) {
            foreach ($row as $cell) {
                if (is_string($cell) && preg_match($pattern, $cell)) {
                    return true;
                }
            }
        }

        foreach (array_merge($this->clues_across, $this->clues_down) as $clue) {
            if (preg_match($pattern, $clue['clue'] ?? '')) {
                return true;
            }
        }

        foreach ([$this->title, $this->author, $this->copyright, $this->notes] as $field) {
            if (is_string($field) && preg_match($pattern, $field)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Convert to a plain array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'author' => $this->author,
            'copyright' => $this->copyright,
            'notes' => $this->notes,
            'width' => $this->width,
            'height' => $this->height,
            'kind' => $this->kind,
            'grid' => $this->grid,
            'solution' => $this->solution,
            'clues_across' => $this->clues_across,
            'clues_down' => $this->clues_down,
            'styles' => $this->styles,
            'metadata' => $this->metadata,
            'prefilled' => $this->prefilled,
        ];
    }
}
