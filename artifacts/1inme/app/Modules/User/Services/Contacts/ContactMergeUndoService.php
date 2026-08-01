<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactEmail;
use App\Modules\User\Models\ContactMergeAudit;
use App\Modules\User\Models\ContactPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Undoes a recorded contact merge (time-limited, see
 * ContactMergeAudit::UNDO_WINDOW_DAYS):
 *
 *  - Recreates the merged-away (source) contact from the audit's snapshot,
 *    including its phones and emails.
 *  - Repoints every recorded reference/capture row back to the recreated
 *    contact — but only rows that STILL point at the surviving primary, so
 *    later re-links or re-merges are never clobbered.
 *  - Removes the phone/email rows the merge created on the primary (only if
 *    they still belong to the primary).
 *
 * Idempotent: the audit row is locked and stamped `undone_at` inside the
 * same transaction; a second undo attempt is rejected. Fields the merge
 * filled onto the primary (notes, tags, blank scalars…) are intentionally
 * left in place — removing them could destroy edits made after the merge.
 */
class ContactMergeUndoService
{
    /**
     * @return Contact the recreated source contact.
     * @throws \RuntimeException when the merge can no longer be undone.
     */
    public function undo(ContactMergeAudit $audit): Contact
    {
        return DB::transaction(function () use ($audit) {
            // Re-read under lock so concurrent undo requests serialize.
            $locked = ContactMergeAudit::whereKey($audit->id)->lockForUpdate()->first();
            if (!$locked) {
                throw new \RuntimeException('Merge record not found.');
            }
            if ($locked->undone_at !== null) {
                throw new \RuntimeException('This merge has already been undone.');
            }
            if (!$locked->isUndoable()) {
                throw new \RuntimeException('The undo window for this merge has expired.');
            }

            $snapshot = (array) $locked->source_snapshot;
            $moved    = (array) ($locked->moved ?? []);

            // 1. Recreate the source contact from the snapshot.
            $restored = $this->recreateContact($locked, $snapshot);

            // 2. Repoint recorded reference/capture rows back — only rows
            //    still pointing at the primary (later merges/edits win).
            $phoneEmailTables = ['contact_phones', 'contact_emails'];
            foreach ($moved as $table => $ids) {
                if (in_array($table, $phoneEmailTables, true)) {
                    continue;
                }
                $ids = array_values(array_filter(array_map('intval', (array) $ids)));
                if (empty($ids) || !preg_match('/^[a-z_]+$/', $table)) {
                    continue;
                }
                try {
                    DB::table($table)
                        ->whereIn('id', $ids)
                        ->where('contact_id', $locked->primary_contact_id)
                        ->update(['contact_id' => $restored->id]);
                } catch (\Throwable $e) {
                    Log::warning('ContactMergeUndoService: repoint failed', [
                        'table' => $table, 'err' => $e->getMessage(),
                    ]);
                }
            }

            // 3. Remove the phone/email rows the merge added to the primary
            //    (the restored contact carries its own copies from the
            //    snapshot). Scoped to the primary so unrelated rows are safe.
            foreach ((array) ($moved['contact_phones'] ?? []) as $id) {
                ContactPhone::whereKey((int) $id)
                    ->where('contact_id', $locked->primary_contact_id)
                    ->delete();
            }
            foreach ((array) ($moved['contact_emails'] ?? []) as $id) {
                ContactEmail::whereKey((int) $id)
                    ->where('contact_id', $locked->primary_contact_id)
                    ->delete();
            }

            // 4. Stamp the audit as undone.
            $locked->forceFill([
                'undone_at'            => now(),
                'restored_contact_id'  => $restored->id,
            ])->save();

            return $restored;
        });
    }

    protected function recreateContact(ContactMergeAudit $audit, array $snapshot): Contact
    {
        $phones = (array) ($snapshot['phones'] ?? []);
        $emails = (array) ($snapshot['emails'] ?? []);
        unset($snapshot['phones'], $snapshot['emails']);

        // Only restore known contact columns; drop timestamps so the row
        // gets fresh created/updated stamps, and never resurrect Google sync
        // linkage (the Google-side contact was tombstoned at merge time).
        $attrs = collect($snapshot)
            ->except(['id', 'created_at', 'updated_at', 'google_contacts_account_id', 'google_resource_name', 'google_etag'])
            ->all();

        $contact = new Contact();
        // setRawAttributes: the snapshot came from getAttributes(), i.e. raw
        // column values (JSON casts already encoded as strings). forceFill
        // would run them through the casts again and double-encode.
        $contact->setRawAttributes(array_merge($attrs, [
            'user_id'             => $audit->user_id,
            'locally_modified_at' => now()->toDateTimeString(),
        ]));
        $contact->save();

        foreach ($phones as $p) {
            if (empty($p['value'])) continue;
            ContactPhone::create([
                'contact_id' => $contact->id,
                'label'      => $p['label'] ?? null,
                'value'      => $p['value'],
                'value_e164' => $p['value_e164'] ?? ContactPhone::normalize($p['value']),
                'is_primary' => (bool) ($p['is_primary'] ?? false),
            ]);
        }
        foreach ($emails as $e) {
            if (empty($e['value'])) continue;
            ContactEmail::create([
                'contact_id' => $contact->id,
                'label'      => $e['label'] ?? null,
                'value'      => $e['value'],
                'is_primary' => (bool) ($e['is_primary'] ?? false),
            ]);
        }

        return $contact;
    }
}
