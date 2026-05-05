<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class AnthropicClient
{
    /**
     * Send a single user message (back-compat wrapper for simple callers).
     *
     * @return array{success: true, data: array<string, mixed>}|array{success: false, status: int|null, body: string}
     */
    public function sendMessage(string $systemPrompt, string $userMessage): array
    {
        return $this->send($systemPrompt, [
            ['role' => 'user', 'content' => $userMessage],
        ]);
    }

    /**
     * Send a full message thread with optional tool-use, temperature, and thinking.
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @param  array{model?: string, tools?: list<array<string, mixed>>, tool_choice?: array<string, mixed>, temperature?: float, thinking?: array<string, mixed>, max_tokens?: int, timeout?: int}  $options
     * @return array{success: true, data: array<string, mixed>}|array{success: false, status: int|null, body: string}
     */
    public function send(string $systemPrompt, array $messages, array $options = []): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return ['success' => false, 'status' => null, 'body' => 'API key not configured'];
        }

        $payload = [
            'model' => $options['model'] ?? config('services.anthropic.model', 'claude-sonnet-4-6'),
            'max_tokens' => $options['max_tokens'] ?? 4096,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        foreach (['tools', 'tool_choice', 'temperature', 'thinking'] as $key) {
            if (isset($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
        ])
            ->timeout($options['timeout'] ?? 120)
            ->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            return ['success' => false, 'status' => $response->status(), 'body' => $response->body()];
        }

        return ['success' => true, 'data' => $response->json()];
    }

    /**
     * Extract the first text block from a successful API response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function extractText(array $data): string
    {
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                return $block['text'] ?? '';
            }
        }

        return '';
    }

    /**
     * Extract the input payload from the first matching tool_use block.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    public static function extractToolUse(array $data, string $toolName): ?array
    {
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === $toolName) {
                return $block['input'] ?? [];
            }
        }

        return null;
    }

    /**
     * Extract the tool_use_id of the first matching tool_use block.
     *
     * @param  array<string, mixed>  $data
     */
    public static function extractToolUseId(array $data, string $toolName): ?string
    {
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'tool_use' && ($block['name'] ?? '') === $toolName) {
                return $block['id'] ?? null;
            }
        }

        return null;
    }
}
