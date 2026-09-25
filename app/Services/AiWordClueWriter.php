<?php

namespace App\Services;

use App\Services\Anthropic\AnthropicAction;
use App\Services\Anthropic\AnthropicClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes a batch of distinct crossword clues for a single answer word. Used
 * to backfill catalog words that have no clues yet.
 */
class AiWordClueWriter extends AnthropicAction
{
    /**
     * @return array{success: bool, clues: list<string>, message: string}
     */
    public function write(string $word, int $limit): array
    {
        $word = strtoupper(trim($word));

        if ($word === '' || $limit < 1) {
            return ['success' => true, 'clues' => [], 'message' => 'Nothing to write.'];
        }

        return $this->dispatch(
            $this->buildSystemPrompt(),
            [['role' => 'user', 'content' => $this->buildUserMessage($word, $limit)]],
            ['max_tokens' => 1024],
            fn (array $data): array => $this->parseResponse($data, $word, $limit),
        );
    }

    /**
     * @return array{success: false, clues: list<string>, message: string}
     */
    protected function onMissingKey(): array
    {
        return $this->failure('Anthropic API key is not configured. Add ANTHROPIC_API_KEY to your .env file.');
    }

    /**
     * @return array{success: false, clues: list<string>, message: string}
     */
    protected function onError(?int $status, string $body): array
    {
        Log::warning('Anthropic API error during word clue backfill', ['status' => $status, 'body' => $body]);

        return $this->failure('AI service returned an error.');
    }

    /**
     * @return array{success: false, clues: list<string>, message: string}
     */
    protected function onException(Throwable $e): array
    {
        Log::error('AI word clue backfill failed', ['error' => $e->getMessage()]);

        return $this->failure('Failed to connect to AI service: '.$e->getMessage());
    }

    /**
     * @return array{success: false, clues: list<string>, message: string}
     */
    private function failure(string $message): array
    {
        return ['success' => false, 'clues' => [], 'message' => $message];
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
You are an expert crossword clue writer building a clue library for a single answer.

Guidelines:
1. Every clue must be a distinct angle on the answer: definitions, synonyms, fill-in-the-blank, wordplay, puns, and misdirection.
2. Keep clues concise, typically 3-8 words, suitable for a general audience.
3. Use standard crossword conventions: a "?" suffix for puns and wordplay, "___" for fill-in-the-blank, abbreviations only when signposted.
4. Never include the answer word itself, or an obvious inflection of it, in a clue.
5. If the answer is a multi-word phrase written without spaces, clue the full phrase.

Respond with ONLY a JSON array of clue strings. No other text.

Example response:
["Feline friend", "Whiskered pet", "Jazz enthusiast, in slang"]
PROMPT;
    }

    private function buildUserMessage(string $word, int $limit): string
    {
        return "Answer: {$word}\n\nWrite up to {$limit} distinct clues for this answer. Respond with ONLY the JSON array.";
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{success: bool, clues: list<string>, message: string}
     */
    private function parseResponse(array $response, string $word, int $limit): array
    {
        $text = AnthropicClient::extractText($response);

        if (! preg_match('/\[[\s\S]*]/', $text, $matches)) {
            return $this->failure('AI returned an unexpected response format.');
        }

        $parsed = json_decode($matches[0], true);

        if (! is_array($parsed)) {
            return $this->failure('AI returned invalid JSON.');
        }

        $clues = self::sanitize($parsed, $word, $limit);

        if ($clues === []) {
            return $this->failure('AI returned no usable clues.');
        }

        return ['success' => true, 'clues' => $clues, 'message' => 'Generated '.count($clues).' clue(s).'];
    }

    /**
     * Trim, drop anything empty, over-long, or containing the answer, and
     * de-duplicate case-insensitively, keeping the first $limit survivors.
     *
     * @param  array<mixed>  $candidates
     * @return list<string>
     */
    public static function sanitize(array $candidates, string $word, int $limit): array
    {
        $word = strtoupper($word);
        $seen = [];
        $clues = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $clue = trim(preg_replace('/\s+/', ' ', $candidate) ?? '');
            $length = mb_strlen($clue);

            if ($length < 2 || $length > 500) {
                continue;
            }

            if (str_contains(strtoupper(preg_replace('/[^A-Za-z]/', '', $clue) ?? ''), $word)) {
                continue;
            }

            $key = mb_strtolower($clue);
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $clues[] = $clue;

            if (count($clues) >= $limit) {
                break;
            }
        }

        return $clues;
    }
}
