<?php

namespace App\Modules\Common\Services;

use App\Mail\PrivacyRequestMail;
use App\Modules\Common\Models\PrivacyRequest;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Centralises every outbound message a privacy data request can produce:
 *
 *  - the requester is emailed at each state change, and
 *  - admins get an in-app notification when a new request is verified and
 *    ready for review.
 *
 * Every send is best-effort — a mail/notification failure must never block
 * the request lifecycle (verification, approval, fulfillment).
 */
class PrivacyRequestNotifier
{
    /** Permission whose holders staff the privacy queue + get alerted. */
    public const QUEUE_PERMISSION = 'users.delete';

    public const NOTIFY_TYPE = 'privacy_request';

    /** Email the verification link to an anonymous submitter. */
    public function sendVerification(PrivacyRequest $pr, string $verifyUrl): void
    {
        $this->mail($pr, 'verify', $verifyUrl);
    }

    public function notifyReceived(PrivacyRequest $pr): void
    {
        $this->mail($pr, 'received');
    }

    /** Confirmation to the requester + in-app alert to the admin queue. */
    public function notifyVerified(PrivacyRequest $pr): void
    {
        $this->mail($pr, 'verified');
        $this->alertAdmins($pr);
    }

    public function notifyApproved(PrivacyRequest $pr): void
    {
        $this->mail($pr, 'approved');
    }

    public function notifyRejected(PrivacyRequest $pr): void
    {
        $this->mail($pr, 'rejected');
    }

    public function notifyDeletionCompleted(PrivacyRequest $pr): void
    {
        $this->mail($pr, 'completed');
    }

    public function notifyExportReady(PrivacyRequest $pr, string $downloadUrl): void
    {
        $this->mail($pr, 'ready', $downloadUrl);
    }

    /** Fan an in-app notification out to every staffer who can action the queue. */
    public function alertAdmins(PrivacyRequest $pr): void
    {
        try {
            $url = $this->queueUrl($pr);
            $subject = 'New privacy ' . ($pr->isDeletion() ? 'deletion' : 'export') . ' request';
            $body = $pr->email . ' requested ' . strtolower($pr->typeLabel())
                . ($pr->user_id ? '' : ' (no matching account found)') . '.';

            $admins = User::query()->withPermission(self::QUEUE_PERMISSION)->get();
            foreach ($admins as $admin) {
                try {
                    UserNotification::create([
                        'user_id'    => $admin->id,
                        'type'       => self::NOTIFY_TYPE,
                        'data'       => [
                            'subject'    => $subject,
                            'body'       => $body,
                            'message'    => $body,
                            'url'        => $url,
                            'target_url' => $url,
                            'request_id' => $pr->id,
                        ],
                        'created_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Privacy-request admin alert failed for user ' . $admin->id . ': ' . $e->getMessage());
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Privacy-request admin fan-out failed: ' . $e->getMessage());
        }
    }

    protected function queueUrl(PrivacyRequest $pr): string
    {
        try {
            return route('admin.privacy-requests.show', ['privacyRequest' => $pr->id]);
        } catch (\Throwable $e) {
            return url('/admin/privacy-requests');
        }
    }

    protected function mail(PrivacyRequest $pr, string $stage, ?string $actionUrl = null): void
    {
        $email = trim((string) $pr->email);
        if ($email === '') {
            return;
        }
        try {
            \App\Modules\Common\Services\Emailer::sendMailable('privacy.request', $email, new PrivacyRequestMail($pr, $stage, $actionUrl), ['stage' => $stage], ['related' => $pr]);
        } catch (\Throwable $e) {
            Log::warning("Privacy-request '{$stage}' email to {$email} failed: " . $e->getMessage());
        }
    }
}
