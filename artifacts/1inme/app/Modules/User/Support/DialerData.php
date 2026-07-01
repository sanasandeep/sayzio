<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\DialerFavorite;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\DialerNumberFlag;
use Illuminate\Support\Collection;

/**
 * Read-side helpers shared by the web + API dialer controllers so the
 * everyday-tool surfaces (favorites, frequent strip, grouped recents,
 * call log, flags) are computed identically across web and mobile.
 */
class DialerData
{
    /** Speed-dial favorites for a user, ordered, enriched. */
    public static function favorites(int $userId): array
    {
        return DialerFavorite::where('user_id', $userId)
            ->with('contact.phones')
            ->orderBy('sort_order')->orderBy('id')
            ->get()
            ->map(fn ($f) => self::transformFavorite($f))
            ->all();
    }

    public static function transformFavorite(DialerFavorite $f): array
    {
        $contact = $f->contact;
        $number = $f->number_e164
            ?: ($contact?->phones->first()?->value_e164 ?: $contact?->phones->first()?->value);
        return [
            'id'          => $f->id,
            'contact_id'  => $f->contact_id,
            'number'      => $number,
            'number_e164' => $f->number_e164 ?: ($contact?->phones->first()?->value_e164),
            'label'       => $f->label ?: ($contact?->nameForDisplay() ?: $number),
            'initials'    => $contact?->initials() ?: self::numberInitials($number),
            'biolink'     => (bool) ($contact?->biolink_user_id),
            'sort_order'  => $f->sort_order,
        ];
    }

    /**
     * Frequently-contacted strip: top numbers by call count over the last
     * 60 days, excluding blocked numbers. Resolved to contacts where known.
     */
    public static function frequent(int $userId, int $limit = 8): array
    {
        $since = now()->subDays(60);
        $rows = DialerLookup::where('user_id', $userId)
            ->where('looked_up_at', '>=', $since)
            ->whereNotNull('number_e164')
            ->selectRaw('number_e164, count(*) as calls, max(looked_up_at) as last_at')
            ->groupBy('number_e164')
            ->orderByDesc('calls')
            ->limit($limit * 2)
            ->get();

        $numbers = $rows->pluck('number_e164')->all();
        $contacts = self::contactsForNumbers($userId, $numbers);
        $flags = self::flagsForNumbers($userId, $numbers);

        return $rows
            ->reject(fn ($r) => (bool) ($flags[$r->number_e164]['is_blocked'] ?? false))
            ->take($limit)
            ->map(function ($r) use ($contacts, $flags) {
                $c = $contacts[$r->number_e164] ?? null;
                $flag = $flags[$r->number_e164] ?? null;
                return [
                    'number'      => $r->number_e164,
                    'number_e164' => $r->number_e164,
                    'contact_id'  => $c?->id,
                    'name'        => $c?->nameForDisplay() ?: $r->number_e164,
                    'initials'    => $c?->initials() ?: self::numberInitials($r->number_e164),
                    'calls'       => (int) $r->calls,
                    'biolink'     => (bool) ($c?->biolink_user_id),
                    'is_spam'     => (bool) ($flag['is_spam'] ?? false),
                ];
            })->values()->all();
    }

