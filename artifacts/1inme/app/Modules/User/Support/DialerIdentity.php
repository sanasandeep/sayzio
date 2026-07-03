<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Builds the canonical "Identity Profile" payload shared by the web Dialer
 * profile page and the mobile Dialer profile screen.
 *
 * It resolves a phone number (and/or a saved contact) to a Sayzio user,
 * auto-pulls the user's public socials / shared map locations / reachable
 * channels from their biolink, layers the owner's manual additions on top
 * (kept deliberately distinct), and produces a shareable Export-vCard URL.
 */
class DialerIdentity
{
    /**
     * Resolve a contact + matched Sayzio user + their default biolink from a
     * contact id and/or a phone number, scoped to the requesting owner.
     *
     * @return array{contact: ?Contact, matchedUser: ?User, bio: ?Link, number: string, needle: ?string, isSelf: bool, isSaved: bool}
     */
    public static function resolve(User $owner, ?int $contactId, ?string $number): array
    {
        $number = trim((string) ($number ?? ''));
        $contact = null;

        if ($contactId) {
            $contact = Contact::where('user_id', $owner->id)->where('id', $contactId)
                ->with(['phones', 'emails', 'biolinkUser'])->first();
            if ($contact && $number === '') {
                $number = $contact->phones->first()?->value_e164
                    ?? $contact->phones->first()?->value
                    ?? '';
            }
        }

        $needle = $number !== '' ? ContactPhone::normalize($number) : null;

        if (!$contact && $needle) {
            $contact = Contact::where('user_id', $owner->id)
                ->whereHas('phones', fn ($q) => $q->where('value_e164', $needle))
                ->with(['phones', 'emails', 'biolinkUser'])
                ->first();
        }

        // Prefer the contact's attached biolink owner; otherwise resolve the
        // number against verified linked identifiers.
        $matchedUser = $contact?->biolinkUser;
        if (!$matchedUser && $needle) {
            $matchedUser = LinkedIdentifier::resolveUser('phone', $needle);
        }

        // Never enrich caller-ID with a creator the searcher can't reach right
        // now (suspended/deactivated, or one that has blocked the searcher).
        $matchedUser = DialerReachability::enrichableCreator($owner->id, $matchedUser);

        $bio = null;
        if ($matchedUser) {
            $bio = Link::where('user_id', $matchedUser->id)
                ->whereIn('type', Link::BIOLINK_FAMILY)
                ->where('is_active', true)
                ->orderByDesc('id')
                ->first();
        }

        // Task #3497: the two exemptions from a creator's contact-privacy
        // prefs — looking yourself up, and a number you've already saved as
        // a contact (the row is always scoped to $owner, so its mere
        // presence means "already saved").
        $isSelf = $matchedUser !== null && $owner->id === $matchedUser->id;
        $isSaved = $contact !== null;

        return compact('contact', 'matchedUser', 'bio', 'number', 'needle', 'isSelf', 'isSaved');
    }

    /**
     * Build the full enriched identity payload from a resolved set.
     *
     * @param array{contact: ?Contact, matchedUser: ?User, bio: ?Link, number: string, needle: ?string} $resolved
     */
    public static function payload(User $owner, array $resolved): array
    {
        /** @var ?Contact $contact */
        $contact = $resolved['contact'] ?? null;
        /** @var ?User $matchedUser */
        $matchedUser = $resolved['matchedUser'] ?? null;
        /** @var ?Link $bio */
        $bio = $resolved['bio'] ?? null;
        $number = (string) ($resolved['number'] ?? '');

        $extracted = $bio ? self::extractFromBiolink($bio) : ['socials' => [], 'locations' => [], 'channels' => []];

        $manual = self::normalizeManual($contact?->manual_profile);

        $payload = [
            'number'   => $number,
            'contact'  => $contact ? [
                'id'           => $contact->id,
                'display_name' => $contact->nameForDisplay(),
                'organization' => $contact->organization,
                'job_title'    => $contact->job_title,
                'photo_url'    => $contact->photoUrl(),
                'phones'       => $contact->phones->map(fn ($p) => [
                    'label'      => $p->label,
                    'value'      => $p->value,
                    'value_e164' => $p->value_e164,
                ])->values()->all(),
                'emails'       => $contact->emails->map(fn ($e) => [
                    'label' => $e->label,
                    'value' => $e->value,
                ])->values()->all(),
            ] : null,
            'biolink'  => $matchedUser ? [
                'user_id'    => $matchedUser->id,
                'name'       => $matchedUser->name,
                'handle'     => $matchedUser->publicHandle(),
                'url'        => $bio ? url('/' . $bio->alias) : null,
                'link_id'    => $bio?->id,
                'avatar_url' => self::avatarUrl($matchedUser),
            ] : null,
            // Auto-pulled (read-only) from the matched user's biolink.
            'socials'   => $extracted['socials'],
            'locations' => $extracted['locations'],
            // Candidate comms channels. The mobile client further filters
            // these by Linking.canOpenURL before presenting the chooser.
            'channels'  => self::channels($number, $contact, $extracted['channels']),
            // Owner-entered additions, kept distinct from the auto data.
            'manual'    => $manual,
            'vcard_url' => self::vcardUrl($owner, $contact, $number),
        ];

        // Task #3497: strip the fields/channels this creator has hidden from
        // strangers. No-op for self-lookups and already-saved contacts.
        if ($matchedUser) {
            $payload = ContactPrivacy::applyToPayload(
                $matchedUser,
                $payload,
                (bool) ($resolved['isSelf'] ?? ($owner->id === $matchedUser->id)),
                (bool) ($resolved['isSaved'] ?? ($contact !== null)),
            );
        }

        return $payload;
    }

