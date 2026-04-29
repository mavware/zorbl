<?php

namespace App\Services;

use App\Enums\TemplateStyle;
use App\Models\Template;
use App\Support\GenerationCandidate;
use App\Support\GenerationSpec;

class TemplateGeneratorService
{
    private const MODEL = 'claude-opus-4-7';

    private const MAX_EXAMPLES = 6;

    private const MAX_CONTRASTS = 2;

    public function __construct(
        private AnthropicClient $anthropic,
        private TemplateStatsService $stats,
    ) {}

    /**
     * @return list<GenerationCandidate>
     */
    public function generate(GenerationSpec $spec): array
    {
        $examples = $this->selectExamples($spec);
        $contrasts = $this->selectContrasts($spec, $examples);

        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($spec, $examples, $contrasts);

        $tool = $this->generationTool($spec);

        $response = $this->anthropic->send(
            systemPrompt: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userMessage]],
            options: [
                'model' => self::MODEL,
                'max_tokens' => 16000,
                'tools' => [$tool],
                // `any` plus a single tool effectively forces that tool. The
                // API forbids combining any tool-use forcing with adaptive
                // thinking — we'd rather have schema-constrained output than
                // thinking, so thinking is intentionally omitted here.
                'tool_choice' => ['type' => 'any'],
                'timeout' => 300,
            ],
        );

        if (! $response['success']) {
            throw new \RuntimeException(
                'Generation failed: '.($response['body'] ?? 'unknown error').
                ' (status '.($response['status'] ?? 'unknown').')'
            );
        }

        $payload = AnthropicClient::extractToolUse($response['data'], 'generate_templates');

        if ($payload === null || ! is_array($payload['candidates'] ?? null)) {
            throw new \RuntimeException('Generation succeeded but no candidates were returned.');
        }

        return array_map(
            fn (array $raw) => $this->buildCandidate($raw, $spec),
            array_slice($payload['candidates'], 0, $spec->candidateCount),
        );
    }

    /**
     * @param  list<GenerationCandidate>  $candidates
     * @return list<int> template IDs that were saved
     */
    public function saveAsDrafts(array $candidates): array
    {
        $ids = [];
        foreach ($candidates as $candidate) {
            if (! $candidate->isValid()) {
                continue;
            }

            $template = Template::create([
                'name' => $this->uniqueName($candidate->name),
                'width' => $candidate->width,
                'height' => $candidate->height,
                'grid' => $candidate->grid,
                'styles' => null,
                'min_word_length' => 3,
                'sort_order' => 0,
                'is_active' => false,
            ]);

            $template->annotation()->create([
                'philosophy' => $candidate->philosophy,
                'strengths' => $candidate->strengths,
                'compromises' => $candidate->compromises,
                'best_for' => $candidate->bestFor,
                'avoid_when' => $candidate->avoidWhen,
            ]);

            $ids[] = $template->id;
        }

        return $ids;
    }

    /**
     * @return list<Template>
     */
    private function selectExamples(GenerationSpec $spec): array
    {
        $sizeMatches = Template::with(['annotation', 'templateTags'])
            ->where('width', $spec->width)
            ->where('height', $spec->height)
            ->whereHas('annotation')
            ->get();

        if ($sizeMatches->isEmpty()) {
            // Fall back to all annotated templates; sort by closest size
            $sizeMatches = Template::with(['annotation', 'templateTags'])
                ->whereHas('annotation')
                ->get()
                ->sortBy(fn (Template $t) => abs($t->width - $spec->width) + abs($t->height - $spec->height))
                ->values();
        }

        $tagValues = collect($spec->styleTags)->map(fn (TemplateStyle $t) => $t->value)->all();

        $scored = $sizeMatches->map(function (Template $t) use ($tagValues) {
            $tTags = $t->templateTags->pluck('tag.value')->all();
            $overlap = count(array_intersect($tagValues, $tTags));

            return ['template' => $t, 'score' => $overlap];
        });

        return $scored->sortByDesc('score')->take(self::MAX_EXAMPLES)->pluck('template')->values()->all();
    }

    /**
     * @param  list<Template>  $examples
     * @return list<Template>
     */
    private function selectContrasts(GenerationSpec $spec, array $examples): array
    {
        $usedIds = collect($examples)->pluck('id')->all();
        $tagValues = collect($spec->styleTags)->map(fn (TemplateStyle $t) => $t->value)->all();

        return Template::with(['annotation', 'templateTags'])
            ->where('width', $spec->width)
            ->where('height', $spec->height)
            ->whereHas('annotation')
            ->whereNotIn('id', $usedIds)
            ->get()
            ->filter(function (Template $t) use ($tagValues) {
                $tTags = $t->templateTags->pluck('tag.value')->all();

                return count(array_intersect($tagValues, $tTags)) === 0;
            })
            ->take(self::MAX_CONTRASTS)
            ->values()
            ->all();
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are a master crossword-template designer. Your job is to invent new, original blocks-and-whites grid templates that are structurally sound and stylistically distinctive.

You will be shown a curated set of example templates with their grids, computed structural stats, style tags, and prose annotations. Use these examples to internalize the design vocabulary — block density, word counts, symmetry, corner shapes, theme-row placement — then produce candidates that match the user's spec.

Hard rules for every grid you produce:
- Every cell is either 0 (open white square) or '#' (block). No other values.
- Grid must match the requested width and height exactly.
- Unless the user explicitly requests asymmetry, use 180-degree rotational symmetry — i.e. for every cell (r, c), the cell at (height-1-r, width-1-c) has the same state.
- Every white cell must be reachable from every other white cell via orthogonal moves through whites only (the white-square graph is connected).
- Every white cell must be part of both an across word AND a down word of length >= 2.
- Avoid runs of open cells shorter than 3 letters unless the user explicitly asks for a "relaxed-rules" or "bar-grid" style template.

Produce candidates that are *different from each other and from the examples* — variations on placement, density, or signature. Don't just rotate or mirror an existing example. The annotation you write for each candidate should state its intent, three concrete strengths, three concrete trade-offs, a one-sentence "best_for" use case, and a one-sentence "avoid_when" anti-use-case. Match the voice of the example annotations: terse, opinionated, structural — not promotional.
PROMPT;
    }

    /**
     * @param  list<Template>  $examples
     * @param  list<Template>  $contrasts
     */
    private function buildUserMessage(GenerationSpec $spec, array $examples, array $contrasts): string
    {
        $lines = ['# Curated examples', ''];

        foreach ($examples as $t) {
            $lines[] = $this->renderTemplateForPrompt($t, role: 'EXAMPLE');
            $lines[] = '';
        }

        if ($contrasts !== []) {
            $lines[] = '# Contrast examples (NOT what we want)';
            $lines[] = '';
            foreach ($contrasts as $t) {
                $lines[] = $this->renderTemplateForPrompt($t, role: 'CONTRAST');
                $lines[] = '';
            }
        }

        $lines[] = '# Your task';
        $lines[] = '';
        $lines[] = 'Generate '.$spec->candidateCount.' candidate templates matching this spec:';
        $lines[] = '';
        $lines[] = '- size: '.$spec->width.'x'.$spec->height;

        if ($spec->styleTags !== []) {
            $tagList = implode(', ', array_map(fn (TemplateStyle $t) => $t->value, $spec->styleTags));
            $lines[] = '- target tags: '.$tagList;
        }

        if (filled($spec->philosophyHint)) {
            $lines[] = '- philosophy direction: '.$spec->philosophyHint;
        }

        if ($spec->seedEntries !== []) {
            $lines[] = '- seed entries (long answers the constructor wants to feature): '.implode(', ', $spec->seedEntries);
            $lines[] = '  Place blocks so that long open runs accommodate these lengths in plausible positions.';
        }

        $lines[] = '';
        $lines[] = 'Return your output via the `generate_templates` tool. Each candidate must be meaningfully distinct from the others and from the examples — different block-cluster shapes, different signature features, or different theme-row positions.';

        return implode("\n", $lines);
    }

    private function renderTemplateForPrompt(Template $template, string $role): string
    {
        $stats = $this->stats->forTemplate($template);
        $tags = $template->templateTags->pluck('tag.value')->sort()->values()->all();
        $annotation = $template->annotation;

        $yaml = "- {$role}: \"{$template->name}\"\n";
        $yaml .= "  size: {$template->width}x{$template->height}\n";
        $yaml .= '  tags: ['.implode(', ', $tags)."]\n";
        $yaml .= sprintf(
            "  stats: density=%.1f%%, words=%d, max_word=%d, avg_word=%.1f, symmetry=%s\n",
            $stats->blockDensity * 100,
            $stats->wordCount,
            $stats->maxWordLength,
            $stats->avgWordLength,
            $stats->isRotationallySymmetric ? 'rotational-180' : 'asymmetric',
        );

        $yaml .= "  grid: |\n";
        foreach ($template->grid as $row) {
            $yaml .= '    '.implode(' ', array_map(fn ($cell) => $cell === '#' ? '#' : '.', $row))."\n";
        }

        if ($annotation) {
            $yaml .= '  philosophy: "'.$this->escape($annotation->philosophy)."\"\n";

            if (is_array($annotation->strengths) && $annotation->strengths !== []) {
                $yaml .= "  strengths:\n";
                foreach ($annotation->strengths as $s) {
                    $yaml .= '    - "'.$this->escape($s)."\"\n";
                }
            }

            if (is_array($annotation->compromises) && $annotation->compromises !== []) {
                $yaml .= "  compromises:\n";
                foreach ($annotation->compromises as $c) {
                    $yaml .= '    - "'.$this->escape($c)."\"\n";
                }
            }
        }

        return rtrim($yaml);
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $text);
    }

    /**
     * @return array<string, mixed>
     */
    private function generationTool(GenerationSpec $spec): array
    {
        return [
            'name' => 'generate_templates',
            'description' => 'Submit the generated crossword templates with their grids and prose annotations.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'candidates' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => max(1, $spec->candidateCount),
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => [
                                    'type' => 'string',
                                    'description' => 'A short, evocative name for this template (e.g. "Pinwheel Center", "Twin Stacks"). Distinct from any example name.',
                                ],
                                'grid' => [
                                    'type' => 'array',
                                    'description' => '2D array of '.$spec->height.' rows of '.$spec->width.' cells each. Each cell is integer 0 (white) or string "#" (block). Must satisfy the structural rules in the system prompt.',
                                    'minItems' => $spec->height,
                                    'maxItems' => $spec->height,
                                    'items' => [
                                        'type' => 'array',
                                        'minItems' => $spec->width,
                                        'maxItems' => $spec->width,
                                    ],
                                ],
                                'philosophy' => ['type' => 'string'],
                                'strengths' => [
                                    'type' => 'array',
                                    'minItems' => 3,
                                    'maxItems' => 5,
                                    'items' => ['type' => 'string'],
                                ],
                                'compromises' => [
                                    'type' => 'array',
                                    'minItems' => 3,
                                    'maxItems' => 5,
                                    'items' => ['type' => 'string'],
                                ],
                                'best_for' => ['type' => 'string'],
                                'avoid_when' => ['type' => 'string'],
                            ],
                            'required' => ['name', 'grid', 'philosophy', 'strengths', 'compromises'],
                        ],
                    ],
                ],
                'required' => ['candidates'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private function buildCandidate(array $raw, GenerationSpec $spec): GenerationCandidate
    {
        $errors = [];
        $grid = $raw['grid'] ?? null;

        if (! is_array($grid)) {
            $errors[] = 'grid is missing or not an array';
        } else {
            if (count($grid) !== $spec->height) {
                $errors[] = sprintf('grid has %d rows, expected %d', count($grid), $spec->height);
            }
            foreach ($grid as $r => $row) {
                if (! is_array($row) || count($row) !== $spec->width) {
                    $errors[] = sprintf('grid row %d has %d cells, expected %d', $r, is_array($row) ? count($row) : 0, $spec->width);
                    break;
                }
                foreach ($row as $c => $cell) {
                    if ($cell !== 0 && $cell !== '#') {
                        $errors[] = sprintf('grid[%d][%d] is invalid (must be 0 or "#"): %s', $r, $c, var_export($cell, true));
                        break 2;
                    }
                }
            }
        }

        $stats = null;
        if ($errors === [] && is_array($grid)) {
            try {
                $stats = $this->stats->forGrid($grid, $spec->width, $spec->height);
                if (! $stats->isConnected) {
                    $errors[] = 'white squares are not all connected';
                }
                if (! $stats->isFullyChecked) {
                    $errors[] = 'grid is not fully checked (some white cell is not in both an across and down word of length 2+)';
                }
            } catch (\Throwable $e) {
                $errors[] = 'stats computation failed: '.$e->getMessage();
            }
        }

        return new GenerationCandidate(
            name: (string) ($raw['name'] ?? 'Untitled'),
            width: $spec->width,
            height: $spec->height,
            grid: is_array($grid) ? $grid : [],
            philosophy: (string) ($raw['philosophy'] ?? ''),
            strengths: array_values(array_filter((array) ($raw['strengths'] ?? []), 'is_string')),
            compromises: array_values(array_filter((array) ($raw['compromises'] ?? []), 'is_string')),
            bestFor: isset($raw['best_for']) ? (string) $raw['best_for'] : null,
            avoidWhen: isset($raw['avoid_when']) ? (string) $raw['avoid_when'] : null,
            stats: $stats,
            validationErrors: $errors,
        );
    }

    private function uniqueName(string $proposed): string
    {
        $base = trim($proposed) !== '' ? trim($proposed) : 'Generated';
        $candidate = $base;
        $i = 2;

        while (Template::where('name', $candidate)->exists()) {
            $candidate = $base.' '.$i;
            $i++;
        }

        return $candidate;
    }
}
