<?php

namespace App\Services;

use App\Services\Anthropic\AnthropicAction;
use App\Services\Anthropic\AnthropicClient;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Crossword Theme Builder.
 *
 * Generates theme-appropriate words and phrases for crossword puzzle
 * construction based on a given theme, wordplay style, and concept.
 */
class AiThemeBuilder extends AnthropicAction
{
    private const string MODEL = 'claude-opus-4-8';

    private const string TOOL_NAME = 'submit_theme_entries';

    /**
     * Generate candidate theme entries for a crossword concept.
     *
     * @param  string  $prompt  Free-text description of the theme, wordplay angle, and concept.
     * @param  string  $theme  Optional explicit theme label.
     * @param  string  $wordplayStyle  Optional wordplay style (e.g. "hidden words", "puns", "anagrams").
     * @return array{success: bool, entries: list<array{entry: string, length: int, explanation: string}>, assumptions: string, message: string}
     */
    public function build(string $prompt, string $theme = '', string $wordplayStyle = ''): array
    {
        if (trim($prompt) === '' && trim($theme) === '') {
            return $this->failure('Describe a theme or concept to build theme entries for.');
        }

        return $this->dispatch(
            $this->buildSystemPrompt(),
            [['role' => 'user', 'content' => $this->buildUserMessage($prompt, $theme, $wordplayStyle)]],
            [
                'model' => self::MODEL,
                'tools' => [$this->toolSchema()],
                'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
                'max_tokens' => 2048,
            ],
            fn (array $data): array => $this->parseResponse($data),
        );
    }

    /**
     * @return array{success: false, entries: list<never>, assumptions: string, message: string}
     */
    protected function onMissingKey(): array
    {
        return $this->failure('Anthropic API key is not configured. Add ANTHROPIC_API_KEY to your .env file.');
    }

    /**
     * @return array{success: false, entries: list<never>, assumptions: string, message: string}
     */
    protected function onError(?int $status, string $body): array
    {
        Log::warning('AI theme builder error', ['status' => $status, 'body' => $body]);

        return $this->failure('AI service returned an error. Please try again.');
    }

    /**
     * @return array{success: false, entries: list<never>, assumptions: string, message: string}
     */
    protected function onException(Throwable $e): array
    {
        Log::error('AI theme building failed', ['error' => $e->getMessage()]);

        return $this->failure('Failed to connect to AI service: '.$e->getMessage());
    }

    /**
     * @return array{success: false, entries: list<never>, assumptions: string, message: string}
     */
    private function failure(string $message): array
    {
        return ['success' => false, 'entries' => [], 'assumptions' => '', 'message' => $message];
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
        You are a crossword puzzle construction assistant specializing in theme development. Given a prompt describing a theme, wordplay angle, and concept, generate a set of theme-appropriate entries (words or phrases) suitable for featuring as crossword theme answers.

        For each request:
        - Propose 5-12 candidate theme entries that fit the theme and wordplay style described.
        - Entries may be single words, multi-word phrases, idioms, or common expressions — idioms and familiar phrases are especially welcome, since they make lively crossword theme answers.
        - Note approximate letter counts. Consistent lengths or symmetry can help grid construction, but the entries do NOT all need to be the same length or symmetrical — mixed lengths are perfectly fine, so prioritize strong, on-theme answers over matching lengths.
        - Prioritize entries that relate to the theme in a non-literal, ironic, or tangential way over on-the-nose literal matches — the most interesting theme answers approach the concept sideways.
        - Explain briefly how each entry connects to the theme or wordplay conceit (e.g., pun, hidden word, category member).
        - Favor common, crossword-friendly words/phrases (avoid obscure or overly technical terms unless the theme demands it).
        - IMPORTANT: at least 5 of the returned entries must share no words in common with one another. Treat this as a hard requirement — before submitting, verify that you have at least 5 entries where no word appears in more than one of them.
        - If the prompt is ambiguous, make reasonable assumptions and state them rather than asking excessive clarifying questions.
        - Offer to refine, expand, or narrow the list if asked.
        - Keep output organized and scannable — a simple list with brief annotations is ideal.
        PROMPT;
    }

    private function buildUserMessage(string $prompt, string $theme, string $wordplayStyle): string
    {
        $message = '';

        if (trim($theme) !== '') {
            $message .= 'Theme: '.trim($theme)."\n";
        }

        if (trim($wordplayStyle) !== '') {
            $message .= 'Wordplay style: '.trim($wordplayStyle)."\n";
        }

        if (trim($prompt) !== '') {
            $message .= ($message !== '' ? "\n" : '').'Concept / prompt: '.trim($prompt)."\n";
        }

        $message .= "\nPropose theme entries and call ".self::TOOL_NAME.' with your list.';

        return $message;
    }

    /**
     * Extract theme entries from the tool_use response.
     *
     * @param  array<string, mixed>  $data
     * @return array{success: bool, entries: list<array{entry: string, length: int, explanation: string}>, assumptions: string, message: string}
     */
    private function parseResponse(array $data): array
    {
        $input = AnthropicClient::extractToolUse($data, self::TOOL_NAME) ?? [];

        $entries = [];

        foreach ($input['entries'] ?? [] as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $entry = is_string($raw['entry'] ?? null) ? trim($raw['entry']) : '';

            if ($entry === '') {
                continue;
            }

            $length = isset($raw['length']) && is_int($raw['length'])
                ? $raw['length']
                : mb_strlen((string) preg_replace('/[^A-Za-z]/', '', $entry));

            $entries[] = [
                'entry' => $entry,
                'length' => $length,
                'explanation' => is_string($raw['explanation'] ?? null) ? trim($raw['explanation']) : '',
            ];
        }

        if ($entries === []) {
            return $this->failure('AI could not generate theme entries. Try rephrasing the theme.');
        }

        $assumptions = is_string($input['assumptions'] ?? null) ? trim($input['assumptions']) : '';
        $count = count($entries);

        return [
            'success' => true,
            'entries' => $entries,
            'assumptions' => $assumptions,
            'message' => "Generated {$count} theme ".str('entry')->plural($count).'.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolSchema(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Submit the proposed crossword theme entries, each with its letter count and a short explanation.',
            'input_schema' => [
                'type' => 'object',
                'properties' => [
                    'entries' => [
                        'type' => 'array',
                        'minItems' => 5,
                        'maxItems' => 8,
                        'description' => '5 to 8 candidate theme entries.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'entry' => [
                                    'type' => 'string',
                                    'description' => 'The theme entry — a word, multi-word phrase, or idiom — in uppercase (e.g. "SEA CHANGE", "PIECE OF CAKE").',
                                ],
                                'length' => [
                                    'type' => 'integer',
                                    'description' => 'Approximate letter count, excluding spaces and punctuation.',
                                ],
                                'explanation' => [
                                    'type' => 'string',
                                    'description' => 'One brief sentence on how the entry connects to the theme or wordplay conceit.',
                                ],
                            ],
                            'required' => ['entry', 'length', 'explanation'],
                        ],
                    ],
                    'assumptions' => [
                        'type' => 'string',
                        'description' => 'Any assumptions made when the prompt was ambiguous. Empty string if none.',
                    ],
                ],
                'required' => ['entries'],
            ],
        ];
    }
}
