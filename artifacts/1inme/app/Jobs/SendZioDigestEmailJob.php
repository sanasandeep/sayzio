<?php

namespace App\Jobs;

use App\Modules\Common\Models\ZioDigest;
use App\Modules\Common\Models\ZioDigestRecipient;
use App\Services\Integrations\SendGridSettings;
use App\Services\ZioDigest\SendGridMailer;
use App\Services\ZioDigest\ZioDigestRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Deliver a Zio Digest by email (SendGrid v3 API) to every queued email
 * recipient row. Follows the SendNewsletterIssueJob claim pattern: the
 * digest's email_status is atomically flipped queued -> sending so a
 * duplicate dispatch can't double-send.
 */
class SendZioDigestEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public int $digestId) {}

    public function handle(): void
    {
        $claimed = DB::table('zio_digests')
            ->where('id', $this->digestId)
            ->where('email_status', 'queued')
            ->update(['email_status' => 'sending', 'updated_at' => now()]);
        if ($claimed === 0) return;

        /** @var ZioDigest|null $digest */
        $digest = ZioDigest::find($this->digestId);
        if (!$digest) return;

        // Fail fast (clear, not silent) when the key is missing: every queued
        // row is marked failed with an explicit error.
        if (!SendGridSettings::configured()) {
            $error = 'SendGrid API key is not configured.';
            ZioDigestRecipient::where('digest_id', $digest->id)
                ->where('channel', 'email')->where('status', 'queued')
                ->update(['status' => 'failed', 'error' => $error, 'updated_at' => now()]);
            $digest->forceFill([
                'email_status'       => 'failed',
                'email_failed_count' => ZioDigestRecipient::where('digest_id', $digest->id)->where('channel', 'email')->where('status', 'failed')->count(),
                'email_sent_at'      => now(),
            ])->save();
            Log::warning("Zio Digest #{$digest->id} email send aborted: {$error}");

            return;
        }

        $mailer   = new SendGridMailer();
        $renderer = new ZioDigestRenderer();
        $sent = 0;
        $failed = 0;

        try {
            ZioDigestRecipient::query()
                ->where('digest_id', $digest->id)
                ->where('channel', 'email')
                ->where('status', 'queued')
                ->with('user')
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($digest, $mailer, $renderer, &$sent, &$failed) {
                    foreach ($rows as $recipient) {
                        $user = $recipient->user;
                        if (!$user || empty($user->email) || $user->digest_email_opt_out) {
                            $recipient->update(['status' => 'skipped', 'error' => 'No email address or opted out.']);
                            continue;
                        }
                        $unsubUrl = ZioDigestRenderer::unsubscribeUrl($user->id, $digest->id);
                        $result = $mailer->send(
                            'digest.issue',
                            $user->email,
                            $user->name,
                            $digest->title,
                            $renderer->emailHtml($digest, $unsubUrl),
                            [
                                'List-Unsubscribe'      => '<' . $unsubUrl . '>',
                                'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                            ],
                            [
                                'user_id'      => $user->id,
                                'related_type' => $digest->getMorphClass(),
                                'related_id'   => $digest->id,
                            ],
                        );
                        if ($result['ok']) {
                            $sent++;
                            $recipient->update(['status' => 'sent', 'error' => null]);
                        } else {
                            $failed++;
                            $recipient->update(['status' => 'failed', 'error' => mb_substr((string) $result['error'], 0, 500)]);
                        }
                    }
                    // Persist progress so admins can watch counts move.
                    $digest->forceFill(['email_sent_count' => $sent, 'email_failed_count' => $failed])->save();
                });

            $digest->forceFill([
                'email_status'       => 'sent',
                'email_sent_count'   => $sent,
                'email_failed_count' => $failed,
                'email_sent_at'      => now(),
            ])->save();
        } catch (\Throwable $e) {
            $digest->forceFill([
                'email_status'       => 'failed',
                'email_sent_count'   => $sent,
                'email_failed_count' => $failed,
                'email_sent_at'      => now(),
            ])->save();
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        try {
            ZioDigest::where('id', $this->digestId)
                ->whereIn('email_status', ['queued', 'sending'])
                ->update(['email_status' => 'failed', 'updated_at' => now()]);
        } catch (\Throwable $ignore) {
        }
        Log::error("SendZioDigestEmailJob failed for digest #{$this->digestId}: " . $e->getMessage());
    }
}
