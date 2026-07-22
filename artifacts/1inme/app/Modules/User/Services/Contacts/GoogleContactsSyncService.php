<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactDeletionTombstone;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\GoogleContactsAccount;
use App\Modules\User\Models\UserNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleContactsSyncService
{
    /**
     * Minimum seconds between two real syncs of the same account. On-demand
     * triggers, the sync-on-open background job, and the scheduled backstop
     * all funnel through syncNow() so no single account can be hammered
     * against the Google People API regardless of how often it's requested.
     */
    public const SYNC_COOLDOWN_SECONDS = 15;

    public function __construct(
        protected GoogleContactsProvider $provider,
        protected BiolinkAttachResolver $resolver,
    ) {}

    /**
     * Run a sync for one account, throttled per-account so it can't be
     * hammered. Returns a small status envelope the callers surface to the
     * user:
     *   ['status' => 'ok',          'stats' => [...]]              — ran now
     *   ['status' => 'throttled',   'retry_after' => N, 'stats' => null]
     *   ['status' => 'in_progress', 'retry_after' => N, 'stats' => null]
     *
     * The cooldown gate uses a short-lived cache stamp; an atomic lock guards
     * against two concurrent syncs of the same account (e.g. a manual click
     * racing the scheduled backstop). Pass $force=true to bypass the cooldown
     * (used for the very first sync right after connecting an account).
     */
    public function syncNow(GoogleContactsAccount $account, bool $force = false, int $cooldown = self::SYNC_COOLDOWN_SECONDS): array
    {
        // Revoked/expired Google connection: don't waste any Google calls,
        // tell the caller a reconnect is required instead.
        if ($account->needsReauth()) {
            return ['status' => 'needs_reauth', 'retry_after' => null, 'stats' => null];
        }

        $stampKey = "google-contacts:sync-at:{$account->id}";
        $now      = time();

        if (!$force) {
            $last = (int) Cache::get($stampKey, 0);
            $elapsed = $now - $last;
            if ($last && $elapsed < $cooldown) {
                return ['status' => 'throttled', 'retry_after' => max(1, $cooldown - $elapsed), 'stats' => null];
            }
        }

        $lock = Cache::lock("google-contacts:sync-lock:{$account->id}", 180);
        if (!$lock->get()) {
            return ['status' => 'in_progress', 'retry_after' => 5, 'stats' => null];
        }

        try {
            // Re-stamp before running so a burst of requests that arrive while
            // this sync is in flight are throttled off the current attempt.
            Cache::put($stampKey, $now, now()->addSeconds(max($cooldown, 60)));
            $stats = $this->syncAccount($account);
        } finally {
            $lock->release();
        }

        return ['status' => 'ok', 'stats' => $stats];
    }

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
                    if ($this->attemptTombstoneDelete($account, $t)) {
                        $stats['deleted']++;
                    } else {
                        $stats['errors']++;
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
                    if (!empty($stats['skipped_capped'])) {
                        $this->notifyCapHit($account, (int) $stats['skipped_capped'], $cap);
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
        } catch (GoogleReauthRequiredException $e) {
            // The provider already stamped needs_reauth_at; make sure the
            // status reflects it (and never regress it to a raw 'error').
            $account->markNeedsReauth($e->getMessage());
            $stats['errors']++;
            Log::info('Contacts sync skipped — Google connection needs reauthorization', ['account' => $account->id]);
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

    /**
     * Create an in-app notice that the plan cap was hit during sync.
     * Deduped: if there's already an unread cap notice for this account,
     * just bump its skipped count instead of stacking new rows.
     */
    protected function notifyCapHit(GoogleContactsAccount $account, int $skipped, int $cap): void
    {
        $existing = UserNotification::where('user_id', $account->user_id)
            ->where('type', 'contacts_sync_capped')
            ->whereNull('read_at')
            ->where('data->account_id', $account->id)
            ->first();

        if ($existing) {
            $data = $existing->data ?? [];
            $data['skipped']      = ((int) ($data['skipped'] ?? 0)) + $skipped;
            $data['contacts_max'] = $cap;
            $data['message']      = "Google Contacts sync skipped {$data['skipped']} contact(s) because you've hit your plan's contact limit ({$cap}). Upgrade your plan to import the rest.";
            $existing->forceFill(['data' => $data])->save();
            return;
        }

        UserNotification::create([
            'user_id'    => $account->user_id,
            'type'       => 'contacts_sync_capped',
            'data'       => [
                'account_id'     => $account->id,
                'account_email'  => $account->account_email,
                'skipped'        => $skipped,
                'contacts_max'   => $cap,
                'fix_url'        => route('user.contacts.index'),
                'message'        => "Google Contacts sync skipped {$skipped} contact(s) because you've hit your plan's contact limit ({$cap}). Upgrade your plan to import the rest.",
            ],
            'created_at' => now(),
        ]);
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

    /**
     * Attempt to finalise one deletion tombstone on Google. On success the
     * tombstone is removed; on failure its attempt counter is bumped and it's
     * left in place for the next scheduled drain (retry safety). Returns true
     * only when the Google-side delete succeeded. Shared by the scheduled
     * sync and the immediate best-effort delete on the destroy paths, so the
     * retry/backoff behaviour lives in exactly one place.
     */
    public function attemptTombstoneDelete(GoogleContactsAccount $account, ContactDeletionTombstone $tombstone): bool
    {
        try {
            $this->provider->deletePerson($account, $tombstone->google_resource_name);
            $tombstone->delete();
            return true;
        } catch (\Throwable $e) {
            $tombstone->increment('attempts');
            $tombstone->update(['last_error' => Str::limit($e->getMessage(), 500)]);
            Log::warning('Google contact deletion retry failed', ['tomb' => $tombstone->id, 'err' => $e->getMessage()]);
            return false;
        }
    }
}
