<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Text-to-speech via ElevenLabs. Returns the raw MP3 bytes plus a coin
 * wallet charge tagged `feature => voice_tts`. Spend is metered per
 * 1 000 characters using the admin-configured fractional coin rate.
 */
class ElevenLabsService
{
    public const BASE_URL = 'https://api.elevenlabs.io/v1';

    public function __construct(protected AiUsageCharger $credits) {}

    /**
     * Synthesize speech for the given text.
     *
     * @return array{audio:string, mime:string, characters:int, credits_spent:int, model:string, voice_id:string}
     */
    public function speak(User $user, string $text, array $opts = []): array
    {
        $this->guard();

        $key = AiEngineSettings::elevenLabsKey();
        if (!$key) {
            throw new \RuntimeException('ElevenLabs API key is not configured.');
        }

        $voiceId = $opts['voice_id'] ?? AiEngineSettings::elevenLabsVoiceId();
        $model   = $opts['model']    ?? AiEngineSettings::elevenLabsModel();
        $chars   = (int) mb_strlen($text);

        $multiplier = AiPlanAccess::coinMultiplier($user, 'elevenlabs');
        $cost = (int) ceil($chars * AiEngineSettings::voiceTtsCoinsPer1kChars() / 1000 * $multiplier);
        if ($cost > 0) $this->ensureCanAfford($user, $cost);

        $res = Http::withHeaders([
                'xi-api-key' => $key,
                'Accept'     => 'audio/mpeg',
            ])
            ->timeout(60)
            ->post(self::BASE_URL . "/text-to-speech/{$voiceId}", [
                'text'     => $text,
                'model_id' => $model,
            ]);

        if ($res->failed()) {
            $msg = (string) Str::of($res->body())->limit(300);
            Log::warning("ElevenLabs failed: HTTP {$res->status()} {$msg}");
            throw new \RuntimeException("ElevenLabs request failed (HTTP {$res->status()}).");
        }

        $tx = $cost > 0
            ? $this->credits->charge($user, $cost, [
                'feature'    => 'voice_tts',
                'related_id' => $opts['related_id'] ?? null,
                'model'      => $model,
                'provider'   => 'elevenlabs',
                'multiplier' => $multiplier,
                'reason'     => "Voice TTS ({$model}, {$chars} chars)",
                'meta'       => array_merge(
                    is_array($opts['meta'] ?? null) ? $opts['meta'] : [],
                    ['characters' => $chars, 'voice_id' => $voiceId],
                ),
            ])
            : null;

        return [
            'audio'         => $res->body(),
            'mime'          => 'audio/mpeg',
            'characters'    => $chars,
            'credits_spent' => $tx ? (int) abs($tx->delta_coins) : 0,
            'model'         => $model,
            'voice_id'      => $voiceId,
        ];
    }

    /**
     * Lightweight, UNMETERED connectivity check for the admin AI Engine
     * page. Hits the cheap GET /v1/voices endpoint with the supplied key
     * (or the stored key when null) purely to confirm the credential is
     * accepted. It never touches the coin wallet and never requires the
     * AI Engine to be enabled, so an admin can validate the key before
     * relying on the Voice Assistant's text-to-speech.
     *
     * @return array{ok:bool,message:string,status?:int}
     */
    public function testKey(?string $key = null): array
    {
        $key = ($key !== null && trim($key) !== '') ? trim($key) : AiEngineSettings::elevenLabsKey();
        if (!$key) {
            return ['ok' => false, 'message' => 'No ElevenLabs API key is configured. Paste a key above (or save one) and try again.'];
        }

        try {
            $res = Http::withHeaders([
                    'xi-api-key' => $key,
                    'Accept'     => 'application/json',
                ])
                ->timeout(20)
                ->get(self::BASE_URL . '/voices');
        } catch (\Throwable $e) {
            Log::warning('ElevenLabs test connection threw: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Network error reaching ElevenLabs: ' . Str::limit($e->getMessage(), 160)];
        }

        if ($res->successful()) {
            $count = is_array($res->json('voices')) ? count($res->json('voices')) : null;
            $suffix = $count !== null ? " ({$count} voices available)" : '';
            return ['ok' => true, 'message' => "Success — ElevenLabs accepted the key{$suffix}."];
        }

        $status = $res->status();
        $detail = $res->json('detail');
        $apiMsg = is_array($detail) ? (string) ($detail['message'] ?? '') : (string) ($detail ?: '');
        $message = match (true) {
            $status === 401 => 'Invalid API key — ElevenLabs rejected it (401 Unauthorized).',
            $status === 403 => 'Key lacks access (403). Check the ElevenLabs subscription/permissions.',
            $status === 429 => 'Rate limited or quota exhausted (429). The key works but is out of limit.',
            default => "ElevenLabs returned HTTP {$status}" . ($apiMsg !== '' ? ': ' . Str::limit($apiMsg, 160) : '.'),
        };

        return ['ok' => false, 'status' => $status, 'message' => $message];
    }

    protected function guard(): void
    {
        if (!AiEngineSettings::isEnabled()) {
            throw new \RuntimeException('AI Engine is disabled.');
        }
    }

    protected function ensureCanAfford(User $user, int $minCredits): void
    {
        $balance = $this->credits->getBalance($user);
        if ($balance < $minCredits) {
            throw new InsufficientCoinsForAiException($minCredits, $balance);
        }
    }
}
