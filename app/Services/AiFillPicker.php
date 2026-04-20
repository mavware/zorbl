<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AiFillPicker
{
    private const string TOOL_NAME = 'submit_choice';

    public function __construct(private readonly AnthropicClient $client) {}

    /**
     * Pick the best of several candidate crossword fills.
     *
     * @param  list<list<array{direction: string, number: int, word: string}>>  $candidateFills  Each entry is a complete set of fills for the empty slots.
     * @param  list<array{direction: string, number: int, word: string}>  $pinnedWords  Words that are identical across every candidate (pre-filled cells).
     * @return array{success: bool, index: int, message: string}
     */
    public function pick(
        array $candidateFills,
        string $title = '',
        string $notes = '',
        array $pinnedWords = [],
        string $secretTheme = '',
    ): array {
        $count = count($candidateFills);

        if ($count === 0) {
            return ['success' => false, 'index' => 0, 'message' => 'No candidate fills to choose from.'];
        }

        if ($count === 1) {
            return ['success' => true, 'index' => 0, 'message' => 'Only one distinct fill available.'];
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($candidateFills, $title, $notes, $pinnedWords, $secretTheme);

        $options = [
            'tools' => [$this->toolSchema($count)],
            'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
            'temperature' => 0.2,
            'max_tokens' => 1024,
        ];

        try {
            $result = $this->client->send(
                $systemPrompt,
                [['role' => 'user', 'content' => $userMessage]],
                $options,
            );

            if (! $result['success']) {
                Log::warning('AI fill picker error', [
                    'status' => $result['status'] ?? null,
                    'body' => $result['body'] ?? null,
                ]);

                return [
                    'success' => false,
                    'index' => 0,
                    'message' => 'AI picker unavailable; using first candidate.',
                ];
            }

            $input = AnthropicClient::extractToolUse($result['data'], self::TOOL_NAME) ?? [];
            $choice = $input['choice'] ?? null;

            if (! is_int($choice) || $choice < 1 || $choice > $count) {
                return [
                    'success' => false,
                    'index' => 0,
                    'message' => 'AI returned an invalid choice; using first candidate.',
                ];
            }

            $reasoning = is_string($input['reasoning'] ?? null) ? trim($input['reasoning']) : '';
            $message = $reasoning !== ''
                ? "AI chose fill #{$choice} of {$count}: {$reasoning}"
                : "AI chose fill #{$choice} of {$count}.";

            return ['success' => true, 'index' => $choice - 1, 'message' => $message];
        } catch (\Exception $e) {
            Log::error('AI fill picker failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'index' => 0,
                'message' => 'AI picker error: '.$e->getMessage(),
            ];
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert crossword editor judging several complete grid fills for the same puzzle. Every fill is already valid — same grid, same crossings, all real words. Pick the single best one.

Judging criteria, in order of importance:
1. Theme fit. Theme inputs are ordered by priority — use the highest-priority one that is provided:
   - "Secret theme" (highest priority): an internal hint the constructor gave you for choosing words. Treat it as the definitive theme.
   - "Puzzle title" (next): use as the theme if no secret theme was given.
   - "Puzzle notes" (lowest): use as the theme only if neither of the above is given.
   When a theme source is present, strongly prefer the fill whose words most resonate with it.
2. Cluability. When no theme source is given, prefer the fill with the most interesting, cluable words — words that naturally lend themselves to clever wordplay, double meanings, or memorable clues.
3. Freshness. Penalize fills that lean on obscure crosswordese, awkward abbreviations, or partials when a more natural alternative exists.
4. Variety. Penalize fills that feel monotonous (all short function words, all proper nouns, all the same register).

Call the submit_choice tool exactly once with the 1-based number of your chosen fill and a one-sentence reason.
PROMPT;
    }

    /**
     * @param  list<list<array{direction: string, number: int, word: string}>>  $candidateFills
     * @param  list<array{direction: string, number: int, word: string}>  $pinnedWords
     */
    private function buildUserMessage(array $candidateFills, string $title, string $notes, array $pinnedWords, string $secretTheme = ''): string
    {
        $message = '';

        if ($secretTheme !== '') {
            $message .= "Secret theme (highest priority): {$secretTheme}\n";
        }

        if ($title !== '') {
            $message .= "Puzzle title: {$title}\n";
        }

        if ($notes !== '') {
            $message .= "Puzzle notes: {$notes}\n";
        }

        if (! empty($pinnedWords)) {
            $message .= "\nWords pre-filled in every candidate (shared context):\n";
            foreach ($pinnedWords as $w) {
                $message .= "- {$w['number']} ".ucfirst($w['direction']).": {$w['word']}\n";
            }
        }

        foreach ($candidateFills as $i => $fills) {
            $num = $i + 1;
            $message .= "\nCandidate #{$num}:\n";
            foreach ($fills as $f) {
                $message .= "- {$f['number']} ".ucfirst($f['direction']).": {$f['word']}\n";
            }
        }

        $message .= "\nCall submit_choice with the number of the best candidate and a one-sentence reason.";

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function toolSchema(int $count): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Submit the number of the best candidate fill and a short reason for the choice.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'choice' => [
                        'type' => 'integer',
                        'minimum' => 1,
                        'maximum' => $count,
                        'description' => "The 1-based number of the chosen candidate fill (1 through {$count}).",
                    ],
                    'reasoning' => [
                        'type' => 'string',
                        'description' => 'One sentence explaining why this candidate was chosen.',
                    ],
                ],
                'required' => ['choice', 'reasoning'],
            ],
        ];
    }
}
