<?php

namespace App\Services\AI;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\WalletTransaction;
use App\Support\PlanLimit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * Unmetered "Test connection" probe for the Replicate token. Hits the
     * cheap, authenticated GET /v1/account endpoint to confirm the token is
     * valid without queuing a prediction or spending anything. Prefers the
     * token passed in (just typed into the admin form); falls back to the
     * stored/env token. Returns an ok/fail array for inline JSON rendering.
     *
     * @return array{ok:bool,message:string,status?:int,account?:string}
     */
    public function testToken(?string $token = null): array
    {
        $token = ($token !== null && trim($token) !== '') ? trim($token) : AiEngineSettings::replicateKey();
        if (!$token) {
            return ['ok' => false, 'message' => 'No Replicate token is configured. Paste a token above (or save one) and try again.'];
        }

        try {
            $res = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->get('https://api.replicate.com/v1/account');
        } catch (\Throwable $e) {
            Log::warning('Replicate test connection threw: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Network error reaching Replicate: ' . Str::limit($e->getMessage(), 160)];
        }

        if ($res->successful()) {
            $account = (string) ($res->json('username') ?: $res->json('name') ?: '');
            $message = $account !== ''
                ? "Success — Replicate accepted the token (account \"{$account}\")."
                : 'Success — Replicate accepted the token.';
            return ['ok' => true, 'account' => $account, 'message' => $message];
        }

        $status = $res->status();
        $apiMsg = (string) ($res->json('detail') ?? '');
        $message = match (true) {
            $status === 401 => 'Invalid token — Replicate rejected it (401 Unauthorized).',
            $status === 403 => 'Token lacks access (403). Check the account permissions.',
            $status === 429 => 'Rate limited (429). The token works but is temporarily throttled.',
            default => "Replicate returned HTTP {$status}" . ($apiMsg !== '' ? ': ' . Str::limit($apiMsg, 160) : '.'),
        };

        return ['ok' => false, 'status' => $status, 'message' => $message];
    }

    // ---------------- Monthly plan allowance (max_qr_art_monthly) ----------------

    /**
     * Normalized monthly allowance for $user: -1 = unlimited (also for
     * plan-limits-bypass holders, whose getPlanFeature returns PHP_INT_MAX),
     * otherwise the finite per-plan cap. Never leaks the bypass sentinel.
     */
    public function monthlyAllowance(User $user): int
    {
        return PlanLimit::normalize((int) $user->getPlanFeature('max_qr_art_monthly', -1));
    }

    /**
     * Successful generations counted against the current billing month
     * (calendar month, matching the other monthly meters). A generation is
     * a `spend` wallet transaction attributed to the qr_art feature; spends
     * that were refunded (failed generation / storage) are excluded so
     * failures never consume allowance.
     */
    public function monthlyUsed(User $user): int
    {
        return WalletTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'spend')
            ->where('meta->feature', 'qr_art')
            ->where('created_at', '>=', now()->startOfMonth())
            ->whereNotExists(function ($q) use ($user) {
                $q->selectRaw('1')
                    ->from('wallet_transactions as r')
                    ->where('r.user_id', $user->id)
                    ->where('r.type', 'refund')
                    ->whereRaw("(r.meta->>'related_id') = wallet_transactions.id::text");
            })
            ->count();
    }

    /** Remaining generations this month: -1 = unlimited, never negative. */
    public function monthlyRemaining(User $user): int
    {
        $allowance = $this->monthlyAllowance($user);
        if ($allowance < 0) {
            return -1;
        }
        return max(0, $allowance - $this->monthlyUsed($user));
    }

    /**
     * Throw when the user's monthly allowance is used up. Called at the top
     * of generate() so the check always runs BEFORE the coin charge.
     */
    protected function assertMonthlyAllowance(User $user): void
    {
        $allowance = $this->monthlyAllowance($user);
        if ($allowance < 0) {
            return; // unlimited (-1 or bypass permission)
        }
        $used = $this->monthlyUsed($user);
        if ($used < $allowance) {
            return;
        }
        $msg = "You've used all {$allowance} AI QR art generation" . ($allowance === 1 ? '' : 's')
            . " included in your plan this month. Your allowance resets next month.";
        if ($plan = $user->planThatUnlocks('max_qr_art_monthly', $used)) {
            $msg .= " Upgrade to the {$plan->name} plan for more.";
        }
        throw new QrArtAllowanceExceededException($msg, $allowance, $used);
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

        // Monthly plan allowance — must reject BEFORE any coin charge.
        $this->assertMonthlyAllowance($user);

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
            'qr_conditioning_scale' => $this->conditioningScale($opts['strength'] ?? null),
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

    /**
     * Map the user-facing "artistic strength" (0 = most reliable, 100 = most
     * artistic) onto the model's `qr_conditioning_scale`. A higher conditioning
     * scale enforces the QR pattern more strongly (more scannable, less
     * artistic), so strength and scale move in opposite directions. The default
     * (strength 60 → ~1.46) keeps parity with the previous fixed 1.5 value.
     */
    protected function conditioningScale(?int $strength): float
    {
        $s = max(0, min(100, (int) ($strength ?? 60)));
        // strength 0 → 2.0 (most faithful), strength 100 → 1.1 (most artistic)
        return round(2.0 - ($s / 100) * 0.9, 2);
    }

    protected function errorFrom($json, string $fallback): string
    {
        $err = is_array($json) ? ($json['error'] ?? ($json['detail'] ?? null)) : null;
        return is_string($err) && $err !== '' ? $err : $fallback;
    }
}
