<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Shared OpenAI client used by every AI feature (Mind, Persona,
 * Companion, Coach). Centralising here means key rotation, model
 * gating, retries, and coin metering live in one place.
 *
 * Usage:
 *   $svc->chat($user, 'gpt-4o-mini', $messages, ['feature' => 'coach']);
 *   $svc->embed($user, 'text-embedding-3-small', ['hello'], ['feature' => 'mind']);
 *
 * Both methods:
 *   1. Reject the call if AI is disabled, the key is missing, or the
 *      requested model isn't enabled.
 *   2. Pre-check the user can afford the worst-case cost (a tiny
 *      prepaid floor) so we fail fast before hitting OpenAI.
 *   3. Execute the HTTP call.
 *   4. Compute the actual coins spent from returned token usage and
 *      the admin's per-model coins-per-1k-token rate.
 *   5. Charge the coin wallet via {@see AiUsageCharger}, tagged with
 *      the feature.
 */
class OpenAiService
{
    public const BASE_URL = 'https://api.openai.com/v1';

    /** Absolute floor for any chargeable call (covers nano-cost models). */
    protected const MIN_PREPAY_CREDITS = 1;

    /** Rough chars-per-token used when caller didn't pre-tokenize. */
    protected const CHARS_PER_TOKEN = 4;

    /** Worst-case completion length when caller didn't set max_tokens. */
    protected const DEFAULT_MAX_OUTPUT_TOKENS = 1024;

    public function __construct(protected AiUsageCharger $credits) {}

    /**
     * Chat completion. Returns:
     *   ['content' => string, 'tokens_in' => int, 'tokens_out' => int,
     *    'credits_spent' => int, 'model' => string, 'raw' => array]
     */
    public function chat(User $user, string $model, array $messages, array $opts = []): array
    {
        $modelCfg = $this->guard($model, 'chat');

        // Worst-case prepay gate: estimate prompt tokens from message
        // text length and assume the model will emit max_tokens of output
        // (or DEFAULT_MAX_OUTPUT_TOKENS if unbounded). We refuse the
        // request — and never hit OpenAI — if the user can't afford the
        // upper bound.
        $multiplier   = AiPlanAccess::coinMultiplier($user, 'openai');
        $estimatedIn  = $this->estimateChatPromptTokens($messages);
        $maxOut       = (int) ($opts['max_tokens'] ?? self::DEFAULT_MAX_OUTPUT_TOKENS);
        // Honor admin intent: if model rates are set to 0, the floor is 0
        // too. We still respect any caller-supplied min_credits.
        $worstCase    = max(
            (int) ($opts['min_credits'] ?? 0),
            $this->computeCost($modelCfg, $estimatedIn, $maxOut, $multiplier),
        );
        if ($worstCase > 0) $this->ensureCanAfford($user, $worstCase);

        $payload = array_filter([
            'model'             => $model,
            'messages'          => $messages,
            'temperature'       => $opts['temperature'] ?? null,
            'max_tokens'        => $opts['max_tokens'] ?? null,
            'response_format'   => $opts['response_format'] ?? null,
            // Native OpenAI function-calling. When `tools` is set the
            // model may answer with `tool_calls` instead of content;
            // the caller is responsible for executing them and feeding
            // the results back as `role=tool` messages on a follow-up
            // call. `tool_choice` lets callers force/forbid calls.
            'tools'             => $opts['tools'] ?? null,
            'tool_choice'       => $opts['tool_choice'] ?? null,
        ], fn($v) => $v !== null);

        $response = $this->request('POST', '/chat/completions', $payload);

        $tokensIn  = (int) ($response['usage']['prompt_tokens'] ?? 0);
        $tokensOut = (int) ($response['usage']['completion_tokens'] ?? 0);
        $cost      = $this->computeCost($modelCfg, $tokensIn, $tokensOut, $multiplier);

        // Skip the ledger entry entirely when the admin has set rates to
        // zero — there is nothing to charge and we don't want to pollute
        // the audit trail with no-op rows.
        $tx = $cost > 0
            ? $this->credits->charge($user, $cost, [
                'feature'    => $opts['feature'] ?? null,
                'related_id' => $opts['related_id'] ?? null,
                'model'      => $model,
                'tokens_in'  => $tokensIn,
                'tokens_out' => $tokensOut,
                'provider'   => 'openai',
                'multiplier' => $multiplier,
                'reason'     => $opts['reason'] ?? "OpenAI chat ({$model})",
                'meta'       => array_merge(
                    is_array($opts['meta'] ?? null) ? $opts['meta'] : [],
                    ['call_id' => $response['id'] ?? null],
                ),
            ])
            : null;

        $message     = $response['choices'][0]['message'] ?? [];
        $finish      = (string) ($response['choices'][0]['finish_reason'] ?? '');
        $toolCalls   = is_array($message['tool_calls'] ?? null) ? $message['tool_calls'] : [];

        return [
            'content'       => (string) ($message['content'] ?? ''),
            'tool_calls'    => $toolCalls,
            'finish_reason' => $finish,
            'tokens_in'     => $tokensIn,
            'tokens_out'    => $tokensOut,
            'credits_spent' => $tx ? (int) abs($tx->delta_coins) : 0,
            'model'         => $model,
            'raw'           => $response,
        ];
    }

