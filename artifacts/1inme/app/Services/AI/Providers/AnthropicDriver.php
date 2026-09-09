<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiEngineSettings;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Anthropic's Messages API.
 *
 * Not a subclass of OpenAiDriver, because almost nothing about the wire
 * format matches. Five real differences, each handled below:
 *
 *   1. Auth is an `x-api-key` header plus a required `anthropic-version`,
 *      not a bearer token.
 *   2. The system prompt is a TOP-LEVEL `system` field. Leaving a
 *      role:"system" message in the array is rejected outright.
 *   3. `max_tokens` is REQUIRED. OpenAI treats it as optional, so callers
 *      routinely omit it; omitting it here is a 400.
 *   4. Content is a list of typed blocks, not a string, in both directions.
 *   5. Usage is `input_tokens` / `output_tokens`, and streaming reports them
 *      across two different events (message_start and message_delta).
 *
 * Tool calls are translated both ways so callers keep writing OpenAI-shaped
 * tools and reading OpenAI-shaped tool_calls, whichever vendor answered.
 */
class AnthropicDriver implements AiProviderDriver
{
    protected const BASE_URL = 'https://api.anthropic.com/v1';

    /** Pinned deliberately: Anthropic versions its API by date. */
    protected const API_VERSION = '2023-06-01';

    /** Used only when a caller omits max_tokens, which Anthropic forbids. */
    protected const DEFAULT_MAX_TOKENS = 4096;

    public function slug(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return 'Claude API';
    }

    public function key(): ?string
    {
        return AiEngineSettings::anthropicKey();
    }

    public function supportsEmbeddings(): bool
    {
        return false;
    }

    public function embed(string $model, array $inputs, array $opts = []): array
    {
        throw new AiProviderException(
            'Anthropic has no embeddings endpoint; point the embedding model at OpenAI.',
            400,
            $this->slug(),
        );
    }

    public function chat(string $model, array $messages, array $opts = []): array
    {
        $json = $this->request('/messages', $this->payload($model, $messages, $opts));

        return $this->normalise($json);
    }

