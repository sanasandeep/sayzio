<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
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

        // 1. Move phones from loser → primary (skip duplicates)
        foreach ($loser->phones as $phone) {
            $norm = $phone->value_e164 ?: \App\Modules\User\Models\ContactPhone::normalize($phone->value);
            $alreadyExists = $primary->phones()
                ->where(function ($q) use ($norm, $phone) {
                    $q->where('value_e164', $norm)
                      ->orWhere('value', $phone->value);
                })->exists();
            if (!$alreadyExists) {
                ContactPhone::create([
                    'contact_id' => $primary->id,
                    'label'      => $phone->label,
                    'value'      => $phone->value,
                    'value_e164' => $phone->value_e164,
                    'is_primary' => false,
                ]);
            }
        }

        // 2. Move emails from loser → primary (skip duplicates)
        foreach ($loser->emails as $email) {
            $norm = strtolower(trim($email->value));
            $alreadyExists = $primary->emails()
                ->whereRaw('LOWER(TRIM(value)) = ?', [$norm])
                ->exists();
            if (!$alreadyExists) {
                ContactEmail::create([
                    'contact_id' => $primary->id,
                    'label'      => $email->label,
                    'value'      => $email->value,
                    'is_primary' => false,
                ]);
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
        $this->repointReferences($loser->id, $primary->id);

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

        $loser->delete();
    }

    /**
     * Repoint all rows that reference the old contact_id to the new one.
     * Tables: dialer_lookups, dialer_favorites, leads, card_scans, invoices,
     * conversation_sessions — each has its own FK semantics so we UPDATE
     * rather than relying on cascades.
     */
    protected function repointReferences(int $fromId, int $toId): void
    {
        // dialer_lookups: contact_id nullable nullOnDelete — repoint to primary
        DB::table('dialer_lookups')
            ->where('contact_id', $fromId)
            ->update(['contact_id' => $toId]);

        // dialer_favorites: contact_id nullable
        DB::table('dialer_favorites')
            ->where('contact_id', $fromId)
            ->update(['contact_id' => $toId]);

        // leads: contact_id nullable nullOnDelete
        DB::table('leads')
            ->where('contact_id', $fromId)
            ->update(['contact_id' => $toId]);

        // card_scans: contact_id nullable, no FK constraint (per migration)
        DB::table('card_scans')
            ->where('contact_id', $fromId)
            ->update(['contact_id' => $toId]);

        // invoices: contact_id nullable
        DB::table('invoices')
            ->where('contact_id', $fromId)
            ->update(['contact_id' => $toId]);

        // conversation_sessions: contact_id nullable
        DB::table('conversation_sessions')
            ->where('contact_id', $fromId)
            ->update(['contact_id' => $toId]);
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
