<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactDeletionTombstone;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\GoogleContactsAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleContactsSyncService
{
    public function __construct(
        protected GoogleContactsProvider $provider,
        protected BiolinkAttachResolver $resolver,
    ) {}

    /** Per-user contacts cap from the active plan. -1 = unlimited. */
    protected function contactsCapFor(GoogleContactsAccount $account): int
    {
        $user = $account->user;
        $features = ($user && $user->plan && $user->plan->features) ? $user->plan->features : [];
        return (int) ($features['contacts_max'] ?? 5000);
    }

    public function syncAccount(GoogleContactsAccount $account): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'deleted' => 0, 'pushed' => 0, 'errors' => 0, 'skipped_capped' => 0];
        $account->update(['last_sync_status' => 'running', 'last_sync_error' => null]);

        try {
            // 1) Push local pending changes. Three buckets:
            //   a) Brand-new locally-created contacts (no google_resource_name)
            //   b) Locally-modified contacts whose locally_modified_at is newer
            //      than last_synced_at — i.e. updates the user has made since
            //      the last successful sync.
            //   c) Pending deletion tombstones — drained with retry counters.
            if ($account->push_enabled) {
                // a) creates
                $newLocal = Contact::where('user_id', $account->user_id)
                    ->where(function ($q) use ($account) {
                        $q->whereNull('google_contacts_account_id')
                          ->orWhere('google_contacts_account_id', $account->id);
                    })
                    ->whereNull('google_resource_name')
                    ->with(['phones', 'emails'])
                    ->limit(200)->get();
                foreach ($newLocal as $c) {
                    try {
                        $res = $this->provider->createPerson($account, $this->contactToPayload($c));
                        if (!empty($res['resource_name'])) {
                            $c->forceFill([
                                'google_contacts_account_id' => $account->id,
                                'google_resource_name'       => $res['resource_name'],
                                'google_etag'                => $res['etag'] ?? null,
                                'last_synced_at'             => now(),
                            ])->save();
                            $stats['pushed']++;
                        }
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::warning('Google contact push (create) failed', ['contact' => $c->id, 'err' => $e->getMessage()]);
                    }
                }

                // b) updates — locally-edited rows already linked to Google.
                $dirty = Contact::where('user_id', $account->user_id)
                    ->where('google_contacts_account_id', $account->id)
                    ->whereNotNull('google_resource_name')
                    ->whereNotNull('locally_modified_at')
                    ->where(function ($q) {
                        $q->whereNull('last_synced_at')
                          ->orWhereColumn('locally_modified_at', '>', 'last_synced_at');
                    })
                    ->with(['phones', 'emails'])
                    ->limit(200)->get();
                foreach ($dirty as $c) {
                    try {
                        $this->pushContact($account, $c);
                        $stats['pushed']++;
                    } catch (\Throwable $e) {
                        $stats['errors']++;
                        Log::warning('Google contact push (update) failed', ['contact' => $c->id, 'err' => $e->getMessage()]);
                    }
                }

                // c) deletions — drain tombstones with retry/backoff.
                $tombstones = ContactDeletionTombstone::where('google_contacts_account_id', $account->id)
                    ->where('attempts', '<', 5)
                    ->limit(200)->get();
                foreach ($tombstones as $t) {
                    try {
                        $this->provider->deletePerson($account, $t->google_resource_name);
                        $t->delete();
                        $stats['deleted']++;
                    } catch (\Throwable $e) {
                        $t->increment('attempts');
                        $t->update(['last_error' => \Illuminate\Support\Str::limit($e->getMessage(), 500)]);
                        $stats['errors']++;
                        Log::warning('Google contact deletion retry failed', ['tomb' => $t->id, 'err' => $e->getMessage()]);
                    }
                }
            }

            // 2) Pull (incremental if we have a sync token).
            if ($account->pull_enabled) {
                $newSyncToken = null;
                $cap = $this->contactsCapFor($account);
                try {
                    foreach ($this->provider->listPeople($account, function ($t) use (&$newSyncToken) { $newSyncToken = $t; }) as $p) {
                        try {
                            $r = $this->upsertFromGoogle($account, $p, $cap);
                            $stats[$r] = ($stats[$r] ?? 0) + 1;
                        } catch (\Throwable $e) {
                            $stats['errors']++;
                            Log::warning('Google contact upsert failed', ['rn' => $p['resource_name'] ?? null, 'err' => $e->getMessage()]);
                        }
                    }
                    if ($newSyncToken) {
                        $account->update(['sync_token' => $newSyncToken]);
                    }
                } catch (ContactsSyncException $e) {
                    if (str_contains($e->getMessage(), 'sync token expired')) {
                        // sync_token already cleared in provider; user can hit Sync now to re-pull
                        Log::info('Google contacts sync token expired; cleared.', ['account' => $account->id]);
                    } else {
                        throw $e;
                    }
                }
            }

            $account->update([
                'last_sync_status' => 'ok',
                'last_synced_at'   => now(),
                'last_sync_error'  => null,
            ]);
        } catch (\Throwable $e) {
            $account->update([
                'last_sync_status' => 'error',
                'last_sync_error'  => Str::limit($e->getMessage(), 500),
            ]);
            $stats['errors']++;
            Log::error('Contacts sync failed', ['account' => $account->id, 'err' => $e->getMessage()]);
        }

        return $stats;
    }

    /** Returns 'created' | 'updated' | 'deleted' | 'skipped' | 'skipped_capped'. */
    protected function upsertFromGoogle(GoogleContactsAccount $account, array $p, ?int $cap = null): string
    {
        $rn = $p['resource_name'] ?? null;
        if (!$rn) return 'skipped';
        $cap = $cap ?? $this->contactsCapFor($account);

        return DB::transaction(function () use ($account, $p, $rn, $cap) {
            $contact = Contact::where('user_id', $account->user_id)
                ->where('google_resource_name', $rn)->first();

            if (!empty($p['deleted'])) {
                if ($contact) { $contact->delete(); return 'deleted'; }
                return 'skipped';
            }

            $isNew = !$contact;
            // Plan-based cap: refuse to import additional contacts for users
            // whose plan does not allow it. Existing rows still get updates.
            if ($isNew && $cap !== -1
                && Contact::where('user_id', $account->user_id)->count() >= $cap) {
                return 'skipped_capped';
            }
            // Conflict guard: if the local row has unsynced edits
            // (locally_modified_at newer than last_synced_at), the user's
            // changes win until the next push completes — never let a pull
            // overwrite pending local edits even if a push attempt failed.
            if ($contact && $contact->locally_modified_at
                && (!$contact->last_synced_at || $contact->locally_modified_at->gt($contact->last_synced_at))) {
                return 'skipped';
            }
            if (!$contact) {
                $contact = new Contact([
                    'user_id'                    => $account->user_id,
                    'google_contacts_account_id' => $account->id,
                    'google_resource_name'       => $rn,
                ]);
            }
            $contact->display_name  = $p['display_name'];
            $contact->given_name    = $p['given_name'];
            $contact->family_name   = $p['family_name'];
            $contact->organization  = $p['organization'];
            $contact->job_title     = $p['job_title'];
            $contact->notes         = $p['notes'];
            $contact->photo_url     = $p['photo_url'];
            $contact->google_etag   = $p['etag'];
            $contact->last_synced_at = now();
            $contact->save();

            $contact->phones()->delete();
            foreach ($p['phones'] as $ph) {
                $contact->phones()->create([
                    'label'      => $ph['label'],
                    'value'      => $ph['value'],
                    'value_e164' => ContactPhone::normalize($ph['value']),
                    'is_primary' => $ph['primary'],
                ]);
            }
            $contact->emails()->delete();
            foreach ($p['emails'] as $e) {
                $contact->emails()->create([
                    'label'      => $e['label'],
                    'value'      => ContactEmail::normalize($e['value']),
                    'is_primary' => $e['primary'],
                ]);
            }

            $this->resolver->resolveFor($contact->fresh('phones'));
            return $isNew ? 'created' : 'updated';
        });
    }

    public function contactToPayload(Contact $contact): array
    {
        $contact->loadMissing(['phones', 'emails']);
        return [
            'display_name' => $contact->display_name,
            'given_name'   => $contact->given_name,
            'family_name'  => $contact->family_name,
            'organization' => $contact->organization,
            'job_title'    => $contact->job_title,
            'notes'        => $contact->notes,
            'phones'       => $contact->phones->map(fn ($p) => ['label' => $p->label, 'value' => $p->value])->all(),
            'emails'       => $contact->emails->map(fn ($e) => ['label' => $e->label, 'value' => $e->value])->all(),
        ];
    }

    /** Push a single local change (create or update) to Google. */
    public function pushContact(GoogleContactsAccount $account, Contact $contact): void
    {
        if (!$account->push_enabled) return;
        $payload = $this->contactToPayload($contact);
        if ($contact->google_resource_name && $contact->google_etag) {
            $res = $this->provider->updatePerson($account, $contact->google_resource_name, $contact->google_etag, $payload);
        } else {
            $res = $this->provider->createPerson($account, $payload);
        }
        $contact->forceFill([
            'google_contacts_account_id' => $account->id,
            'google_resource_name'       => $res['resource_name'] ?? $contact->google_resource_name,
            'google_etag'                => $res['etag'] ?? $contact->google_etag,
            'last_synced_at'             => now(),
            'locally_modified_at'        => $contact->locally_modified_at, // keep so equality check works
        ])->save();
        // Bump last_synced_at strictly after locally_modified_at to prevent
        // immediate re-push on the next pass.
        $contact->forceFill(['last_synced_at' => now()->addSecond()])->save();
    }

    public function deleteContact(GoogleContactsAccount $account, Contact $contact): void
    {
        if ($contact->google_resource_name) {
            try { $this->provider->deletePerson($account, $contact->google_resource_name); }
            catch (\Throwable $e) { Log::warning('Google contact delete failed', ['c' => $contact->id, 'err' => $e->getMessage()]); }
        }
    }
}
