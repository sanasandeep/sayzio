<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for AI image generation used by Brand Kit visual assets
 * (Task #5612). Kept as its own injectable class so feature tests can swap
 * it out in the container (no network) while the charging/refund/storage
 * logic in {@see \App\Services\Brand\BrandKitAssetService} stays real.
 *
 * Uses the OpenAI Images API (gpt-image-1) with the same admin-stored key
 * as every other OpenAI feature ({@see AiEngineSettings::openAiKey()}).
 */
class BrandAssetImageClient
{
    public const MODEL = 'gpt-image-1';

    /** Feature is usable only when the engine is on and a key is stored. */
    public function enabled(): bool
    {
        return AiEngineSettings::isEnabled() && AiEngineSettings::openAiKey() !== null;
    }

    /**
     * Render one PNG for the prompt at the given size and return the raw
     * bytes. Throws a RuntimeException with a user-safe message on failure
     * (the caller refunds the coin charge).
     */
    public function generate(string $prompt, string $size = '1024x1024'): string
    {
        $key = AiEngineSettings::openAiKey();
        if ($key === null) {
            throw new RuntimeException('AI image generation is not configured.');
        }

        $res = Http::withToken($key)
            ->acceptJson()
            ->timeout(180)
            ->post('https://api.openai.com/v1/images/generations', [
                'model'  => self::MODEL,
                'prompt' => $prompt,
                'size'   => $size,
                'n'      => 1,
            ]);

        if (!$res->successful()) {
            $msg = (string) ($res->json('error.message') ?? '');
            throw new RuntimeException(
                'The image engine could not render this asset'
                . ($msg !== '' ? ': ' . mb_substr($msg, 0, 200) : '.')
            );
        }

        $b64 = (string) ($res->json('data.0.b64_json') ?? '');
        if ($b64 !== '') {
            $bytes = base64_decode($b64, true);
            if ($bytes !== false && $bytes !== '') {
                return $bytes;
            }
        }

        // Some responses return a URL instead of inline base64.
        $url = (string) ($res->json('data.0.url') ?? '');
        if ($url !== '') {
            $img = Http::timeout(120)->get($url);
            if ($img->successful() && $img->body() !== '') {
                return $img->body();
            }
        }

        throw new RuntimeException('The image engine returned an empty result.');
    }
}