    /**
     * Pull socials, map locations and messaging channels out of the matched
     * user's active biolink blocks.
     *
     * @return array{socials: array<int,array>, locations: array<int,array>, channels: array<int,array>}
     */
    public static function extractFromBiolink(Link $bio): array
    {
        $blocks = BiolinkBlock::where('link_id', $bio->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $socials = [];
        $locations = [];
        $channels = [];

        foreach ($blocks as $block) {
            $s = is_array($block->settings) ? $block->settings : [];
            $type = (string) $block->type;

            if ($type === 'socials' || $type === 'socials_multi') {
                $platforms = $s['platforms'] ?? [];
                if ($type === 'socials_multi' && isset($s['groups']) && is_array($s['groups'])) {
                    $platforms = [];
                    foreach ($s['groups'] as $group) {
                        $platforms = array_merge($platforms, $group['platforms'] ?? []);
                    }
                }
                foreach ($platforms as $p) {
                    $url = trim((string) ($p['url'] ?? ''));
                    $name = trim((string) ($p['name'] ?? ''));
                    if ($url === '' || $url === '#') continue;
                    $socials[] = [
                        'platform' => $name ?: 'link',
                        'label'    => $name !== '' ? ucfirst($name) : 'Link',
                        'url'      => $url,
                    ];
                }
            } elseif ($type === 'map_location' || $type === 'map') {
                $addr = trim((string) ($s['address'] ?? ''));
                $lat = is_numeric($s['lat'] ?? null) ? (float) $s['lat'] : null;
                $lng = is_numeric($s['lng'] ?? null) ? (float) $s['lng'] : null;
                if ($addr === '' && ($lat === null || $lng === null)) continue;
                $query = ($lat !== null && $lng !== null)
                    ? sprintf('%.6f,%.6f', $lat, $lng)
                    : $addr;
                $locations[] = [
                    'label'    => trim((string) ($s['label'] ?? '')) ?: ($addr ?: 'Location'),
                    'address'  => $addr,
                    'lat'      => $lat,
                    'lng'      => $lng,
                    'maps_url' => 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query),
                ];
            } elseif ($type === 'whatsapp-widget' || $type === 'whatsapp-number-subscribe') {
                $digits = preg_replace('/[^0-9]/', '', (string) ($s['phone'] ?? ''));
                if ($digits === '') continue;
                $channels[] = [
                    'type'       => 'whatsapp',
                    'label'      => 'WhatsApp',
                    'value'      => '+' . $digits,
                    'url'        => 'https://wa.me/' . $digits,
                    'scheme_url' => 'whatsapp://send?phone=' . $digits,
                    'source'     => 'biolink',
                ];
            } elseif ($type === 'whatsapp-channel-subscribe') {
                $url = trim((string) ($s['channel_url'] ?? ''));
                if ($url === '') continue;
                $channels[] = [
                    'type'   => 'whatsapp_channel',
                    'label'  => 'WhatsApp Channel',
                    'value'  => $url,
                    'url'    => $url,
                    'source' => 'biolink',
                ];
            }
        }

        return [
            'socials'   => self::dedupe($socials, 'url'),
            'locations' => $locations,
            'channels'  => self::dedupe($channels, 'url'),
        ];
    }

    /**
     * Assemble the candidate comms channels for the dialed number. The mobile
     * client tests each `scheme_url` (falling back to `url`) via canOpenURL
     * and only shows the apps the device can actually open.
     *
     * @param array<int,array> $biolinkChannels
     * @return array<int,array>
     */
    protected static function channels(string $number, ?Contact $contact, array $biolinkChannels): array
    {
        $channels = [];
        $digits = preg_replace('/[^0-9]/', '', $number);

        if ($number !== '') {
            $channels[] = [
                'type'   => 'phone',
                'label'  => 'Phone call',
                'value'  => $number,
                'url'    => 'tel:' . $number,
                'source' => 'number',
            ];
            $channels[] = [
                'type'   => 'sms',
                'label'  => 'Text message',
                'value'  => $number,
                'url'    => 'sms:' . $number,
                'source' => 'number',
            ];
            if ($digits !== '') {
                $channels[] = [
                    'type'       => 'whatsapp',
                    'label'      => 'WhatsApp',
                    'value'      => $number,
                    'url'        => 'https://wa.me/' . $digits,
                    'scheme_url' => 'whatsapp://send?phone=' . $digits,
                    'source'     => 'number',
                ];
            }
            $channels[] = [
                'type'   => 'facetime_audio',
                'label'  => 'FaceTime Audio',
                'value'  => $number,
                'url'    => 'facetime-audio:' . $number,
                'source' => 'number',
            ];
            $channels[] = [
                'type'   => 'facetime_video',
                'label'  => 'FaceTime',
                'value'  => $number,
                'url'    => 'facetime:' . $number,
                'source' => 'number',
            ];
        }

        // Email from the saved contact (first / primary).
        $email = $contact?->emails->first()?->value;
        if ($email) {
            $channels[] = [
                'type'   => 'email',
                'label'  => 'Email',
                'value'  => $email,
                'url'    => 'mailto:' . $email,
                'source' => 'contact',
            ];
        }

        // Channels surfaced from the biolink (e.g. a different WhatsApp line).
        foreach ($biolinkChannels as $c) {
            $channels[] = $c;
        }

        return self::dedupe($channels, 'url');
    }

