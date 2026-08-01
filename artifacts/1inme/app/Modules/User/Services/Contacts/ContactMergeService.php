<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactMergeAudit;
use App\Modules\User\Models\ContactPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Merges one or more "loser" contacts into a designated primary contact.
 *
 * What happens during a merge:
 *  - Phones/emails from losers that don't already exist on the primary are
 *    moved to the primary (union, deduplicated).
 *  - Text fields (notes, organization, job_title, website) are filled in on
 *    the primary only when blank.
 *  - tags, sources, and detached_biolink_user_ids are unioned.
 *  - socials: per-platform first-wins (primary values preserved).
 *  - biolink_user_id: primary keeps its value; if null, it inherits the
 *    first loser's non-null value.
 *  - All referencing rows in dialer_lookups, dialer_favorites, leads,
 *    card_scans, invoices, and conversation_sessions are repointed to the
 *    primary before the losers are deleted.
 *  - Google resource names: primary keeps its own; losers' Google contacts
 *    are left as orphan tombstones so the next sync can clean them up.
 *  - The dismissed-pair entry for each (primary, loser) pair is cleaned up
 *    so the merge doesn't leave stale dismissal data.
 *
 * All work happens inside a single DB transaction.
 */
class ContactMergeService
{
    /**
     * Customer-capture tables (unified contact linking) that carry a
     * nullable, FK-less contact_id. Merges must repoint these so capture
     * history follows the surviving contact.
     */
    public const CAPTURE_TABLES = [
        'subscribers',
        'form_submissions',
        'restaurant_orders',
        'store_orders',
        'service_booking_requests',
        'rsvps',
        'event_tickets',
        'product_orders',
        'reviews',
        'inbox_threads',
    ];

    /**
     * Merge $losers into $primary.
     *
     * @param  Contact   $primary   The contact that survives.
     * @param  Contact[] $losers    One or more contacts to merge in and delete.
     * @return Contact              The updated primary (freshly reloaded).
     */
    public function merge(Contact $primary, array $losers): Contact
    {
        return DB::transaction(function () use ($primary, $losers) {
            foreach ($losers as $loser) {
                $this->mergeOne($primary, $loser);
            }

            // Reload so the caller gets a fully up-to-date model.
            return $primary->fresh(['phones', 'emails']);
        });
    }

    // ------------------------------------------------------------------
    //  Internals
    // ------------------------------------------------------------------

