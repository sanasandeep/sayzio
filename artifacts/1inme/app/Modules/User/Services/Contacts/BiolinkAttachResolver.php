<?php

namespace App\Modules\User\Services\Contacts;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserBlock;
use App\Modules\User\Support\DialerReachability;

class BiolinkAttachResolver
{
    /**
     * Look up each phone on the contact in linked_identifiers. If a verified
     * Sayzio user owns one of the numbers AND the owner of the contact has
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
            // Never auto-attach a creator the contact owner can't reach right
            // now (suspended/deactivated, or one that has blocked the owner);
            // that seeds the caller-ID surface with an unreachable account.
            if (!DialerReachability::reaches($contact->user_id, $matched)) continue;

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

    /**
     * Clean up a stale auto-attachment whose creator is no longer reachable to
     * the contact owner. Reachability is decided by DialerReachability (the
     * single source of truth also used at read time): a reachable creator is
     * left untouched.
     *
     * When the creator has *blocked* the owner we record a detach so the
     * attachment can never silently reappear if they later unblock — the owner
     * can re-attach manually. A merely suspended/deactivated (or deleted)
     * creator is only cleared, so a later reactivation lets the normal resolve
     * path re-attach on the next sync.
     *
     * Detach memory is respected throughout: a manually detached creator was
     * already cleared and never re-attaches, so this is a no-op for it.
     */
    public function reconcile(Contact $contact): void
    {
        $attachedId = $contact->biolink_user_id;
        if (!$attachedId) return;

        $creator = $contact->relationLoaded('biolinkUser')
            ? $contact->biolinkUser
            : User::find($attachedId);

        // Still reachable right now — keep the attachment.
        if (DialerReachability::reaches($contact->user_id, $creator)) {
            return;
        }

        // Unreachable. If the cause is a directional block (creator blocked the
        // owner) remember the detach so an unblock can't silently re-attach;
        // otherwise (suspended/deactivated/deleted) just clear.
        $blockedOwner = $creator && UserBlock::where('blocker_user_id', $creator->id)
            ->where('blocked_user_id', $contact->user_id)
            ->exists();

        if ($blockedOwner) {
            $this->detach($contact, (int) $attachedId);
            return;
        }

        $contact->forceFill([
            'biolink_user_id'     => null,
            'biolink_attached_at' => null,
        ])->save();
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
