<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\LinkedIdentifier;

class BiolinkAttachResolver
{
    /**
     * Look up each phone on the contact in linked_identifiers. If a verified
     * 1INME user owns one of the numbers AND the owner of the contact has
     * not previously detached that biolink, attach silently.
     *
     * Re-evaluated on every contact create/update and on each Google sync.
     */
    public function resolveFor(Contact $contact): void
    {
        $contact->loadMissing('phones');
        $detached = collect($contact->detached_biolink_user_ids ?? []);

        foreach ($contact->phones as $phone) {
            $needle = $phone->value_e164 ?: ContactPhoneNormalize($phone->value);
            if (!$needle) continue;
            $matched = LinkedIdentifier::resolveUser('phone', $needle);
            if (!$matched) continue;
            if ($matched->id === $contact->user_id) continue; // don't auto-attach own account
            if ($detached->contains($matched->id)) continue;

            if ($contact->biolink_user_id !== $matched->id) {
                $contact->forceFill([
                    'biolink_user_id'     => $matched->id,
                    'biolink_attached_at' => now(),
                ])->save();
            }
            return;
        }

        // No match — clear an attachment that no longer applies (number
        // changed, etc.) UNLESS the user manually attached one (not
        // tracked separately yet, so we conservatively clear only when
        // the previously-attached user is no longer reachable by phone).
        if ($contact->biolink_user_id) {
            $stillReachable = false;
            foreach ($contact->phones as $phone) {
                $needle = $phone->value_e164 ?: ContactPhoneNormalize($phone->value);
                if (!$needle) continue;
                $u = LinkedIdentifier::resolveUser('phone', $needle);
                if ($u && $u->id === $contact->biolink_user_id) { $stillReachable = true; break; }
            }
            if (!$stillReachable) {
                $contact->forceFill(['biolink_user_id' => null, 'biolink_attached_at' => null])->save();
            }
        }
    }

    /** Mark a biolink as detached so a subsequent sync won't re-attach it. */
    public function detach(Contact $contact, int $biolinkUserId): void
    {
        $list = collect($contact->detached_biolink_user_ids ?? [])->push($biolinkUserId)->unique()->values()->all();
        $contact->forceFill([
            'detached_biolink_user_ids' => $list,
            'biolink_user_id'           => $contact->biolink_user_id === $biolinkUserId ? null : $contact->biolink_user_id,
            'biolink_attached_at'       => $contact->biolink_user_id === $biolinkUserId ? null : $contact->biolink_attached_at,
        ])->save();
    }
}

function ContactPhoneNormalize(?string $raw): string
{
    return \App\Modules\User\Models\ContactPhone::normalize((string) $raw);
}