    /**
     * Streaming chat completion. Same gating + ledger semantics as
     * {@see chat()} but pushes tokens to `$onChunk(string $delta)` as
     * the model produces them, so callers can re-emit them down an SSE
     * channel for word-by-word rendering.
     *
     * Returns the same shape as chat() (sans `raw`).
     */
    public function chatStream(
        User $user,
        string $model,
        array $messages,
        array $opts,
        callable $onChunk,
    ): array {
        $modelCfg = $this->guard($model, 'chat');

        $multiplier  = AiPlanAccess::coinMultiplier($user, 'openai');
        $estimatedIn = $this->estimateChatPromptTokens($messages);
        $maxOut      = (int) ($opts['max_tokens'] ?? self::DEFAULT_MAX_OUTPUT_TOKENS);
        $worstCase   = max(
            (int) ($opts['min_credits'] ?? 0),
            $this->computeCost($modelCfg, $estimatedIn, $maxOut, $multiplier),
        );
        if ($worstCase > 0) $this->ensureCanAfford($user, $worstCase);

        $payload = array_filter([
            'model'           => $model,
            'messages'        => $messages,
            'temperature'     => $opts['temperature'] ?? null,
            'max_tokens'      => $opts['max_tokens'] ?? null,
            'stream'          => true,
            // Ask OpenAI to emit a final usage frame so we can charge
            // the exact token counts instead of estimating.
            'stream_options'  => ['include_usage' => true],
        ], fn($v) => $v !== null);

        $key = AiEngineSettings::openAiKey();
        $url = self::BASE_URL . '/chat/completions';

        $response = Http::withToken($key)
            ->withOptions(['stream' => true])
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->timeout(120)
            ->post($url, $payload);

        if ($response->failed()) {
            $msg = (string) Str::of($response->body())->limit(300);
            Log::warning("OpenAI stream failed: HTTP {$response->status()} {$msg}");
            throw new \RuntimeException("OpenAI request failed (HTTP {$response->status()}).");
        }

        $body      = $response->toPsrResponse()->getBody();
        $buffer    = '';
        $content   = '';
        $tokensIn  = 0;
        $tokensOut = 0;
        $callId    = null;

        // Snapshot the visitor's balance up front so we can monitor
        // running cost vs. remaining credits without hammering the
        // ledger on every delta. The pre-call gate already proved the
        // worst case fits, but the model may overshoot the estimate or
        // ignore max_tokens — without this guard a longer-than-expected
        // reply would silently push the balance below zero, charge for
        // it, and leave the visitor unable to continue with no warning.
        $startBalance = $this->credits->getBalance($user);
        $hasRates     = ((float) $modelCfg['in_coins_per_1k']) > 0
                     || ((float) $modelCfg['out_coins_per_1k']) > 0;
        $exhausted    = false;

        while (!$body->eof()) {
            $chunk = $body->read(2048);
            if ($chunk === '') continue;
            // Normalize CRLF so parsers downstream only have to look for "\n\n".
            $buffer .= str_replace("\r\n", "\n", $chunk);

            // SSE frames are delimited by a blank line (\n\n).
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $frame  = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);
                foreach (explode("\n", $frame) as $line) {
                    if (!str_starts_with($line, 'data:')) continue;
                    $data = trim(substr($line, 5));
                    if ($data === '' || $data === '[DONE]') continue;
                    $j = json_decode($data, true);
                    if (!is_array($j)) continue;
                    if ($callId === null && isset($j['id'])) $callId = $j['id'];
                    $delta = $j['choices'][0]['delta']['content'] ?? null;
                    if (is_string($delta) && $delta !== '') {
                        $content .= $delta;
                        $onChunk($delta);
                    }
                    if (isset($j['usage']) && is_array($j['usage'])) {
                        $tokensIn  = (int) ($j['usage']['prompt_tokens']     ?? $tokensIn);
                        $tokensOut = (int) ($j['usage']['completion_tokens'] ?? $tokensOut);
                    }
                }

                // After draining each frame, check whether the running
                // estimated cost has eaten through the visitor's balance.
                // We use estimates here because OpenAI only sends the
                // authoritative usage frame at the very end of the
                // stream, which is too late to stop early.
                if ($hasRates) {
                    $runningIn  = $tokensIn  > 0 ? $tokensIn  : $estimatedIn;
                    $runningOut = $tokensOut > 0 ? $tokensOut : $this->estimateTextTokens($content);
                    $runningCost = $this->computeCost($modelCfg, $runningIn, $runningOut, $multiplier);
                    if ($runningCost > $startBalance) {
                        $exhausted = true;
                        break;
                    }
                }
            }
            if ($exhausted) {
                // Cut the upstream connection so we stop accruing tokens
                // and the visitor doesn't keep watching text they cannot
                // afford. The body close also lets the HTTP client tear
                // down the socket promptly.
                try { $body->close(); } catch (\Throwable $e) { /* best-effort */ }
                break;
            }
        }

        // Fall back to estimates if OpenAI omitted the usage frame
        // (always the case when we cut the stream short).
        if ($tokensIn  === 0) $tokensIn  = $estimatedIn;
        if ($tokensOut === 0) $tokensOut = $this->estimateTextTokens($content);

        $cost = $this->computeCost($modelCfg, $tokensIn, $tokensOut, $multiplier);
        // Cap the charge at whatever the visitor actually had so we
        // never write a transaction that drops the balance below zero
        // because of a mid-stream cutoff. Anything above the start
        // balance was effectively absorbed by us.
        if ($exhausted && $cost > $startBalance) {
            $cost = $startBalance;
        }
        $tx = $cost > 0
            ? $this->credits->charge($user, $cost, [
                'feature'    => $opts['feature'] ?? null,
                'related_id' => $opts['related_id'] ?? null,
                'model'      => $model,
                'tokens_in'  => $tokensIn,
                'tokens_out' => $tokensOut,
                'provider'   => 'openai',
                'multiplier' => $multiplier,
                'reason'     => $opts['reason'] ?? "OpenAI chat stream ({$model})",
                'meta'       => array_merge(
                    is_array($opts['meta'] ?? null) ? $opts['meta'] : [],
                    array_filter([
                        'call_id'   => $callId,
                        'streamed'  => true,
                        'truncated' => $exhausted ? 'out_of_credits' : null,
                    ], fn ($v) => $v !== null),
                ),
            ])
            : null;

        $creditsSpent = $tx ? (int) abs($tx->delta_coins) : 0;

        if ($exhausted) {
            // Surface a typed signal to the runtime so it can persist
            // the partial transcript and emit a clear out-of-credits
            // SSE error frame instead of letting the stream end with
            // an unexplained gap.
            throw new StreamCoinsExhaustedException(
                required: $cost > 0 ? $cost : 1,
                balance: max(0, $startBalance - $creditsSpent),
                partialContent: $content,
                tokensIn: $tokensIn,
                tokensOut: $tokensOut,
                creditsSpent: $creditsSpent,
            );
        }

        return [
            'content'       => $content,
            'tokens_in'     => $tokensIn,
            'tokens_out'    => $tokensOut,
            'credits_spent' => $creditsSpent,
            'model'         => $model,
        ];
    }

    /**
     * Embedding call. `$inputs` is a list of strings. Returns:
     *   ['vectors' => list<list<float>>, 'tokens_in' => int,
     *    'credits_spent' => int, 'model' => string]
     */
    public function embed(User $user, string $model, array $inputs, array $opts = []): array
    {
        $modelCfg = $this->guard($model, 'embedding');

        // Worst-case prepay gate based on the cumulative input length.
        $multiplier  = AiPlanAccess::coinMultiplier($user, 'openai');
        $estimatedIn = $this->estimateTextTokens(implode("\n", $inputs));
        $worstCase   = max(
            (int) ($opts['min_credits'] ?? 0),
            $this->computeCost($modelCfg, $estimatedIn, 0, $multiplier),
        );
        if ($worstCase > 0) $this->ensureCanAfford($user, $worstCase);

        $response = $this->request('POST', '/embeddings', [
            'model' => $model,
            'input' => $inputs,
        ]);

        $tokensIn = (int) ($response['usage']['prompt_tokens'] ?? 0);
        $cost     = $this->computeCost($modelCfg, $tokensIn, 0, $multiplier);

        $tx = $cost > 0
            ? $this->credits->charge($user, $cost, [
                'feature'    => $opts['feature'] ?? null,
                'related_id' => $opts['related_id'] ?? null,
                'model'      => $model,
                'tokens_in'  => $tokensIn,
                'tokens_out' => 0,
                'provider'   => 'openai',
                'multiplier' => $multiplier,
                'reason'     => $opts['reason'] ?? "OpenAI embedding ({$model})",
                'meta'       => is_array($opts['meta'] ?? null) ? $opts['meta'] : null,
            ])
            : null;

        $vectors = array_map(fn($d) => $d['embedding'] ?? [], $response['data'] ?? []);

        return [
            'vectors'       => $vectors,
            'tokens_in'     => $tokensIn,
            'credits_spent' => $tx ? (int) abs($tx->delta_coins) : 0,
            'model'         => $model,
        ];
    }

    /**
     * Lightweight, UNMETERED connectivity check for the admin AI Engine
     * page. Makes a tiny 1-token chat completion against $model using the
     * supplied key (or the stored key when null) purely to confirm the
     * credential works and the model is reachable. It never touches the
     * coin wallet and never requires the AI Engine to be enabled, so an
     * admin can validate a key before flipping the master switch.
     *
     * @return array{ok:bool,model?:string,message:string,status?:int}
     */
    public function testKey(?string $key = null, ?string $model = null): array
    {
        $key = ($key !== null && trim($key) !== '') ? trim($key) : AiEngineSettings::openAiKey();
        if (!$key) {
            return ['ok' => false, 'message' => 'No OpenAI API key is configured. Paste a key above (or save one) and try again.'];
        }

        $model = ($model !== null && trim($model) !== '') ? trim($model) : AiEngineSettings::DEFAULT_FEATURE_MODEL;

        try {
            $res = Http::withToken($key)
                ->acceptJson()
                ->timeout(20)
                ->post(self::BASE_URL . '/chat/completions', [
                    'model'      => $model,
                    'messages'   => [['role' => 'user', 'content' => 'ping']],
                    'max_tokens' => 1,
                ]);
        } catch (\Throwable $e) {
            Log::warning('OpenAI test connection threw: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Network error reaching OpenAI: ' . Str::limit($e->getMessage(), 160)];
        }

        if ($res->successful()) {
            $echoed = (string) ($res->json('model') ?: $model);
            return ['ok' => true, 'model' => $echoed, 'message' => "Success — OpenAI responded using model \"{$echoed}\"."];
        }

        $status = $res->status();
        $apiMsg = (string) ($res->json('error.message') ?? '');
        $message = match (true) {
            $status === 401 => 'Invalid API key — OpenAI rejected it (401 Unauthorized).',
            $status === 403 => 'Key lacks access (403). Check the project/org and model permissions.',
            $status === 404 => "Model \"{$model}\" is not accessible to this key (404 Not Found).",
            $status === 429 => 'Rate limited or quota exhausted (429). The key works but is out of credit/limit.',
            default => "OpenAI returned HTTP {$status}" . ($apiMsg !== '' ? ': ' . Str::limit($apiMsg, 160) : '.'),
        };

        return ['ok' => false, 'status' => $status, 'message' => $message];
    }

    /** Returns the validated model config or throws. */
    protected function guard(string $model, string $expectedKind): array
    {
        if (!AiEngineSettings::isEnabled()) {
            throw new \RuntimeException('AI Engine is disabled.');
        }
        if (!AiEngineSettings::openAiKey()) {
            throw new \RuntimeException('OpenAI API key is not configured.');
        }
        $cfg = AiEngineSettings::model($model);
        if (!$cfg || !$cfg['enabled']) {
            throw new \RuntimeException("Model not enabled: {$model}");
        }
        if ($cfg['kind'] !== $expectedKind) {
            throw new \RuntimeException("Model {$model} is configured as {$cfg['kind']}, not {$expectedKind}.");
        }
        return $cfg;
    }

    protected function ensureCanAfford(User $user, int $minCredits): void
    {
        $balance = $this->credits->getBalance($user);
        if ($balance < $minCredits) {
            throw new InsufficientCoinsForAiException($minCredits, $balance);
        }
    }

    /**
     * Public worst-case estimator for "how many coins will this chat
     * call cost?" Used by features that surface the price before the
     * user clicks Run (e.g. resume tailoring). Returns 0 when the model
     * isn't enabled or the admin's per-token rates are zero.
     */
    public function estimateChatCoins(string $model, array $messages, int $maxOutputTokens = self::DEFAULT_MAX_OUTPUT_TOKENS, ?User $user = null): int
    {
        if (!AiEngineSettings::isEnabled()) return 0;
        $cfg = AiEngineSettings::model($model);
        if (!$cfg || !$cfg['enabled'] || $cfg['kind'] !== 'chat') return 0;
        // Route the displayed estimate through the same per-plan multiplier
        // the real charge uses, so the gate and the price never disagree.
        $multiplier = $user ? AiPlanAccess::coinMultiplier($user, 'openai') : 1.0;
        $tokensIn = $this->estimateChatPromptTokens($messages);
        return $this->computeCost($cfg, $tokensIn, max(1, $maxOutputTokens), $multiplier);
    }

    /**
     * Cheap, dependency-free token estimate. Always rounds *up* so the
     * worst-case gate stays conservative.
     */
    protected function estimateTextTokens(string $text): int
    {
        $chars = mb_strlen($text);
        return (int) max(1, ceil($chars / self::CHARS_PER_TOKEN));
    }

    protected function estimateChatPromptTokens(array $messages): int
    {
        $total = 0;
        foreach ($messages as $m) {
            // OpenAI adds ~4 framing tokens per message (role + delimiters).
            $content = is_array($m['content'] ?? null)
                ? json_encode($m['content'])
                : (string) ($m['content'] ?? '');
            $total += 4 + $this->estimateTextTokens($content);
        }
        return max(1, $total + 2); // closing assistant priming
    }

    /**
     * Per-call cost in whole coins. Rates are fractional coins per 1 000
     * tokens; we sum the exact float cost and ceil() so the wallet (an
     * integer coin balance) is never under-charged and never left
     * negative. A non-zero-rate call always costs at least 1 coin.
     */
    protected function computeCost(array $modelCfg, int $tokensIn, int $tokensOut, float $multiplier = 1.0): int
    {
        $inRate  = (float) ($modelCfg['in_coins_per_1k'] ?? 0);
        $outRate = (float) ($modelCfg['out_coins_per_1k'] ?? 0);
        $cost = ($tokensIn * $inRate + $tokensOut * $outRate) / 1000;
        // Scale the exact float cost by the plan multiplier BEFORE ceil so
        // a non-zero base rate still costs at least 1 whole coin (the
        // multiplier is normalised to >0 by AiPlanAccess::coinMultiplier).
        $cost *= ($multiplier > 0 ? $multiplier : 1.0);
        return max(0, (int) ceil($cost));
    }

    /**
     * Wrapped HTTP call so retries / logging live in one place.
     * Throws on non-2xx with a typed message — features surface this.
     */
    protected function request(string $method, string $path, array $payload): array
    {
        $key = AiEngineSettings::openAiKey();
        $url = self::BASE_URL . $path;
        $req = Http::withToken($key)
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 250, function ($e) {
                if ($e instanceof \Illuminate\Http\Client\ConnectionException) return true;
                return false;
            }, throw: false);

        $res = strtoupper($method) === 'POST'
            ? $req->post($url, $payload)
            : $req->get($url, $payload);
        if ($res->failed()) {
            $msg = (string) Str::of($res->body())->limit(300);
            Log::warning("OpenAI {$path} failed: HTTP {$res->status()} {$msg}");
            throw new \RuntimeException("OpenAI request failed (HTTP {$res->status()}).");
        }
        return $res->json() ?? [];
    }
}