    protected function mergeOne(Contact $primary, Contact $loser): void
    {
        if ($primary->user_id !== $loser->user_id) {
            throw new \InvalidArgumentException('Cannot merge contacts across different users.');
        }
        if ($primary->id === $loser->id) {
            return;
        }

        $loser->loadMissing(['phones', 'emails']);

        // 0. Snapshot the loser BEFORE any mutation so a later "Undo merge"
        //    can faithfully recreate it. Row ids moved to the primary are
        //    collected as we go and recorded on the same audit row.
        $snapshot = $this->snapshotContact($loser);
        $moved    = [];

        // 1. Move phones from loser → primary (skip duplicates)
        foreach ($loser->phones as $phone) {
            $norm = $phone->value_e164 ?: \App\Modules\User\Models\ContactPhone::normalize($phone->value);
            $alreadyExists = $primary->phones()
                ->where(function ($q) use ($norm, $phone) {
                    $q->where('value_e164', $norm)
                      ->orWhere('value', $phone->value);
                })->exists();
            if (!$alreadyExists) {
                $created = ContactPhone::create([
                    'contact_id' => $primary->id,
                    'label'      => $phone->label,
                    'value'      => $phone->value,
                    'value_e164' => $phone->value_e164,
                    'is_primary' => false,
                ]);
                $moved['contact_phones'][] = $created->id;
            }
        }

        // 2. Move emails from loser → primary (skip duplicates)
        foreach ($loser->emails as $email) {
            $norm = strtolower(trim($email->value));
            $alreadyExists = $primary->emails()
                ->whereRaw('LOWER(TRIM(value)) = ?', [$norm])
                ->exists();
            if (!$alreadyExists) {
                $created = ContactEmail::create([
                    'contact_id' => $primary->id,
                    'label'      => $email->label,
                    'value'      => $email->value,
                    'is_primary' => false,
                ]);
                $moved['contact_emails'][] = $created->id;
            }
        }

        // Reload primary phones/emails for fresh checks
        $primary->unsetRelation('phones')->unsetRelation('emails');
        $primary->loadMissing(['phones', 'emails']);

        // 3. Scalar text fields — fill blank primary fields from loser
        $scalarFills = array_filter([
            'display_name' => $primary->display_name ?: $loser->display_name,
            'given_name'   => $primary->given_name   ?: $loser->given_name,
            'family_name'  => $primary->family_name  ?: $loser->family_name,
            'organization' => $primary->organization ?: $loser->organization,
            'job_title'    => $primary->job_title    ?: $loser->job_title,
            'website'      => $primary->website      ?: $loser->website,
            'notes'        => $this->mergeNotes($primary->notes, $loser->notes),
        ], fn ($v) => $v !== null && $v !== '');
        if (!empty($scalarFills)) {
            $primary->fill($scalarFills);
        }

        // 4. Tags: union
        $tags = array_values(array_unique(array_merge(
            (array) ($primary->tags ?? []),
            (array) ($loser->tags ?? [])
        )));
        $primary->tags = empty($tags) ? null : $tags;

        // 5. Sources: union
        $sources = (array) ($primary->sources ?? []);
        foreach ((array) ($loser->sources ?? []) as $src) {
            $url = is_array($src) ? ($src['url'] ?? null) : null;
            if ($url && !collect($sources)->pluck('url')->contains($url)) {
                $sources[] = $src;
            } elseif (!is_array($src)) {
                // Legacy string source
                if (!in_array($src, $sources, true)) $sources[] = $src;
            }
        }
        $primary->sources = empty($sources) ? null : $sources;

        // 6. Socials: per-platform first-wins
        $socials = (array) ($primary->socials ?? []);
        foreach ((array) ($loser->socials ?? []) as $platform => $value) {
            if (empty($socials[$platform])) $socials[$platform] = $value;
        }
        $primary->socials = empty($socials) ? null : $socials;

        // 7. Biolink: primary keeps its value; inherit loser's if primary has none
        if (!$primary->biolink_user_id && $loser->biolink_user_id) {
            $primary->biolink_user_id   = $loser->biolink_user_id;
            $primary->biolink_attached_at = $loser->biolink_attached_at;
        }

        // 8. detached_biolink_user_ids: union
        $detached = array_values(array_unique(array_merge(
            (array) ($primary->detached_biolink_user_ids ?? []),
            (array) ($loser->detached_biolink_user_ids   ?? [])
        )));
        $primary->detached_biolink_user_ids = empty($detached) ? null : $detached;

        // 9. Photo: keep primary's; inherit loser's if primary has none
        if (!$primary->photo_path && !$primary->photo_url) {
            $primary->photo_path = $loser->photo_path;
            $primary->photo_url  = $loser->photo_url;
        }

        $primary->locally_modified_at = now();
        $primary->save();

        // 10. Repoint referencing rows to the primary BEFORE deleting the loser
        $moved = array_merge($moved, $this->repointReferences($loser->id, $primary->id));

        // 11. Remove dismissed-pair records for the loser ↔ primary pair
        $a = min($primary->id, $loser->id);
        $b = max($primary->id, $loser->id);
        DB::table('contact_dismissed_pairs')
            ->where('user_id', $primary->user_id)
            ->where('contact_id_a', $a)
            ->where('contact_id_b', $b)
            ->delete();

        // 12. Delete the loser. cascade deletes its phones/emails.
        // Keep the Google resource name on a tombstone if needed so the
        // next sync removes it from Google too.
        if ($loser->google_contacts_account_id && $loser->google_resource_name) {
            try {
                \App\Modules\User\Models\ContactDeletionTombstone::create([
                    'user_id'                    => $loser->user_id,
                    'google_contacts_account_id' => $loser->google_contacts_account_id,
                    'google_resource_name'       => $loser->google_resource_name,
                ]);
            } catch (\Throwable $e) {
                Log::warning('ContactMergeService: could not create tombstone', ['err' => $e->getMessage()]);
            }
        }

        // 13. Record the merge audit so the merge can be undone (time-limited).
        //     Same transaction as the merge itself, so an audit row exists
        //     iff the merge committed.
        try {
            ContactMergeAudit::create([
                'user_id'            => $primary->user_id,
                'primary_contact_id' => $primary->id,
                'source_contact_id'  => $loser->id,
                'source_snapshot'    => $snapshot,
                'moved'              => (object) $moved,
            ]);
        } catch (\Throwable $e) {
            // Never let audit bookkeeping break the merge itself (e.g. a
            // partial schema without the audits table yet).
            Log::warning('ContactMergeService: could not record merge audit', ['err' => $e->getMessage()]);
        }

        $loser->delete();
    }

