<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Speech-to-text via OpenAI Whisper. Sibling of {@see OpenAiService} —
 * shares the encrypted key store + coin wallet so every voice-stage
 * spend lands in the same audit trail.
 *
 * Spend is metered per-minute (rounded up) using the admin-configured
 * `voice.price.stt_coins_per_minute` (fractional coins). Each charge is
 * tagged `feature => voice_stt` and the model used is recorded.
 */
class WhisperService
{
    public const BASE_URL = 'https://api.openai.com/v1';

    public function __construct(protected AiUsageCharger $credits) {}

    /**
     * Transcribe an audio file/string and charge credits.
     *
     * @param  UploadedFile|string  $audio  Uploaded file *or* raw bytes.
     * @return array{text:string, duration_seconds:float, credits_spent:int, model:string}
     */
    public function transcribe(User $user, $audio, array $opts = []): array
    {
        $this->guard();
        $model = AiEngineSettings::whisperModel();

        // Whisper is an OpenAI service, so it bills against the user's
        // OpenAI plan multiplier (scales the per-minute base rate).
        $multiplier = AiPlanAccess::coinMultiplier($user, 'openai');

        // Worst-case prepay: if we have an UploadedFile we know its size
        // and can roughly estimate duration; otherwise assume 60s.
        $estSec = $this->estimateDurationSeconds($audio);
        $estMin = max(1, (int) ceil($estSec / 60));
        $worstCase = (int) ceil($estMin * AiEngineSettings::voiceSttCoinsPerMinute() * $multiplier);
        $noCharge = !empty($opts['no_charge']);
        if (!$noCharge && $worstCase > 0) $this->ensureCanAfford($user, $worstCase);

        $key = AiEngineSettings::whisperKey();
        if (!$key) {
            throw new \RuntimeException('Whisper API key is not configured.');
        }

        $res = Http::withToken($key)
            ->timeout(120)
            ->attach('file', $this->audioContents($audio), $this->audioName($audio))
            ->post(self::BASE_URL . '/audio/transcriptions', [
                'model'           => $model,
                'response_format' => 'verbose_json',
            ] + (!empty($opts['language']) ? ['language' => (string) $opts['language']] : []));

        if ($res->failed()) {
            $msg = (string) Str::of($res->body())->limit(300);
            Log::warning("Whisper failed: HTTP {$res->status()} {$msg}");
            throw new \RuntimeException("Whisper request failed (HTTP {$res->status()}).");
        }

        $body     = $res->json() ?? [];
        $text     = (string) ($body['text'] ?? '');
        $duration = (float) ($body['duration'] ?? $estSec);

        $minutes = max(1, (int) ceil($duration / 60));
        $cost    = (int) ceil($minutes * AiEngineSettings::voiceSttCoinsPerMinute() * $multiplier);

        $tx = ($cost > 0 && !$noCharge)
            ? $this->credits->charge($user, $cost, [
                'feature' => 'voice_stt',
                'related_id' => $opts['related_id'] ?? null,
                'model'   => $model,
                'provider'   => 'openai',
                'multiplier' => $multiplier,
                'reason'  => "Voice STT ({$model}, {$minutes} min)",
                'meta'    => array_merge(
                    is_array($opts['meta'] ?? null) ? $opts['meta'] : [],
                    ['duration_seconds' => $duration],
                ),
            ])
            : null;

        return [
            'text'             => $text,
            'duration_seconds' => $duration,
            'credits_spent'    => $tx ? (int) abs($tx->delta_coins) : 0,
            'model'            => $model,
        ];
    }

    /**
     * Lightweight, UNMETERED connectivity check for the admin AI Engine
     * page. Lists OpenAI models with the supplied Whisper key (or the
     * stored Whisper key when null — which itself falls back to the main
     * OpenAI key, exactly like the transcribe runtime). It never touches
     * the coin wallet and never requires the AI Engine to be enabled, so
     * an admin can validate the key before relying on the Voice Assistant.
     *
     * @return array{ok:bool,message:string,status?:int}
     */
    public function testKey(?string $key = null): array
    {
        $key = ($key !== null && trim($key) !== '') ? trim($key) : AiEngineSettings::whisperKey();
        if (!$key) {
            return ['ok' => false, 'message' => 'No Whisper key is configured, and no main OpenAI key to fall back on. Paste a key above (or save one) and try again.'];
        }

        try {
            $res = Http::withToken($key)
                ->acceptJson()
                ->timeout(20)
                ->get(self::BASE_URL . '/models');
        } catch (\Throwable $e) {
            Log::warning('Whisper test connection threw: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Network error reaching OpenAI: ' . Str::limit($e->getMessage(), 160)];
        }

        if ($res->successful()) {
            return ['ok' => true, 'message' => 'Success — OpenAI accepted the Whisper key.'];
        }

        $status = $res->status();
        $apiMsg = (string) ($res->json('error.message') ?? '');
        $message = match (true) {
            $status === 401 => 'Invalid Whisper key — OpenAI rejected it (401 Unauthorized).',
            $status === 403 => 'Key lacks access (403). Check the project/org permissions.',
            $status === 429 => 'Rate limited or quota exhausted (429). The key works but is out of credit/limit.',
            default => "OpenAI returned HTTP {$status}" . ($apiMsg !== '' ? ': ' . Str::limit($apiMsg, 160) : '.'),
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

    protected function audioContents($audio): string
    {
        if ($audio instanceof UploadedFile) {
            return file_get_contents($audio->getRealPath());
        }
        return (string) $audio;
    }

    protected function audioName($audio): string
    {
        if ($audio instanceof UploadedFile) {
            $name = $audio->getClientOriginalName() ?: 'audio.webm';
            return $name;
        }
        return 'audio.webm';
    }

    /** Cheap upper-bound estimate. Browsers ship Opus/WebM at ~16 kB/s. */
    protected function estimateDurationSeconds($audio): float
    {
        $bytes = 0;
        if ($audio instanceof UploadedFile) {
            $bytes = (int) $audio->getSize();
        } elseif (is_string($audio)) {
            $bytes = strlen($audio);
        }
        if ($bytes <= 0) return 60.0;
        return max(1.0, $bytes / 16000.0);
    }
}
