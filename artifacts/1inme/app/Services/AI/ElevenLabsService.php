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

        $cost = (int) ceil($chars * AiEngineSettings::voiceTtsCoinsPer1kChars() / 1000);
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