    /**
     * Full attribute snapshot of a contact (plus its phones/emails) taken
     * before the merge mutates anything — the raw material for "Undo merge".
     */
    protected function snapshotContact(Contact $contact): array
    {
        $attrs = $contact->getAttributes();
        unset($attrs['id']);

        return array_merge($attrs, [
            'phones' => $contact->phones->map(fn ($p) => [
                'label'      => $p->label,
                'value'      => $p->value,
                'value_e164' => $p->value_e164,
                'is_primary' => (bool) $p->is_primary,
            ])->all(),
            'emails' => $contact->emails->map(fn ($e) => [
                'label'      => $e->label,
                'value'      => $e->value,
                'is_primary' => (bool) $e->is_primary,
            ])->all(),
        ]);
    }

    /**
     * Repoint all rows that reference the old contact_id to the new one.
     * Tables: dialer_lookups, dialer_favorites, leads, card_scans, invoices,
     * conversation_sessions — each has its own FK semantics so we UPDATE
     * rather than relying on cascades.
     *
     * Returns a map of table => [row ids] that were actually repointed, so
     * the merge audit can record exactly which rows to move back on undo.
     */
    protected function repointReferences(int $fromId, int $toId): array
    {
        $moved = [];

        // Reference tables with their own FK semantics: dialer_lookups,
        // dialer_favorites, leads, card_scans, invoices,
        // conversation_sessions — contact_id nullable on all of them.
        $referenceTables = [
            'dialer_lookups', 'dialer_favorites', 'leads',
            'card_scans', 'invoices', 'conversation_sessions',
        ];
        foreach ($referenceTables as $table) {
            $ids = DB::table($table)->where('contact_id', $fromId)->pluck('id')->all();
            if (empty($ids)) {
                continue;
            }
            DB::table($table)->whereIn('id', $ids)->update(['contact_id' => $toId]);
            $moved[$table] = $ids;
        }

        // Capture tables (subscribers, form_submissions, orders, bookings,
        // RSVPs, tickets, reviews, inbox threads): contact_id is nullable
        // with no FK constraint, so stale ids would silently orphan capture
        // history. Repoint them all; guard per-table so a missing table in
        // a partial schema never aborts the merge transaction spuriously.
        foreach (self::CAPTURE_TABLES as $table) {
            if (!$this->captureTableHasContactId($table)) {
                continue;
            }
            $ids = DB::table($table)->where('contact_id', $fromId)->pluck('id')->all();
            if (empty($ids)) {
                continue;
            }
            DB::table($table)->whereIn('id', $ids)->update(['contact_id' => $toId]);
            $moved[$table] = $ids;
        }

        return $moved;
    }

    /** @var array<string,bool> memoized schema checks (per request) */
    protected array $captureColumnCache = [];

    protected function captureTableHasContactId(string $table): bool
    {
        return $this->captureColumnCache[$table] ??=
            \Illuminate\Support\Facades\Schema::hasTable($table)
            && \Illuminate\Support\Facades\Schema::hasColumn($table, 'contact_id');
    }

    /**
     * Merge two notes strings: keep primary as-is; if loser has content that
     * isn't already present in primary, append it separated by a divider.
     */
    protected function mergeNotes(?string $primary, ?string $loser): ?string
    {
        $p = trim((string) $primary);
        $l = trim((string) $loser);
        if ($l === '' || $l === $p) return $p !== '' ? $p : null;
        if ($p === '') return $l;
        // Append loser's notes only if they differ
        return $p . "\n\n---\n" . $l;
    }
}
