<?php

namespace App\Modules\User\Services\ConnectedApps;

use App\Modules\User\Models\ConnectedApp;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates two-way CRM sync:
 *   - pushLead(): fan a captured lead/contact/subscriber/form submission out
 *     to every active, push-enabled CRM connection for a user.
 *   - pull(): pull inbound contacts from one connection into Sayzio contacts,
 *     honouring the plan's contacts cap and never overwriting local edits.
 *
 * All provider errors are recorded on the connection (last_sync_status /
 * last_sync_error) and reported, never thrown to the caller — outbound pushes
 * run from a queued job off the request hot path.
 */
class CrmSyncService
{
    public function __construct(private ConnectedAppManager $manager)
    {
    }

    /** True when the user's plan still includes the Connected Apps feature. */
    private function userEntitled(int $userId): bool
    {
        return (bool) optional(User::find($userId))->getPlanFeature('connected_apps', false);
    }

    /**
     * @param array<string,mixed> $lead normalized lead:
     *   ['email','first_name','last_name','display_name','phone','company','source']
     */
    public function pushLead(int $userId, array $lead): void
    {
        if (!$this->userEntitled($userId)) {
            return; // plan no longer includes Connected Apps
        }
        $conns = ConnectedApp::forUser($userId)->crm()
            ->where('push_enabled', true)
            ->get()
            ->filter->isActive();

        foreach ($conns as $conn) {
            try {
                $connector = $this->manager->connector($conn->provider);
                if (!$connector->isConfigured()) {
                    continue;
                }
                $connector->pushContact($conn, $lead);
                $conn->forceFill([
                    'records_sent'     => $conn->records_sent + 1,
                    'last_synced_at'   => now(),
                    'last_sync_status' => 'ok',
                    'last_sync_error'  => null,
                    'status'           => ConnectedApp::STATUS_CONNECTED,
                ])->save();
            } catch (\Throwable $e) {
                $conn->forceFill([
                    'last_sync_status' => 'error',
                    'last_sync_error'  => Str::limit($e->getMessage(), 500),
                    'status'           => ConnectedApp::STATUS_ERROR,
                ])->save();
                report($e);
            }
        }
    }

    /** Pull inbound contacts for one CRM connection. Returns count imported. */
    public function pull(ConnectedApp $conn): int
    {
        if (!$conn->isCrm() || !$conn->pull_enabled || !$conn->isActive()) {
            return 0;
        }
        if (!$this->userEntitled($conn->user_id)) {
            return 0; // plan no longer includes Connected Apps
        }
        $connector = $this->manager->connector($conn->provider);
        if (!$connector->isConfigured()) {
            return 0;
        }

        $cap     = $this->contactsCap($conn->user_id);
        $created = 0;
        $updated = 0;

        try {
            foreach ($connector->pullContacts($conn, function ($cursor) use ($conn) {
                $conn->forceFill(['pull_cursor' => $cursor])->save();
            }) as $person) {
                $r = $this->upsertContact($conn, $person, $cap);
                if ($r === 'created') {
                    $created++;
                } elseif ($r === 'updated') {
                    $updated++;
                }
            }
            $conn->forceFill([
                'last_pull_at'     => now(),
                'last_synced_at'   => now(),
                'last_sync_status' => 'ok',
                'last_sync_error'  => null,
                'records_pulled'   => $conn->records_pulled + $created,
                'status'           => ConnectedApp::STATUS_CONNECTED,
            ])->save();
        } catch (\Throwable $e) {
            $conn->forceFill([
                'last_sync_status' => 'error',
                'last_sync_error'  => Str::limit($e->getMessage(), 500),
                'status'           => ConnectedApp::STATUS_ERROR,
            ])->save();
            report($e);
        }

        return $created + $updated;
    }

    /** @return 'created'|'updated'|'skipped'|'skipped_capped' */
    private function upsertContact(ConnectedApp $conn, array $p, int $cap): string
    {
        $email = $p['emails'][0]['value'] ?? null;
        $phone = $p['phones'][0]['value'] ?? null;
        if (!$email && !$phone && empty($p['display_name'])) {
            return 'skipped';
        }

        return DB::transaction(function () use ($conn, $p, $email, $cap) {
            $existing = null;
            if ($email) {
                $existing = Contact::where('user_id', $conn->user_id)
                    ->whereHas('emails', fn ($q) => $q->where('value', ContactEmail::normalize($email)))
                    ->first();
            }

            $isNew = !$existing;
            if ($isNew && $cap !== -1
                && Contact::where('user_id', $conn->user_id)->count() >= $cap) {
                return 'skipped_capped';
            }

            // Never clobber unsynced local edits.
            if ($existing && $existing->locally_modified_at
                && (!$existing->last_synced_at || $existing->locally_modified_at->gt($existing->last_synced_at))) {
                return 'skipped';
            }

            $contact = $existing ?: new Contact(['user_id' => $conn->user_id]);
            $contact->display_name = $p['display_name'] ?? $contact->display_name;
            $contact->given_name   = $p['given_name'] ?? $contact->given_name;
            $contact->family_name  = $p['family_name'] ?? $contact->family_name;
            $contact->organization = $p['organization'] ?? $contact->organization;
            $contact->job_title    = $p['job_title'] ?? $contact->job_title;

            $sources = collect($contact->sources ?? [])->push('crm:' . $conn->provider)->unique()->values()->all();
            $contact->sources = $sources;
            $contact->last_synced_at = now();
            $contact->save();

            foreach ($p['phones'] ?? [] as $ph) {
                if (empty($ph['value'])) {
                    continue;
                }
                $norm = ContactPhone::normalize($ph['value']);
                $has = $contact->phones()->where('value_e164', $norm)->orWhere('value', $ph['value'])->exists();
                if (!$has) {
                    $contact->phones()->create([
                        'label'      => $ph['label'] ?? 'work',
                        'value'      => $ph['value'],
                        'value_e164' => $norm,
                        'is_primary' => (bool) ($ph['primary'] ?? false),
                    ]);
                }
            }
            foreach ($p['emails'] ?? [] as $e) {
                if (empty($e['value'])) {
                    continue;
                }
                $norm = ContactEmail::normalize($e['value']);
                if (!$contact->emails()->where('value', $norm)->exists()) {
                    $contact->emails()->create([
                        'label'      => $e['label'] ?? 'work',
                        'value'      => $norm,
                        'is_primary' => (bool) ($e['primary'] ?? false),
                    ]);
                }
            }

            return $isNew ? 'created' : 'updated';
        });
    }

    private function contactsCap(int $userId): int
    {
        $user = User::find($userId);
        if (!$user) {
            return 0;
        }
        return (int) $user->getPlanFeature('contacts_max', 5000);
    }
}