    /**
     * Smart grouped recents: repeated calls collapsed by number, with call
     * count, last-contacted, latest outcome/note/tag, and flags. Blocked
     * numbers are kept but badged (so the user can unblock from the list).
     */
    public static function groupedRecents(int $userId, int $limit = 20): array
    {
        $rows = DialerLookup::where('user_id', $userId)
            ->orderByDesc('looked_up_at')
            ->limit(300)
            ->get();

        $numbers = $rows->pluck('number_e164')->filter()->unique()->values()->all();
        $contacts = self::contactsForNumbers($userId, $numbers);
        $flags = self::flagsForNumbers($userId, $numbers);

        $groups = [];
        foreach ($rows as $r) {
            $key = $r->number_e164 ?: ('c:' . $r->contact_id);
            if ($key === 'c:') continue;
            if (!isset($groups[$key])) {
                $c = $r->number_e164 ? ($contacts[$r->number_e164] ?? null) : null;
                $flag = $r->number_e164 ? ($flags[$r->number_e164] ?? null) : null;
                $groups[$key] = [
                    'number'      => $r->number_e164,
                    'number_e164' => $r->number_e164,
                    'contact_id'  => $c?->id ?: $r->contact_id,
                    'name'        => $c?->nameForDisplay() ?: $r->number_e164,
                    'initials'    => $c?->initials() ?: self::numberInitials($r->number_e164),
                    'biolink'     => (bool) ($c?->biolink_user_id),
                    'is_spam'     => (bool) ($flag['is_spam'] ?? false),
                    'is_blocked'  => (bool) ($flag['is_blocked'] ?? false),
                    'calls'       => 0,
                    'last_at'     => optional($r->looked_up_at)->toIso8601String(),
                    'last_human'  => optional($r->looked_up_at)->diffForHumans(),
                    'outcome'     => null,
                    'note'        => null,
                    'tag'         => null,
                ];
            }
            $groups[$key]['calls']++;
            // First seen (newest) row with an outcome/note/tag wins.
            if ($groups[$key]['outcome'] === null && $r->outcome) $groups[$key]['outcome'] = $r->outcome;
            if ($groups[$key]['note'] === null && $r->note)       $groups[$key]['note'] = $r->note;
            if ($groups[$key]['tag'] === null && $r->tag)         $groups[$key]['tag'] = $r->tag;
        }

        return array_slice(array_values($groups), 0, $limit);
    }

    /** Recent activity rows for a specific number/contact, with enrichment. */
    public static function activityFor(int $userId, ?string $e164, ?int $contactId, int $limit = 15): array
    {
        if (!$e164 && !$contactId) return [];
        return DialerLookup::where('user_id', $userId)
            ->where(function ($q) use ($e164, $contactId) {
                if ($e164) $q->where('number_e164', $e164);
                if ($contactId) $q->orWhere('contact_id', $contactId);
            })
            ->orderByDesc('looked_up_at')->limit($limit)->get()
            ->map(fn ($r) => self::transformLog($r))->all();
    }

    /** The next pending (not yet delivered, future) callback for a number/contact. */
    public static function pendingCallback(int $userId, ?string $e164, ?int $contactId): ?array
    {
        if (!$e164 && !$contactId) return null;
        $row = DialerLookup::where('user_id', $userId)
            ->whereNotNull('callback_at')
            ->whereNull('callback_notified_at')
            ->where(function ($q) use ($e164, $contactId) {
                if ($e164) $q->where('number_e164', $e164);
                if ($contactId) $q->orWhere('contact_id', $contactId);
            })
            ->orderBy('callback_at')->first();
        return $row ? self::transformLog($row) : null;
    }

    public static function isFavorite(int $userId, ?string $e164, ?int $contactId): bool
    {
        return DialerFavorite::where('user_id', $userId)
            ->where(function ($q) use ($e164, $contactId) {
                if ($contactId) $q->where('contact_id', $contactId);
                if ($e164) $q->orWhere('number_e164', $e164);
            })->exists();
    }

    public static function transformLog(DialerLookup $r): array
    {
        return [
            'id'          => $r->id,
            'number'      => $r->number_e164,
            'number_e164' => $r->number_e164,
            'contact_id'  => $r->contact_id,
            'outcome'     => $r->outcome,
            'note'        => $r->note,
            'tag'         => $r->tag,
            'callback_at' => optional($r->callback_at)->toIso8601String(),
            'at'          => optional($r->looked_up_at)->toIso8601String(),
            'at_human'    => optional($r->looked_up_at)->diffForHumans(),
        ];
    }