    public function chatStream(string $model, array $messages, array $opts, callable $onDelta): array
    {
        $payload = $this->payload($model, $messages, $opts) + ['stream' => true];

        $response = $this->pending()
            ->withOptions(['stream' => true])
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->timeout(120)
            ->post(static::BASE_URL . '/messages', $payload);

        $this->throwIfFailed($response, '/messages (stream)');

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

                // Anthropic sends both `event:` and `data:` lines; the type
                // is also inside the JSON, so the event line is redundant.
                if ($line === '' || !str_starts_with($line, 'data:')) {
                    continue;
                }

                $frame = json_decode(trim(substr($line, 5)), true);
                if (!is_array($frame)) {
                    continue;
                }

                switch ($frame['type'] ?? '') {
                    case 'message_start':
                        // Input tokens are known up front and never repeated.
                        $tokensIn = (int) ($frame['message']['usage']['input_tokens'] ?? 0);
                        break;

                    case 'content_block_start':
                        if (($frame['content_block']['type'] ?? '') === 'tool_use') {
                            $toolCalls[(int) ($frame['index'] ?? 0)] = [
                                'id'       => $frame['content_block']['id'] ?? null,
                                'type'     => 'function',
                                'function' => [
                                    'name'      => $frame['content_block']['name'] ?? '',
                                    'arguments' => '',
                                ],
                            ];
                        }
                        break;

                    case 'content_block_delta':
                        $d = $frame['delta'] ?? [];
                        if (($d['type'] ?? '') === 'text_delta') {
                            $piece = (string) ($d['text'] ?? '');
                            if ($piece !== '') {
                                $content .= $piece;
                                $onDelta($piece);
                            }
                        } elseif (($d['type'] ?? '') === 'input_json_delta') {
                            $i = (int) ($frame['index'] ?? 0);
                            if (isset($toolCalls[$i])) {
                                $toolCalls[$i]['function']['arguments'] .= (string) ($d['partial_json'] ?? '');
                            }
                        }
                        break;

                    case 'message_delta':
                        // Output tokens are only final on this event.
                        $tokensOut = (int) ($frame['usage']['output_tokens'] ?? $tokensOut);
                        break;

                    case 'message_stop':
                        break 3;
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

    public function testKey(?string $key = null, ?string $model = null): array
    {
        $key = $key ?: $this->key();
        if (!$key) {
            return ['ok' => false, 'message' => 'Claude API key is not set.'];
        }

        // Anthropic has no cheap /models listing that validates a key, so the
        // test is the smallest possible completion: one token, one word.
        try {
            $res = Http::withHeaders([
                'x-api-key'         => $key,
                'anthropic-version' => static::API_VERSION,
            ])
                ->acceptJson()
                ->timeout(20)
                ->post(static::BASE_URL . '/messages', [
                    'model'      => $model ?: 'claude-3-5-haiku-latest',
                    'max_tokens' => 1,
                    'messages'   => [['role' => 'user', 'content' => 'hi']],
                ]);

            if ($res->successful()) {
                return ['ok' => true, 'message' => 'Claude API key works.'];
            }

            // A 404 here means the key is fine but that model name is not
            // available to this account -- worth saying, because "key
            // rejected" would send the admin looking in the wrong place.
            if ($res->status() === 404) {
                return ['ok' => false, 'message' => 'Key accepted, but that model is not available on this account.'];
            }

            return ['ok' => false, 'message' => 'Claude API rejected the key (HTTP ' . $res->status() . ').'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Claude API unreachable: ' . $e->getMessage()];
        }
    }

    /**
     * Translate an OpenAI-shaped request into Anthropic's.
     *
     * @param  array<int,array<string,mixed>>  $messages
     * @return array<string,mixed>
     */
    protected function payload(string $model, array $messages, array $opts): array
    {
        [$system, $turns] = $this->splitSystem($messages);

        $payload = [
            'model'      => $model,
            'messages'   => $turns,
            // Required by Anthropic. A caller that omitted it wanted "as much
            // as needed"; the ceiling here is the same default the rest of
            // the engine uses for its worst-case cost estimate.
            'max_tokens' => (int) ($opts['max_tokens'] ?? static::DEFAULT_MAX_TOKENS),
        ];

        if ($system !== '') {
            $payload['system'] = $system;
        }
        if (isset($opts['temperature'])) {
            $payload['temperature'] = $opts['temperature'];
        }
        if (!empty($opts['tools'])) {
            $payload['tools'] = $this->translateTools($opts['tools']);
        }

        return $payload;
    }

    /**
     * Hoist every system message into the top-level `system` string.
     * Multiple system messages are joined rather than dropped -- callers do
     * stack them, and silently losing one would change the model's behaviour
     * with nothing to show for it.
     *
     * @return array{0:string,1:array<int,array<string,mixed>>}
     */
    protected function splitSystem(array $messages): array
    {
        $system = [];
        $turns  = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'system') {
                $system[] = is_string($m['content'] ?? null)
                    ? $m['content']
                    : json_encode($m['content']);
                continue;
            }

            $turns[] = [
                'role'    => $role === 'assistant' ? 'assistant' : 'user',
                'content' => $this->translateContent($m['content'] ?? ''),
            ];
        }

        return [trim(implode("\n\n", array_filter($system))), $turns];
    }

    /**
     * OpenAI content is either a string or a list of parts. Anthropic wants
     * typed blocks, and spells images differently: a base64 `source` object
     * rather than an `image_url`.
     */
    protected function translateContent(mixed $content): mixed
    {
        if (is_string($content)) {
            return $content;
        }
        if (!is_array($content)) {
            return (string) $content;
        }

        $blocks = [];
        foreach ($content as $part) {
            $type = $part['type'] ?? 'text';

            if ($type === 'text') {
                $blocks[] = ['type' => 'text', 'text' => (string) ($part['text'] ?? '')];
                continue;
            }

            if ($type === 'image_url') {
                $url = $part['image_url']['url'] ?? '';

                // Anthropic accepts base64 blocks and (newer) plain URLs. A
                // data: URI has to be split into media type and payload.
                if (preg_match('#^data:([^;]+);base64,(.*)$#s', (string) $url, $m)) {
                    $blocks[] = [
                        'type'   => 'image',
                        'source' => ['type' => 'base64', 'media_type' => $m[1], 'data' => $m[2]],
                    ];
                } elseif ($url !== '') {
                    $blocks[] = ['type' => 'image', 'source' => ['type' => 'url', 'url' => $url]];
                }
            }
        }

        return $blocks ?: '';
    }

    /** OpenAI's {type:function, function:{name, description, parameters}} → Anthropic's flat shape. */
    protected function translateTools(array $tools): array
    {
        $out = [];
        foreach ($tools as $t) {
            $fn = $t['function'] ?? $t;
            $out[] = array_filter([
                'name'         => $fn['name'] ?? null,
                'description'  => $fn['description'] ?? null,
                'input_schema' => $fn['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
            ], fn ($v) => $v !== null);
        }

        return $out;
    }

    /** Anthropic's response → the normalised shape every caller expects. */
    protected function normalise(array $json): array
    {
        $text      = '';
        $toolCalls = [];

        foreach ($json['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = [
                    'id'       => $block['id'] ?? null,
                    'type'     => 'function',
                    'function' => [
                        'name'      => $block['name'] ?? '',
                        // Callers json_decode this, so it has to be a string
                        // even though Anthropic already parsed it.
                        'arguments' => json_encode($block['input'] ?? new \stdClass()),
                    ],
                ];
            }
        }

        return [
            'content'    => $text === '' ? null : $text,
            'tool_calls' => $toolCalls,
            'tokens_in'  => (int) ($json['usage']['input_tokens'] ?? 0),
            'tokens_out' => (int) ($json['usage']['output_tokens'] ?? 0),
            'raw'        => $json,
        ];
    }

    protected function pending(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withHeaders([
            'x-api-key'         => $this->key(),
            'anthropic-version' => static::API_VERSION,
        ]);
    }

    protected function request(string $path, array $payload): array
    {
        $res = $this->pending()
            ->acceptJson()
            ->timeout(60)
            ->retry(2, 250, function ($e) {
                return $e instanceof \Illuminate\Http\Client\ConnectionException;
            }, throw: false)
            ->post(static::BASE_URL . $path, $payload);

        $this->throwIfFailed($res, $path);

        return $res->json() ?? [];
    }

    protected function throwIfFailed($res, string $path): void
    {
        if (!$res->failed()) {
            return;
        }

        $msg = (string) Str::of($res->body())->limit(300);
        Log::warning("Claude API {$path} failed: HTTP {$res->status()} {$msg}");

        throw new AiProviderException(
            "Claude API request failed (HTTP {$res->status()}).",
            $res->status(),
            $this->slug(),
        );
    }
}
