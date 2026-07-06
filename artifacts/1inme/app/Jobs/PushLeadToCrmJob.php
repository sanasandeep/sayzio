<?php

namespace App\Jobs;

use App\Modules\User\Services\ConnectedApps\CrmSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fans a captured lead/contact/subscriber/form submission out to every
 * active CRM connection for the owning user, off the request hot path.
 *
 * Callers should cheap-check {@see self::shouldQueue()} first so we never
 * queue a job for users with no CRM connected at all.
 */
class PushLeadToCrmJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    /**
     * @param array<string,mixed> $lead normalized lead payload (used directly
     *   for subscriber/form captures). When $contactId is given the lead is
     *   built lazily from the fresh Contact + its emails/phones in handle().
     */
    public function __construct(private int $userId, private array $lead = [], private ?int $contactId = null)
    {
        // Defer dispatch until the surrounding DB transaction commits so a
        // contact-based push always reloads a fully-persisted record (its
        // emails/phones are written after the Contact row in every capture
        // path). Set via the trait's own setter rather than redeclaring the
        // $afterCommit property here: {@see \Illuminate\Bus\Queueable}
        // declares it with NO default value, and re-declaring it with a
        // different default (`= true`) is a fatal "incompatible property
        // definition" error under PHP 8.3+'s stricter trait-property
        // composition checks — that crashed every Contact/Subscriber/form
        // capture path dispatching this job (pre-existing bug, not
        // introduced by the Leads feature, but it blocked Leads' own
        // approve-into-Contact flow).
        $this->afterCommit();
    }

    public function handle(CrmSyncService $sync): void
    {
        $lead = $this->lead;
        if ($this->contactId !== null) {
            $contact = \App\Modules\User\Models\Contact::with(['emails', 'phones'])->find($this->contactId);
            if (!$contact || (int) $contact->user_id !== $this->userId) {
                return;
            }
            $lead = $contact->toCrmLead();
        }
        if (empty($lead['email']) && empty($lead['phone'])) {
            return; // nothing addressable to sync
        }
        $sync->pushLead($this->userId, $lead);
    }

    /** True when the user has at least one active push-enabled CRM. */
    public static function shouldQueue(int $userId): bool
    {
        // Hard-stop when the plan no longer includes Connected Apps, so a
        // creator who connected while entitled stops syncing after downgrade.
        if (!optional(\App\Modules\User\Models\User::find($userId))->getPlanFeature('connected_apps', false)) {
            return false;
        }

        return \App\Modules\User\Models\ConnectedApp::forUser($userId)
            ->crm()
            ->where('push_enabled', true)
            ->where('status', \App\Modules\User\Models\ConnectedApp::STATUS_CONNECTED)
            ->exists();
    }

    /**
     * Cheap-check then dispatch. No-op when the user has no CRM connected,
     * so callers can fire this on every capture without extra guards.
     *
     * @param array<string,mixed> $lead
     */
    public static function forUser(int $userId, array $lead): void
    {
        if (self::shouldQueue($userId)) {
            self::dispatch($userId, $lead);
        }
    }

    /**
     * Cheap-check then dispatch a push for a captured Contact. The lead is
     * built from the fresh record when the job runs (after commit), so this
     * is safe to fire from the Contact `created` hook before its emails and
     * phones are attached. No-op when the user has no CRM connected.
     */
    public static function forContact(int $userId, int $contactId): void
    {
        if (self::shouldQueue($userId)) {
            self::dispatch($userId, [], $contactId);
        }
    }
}
