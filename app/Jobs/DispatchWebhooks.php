<?php

namespace App\Jobs;

use App\Enums\WebhookEvent;
use App\Models\WebhookEndpoint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class DispatchWebhooks implements ShouldQueue
{
    use Queueable;

    /**
     * @param  int  $puzzleOwnerId  The user who owns the puzzle (webhook endpoints belong to them)
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public WebhookEvent $event,
        public int $puzzleOwnerId,
        public array $payload,
    ) {}

    public function handle(): void
    {
        $endpoints = WebhookEndpoint::query()
            ->where('user_id', $this->puzzleOwnerId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (WebhookEndpoint $ep) => $ep->subscribedTo($this->event));

        foreach ($endpoints as $endpoint) {
            $this->deliver($endpoint);
        }
    }

    private function deliver(WebhookEndpoint $endpoint): void
    {
        $body = [
            'event' => $this->event->value,
            'timestamp' => now()->toIso8601String(),
            'data' => $this->payload,
        ];

        $json = json_encode($body, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $json, $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $this->event->value,
                ])
                ->withBody($json, 'application/json')
                ->post($endpoint->url);

            $endpoint->deliveries()->create([
                'event' => $this->event->value,
                'payload' => $body,
                'response_code' => $response->status(),
                'response_body' => mb_substr($response->body(), 0, 2000),
                'success' => $response->successful(),
                'attempt_count' => 1,
                'delivered_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $endpoint->deliveries()->create([
                'event' => $this->event->value,
                'payload' => $body,
                'response_code' => null,
                'response_body' => mb_substr($e->getMessage(), 0, 2000),
                'success' => false,
                'attempt_count' => 1,
                'delivered_at' => now(),
            ]);
        }

        $endpoint->update(['last_triggered_at' => now()]);
    }
}