    /**
     * Normalize the stored manual_profile JSON into a stable, fully-keyed
     * shape so the clients never have to defend against missing keys.
     *
     * @return array{channels: array<int,array>, socials: array<int,array>, location: ?array}
     */
    public static function normalizeManual($raw): array
    {
        $raw = is_array($raw) ? $raw : [];

        $channels = [];
        foreach (($raw['channels'] ?? []) as $c) {
            if (!is_array($c)) continue;
            $value = trim((string) ($c['value'] ?? ''));
            if ($value === '') continue;
            $type = trim((string) ($c['type'] ?? 'custom')) ?: 'custom';
            $channels[] = [
                'type'   => $type,
                'label'  => trim((string) ($c['label'] ?? '')) ?: ucfirst($type),
                'value'  => $value,
                'url'    => self::channelUrl($type, $value),
                'source' => 'manual',
            ];
        }

        $socials = [];
        foreach (($raw['socials'] ?? []) as $s) {
            if (!is_array($s)) continue;
            $url = trim((string) ($s['url'] ?? ''));
            if ($url === '') continue;
            $platform = trim((string) ($s['platform'] ?? '')) ?: 'link';
            $socials[] = [
                'platform' => $platform,
                'label'    => trim((string) ($s['label'] ?? '')) ?: ucfirst($platform),
                'url'      => $url,
                'source'   => 'manual',
            ];
        }

        $location = null;
        $loc = $raw['location'] ?? null;
        if (is_array($loc)) {
            $addr = trim((string) ($loc['address'] ?? ''));
            $lat = is_numeric($loc['lat'] ?? null) ? (float) $loc['lat'] : null;
            $lng = is_numeric($loc['lng'] ?? null) ? (float) $loc['lng'] : null;
            if ($addr !== '' || ($lat !== null && $lng !== null)) {
                $query = ($lat !== null && $lng !== null) ? sprintf('%.6f,%.6f', $lat, $lng) : $addr;
                $location = [
                    'label'    => trim((string) ($loc['label'] ?? '')) ?: ($addr ?: 'Location'),
                    'address'  => $addr,
                    'lat'      => $lat,
                    'lng'      => $lng,
                    'maps_url' => 'https://www.google.com/maps/search/?api=1&query=' . urlencode($query),
                    'source'   => 'manual',
                ];
            }
        }

        return compact('channels', 'socials', 'location');
    }

    /**
     * Best-effort deep link for an owner-entered channel value.
     */
    protected static function channelUrl(string $type, string $value): string
    {
        $digits = preg_replace('/[^0-9]/', '', $value);
        return match ($type) {
            'phone'          => 'tel:' . $value,
            'sms'            => 'sms:' . $value,
            'whatsapp'       => $digits !== '' ? 'https://wa.me/' . $digits : $value,
            'telegram'       => str_starts_with($value, 'http')
                                    ? $value
                                    : 'https://t.me/' . ltrim($value, '@'),
            'facetime_audio' => 'facetime-audio:' . $value,
            'facetime_video' => 'facetime:' . $value,
            'email'          => 'mailto:' . $value,
            default          => preg_match('~^https?://~i', $value) ? $value : $value,
        };
    }

    protected static function avatarUrl(User $user): ?string
    {
        $avatar = $user->avatar ?? null;
        if (!$avatar) return null;
        if (preg_match('~^https?://~i', $avatar)) return $avatar;
        try {
            return Storage::disk('public')->url($avatar);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Signed, non-expiring shareable URL that streams a .vcf for this
     * contact/number. Signed (not auth) so the owner can share it directly.
     */
    public static function vcardUrl(User $owner, ?Contact $contact, string $number): string
    {
        return URL::signedRoute('user.dialer.vcard', array_filter([
            'u'       => $owner->id,
            'contact' => $contact?->id,
            'number'  => $number !== '' ? $number : null,
        ], fn ($v) => $v !== null));
    }

    /**
     * Drop duplicate rows by a key, preserving first-seen order.
     *
     * @param array<int,array> $rows
     * @return array<int,array>
     */
    protected static function dedupe(array $rows, string $key): array
    {
        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            $k = strtolower(trim((string) ($row[$key] ?? '')));
            if ($k === '' || isset($seen[$k])) continue;
            $seen[$k] = true;
            $out[] = $row;
        }
        return $out;
    }
}