    /**
     * Map of number_e164 => Contact (with phones + biolinkUser) for the given
     * E.164 numbers, scoped to the user.
     *
     * @param array<int, string|null> $numbers
     * @return array<string, Contact>
     */
    public static function contactsForNumbers(int $userId, array $numbers): array
    {
        $numbers = array_values(array_filter(array_unique($numbers)));
        if (empty($numbers)) return [];
        $contacts = Contact::where('user_id', $userId)
            ->whereHas('phones', fn ($q) => $q->whereIn('value_e164', $numbers))
            ->with(['phones', 'biolinkUser'])
            ->get();
        $map = [];
        foreach ($contacts as $c) {
            foreach ($c->phones as $p) {
                if ($p->value_e164 && in_array($p->value_e164, $numbers, true) && !isset($map[$p->value_e164])) {
                    $map[$p->value_e164] = $c;
                }
            }
        }
        return $map;
    }

    /**
     * Map of number_e164 => ['is_spam'=>bool,'is_blocked'=>bool].
     *
     * @param array<int, string|null> $numbers
     * @return array<string, array{is_spam:bool,is_blocked:bool}>
     */
    public static function flagsForNumbers(int $userId, array $numbers): array
    {
        $numbers = array_values(array_filter(array_unique($numbers)));
        if (empty($numbers)) return [];
        return DialerNumberFlag::where('user_id', $userId)
            ->whereIn('number_e164', $numbers)
            ->get()
            ->mapWithKeys(fn ($f) => [$f->number_e164 => [
                'is_spam'    => (bool) $f->is_spam,
                'is_blocked' => (bool) $f->is_blocked,
            ]])->all();
    }

    /**
     * Flags keyed by E.164, resolved from a collection of contacts (uses each
     * contact's first phone number).
     *
     * @param Collection<int, Contact> $contacts
     * @return array<string, array{is_spam:bool,is_blocked:bool}>
     */
    public static function flagsForContacts(int $userId, Collection $contacts): array
    {
        $numbers = $contacts->map(fn ($c) => $c->phones->first()?->value_e164 ?: $c->phones->first()?->value)
            ->filter()->all();
        return self::flagsForNumbers($userId, $numbers);
    }

    private static function numberInitials(?string $number): string
    {
        if (!$number) return '#';
        $digits = preg_replace('/\D+/', '', $number);
        return $digits !== '' ? substr($digits, -2) : '#';
    }

    /**
     * A cheap, monotonic signature of everything the dialer syncs across
     * devices (favorites, recents/call-log, spam/block flags). Any add,
     * remove, reorder, flag toggle or new call changes the signature, so a
     * poller can detect "something changed" without diffing the payload.
     *
     * Favorites/flags carry timestamps (reorder & toggle bump updated_at);
     * the call log is append-only so max(id) advances on every new call. We
     * fold in counts too so deletes are caught even if timestamps regress.
     */
    public static function liveSignature(int $userId): string
    {
        $lookupMaxId = (int) DialerLookup::where('user_id', $userId)->max('id');

        $favCount   = (int) DialerFavorite::where('user_id', $userId)->count();
        $favUpdated = DialerFavorite::where('user_id', $userId)->max('updated_at');
        $favTs      = $favUpdated ? strtotime((string) $favUpdated) : 0;

        $flagCount   = (int) DialerNumberFlag::where('user_id', $userId)->count();
        $flagUpdated = DialerNumberFlag::where('user_id', $userId)->max('updated_at');
        $flagTs      = $flagUpdated ? strtotime((string) $flagUpdated) : 0;

        return implode('.', [$lookupMaxId, $favCount, $favTs, $flagCount, $flagTs]);
    }

    /**
     * Pollable live-sync state. Returns the current cursor and — only when it
     * differs from the caller's `$since` cursor — the fresh favorites,
     * frequent strip and grouped recents. Mirrors the heatmap "live" endpoint
     * pattern (poll with a cursor; no sockets).
     *
     * @return array{cursor:string,changed:bool,favorites?:array,frequent?:array,recents?:array}
     */
    public static function liveState(int $userId, ?string $since = null): array
    {
        $cursor  = self::liveSignature($userId);
        $changed = ($since === null) || ($since !== $cursor);

        $out = ['cursor' => $cursor, 'changed' => $changed];
        if ($changed) {
            $out['favorites'] = self::favorites($userId);
            $out['frequent']  = self::frequent($userId);
            $out['recents']   = self::groupedRecents($userId);
        }

        return $out;
    }
}
