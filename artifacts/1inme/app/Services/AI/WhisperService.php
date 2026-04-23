<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Speech-to-text via OpenAI Whisper. Sibling of {@see OpenAiService} —
 * shares the encrypted key store + AiCreditService ledger so every
 * voice-stage spend lands in the same audit trail.
 *
 * Spend is metered per-minute (rounded up) using the admin-configured
 * `voice.price.stt_credits_per_minute`. Each charge is tagged
 * `feature => voice_stt` and the model used is recorded.
 */
class WhisperService
{
    public const BASE_URL = 'https://api.openai.com/v1';

    public function __construct(protected AiCreditService $credits) {}

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

        // Worst-case prepay: if we have an UploadedFile we know its size
        // and can roughly estimate duration; otherwise assume 60s.
        $estSec = $this->estimateDurationSeconds($audio);
        $estMin = max(1, (int) ceil($estSec / 60));
        $worstCase = $estMin * AiEngineSettings::voiceSttCreditsPerMinute();
        if ($worstCase > 0) $this->ensureCanAfford($user, $worstCase);

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
        $cost    = $minutes * AiEngineSettings::voiceSttCreditsPerMinute();

        $tx = $cost > 0
            ? $this->credits->charge($user, $cost, [
                'feature' => 'voice_stt',
                'related_id' => $opts['related_id'] ?? null,
                'model'   => $model,
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
            'credits_spent'    => $tx ? (int) abs($tx->delta_credits) : 0,
            'model'            => $model,
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
            throw new InsufficientAiCreditsException($minCredits, $balance);
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
