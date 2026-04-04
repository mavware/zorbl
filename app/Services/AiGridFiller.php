<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiGridFiller
{
    /**
     * Fill empty grid slots using Anthropic Claude API.
     *
     * @param  list<array{direction: string, number: int, length: int, pattern: string}>  $slots  Unfilled slots
     * @param  list<array{direction: string, number: int, word: string}>  $filledWords  Already-filled words
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string}
     */
    public function fill(array $slots, array $filledWords, string $title = '', string $notes = ''): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'fills' => [],
                'message' => 'Anthropic API key is not configured. Add ANTHROPIC_API_KEY to your .env file.',
            ];
        }

        if (empty($slots)) {
            return [
                'success' => true,
                'fills' => [],
                'message' => 'No empty slots to fill.',
            ];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($slots, $filledWords, $title, $notes);

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(60)
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model', 'claude-sonnet-4-20250514'),
                    'max_tokens' => 4096,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Anthropic API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'fills' => [],
                    'message' => 'AI service returned an error. Please try again.',
                ];
            }

            return $this->parseResponse($response->json(), $slots);
        } catch (\Exception $e) {
            Log::error('AI grid fill failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'fills' => [],
                'message' => 'Failed to connect to AI service: '.$e->getMessage(),
            ];
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert crossword constructor. Your task is to fill empty slots in a crossword grid with real English words.

Rules:
1. Every word MUST be a real, commonly known English word or well-known proper noun.
2. Every word MUST exactly match the given letter pattern (uppercase letters are fixed, _ means any letter).
3. Every word MUST be exactly the specified length.
4. Words that cross each other MUST share the same letter at their intersection.
5. Prefer words that are thematically connected to the puzzle title and existing words when possible.
6. Prefer common, well-known words over obscure ones.
7. Never repeat a word that's already in the grid.

Respond with ONLY a JSON array of objects, each with "direction" (string: "across" or "down"), "number" (integer), and "word" (string, uppercase). No other text.
PROMPT;
    }

    /**
     * @param  list<array{direction: string, number: int, length: int, pattern: string}>  $slots
     * @param  list<array{direction: string, number: int, word: string}>  $filledWords
     */
    private function buildUserMessage(array $slots, array $filledWords, string $title, string $notes): string
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
            $message .= "- {$slot['number']} ".ucfirst($slot['direction']).": pattern \"{$slot['pattern']}\" (length {$slot['length']})\n";
        }

        $message .= "\nFill all empty slots with valid crossword words. Remember: crossing words must share the same letter at intersections. Respond with ONLY the JSON array.";

        return $message;
    }

    /**
     * Parse the Claude API response and validate fills.
     *
     * @param  array<string, mixed>  $response
     * @param  list<array{direction: string, number: int, length: int, pattern: string}>  $slots
     * @return array{success: bool, fills: list<array{direction: string, number: int, word: string}>, message: string}
     */
    private function parseResponse(array $response, array $slots): array
    {
        $text = $response['content'][0]['text'] ?? '';

        // Extract JSON from response (may be wrapped in a Markdown code block)
        if (preg_match('/\[[\s\S]*]/', $text, $matches)) {
            $json = $matches[0];
        } else {
            return [
                'success' => false,
                'fills' => [],
                'message' => 'AI returned an unexpected response format.',
            ];
        }

        $fills = json_decode($json, true);

        if (! is_array($fills)) {
            return [
                'success' => false,
                'fills' => [],
                'message' => 'AI returned invalid JSON.',
            ];
        }

        // Index slots by direction+number for validation
        $slotIndex = [];
        foreach ($slots as $slot) {
            $slotIndex[$slot['direction'].'-'.$slot['number']] = $slot;
        }

        $validFills = [];
        foreach ($fills as $fill) {
            if (! isset($fill['direction'], $fill['number'], $fill['word'])) {
                continue;
            }

            $key = $fill['direction'].'-'.$fill['number'];
            $slot = $slotIndex[$key] ?? null;

            if (! $slot) {
                continue;
            }

            $word = strtoupper($fill['word']);

            // Validate length
            if (strlen($word) !== $slot['length']) {
                continue;
            }

            // Validate pattern match
            if (! $this->matchesPattern($word, $slot['pattern'])) {
                continue;
            }

            $validFills[] = [
                'direction' => $fill['direction'],
                'number' => (int) $fill['number'],
                'word' => $word,
            ];
        }

        $totalSlots = count($slots);
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
