<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiEngineSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * OpenAI, and the base for every provider that speaks its protocol.
 *
 * OpenRouter is deliberately a subclass rather than a sibling: it serves the
 * identical /chat/completions contract, so duplicating the request building
 * and SSE parsing for it would mean two copies to keep in step. Anthropic is
 * NOT a subclass -- its wire format genuinely differs -- and pretending
 * otherwise is how adapters rot.
 */
class OpenAiDriver implements AiProviderDriver
{
    protected const BASE_URL = 'https://api.openai.com/v1';

    public function slug(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI';
    }

    public function key(): ?string
    {
        return AiEngineSettings::openAiKey();
    }

    /** Extra headers this vendor wants on every call. */
    protected function headers(): array
    {
        return [];
    }

    protected function baseUrl(): string
    {
        return static::BASE_URL;
    }

    public function supportsEmbeddings(): bool
    {
        return true;
    }

    public function chat(string $model, array $messages, array $opts = []): array
    {
        $json = $this->request('POST', '/chat/completions', $this->chatPayload($model, $messages, $opts));

        $choice = $json['choices'][0]['message'] ?? [];

        return [
            'content'    => $choice['content'] ?? null,
            'tool_calls' => $choice['tool_calls'] ?? [],
            'tokens_in'  => (int) ($json['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($json['usage']['completion_tokens'] ?? 0),
            'raw'        => $json,
        ];
    }

    public function chatStream(string $model, array $messages, array $opts, callable $onDelta): array
    {
        $payload = $this->chatPayload($model, $messages, $opts) + [
            'stream' => true,
            // Ask for a final usage frame so the charge is the vendor's real
            // token count rather than our estimate.
            'stream_options' => ['include_usage' => true],
        ];

        $response = $this->authed()
            ->withOptions(['stream' => true])
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->timeout(120)
            ->post($this->baseUrl() . '/chat/completions', $payload);

        $this->throwIfFailed($response, '/chat/completions (stream)');

        $body      = $response->toPsrResponse()->getBody();
        $buffer    = '';
        $content   = '';
        $tokensIn  = 0;
        $tokensOut = 0;
        $toolCalls = [];

        while (!$body->eof()) {
            $buffer .= $body->read(8192);

            while (($nl = strpos($buffer, "\n")) !== false) {
                $line   = trim(substr($buffer, 0, $nl));
                $buffer = substr($buffer, $nl + 1);

                if ($line === '' || !str_starts_with($line, 'data:')) {
                    continue;
                }

                $data = trim(substr($line, 5));
                if ($data === '[DONE]') {
                    break 2;
                }

                $frame = json_decode($data, true);
                if (!is_array($frame)) {
                    continue;
                }

                // The usage frame arrives with an empty choices array.
                if (isset($frame['usage'])) {
                    $tokensIn  = (int) ($frame['usage']['prompt_tokens'] ?? $tokensIn);
                    $tokensOut = (int) ($frame['usage']['completion_tokens'] ?? $tokensOut);
                }

                $delta = $frame['choices'][0]['delta'] ?? [];

                if (isset($delta['tool_calls'])) {
                    $toolCalls = $this->mergeToolCallDeltas($toolCalls, $delta['tool_calls']);
                }

                $piece = $delta['content'] ?? null;
                if (is_string($piece) && $piece !== '') {
                    $content .= $piece;
                    $onDelta($piece);
                }
            }
        }

        return [
            'content'    => $content === '' ? null : $content,
            'tool_calls' => array_values($toolCalls),
            'tokens_in'  => $tokensIn,
            'tokens_out' => $tokensOut,
            'raw'        => [],
        ];
    }

    /**
     * Streamed tool calls arrive in fragments keyed by index, with the
     * arguments string built up character by character across frames.
     */
    protected function mergeToolCallDeltas(array $acc, array $deltas): array
    {
        foreach ($deltas as $d) {
            $i = (int) ($d['index'] ?? 0);
            $acc[$i] ??= ['id' => null, 'type' => 'function', 'function' => ['name' => '', 'arguments' => '']];

            if (!empty($d['id'])) {
                $acc[$i]['id'] = $d['id'];
            }
            if (!empty($d['function']['name'])) {
                $acc[$i]['function']['name'] .= $d['function']['name'];
            }
            if (isset($d['function']['arguments'])) {
                $acc[$i]['function']['arguments'] .= $d['function']['arguments'];
            }
        }

        return $acc;
    }

    public function embed(string $model, array $inputs, array $opts = []): array
    {
        $json = $this->request('POST', '/embeddings', [
            'model' => $model,
            'input' => $inputs,
        ]);

        $vectors = [];
        foreach ($json['data'] ?? [] as $row) {
            $vectors[] = $row['embedding'] ?? [];
        }

        return [
            'vectors'   => $vectors,
            'tokens_in' => (int) ($json['usage']['prompt_tokens'] ?? 0),
            'raw'       => $json,
        ];
    }

    public function testKey(?string $key = null, ?string $model = null): array
    {
        $key = $key ?: $this->key();
        if (!$key) {
            return ['ok' => false, 'message' => $this->label() . ' key is not set.'];
        }

        try {
            $res = Http::withToken($key)
                ->withHeaders($this->headers())
                ->acceptJson()
                ->timeout(20)
                ->get($this->baseUrl() . '/models');

            return $res->successful()
                ? ['ok' => true, 'message' => $this->label() . ' key works.']
                : ['ok' => false, 'message' => $this->label() . ' rejected the key (HTTP ' . $res->status() . ').'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $this->label() . ' unreachable: ' . $e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    protected function chatPayload(string $model, array $messages, array $opts): array
    {
        return array_filter([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $opts['temperature'] ?? null,
            'max_tokens'  => $opts['max_tokens'] ?? null,
            'tools'       => $opts['tools'] ?? null,
            'tool_choice' => $opts['tool_choice'] ?? null,
        ], fn ($v) => $v !== null);
    }

    protected function authed(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->key())->withHeaders($this->headers());
    }

    protected function request(string $method, string $path, array $payload): array
    {
        $req = $this->authed()
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 250, function ($e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false);

        $url = $this->baseUrl() . $path;

        $res = strtoupper($method) === 'POST'
            ? $req->post($url, $payload)
            : $req->get($url, $payload);

        $this->throwIfFailed($res, $path);

        return $res->json() ?? [];
    }

    protected function throwIfFailed($res, string $path): void
    {
        if (!$res->failed()) {
            return;
        }

        $msg = (string) Str::of($res->body())->limit(300);
        Log::warning("{$this->label()} {$path} failed: HTTP {$res->status()} {$msg}");

        // The status is carried in the message because callers (and the
        // fallback chain) branch on it: a 429 or 5xx is worth trying the
        // next provider for, a 400 is our own bad request and is not.
        throw new AiProviderException(
            "{$this->label()} request failed (HTTP {$res->status()}).",
            $res->status(),
        );
    }
}
