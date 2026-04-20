<?php

namespace App\Services;

use App\Models\Word;
use Illuminate\Support\Facades\Log;

class AiGridFiller
{
    private const TOOL_NAME = 'submit_fills';

    public function __construct(private readonly AnthropicClient $client) {}

    /**
     * Fill empty grid slots using Anthropic Claude API.
     *
     * @param  list<array{direction: string, number: int, length: int, pattern: string, candidates?: list<string>}>  $slots  Unfilled slots
     * @param  list<array{direction: string, number: int, word: string}>  $filledWords  Already-filled words
     * @param  list<array{across_number: int, across_pos: int, down_number: int, down_pos: int}>  $intersections
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string}
     */
    public function fill(array $slots, array $filledWords, array $intersections = [], string $title = '', string $notes = ''): array
    {
        if (empty($slots)) {
            return [
                'success' => true,
                'fills' => [],
                'message' => 'No empty slots to fill.',
            ];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($slots, $filledWords, $intersections, $title, $notes);

        $messages = [
            ['role' => 'user', 'content' => $userMessage],
        ];

        $options = [
            'tools' => [$this->toolSchema()],
            'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
            'temperature' => 0.3,
            'max_tokens' => 4096,
        ];

        try {
            $result = $this->client->send($systemPrompt, $messages, $options);

            if (! $result['success']) {
                return $this->apiErrorResult($result);
            }

            [$validFills, $rejected] = $this->validateFills($result['data'], $slots);

            if (! empty($rejected)) {
                $retry = $this->retryWithFeedback($systemPrompt, $messages, $result['data'], $rejected, $slots, $options);
                if ($retry !== null) {
                    $validFills = $this->mergeFills($validFills, $retry);
                }
            }

            return $this->buildResult($validFills, count($slots));
        } catch (\Exception $e) {
            Log::error('AI grid fill failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'fills' => [],
                'message' => 'Failed to connect to AI service: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Compute intersections between across and down slots.
     *
     * @param  list<array{direction: string, number: int, row: int, col: int, length: int}>  $slots
     * @return list<array{across_number: int, across_pos: int, down_number: int, down_pos: int}>
     */
    public static function computeIntersections(array $slots): array
    {
        $across = array_values(array_filter($slots, fn ($s) => $s['direction'] === 'across'));
        $down = array_values(array_filter($slots, fn ($s) => $s['direction'] === 'down'));

        $intersections = [];
        foreach ($across as $a) {
            foreach ($down as $d) {
                $acrossRow = $a['row'];
                $acrossColStart = $a['col'];
                $acrossColEnd = $a['col'] + $a['length'] - 1;

                $downCol = $d['col'];
                $downRowStart = $d['row'];
                $downRowEnd = $d['row'] + $d['length'] - 1;

                if ($downCol < $acrossColStart || $downCol > $acrossColEnd) {
                    continue;
                }
                if ($acrossRow < $downRowStart || $acrossRow > $downRowEnd) {
                    continue;
                }

                $intersections[] = [
                    'across_number' => $a['number'],
                    'across_pos' => $downCol - $acrossColStart + 1,
                    'down_number' => $d['number'],
                    'down_pos' => $acrossRow - $downRowStart + 1,
                ];
            }
        }

        return $intersections;
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert crossword constructor. Fill every empty slot with a real, commonly known English word (or well-known proper noun) that satisfies the given letter pattern and all intersection constraints.

Requirements:
- The word MUST match the slot length exactly.
- Uppercase letters in the pattern are fixed and cannot change. Underscores (_) can be any letter.
- When a candidate list is provided for a slot, prefer one of those words — they are real dictionary words that already fit the pattern. Only deviate if none of them produces a valid fill across intersections.
- Crossing words share a letter at each intersection listed under "Intersections". Every intersection constraint must be satisfied.
- Do not repeat a word that already appears in the grid or in your own answers.
- Prefer common, well-known words over obscure ones. When a title or notes are given, lean thematically.
- Never invent words. "RCLET", "SUEEE", random letter soup, obscure abbreviations, and made-up strings are not acceptable.

Submit your final answer by calling the submit_fills tool exactly once.
PROMPT;
    }

    /**
     * @param  list<array{direction: string, number: int, length: int, pattern: string, candidates?: list<string>}>  $slots
     * @param  list<array{direction: string, number: int, word: string}>  $filledWords
     * @param  list<array{across_number: int, across_pos: int, down_number: int, down_pos: int}>  $intersections
     */
    private function buildUserMessage(array $slots, array $filledWords, array $intersections, string $title, string $notes): string
    {
        $message = '';

        if ($title) {
            $message .= "Puzzle title: {$title}\n";
        }

        if ($notes) {
            $message .= "Puzzle notes: {$notes}\n";
        }

        if (! empty($filledWords)) {
            $message .= "\nAlready filled words:\n";
            foreach ($filledWords as $word) {
                $message .= "- {$word['number']} ".ucfirst($word['direction']).": {$word['word']}\n";
            }
        }

        $message .= "\nEmpty slots to fill:\n";
        foreach ($slots as $slot) {
            $label = "{$slot['number']} ".ucfirst($slot['direction']);
            $message .= "- {$label} (length {$slot['length']}, pattern \"{$slot['pattern']}\")";

            $candidates = $slot['candidates'] ?? [];
            if (! empty($candidates)) {
                $list = implode(', ', array_slice($candidates, 0, 40));
                $message .= ": choose one of [{$list}]";
            } else {
                $message .= ': no dictionary candidates available — use a real English word that fits the pattern';
            }
            $message .= "\n";
        }

        if (! empty($intersections)) {
            $message .= "\nIntersections (same letter required at each):\n";
            foreach ($intersections as $ix) {
                $message .= "- {$ix['across_number']} Across position {$ix['across_pos']} == {$ix['down_number']} Down position {$ix['down_pos']}\n";
            }
        }

        $message .= "\nCall the submit_fills tool with your chosen word for every empty slot.";

        return $message;
    }

    /**
     * Anthropic tool schema for structured fill submission.
     *
     * @return array<string, mixed>
     */
    private function toolSchema(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Submit the chosen word for every empty slot in the crossword.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'fills' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'direction' => ['type' => 'string', 'enum' => ['across', 'down']],
                                'number' => ['type' => 'integer'],
                                'word' => ['type' => 'string'],
                            ],
                            'required' => ['direction', 'number', 'word'],
                        ],
                    ],
                ],
                'required' => ['fills'],
            ],
        ];
    }

    /**
     * Extract raw fills from a response (prefer tool_use, fall back to text JSON).
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function extractFills(array $data): array
    {
        $toolInput = AnthropicClient::extractToolUse($data, self::TOOL_NAME);
        if (is_array($toolInput) && isset($toolInput['fills']) && is_array($toolInput['fills'])) {
            return array_values($toolInput['fills']);
        }

        $text = AnthropicClient::extractText($data);
        if ($text === '') {
            return [];
        }

        if (! preg_match('/\[[\s\S]*]/', $text, $matches)) {
            return [];
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? array_values($decoded) : [];
    }

    /**
     * Validate raw fills against slot constraints and dictionary.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array{direction: string, number: int, length: int, pattern: string, candidates?: list<string>}>  $slots
     * @return array{0: list<array{direction: string, number: int, word: string}>, 1: list<array{direction: string, number: int, word: string, reason: string}>}
     */
    private function validateFills(array $data, array $slots): array
    {
        $raw = $this->extractFills($data);

        $slotIndex = [];
        foreach ($slots as $slot) {
            $slotIndex[$slot['direction'].'-'.$slot['number']] = $slot;
        }

        $candidates = [];
        foreach ($raw as $fill) {
            if (! isset($fill['direction'], $fill['number'], $fill['word'])) {
                continue;
            }
            $candidates[] = [
                'direction' => (string) $fill['direction'],
                'number' => (int) $fill['number'],
                'word' => strtoupper((string) $fill['word']),
            ];
        }

        $dictionaryMatches = $this->dictionaryLookup(array_column($candidates, 'word'));

        $valid = [];
        $rejected = [];

        foreach ($candidates as $fill) {
            $slot = $slotIndex[$fill['direction'].'-'.$fill['number']] ?? null;
            if (! $slot) {
                $rejected[] = $fill + ['reason' => 'not a requested slot'];

                continue;
            }

            if (strlen($fill['word']) !== $slot['length']) {
                $rejected[] = $fill + ['reason' => "wrong length (expected {$slot['length']})"];

                continue;
            }

            if (! $this->matchesPattern($fill['word'], $slot['pattern'])) {
                $rejected[] = $fill + ['reason' => "does not match pattern {$slot['pattern']}"];

                continue;
            }

            if (! isset($dictionaryMatches[$fill['word']])) {
                $rejected[] = $fill + ['reason' => 'not a real word'];

                continue;
            }

            $valid[] = [
                'direction' => $fill['direction'],
                'number' => $fill['number'],
                'word' => $fill['word'],
            ];
        }

        return [$valid, $rejected];
    }

    /**
     * Retry once, telling the model which fills were invalid.
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @param  array<string, mixed>  $firstResponse
     * @param  list<array{direction: string, number: int, word: string, reason: string}>  $rejected
     * @param  list<array{direction: string, number: int, length: int, pattern: string, candidates?: list<string>}>  $slots
     * @param  array<string, mixed>  $options
     * @return list<array{direction: string, number: int, word: string}>|null
     */
    private function retryWithFeedback(string $systemPrompt, array $messages, array $firstResponse, array $rejected, array $slots, array $options): ?array
    {
        $toolUseId = AnthropicClient::extractToolUseId($firstResponse, self::TOOL_NAME);
        if ($toolUseId === null) {
            return null;
        }

        $feedback = "These fills were invalid:\n";
        foreach ($rejected as $r) {
            $feedback .= "- {$r['number']} ".ucfirst($r['direction']).": {$r['word']} ({$r['reason']})\n";
        }
        $feedback .= "\nPick replacements from the candidate lists that satisfy all pattern and intersection constraints. Call submit_fills again with the corrected fills for those slots (you may resubmit slots that were correct).";

        $messages[] = ['role' => 'assistant', 'content' => $firstResponse['content'] ?? []];
        $messages[] = [
            'role' => 'user',
            'content' => [
                [
                    'type' => 'tool_result',
                    'tool_use_id' => $toolUseId,
                    'content' => $feedback,
                ],
            ],
        ];

        $retry = $this->client->send($systemPrompt, $messages, $options);
        if (! $retry['success']) {
            Log::warning('AI grid fill retry failed', ['status' => $retry['status'] ?? null]);

            return null;
        }

        [$valid] = $this->validateFills($retry['data'], $slots);

        return $valid;
    }

    /**
     * Merge two fill lists; later entries override earlier ones for the same slot.
     *
     * @param  list<array{direction: string, number: int, word: string}>  $first
     * @param  list<array{direction: string, number: int, word: string}>  $second
     * @return list<array{direction: string, number: int, word: string}>
     */
    private function mergeFills(array $first, array $second): array
    {
        $byKey = [];
        foreach ($first as $fill) {
            $byKey[$fill['direction'].'-'.$fill['number']] = $fill;
        }
        foreach ($second as $fill) {
            $byKey[$fill['direction'].'-'.$fill['number']] = $fill;
        }

        return array_values($byKey);
    }

    /**
     * @param  list<string>  $words
     * @return array<string, true>
     */
    private function dictionaryLookup(array $words): array
    {
        $words = array_unique(array_filter($words, fn ($w) => $w !== ''));
        if (empty($words)) {
            return [];
        }

        $found = Word::whereIn('word', $words)->pluck('word')->all();

        return array_fill_keys(array_map('strtoupper', $found), true);
    }

    /**
     * @param  array{status: int|null, body: string}  $result
     * @return array{success: false, fills: array<int, never>, message: string}
     */
    private function apiErrorResult(array $result): array
    {
        if ($result['status'] === null) {
            return [
                'success' => false,
                'fills' => [],
                'message' => 'Anthropic API key is not configured. Add ANTHROPIC_API_KEY to your .env file.',
            ];
        }

        Log::warning('Anthropic API error', [
            'status' => $result['status'],
            'body' => $result['body'],
        ]);

        return [
            'success' => false,
            'fills' => [],
            'message' => 'AI service returned an error. Please try again.',
        ];
    }

    /**
     * @param  list<array{direction: string, number: int, word: string}>  $validFills
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string}
     */
    private function buildResult(array $validFills, int $totalSlots): array
    {
        $filledCount = count($validFills);

        if ($filledCount === 0) {
            return [
                'success' => false,
                'fills' => [],
                'message' => 'AI could not generate valid words for the grid.',
            ];
        }

        return [
            'success' => $filledCount === $totalSlots,
            'fills' => $validFills,
            'message' => $filledCount === $totalSlots
                ? "AI filled {$filledCount} ".str('word')->plural($filledCount).'.'
                : "AI filled {$filledCount} of {$totalSlots} slots. Some words didn't match constraints.",
        ];
    }

    /**
     * Check if a word matches a pattern (uppercase fixed letters, _ wildcards).
     */
    private function matchesPattern(string $word, string $pattern): bool
    {
        if (strlen($word) !== strlen($pattern)) {
            return false;
        }

        for ($i = 0, $len = strlen($pattern); $i < $len; $i++) {
            if ($pattern[$i] !== '_' && $pattern[$i] !== $word[$i]) {
                return false;
            }
        }

        return true;
    }
}
