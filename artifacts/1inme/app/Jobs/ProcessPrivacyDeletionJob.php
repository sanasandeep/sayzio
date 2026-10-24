<?php

namespace App\Jobs;

use App\Modules\Admin\Models\ProtectedAccount;
use App\Modules\Common\Models\PrivacyRequest;
use App\Modules\Common\Services\PrivacyRequestNotifier;
use App\Modules\User\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fulfil an approved account-deletion privacy request. Runs on the queue
 * (so it works even when no one is browsing) after the cooling-off window
 * has elapsed. Re-checks the protected-accounts guard at run time — a
 * defense-in-depth re-check, not just at approval — and aborts+flags if
 * the account became protected in the meantime.
 *
 * Erasure reuses the canonical {@see User::delete()} path (the same one
 * the admin user-management "delete" button uses), which fires the User
 * `deleting` audit hook and lets the DB cascade-delete every child row
 * keyed to the account.
 */
class ProcessPrivacyDeletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public int $requestId) {}

    public function handle(PrivacyRequestNotifier $notifier): void
    {
        /** @var PrivacyRequest|null $pr */
        $pr = PrivacyRequest::find($this->requestId);
        if (!$pr || !$pr->isDeletion()) {
            return;
        }
        // Only act on approved/processing rows; ignore already-finished or
        // rejected ones so a re-dispatch (scheduler safety net) is a no-op.
        if (!in_array($pr->status, [PrivacyRequest::STATUS_APPROVED, PrivacyRequest::STATUS_PROCESSING], true)) {
            return;
        }

        // A queued message must not bypass the approved cooling-off window.
        if (!$pr->scheduled_at || $pr->scheduled_at->isFuture()) return;
        if (!PrivacyRequest::whereKey($pr->id)->where('status',PrivacyRequest::STATUS_APPROVED)->update(['status'=>PrivacyRequest::STATUS_PROCESSING])) return;
        $pr->status=PrivacyRequest::STATUS_PROCESSING;

        $user = $pr->user_id ? User::find($pr->user_id) : PrivacyRequest::matchUser($pr->email);

        // Nothing left to erase — close the request as completed.
        if (!$user) {
            $pr->forceFill([
                'status'       => PrivacyRequest::STATUS_COMPLETED,
                'completed_at' => now(),
                'user_id'      => null,
            ])->save();
            $pr->recordAudit('completed', 'system', 'No matching account found at fulfillment time.');
            return;
        }

        // Defense in depth: a protected account can NEVER be deleted.
        if (ProtectedAccount::isProtected($user)) {
            $pr->forceFill([
                'status'           => PrivacyRequest::STATUS_BLOCKED,
                'rejection_reason' => 'Account is protected and cannot be deleted.',
            ])->save();
            $pr->recordAudit('blocked', 'system', 'Protected account — deletion auto-blocked.');
            $notifier->notifyRejected($pr);
            return;
        }

        if ($pr->type === PrivacyRequest::TYPE_DATA_DELETION) {
            // Selected personal data only; team workspace records and billing stay intact.
            $personal = $user->ensureDefaultWorkspace();
            foreach ((array)$pr->data_scopes as $scope) {
                $model = match($scope) {
                    'links' => \App\Modules\User\Models\Link::class,
                    'contacts' => \App\Modules\User\Models\Contact::class,
                    'notifications' => \App\Modules\User\Models\UserNotification::class,
                    default => null,
                };
                if (!$model) continue;
                $query=$model::withoutGlobalScopes()->where('user_id',$user->id);
                if ($scope!=='notifications') $query->where('workspace_id',$personal->id);
                $query->eachById(fn($row)=>$scope==='notifications' ? $row->forceDelete() : $row->delete());
            }
            $pr->forceFill(['status'=>PrivacyRequest::STATUS_COMPLETED,'completed_at'=>now()])->save();
            $pr->recordAudit('completed','system','Selected personal data removed; account retained.');
            $notifier->notifyDeletionCompleted($pr);
            return;
        }

        $email = $user->email;

        try {
            \App\Modules\User\Models\UserFile::withoutGlobalScopes()->where('user_id',$user->id)->eachById(fn($file)=>$file->deleteFile());
            DB::transaction(function () use ($user) {
                // The DB cascades child rows (links, files, wallet, invoices,
                // notifications, …) on this delete; the User `deleting` hook
                // snapshots the role-detach audit before the row disappears.
                $user->delete();
            });
        } catch (\Throwable $e) {
            $pr->forceFill([
                'status'         => PrivacyRequest::STATUS_FAILED,
                'failure_reason' => Str::limit($e->getMessage(), 500),
            ])->save();
            $pr->recordAudit('failed', 'system', 'Deletion error: ' . Str::limit($e->getMessage(), 200));
            throw $e;
        }

        // The FK nulls privacy_requests.user_id on the user delete; mirror
        // that in memory so saving the row doesn't re-point at a dead id.
        $pr->forceFill([
            'status'       => PrivacyRequest::STATUS_COMPLETED,
            'completed_at' => now(),
            'user_id'      => null,
        ])->save();
        $pr->recordAudit('completed', 'system', 'Account and personal data permanently removed.');

        $notifier->notifyDeletionCompleted($pr);
    }

    public function failed(\Throwable $e): void
    {
        $pr = PrivacyRequest::find($this->requestId);
        if ($pr && $pr->status === PrivacyRequest::STATUS_PROCESSING) {
            $pr->forceFill([
                'status'         => PrivacyRequest::STATUS_FAILED,
                'failure_reason' => Str::limit($e->getMessage(), 500),
            ])->save();
        }
    }
}
