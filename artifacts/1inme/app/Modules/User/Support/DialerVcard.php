<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Models\VcfData;

/**
 * Builds a shareable vCard 3.0 document for a Dialer identity by reusing the
 * existing VcfData::toVcf() generator on a transient (unsaved) instance.
 *
 * It merges the saved contact, the matched Sayzio user's biolink, the auto
 * pulled socials/locations, and the owner's manual additions into one card.
 */
class DialerVcard
{
    public static function build(User $owner, ?Contact $contact, string $number, ?User $matchedUser, ?Link $bio): string
    {
        $extracted = $bio ? DialerIdentity::extractFromBiolink($bio) : ['socials' => [], 'locations' => [], 'channels' => []];
        $manual = DialerIdentity::normalizeManual($contact?->manual_profile);

        // Name — prefer the saved contact, fall back to the matched user.
        $displayName = $contact?->nameForDisplay()
            ?: ($matchedUser?->name ?: ($number !== '' ? $number : 'Contact'));
        [$first, $last] = self::splitName($displayName);

        // Phones — the dialed number plus every number on the saved contact.
        $phones = [];
        if ($number !== '') {
            $phones[] = ['label' => 'Mobile', 'value' => $number];
        }
        foreach (($contact?->phones ?? []) as $p) {
            $val = $p->value_e164 ?: $p->value;
            if ($val) $phones[] = ['label' => $p->label ?: 'Phone', 'value' => $val];
        }

        // Emails.
        $emails = [];
        foreach (($contact?->emails ?? []) as $e) {
            if ($e->value) $emails[] = ['label' => $e->label ?: 'Email', 'value' => $e->value];
        }

        // URLs — biolink page link.
        $urls = [];
        if ($bio) {
            $urls[] = ['label' => 'Sayzio', 'value' => url('/' . $bio->alias)];
        }

        // Social profiles — auto + manual.
        $socials = [];
        foreach ($extracted['socials'] as $s) {
            $socials[] = ['service' => $s['platform'] ?? 'social', 'value' => $s['url']];
        }
        foreach ($manual['socials'] as $s) {
            $socials[] = ['service' => $s['platform'] ?? 'social', 'value' => $s['url']];
        }

        // Addresses — auto map locations + manual location (street holds the
        // human-readable address; vCard ADR has no first-class geo field).
        $addresses = [];
        foreach ($extracted['locations'] as $loc) {
            $street = $loc['address'] ?: (($loc['lat'] !== null && $loc['lng'] !== null) ? "{$loc['lat']}, {$loc['lng']}" : '');
            if ($street !== '') $addresses[] = ['label' => $loc['label'] ?: 'Location', 'street' => $street];
        }
        if ($manual['location']) {
            $loc = $manual['location'];
            $street = $loc['address'] ?: (($loc['lat'] !== null && $loc['lng'] !== null) ? "{$loc['lat']}, {$loc['lng']}" : '');
            if ($street !== '') $addresses[] = ['label' => $loc['label'] ?: 'Location', 'street' => $street];
        }

        $vcf = new VcfData([
            'first_name'      => $first,
            'last_name'       => $last,
            'organization'    => $contact?->organization,
            'title'           => $contact?->job_title,
            'emails'          => $emails,
            'phones'          => $phones,
            'urls'            => $urls,
            'addresses'       => $addresses,
            'social_profiles' => $socials,
            'note'            => 'Saved from Sayzio Dialer',
        ]);

        return $vcf->toVcf();
    }

    /**
     * Suggest a filename slug for the downloaded card.
     */
    public static function filename(?Contact $contact, ?User $matchedUser, string $number): string
    {
        $base = $contact?->nameForDisplay() ?: ($matchedUser?->name ?: ($number !== '' ? $number : 'contact'));
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $base));
        $slug = trim($slug, '-') ?: 'contact';
        return $slug . '.vcf';
    }

    /** @return array{0:string,1:string} [first, last] */
    protected static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        if (count($parts) <= 1) return [$parts[0] ?? $name, ''];
        $first = array_shift($parts);
        return [$first, implode(' ', $parts)];
    }
}
