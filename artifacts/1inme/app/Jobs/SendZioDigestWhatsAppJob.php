<?php

namespace App\Jobs;

use App\Modules\Common\Models\ZioDigest;
use App\Modules\Common\Models\ZioDigestRecipient;
use App\Services\WhatsApp\WhatsAppCloudApi;
use App\Services\ZioDigest\ZioDigestAudience;
use App\Services\ZioDigest\ZioDigestRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deliver a Zio Digest summary over WhatsApp (existing Cloud API service)
 * to every queued whatsapp recipient row. Recipients without a usable
 * phone are marked skipped. Same atomic claim pattern as the email job.
 */
class SendZioDigestWhatsAppJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public int $digestId) {}

    public function handle(): void
    {
        $claimed = DB::table('zio_digests')
            ->where('id', $this->digestId)
            ->where('wa_status', 'queued')
            ->update(['wa_status' => 'sending', 'updated_at' => now()]);
        if ($claimed === 0) return;

        /** @var ZioDigest|null $digest */
        $digest = ZioDigest::find($this->digestId);
        if (!$digest) return;

        $api      = new WhatsAppCloudApi();
        $renderer = new ZioDigestRenderer();
        $message  = $renderer->whatsappMessage($digest);

        $sent = 0;
        $failed = 0;
        $skipped = 0;

        try {
            ZioDigestRecipient::query()
                ->where('digest_id', $digest->id)
                ->where('channel', 'whatsapp')
                ->where('status', 'queued')
                ->with('user')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($digest, $api, $message, &$sent, &$failed, &$skipped) {
                    foreach ($rows as $recipient) {
                        $user  = $recipient->user;
                        $phone = $user ? ZioDigestAudience::phoneFor($user) : null;
                        if ($phone === null) {
                            $skipped++;
                            $recipient->update(['status' => 'skipped', 'error' => 'No usable phone number.']);
                            continue;
                        }
                        if ($api->sendText($phone, $message)) {
                            $sent++;
                            $recipient->update(['status' => 'sent', 'error' => null]);
                        } else {
                            $failed++;
                            $recipient->update(['status' => 'failed', 'error' => 'WhatsApp send failed (see server log; preview mode when credentials are absent).']);
                        }
                    }
                    $digest->forceFill([
                        'wa_sent_count'    => $sent,
                        'wa_failed_count'  => $failed,
                        'wa_skipped_count' => $digest->wa_skipped_count + 0, // updated below
                    ])->save();
                });

            $digest->forceFill([
                'wa_status'        => 'sent',
                'wa_sent_count'    => $sent,
                'wa_failed_count'  => $failed,
                'wa_skipped_count' => ZioDigestRecipient::where('digest_id', $digest->id)->where('channel', 'whatsapp')->where('status', 'skipped')->count(),
                'wa_sent_at'       => now(),
            ])->save();
        } catch (\Throwable $e) {
            $digest->forceFill([
                'wa_status'       => 'failed',
                'wa_sent_count'   => $sent,
                'wa_failed_count' => $failed,
                'wa_sent_at'      => now(),
            ])->save();
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        try {
            ZioDigest::where('id', $this->digestId)
                ->whereIn('wa_status', ['queued', 'sending'])
                ->update(['wa_status' => 'failed', 'updated_at' => now()]);
        } catch (\Throwable $ignore) {
        }
        Log::error("SendZioDigestWhatsAppJob failed for digest #{$this->digestId}: " . $e->getMessage());
    }
}
