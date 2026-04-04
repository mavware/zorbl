<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiClueGenerator
{
    /**
     * Generate crossword clues using Anthropic Claude API.
     *
     * @param  list<array{direction: string, number: int, word: string}>  $words  All words in the grid
     * @return array{success: bool, clues: array{across: array<int, string>, down: array<int, string>}, message: string}
     */
    public function generate(array $words, string $title = '', string $notes = ''): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return [
                'success' => false,
                'clues' => ['across' => [], 'down' => []],
                'message' => 'Anthropic API key is not configured. Add ANTHROPIC_API_KEY to your .env file.',
            ];
        }

        if (empty($words)) {
            return [
                'success' => true,
                'clues' => ['across' => [], 'down' => []],
                'message' => 'No words to generate clues for.',
            ];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($words, $title, $notes);

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
                Log::warning('Anthropic API error during clue generation', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'clues' => ['across' => [], 'down' => []],
                    'message' => 'AI service returned an error. Please try again.',
                ];
            }

            return $this->parseResponse($response->json(), $words);
        } catch (\Exception $e) {
            Log::error('AI clue generation failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'clues' => ['across' => [], 'down' => []],
                'message' => 'Failed to connect to AI service: '.$e->getMessage(),
            ];
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert crossword clue writer. Your task is to write clever, concise crossword clues.

Guidelines:
1. Mix straightforward definitions with wordplay, puns, and misdirection.
2. Keep clues concise — typically 3-8 words.
3. Clues should be appropriate for a general audience.
4. For proper nouns, reference them naturally (e.g., "Big Apple" for NEW YORK).
5. Use standard crossword conventions (e.g., "?" suffix for puns/wordplay).
6. If the puzzle has a theme (indicated by its title), try to incorporate thematic connections where appropriate.

Respond with ONLY a JSON object with two keys: "across" and "down". Each is an object mapping clue numbers (as strings) to clue text. No other text.

Example response:
{"across": {"1": "Feline friend", "5": "Ocean motion"}, "down": {"1": "Tossed greens", "2": "Poker stake"}}
PROMPT;
    }

    /**
     * @param  list<array{direction: string, number: int, word: string}>  $words
     */
    private function buildUserMessage(array $words, string $title, string $notes): string
    {
        $message = '';

        if ($title) {
            $message .= "Puzzle title: {$title}\n";
        }

        if ($notes) {
            $message .= "Puzzle notes: {$notes}\n";
        }

        $message .= "\nWords to write clues for:\n";

        $across = [];
        $down = [];

        foreach ($words as $word) {
            if ($word['direction'] === 'across') {
                $across[$word['number']] = $word['word'];
            } else {
                $down[$word['number']] = $word['word'];
            }
        }

        ksort($across);
        ksort($down);

        if (! empty($across)) {
            $message .= "\nAcross:\n";
            foreach ($across as $num => $word) {
                $message .= "- {$num}: {$word}\n";
            }
        }

        if (! empty($down)) {
            $message .= "\nDown:\n";
            foreach ($down as $num => $word) {
                $message .= "- {$num}: {$word}\n";
            }
        }

        $message .= "\nWrite a clue for each word. Respond with ONLY the JSON object.";

        return $message;
    }

    /**
     * Parse the Claude API response and extract clues.
     *
     * @param  array<string, mixed>  $response
     * @param  list<array{direction: string, number: int, word: string}>  $words
     * @return array{success: bool, clues: array{across: array<int, string>, down: array<int, string>}, message: string}
     */
    private function parseResponse(array $response, array $words): array
    {
        $text = $response['content'][0]['text'] ?? '';

        // Extract JSON object from response
        if (preg_match('/\{[\s\S]*}/', $text, $matches)) {
            $json = $matches[0];
        } else {
            return [
                'success' => false,
                'clues' => ['across' => [], 'down' => []],
                'message' => 'AI returned an unexpected response format.',
            ];
        }

        $parsed = json_decode($json, true);

        if (! is_array($parsed)) {
            return [
                'success' => false,
                'clues' => ['across' => [], 'down' => []],
                'message' => 'AI returned invalid JSON.',
            ];
        }

        $clues = [
            'across' => [],
            'down' => [],
        ];

        $totalWords = count($words);
        $generatedCount = 0;

        foreach (['across', 'down'] as $dir) {
            foreach ($parsed[$dir] ?? [] as $num => $clue) {
                if (is_string($clue) && trim($clue) !== '') {
                    $clues[$dir][(int) $num] = trim($clue);
                    $generatedCount++;
                }
            }
        }

        if ($generatedCount === 0) {
            return [
                'success' => false,
                'clues' => ['across' => [], 'down' => []],
                'message' => 'AI could not generate valid clues.',
            ];
        }

        return [
            'success' => $generatedCount >= $totalWords,
            'clues' => $clues,
            'message' => "Generated {$generatedCount} ".str('clue')->plural($generatedCount).'.',
        ];
    }
}
