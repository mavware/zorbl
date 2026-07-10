<?php

namespace App\Services;

use App\Services\Anthropic\AnthropicClient;
use Illuminate\Support\Facades\Log;

/**
 * Crossword Theme Builder.
 *
 * Generates theme-appropriate words and phrases for crossword puzzle
 * construction based on a given theme, wordplay style, and concept.
 */
class AiThemeBuilder
{
    private const string MODEL = 'claude-opus-4-8';

    private const string TOOL_NAME = 'submit_theme_entries';

    public function __construct(private readonly AnthropicClient $client) {}

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
            return [
                'success' => false,
                'entries' => [],
                'assumptions' => '',
                'message' => 'Describe a theme or concept to build theme entries for.',
            ];
        }

        $options = [
            'model' => self::MODEL,
            'tools' => [$this->toolSchema()],
            'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
            'temperature' => 0.7,
            'max_tokens' => 2048,
        ];

        try {
            $result = $this->client->send(
                $this->buildSystemPrompt(),
                [['role' => 'user', 'content' => $this->buildUserMessage($prompt, $theme, $wordplayStyle)]],
                $options,
            );

            if (! $result['success']) {
                if (($result['status'] ?? null) === null) {
                    return [
                        'success' => false,
                        'entries' => [],
                        'assumptions' => '',
                        'message' => 'Anthropic API key is not configured. Add ANTHROPIC_API_KEY to your .env file.',
                    ];
                }

                Log::warning('AI theme builder error', [
                    'status' => $result['status'] ?? null,
                    'body' => $result['body'] ?? null,
                ]);

                return [
                    'success' => false,
                    'entries' => [],
                    'assumptions' => '',
                    'message' => 'AI service returned an error. Please try again.',
                ];
            }

            return $this->parseResponse($result['data']);
        } catch (\Exception $e) {
            Log::error('AI theme building failed', ['error' => $e->getMessage()]);

            return [
                'success' => false,
                'entries' => [],
                'assumptions' => '',
                'message' => 'Failed to connect to AI service: '.$e->getMessage(),
            ];
        }
    }

    private function buildSystemPrompt(): string
    {
        return <<<'PROMPT'
        You are a crossword puzzle construction assistant specializing in theme development. Given a prompt describing a theme, wordplay angle, and concept, generate a set of theme-appropriate entries (words or phrases) suitable for featuring as crossword theme answers.

        For each request:
        - Propose 5-8 candidate theme entries that fit the theme and wordplay style described.
        - Prefer entries with consistent length patterns or symmetry where relevant (useful for grid construction), and note approximate letter counts.
        - Explain briefly how each entry connects to the theme or wordplay conceit (e.g., pun, hidden word, category member).
        - Favor common, crossword-friendly words/phrases (avoid obscure or overly technical terms unless the theme demands it).
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
            return [
                'success' => false,
                'entries' => [],
                'assumptions' => '',
                'message' => 'AI could not generate theme entries. Try rephrasing the theme.',
            ];
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
                                    'description' => 'The theme word or phrase, in uppercase (e.g. "SEA CHANGE").',
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
