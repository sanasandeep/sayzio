<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Support\Facades\Http;

/**
 * AI Artistic QR generator.
 *
 * Turns a destination (the already-encoded QR content) plus a style prompt
 * into a scannable QR woven into AI-generated artwork using Replicate's
 * QR-ControlNet model over HTTP. Billing mirrors every other AI feature:
 * a flat per-generation COIN charge (Replicate is not token-metered) taken
 * from the wallet via {@see AiUsageCharger}, with an automatic refund if
 * generation or storage fails. The per-generation coin price is
 * admin-configurable ({@see AiEngineSettings::qrArtCoinsPerGeneration()})
 * and scaled by the user's plan provider multiplier.
 */
class QrArtService
{
    public function __construct(protected AiUsageCharger $charger) {}

    /** Feature is usable only when a Replicate token is configured. */
    public function enabled(): bool
    {
        return AiEngineSettings::replicateKey() !== null;
    }

    /** Replicate model slug ("owner/name") to run. */
    public function model(): string
    {
        $m = config('services.replicate.qr_model');
        return is_string($m) && $m !== '' ? $m : 'zylim0702/qr_code_controlnet';
    }

    /** Effective coin cost for this user (admin rate × plan multiplier). */
    public function coinCost(User $user): int
    {
        $base = AiEngineSettings::qrArtCoinsPerGeneration();
        $mult = AiPlanAccess::coinMultiplier($user, 'replicate');
        return max(1, (int) ceil($base * $mult));
    }

    /**
     * Generate an artistic QR for $user. Charges coins up-front and refunds
     * automatically if generation or storage fails.
     *
     * @return array{image_url:string,file_id:int,cost:int,balance:int}
     */
    public function generate(User $user, string $data, string $prompt, array $opts = []): array
    {
        $token = AiEngineSettings::replicateKey();
        if ($token === null) {
            throw new QrArtUnavailableException('AI Artistic QR is not configured.');
        }

        $data   = trim($data);
        $prompt = trim($prompt);
        if ($data === '')   throw new \InvalidArgumentException('There is nothing to encode yet.');
        if ($prompt === '') throw new \InvalidArgumentException('Describe the artwork you want.');

        $cost = $this->coinCost($user);
        $tx = $this->charger->charge($user, $cost, [
            'feature'  => 'qr_art',
            'provider' => 'replicate',
            'reason'   => 'AI Artistic QR',
            'meta'     => ['model' => $this->model()],
        ]);

        try {
            $bytes = $this->callReplicate($token, $data, $prompt, $opts);
            $file = UserFile::createFromBytes(
                $bytes,
                'ai-qr-' . substr(md5($data . microtime()), 0, 8) . '.png',
                'image/png',
                $user,
                ['skip_scan' => true]
            );
        } catch (\Throwable $e) {
            // Refund the up-front charge; idempotency-keyed off the debit so
            // a retry can never double-credit.
            try {
                $this->charger->refund($user, $cost, [
                    'feature'         => 'qr_art',
                    'provider'        => 'replicate',
                    'reason'          => 'AI Artistic QR refund',
                    'idempotency_key' => 'qr_art_refund:' . $tx->id,
                    'meta'            => ['related_id' => $tx->id],
                ]);
            } catch (\Throwable $refundError) {
                // Swallow refund failures so the original cause still surfaces.
            }
            if ($e instanceof QrArtGenerationException) {
                throw $e;
            }
            throw new QrArtGenerationException('QR generation failed: ' . $e->getMessage(), 0, $e);
        }

        return [
            'image_url' => $file->url,
            'file_id'   => $file->id,
            'cost'      => $cost,
            'balance'   => $this->charger->getBalance($user),
        ];
    }

    /** POST the prediction, poll until it settles, return the image bytes. */
    protected function callReplicate(string $token, string $data, string $prompt, array $opts): string
    {
        $negative = trim((string) ($opts['negative_prompt'] ?? ''));
        if ($negative === '') {
            $negative = 'ugly, disfigured, low quality, blurry, watermark, text';
        }

        $input = [
            'url'                   => $data,
            'prompt'                => $prompt,
            'negative_prompt'       => $negative,
            'qr_conditioning_scale' => 1.5,
            'num_inference_steps'   => 40,
            'guidance_scale'        => 7.5,
            'batch_size'            => 1,
        ];

        $create = Http::withToken($token)
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.replicate.com/v1/models/' . $this->model() . '/predictions', [
                'input' => $input,
            ]);

        if ($create->failed()) {
            throw new QrArtGenerationException($this->errorFrom($create->json(), 'The image service rejected the request.'));
        }

        $payload = $create->json();
        $getUrl  = data_get($payload, 'urls.get');
        $status  = data_get($payload, 'status');
        $output  = data_get($payload, 'output');

        $deadline = microtime(true) + 90;
        while (in_array($status, ['starting', 'processing'], true)) {
            if (!$getUrl || microtime(true) > $deadline) {
                throw new QrArtGenerationException('QR generation timed out — please try again.');
            }
            usleep(1_500_000);
            $poll = Http::withToken($token)->acceptJson()->timeout(30)->get($getUrl);
            if ($poll->failed()) {
                throw new QrArtGenerationException('Lost contact with the image service.');
            }
            $status = data_get($poll->json(), 'status');
            $output = data_get($poll->json(), 'output');
            if (in_array($status, ['failed', 'canceled'], true)) {
                throw new QrArtGenerationException($this->errorFrom($poll->json(), 'The generator could not finish this image.'));
            }
        }

        $imageUrl = is_array($output) ? ($output[0] ?? null) : (is_string($output) ? $output : null);
        if (!is_string($imageUrl) || $imageUrl === '') {
            throw new QrArtGenerationException('The generator returned no image.');
        }

        $img = Http::timeout(30)->get($imageUrl);
        if ($img->failed() || $img->body() === '') {
            throw new QrArtGenerationException('Could not download the generated image.');
        }
        return $img->body();
    }

    protected function errorFrom($json, string $fallback): string
    {
        $err = is_array($json) ? ($json['error'] ?? ($json['detail'] ?? null)) : null;
        return is_string($err) && $err !== '' ? $err : $fallback;
    }
}
