<?php

namespace App\Services\Anthropic;

use Closure;
use Throwable;

/**
 * Base class for single-shot Anthropic requests that return a result envelope.
 *
 * Subclasses build a request (system prompt, messages, options) plus a success
 * parser, and declare how to shape failures. This class owns the shared
 * lifecycle: sending, distinguishing a missing API key from an API error,
 * catching transport exceptions, and routing each outcome to the right hook.
 *
 * Multi-turn flows (e.g. tool_use → tool_result → retry) and services that
 * throw on failure keep using {@see AnthropicClient} directly.
 */
abstract class AnthropicAction
{
    public function __construct(protected readonly AnthropicClient $client) {}

    /**
     * Send a single request and route the outcome through $onSuccess or the
     * failure hooks below.
     *
     * @param  list<array{role: string, content: mixed}>  $messages
     * @param  array<string, mixed>  $options
     * @param  Closure(array<string, mixed>): mixed  $onSuccess  Builds the result from the API `data` payload.
     */
    protected function dispatch(string $systemPrompt, array $messages, array $options, Closure $onSuccess): mixed
    {
        try {
            $result = $this->client->send($systemPrompt, $messages, $options);

            if ($result['success']) {
                return $onSuccess($result['data']);
            }

            if (($result['status'] ?? null) === null) {
                return $this->onMissingKey();
            }

            return $this->onError($result['status'], (string) ($result['body'] ?? ''));
        } catch (Throwable $e) {
            return $this->onException($e);
        }
    }

    /**
     * Result when the API key isn't configured. Defaults to the generic error
     * handler; override to surface a distinct "configure your key" message.
     */
    protected function onMissingKey(): mixed
    {
        return $this->onError(null, 'API key not configured');
    }

    /**
     * Result when the API returns a non-success HTTP status.
     */
    abstract protected function onError(?int $status, string $body): mixed;

    /**
     * Result when the transport itself throws. Defaults to the generic error
     * handler; override to log at error level or include the exception message.
     */
    protected function onException(Throwable $e): mixed
    {
        return $this->onError(null, $e->getMessage());
    }
}
