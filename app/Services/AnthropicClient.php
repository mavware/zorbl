<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class AnthropicClient
{
    /**
     * Send a message to the Anthropic Claude API.
     *
     * @return array{success: true, data: array<string, mixed>}|array{success: false, status: int|null, body: string}
     */
    public function sendMessage(string $systemPrompt, string $userMessage): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            return ['success' => false, 'status' => null, 'body' => 'API key not configured'];
        }

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
            return ['success' => false, 'status' => $response->status(), 'body' => $response->body()];
        }

        return ['success' => true, 'data' => $response->json()];
    }

    /**
     * Extract the text content from a successful API response.
     */
    public static function extractText(array $data): string
    {
        return $data['content'][0]['text'] ?? '';
    }
}
