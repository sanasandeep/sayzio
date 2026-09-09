<?php

namespace App\Services\AI\Providers;

/**
 * One AI vendor's wire protocol.
 *
 * Everything above this interface -- coin accounting, plan gating, vision
 * guards, the per-plan/per-module model map -- is vendor-neutral and lives in
 * OpenAiService. What actually differs between OpenAI, OpenRouter and
 * Anthropic is only three things: where the request goes, how it is
 * authenticated, and the shape of the JSON either way. That is all a driver
 * is responsible for.
 *
 * Drivers deliberately do NOT touch the ledger, the User, or AiEngineSettings'
 * model registry. They take a model name and messages, and hand back a
 * NORMALISED result. Keeping them that thin is what stops a third vendor from
 * needing a third copy of the cost logic.
 *
 * The normalised chat result:
 *
 *   [
 *     'content'    => ?string,   // assistant text, null when only tool calls
 *     'tool_calls' => array,     // OpenAI-shaped tool call array, [] if none
 *     'tokens_in'  => int,       // prompt tokens actually billed by the vendor
 *     'tokens_out' => int,       // completion tokens actually billed
 *     'raw'        => array,     // untouched vendor response, for debugging
 *   ]
 *
 * Token counts are the vendor's own numbers, not an estimate: every provider
 * here reports them, and charging a user for an estimate when the real figure
 * was in the response would be indefensible. A driver that genuinely cannot
 * report them returns 0 and the caller falls back to its estimate.
 */
interface AiProviderDriver
{
    /** Stable slug: 'openai', 'openrouter', 'anthropic'. */
    public function slug(): string;

    /** Human name for admin surfaces. */
    public function label(): string;

    /** The configured API key, or null when this provider is not set up. */
    public function key(): ?string;

    /**
     * One non-streaming chat completion.
     *
     * @param  array<int,array<string,mixed>>  $messages  OpenAI-shaped messages
     * @param  array<string,mixed>             $opts      temperature, max_tokens, tools, ...
     * @return array{content:?string,tool_calls:array,tokens_in:int,tokens_out:int,raw:array}
     *
     * @throws \RuntimeException on any non-2xx, with the vendor's message
     */
    public function chat(string $model, array $messages, array $opts = []): array;

    /**
     * Streaming chat completion. $onDelta receives each text fragment as it
     * arrives; the return value is the same normalised array as chat().
     *
     * @param  array<int,array<string,mixed>>  $messages
     * @param  array<string,mixed>             $opts
     * @return array{content:?string,tool_calls:array,tokens_in:int,tokens_out:int,raw:array}
     */
    public function chatStream(string $model, array $messages, array $opts, callable $onDelta): array;

    /**
     * Embeddings. Providers without an embedding endpoint throw, and the
     * caller is expected to keep embeddings on a provider that has one --
     * this is why supportsEmbeddings() exists rather than a silent [].
     *
     * @param  array<int,string>  $inputs
     * @return array{vectors:array<int,array<int,float>>,tokens_in:int,raw:array}
     */
    public function embed(string $model, array $inputs, array $opts = []): array;

    /** Whether embed() is meaningful for this vendor. */
    public function supportsEmbeddings(): bool;

    /**
     * Cheapest possible call that proves a key works, for the admin's
     * "test key" button.
     *
     * @return array{ok:bool,message:string}
     */
    public function testKey(?string $key = null, ?string $model = null): array;
}
