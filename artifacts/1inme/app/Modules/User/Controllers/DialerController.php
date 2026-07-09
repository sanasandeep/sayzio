<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\DialerFavorite;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\DialerNumberFlag;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Support\DialerChannels;
use App\Modules\User\Support\DialerData;
use App\Modules\User\Support\DialerIdentity;
use App\Modules\User\Support\DialerSuggestions;
use App\Modules\User\Support\DialerT9;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DialerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $contacts = collect();

        if ($q !== '') {
            $contacts = $this->searchContacts($user->id, $q);
        }

        // JSON branch — used by the dialer's live filter (debounced fetch as
        // the user types on the keypad or in the search box). Includes T9
        // smart-dial matching so a digit sequence resolves both numbers and
        // keypad-spelled names.
        if ($request->wantsJson()) {
            $flags = DialerData::flagsForContacts($user->id, $contacts);
            return response()->json([
                'q'       => $q,
                'matches' => $contacts->map(function ($c) use ($flags) {
                    $first = $c->phones->first();
                    $e164 = $first?->value_e164 ?: $first?->value;
                    $flag = $e164 ? ($flags[$e164] ?? null) : null;
                    return [
                        'id'          => $c->id,
                        'name'        => $c->nameForDisplay(),
                        'initials'    => $c->initials(),
                        'phone'       => $first?->value,
                        'phone_e164'  => $first?->value_e164,
                        'biolink'     => (bool) $c->biolink_user_id,
                        'is_spam'     => (bool) ($flag['is_spam'] ?? false),
                        'is_blocked'  => (bool) ($flag['is_blocked'] ?? false),
                        'profile_url' => $first
                            ? route('user.dialer.profile', ['number' => $e164, 'contact' => $c->id])
                            : route('user.contacts.show', $c),
                    ];
                })->values(),
            ]);
        }

        $favorites  = DialerData::favorites($user->id);
        $frequent   = DialerData::frequent($user->id);
        $recent     = DialerData::groupedRecents($user->id);
        $liveCursor = DialerData::liveSignature($user->id);

        // Pass the LIST-shaped catalog (each row carries its own `key`) so the
        // view JS can `.map`/`.find` over it; catalog() returns an associative
        // key=>meta map, which would break those array calls.
        $channelPayload = DialerChannels::payloadFor($user);
        $channelCatalog = $channelPayload['catalog'];
        $channelEnabled = $channelPayload['enabled'];

        return view('user.dialer.index', compact(
            'q', 'contacts', 'favorites', 'frequent', 'recent', 'liveCursor',
            'channelCatalog', 'channelEnabled'
        ));
    }

    /**
     * Save the user's preferred messaging channels for the dialer (which of
     * call / SMS / WhatsApp / Telegram / Signal / Viber the one-tap channel
     * rows show, and in what order). Stored on the `settings` JSON.
     */
    public function channelsUpdate(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'channels'   => ['present', 'array'],
            'channels.*' => ['string'],
        ]);

        $enabled = DialerChannels::sanitize($data['channels']);

        $settings = $user->settings ?? [];
        $settings['dialer_channels'] = $enabled;
        $user->settings = $settings;
        $user->save();
        DialerChannels::forget($user->id);

        return response()->json(['data' => ['enabled' => $enabled]]);
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $number = trim((string) ($request->query('number') ?? ''));
        $contactId = $request->query('contact');

        $resolved = DialerIdentity::resolve($user, $contactId ? (int) $contactId : null, $number);
        $contact = $resolved['contact'];
        $matchedUser = $resolved['matchedUser'];
        $bio = $resolved['bio'];
        $number = $resolved['number'];
        $needle = $resolved['needle'];

        if ($number === '' && !$contact) {
            return redirect()->route('user.dialer.index')->with('error', 'No number specified.');
        }

        // Record the lookup (best-effort).
        if ($needle) {
            DialerLookup::create([
                'user_id'      => $user->id,
                'number_e164'  => $needle,
                'contact_id'   => $contact?->id,
                'looked_up_at' => now(),
            ]);
        }

        $payload = DialerIdentity::payload($user, $resolved);

        // Fold per-user spam/block flags + normalized number into the identity payload.
        $flag = $needle ? DialerNumberFlag::where('user_id', $user->id)->where('number_e164', $needle)->first() : null;
        $payload['number_e164'] = $needle;
        $payload['is_spam'] = (bool) ($flag?->is_spam);
        $payload['is_blocked'] = (bool) ($flag?->is_blocked);

        // JSON for mobile consumers.
        if ($request->wantsJson()) {
            return response()->json(['data' => $payload]);
        }

        $recent = DialerData::activityFor($user->id, $needle, $contact?->id);
        $callback = DialerData::pendingCallback($user->id, $needle, $contact?->id);
        $isFavorite = DialerData::isFavorite($user->id, $needle, $contact?->id);

        return view('user.dialer.profile', compact(
            'payload', 'contact', 'matchedUser', 'bio', 'number', 'recent', 'callback', 'isFavorite'
        ));
    }

    /**
     * Pollable live-sync endpoint (no sockets). The dialer page polls this
     * with its last cursor; when another device changes favorites / flags /
     * call-log, the cursor advances and the fresh lists come back so the page
     * re-renders in near-real time. Mirrors the API `/dialer/live` endpoint.
     */
    public function live(Request $request)
    {
        $since = $request->query('since');
        $since = is_string($since) && $since !== '' ? $since : null;

        return response()->json(['data' => DialerData::liveState($request->user()->id, $since)]);
    }

    // ── Favorites (speed dial) ────────────────────────────────────────

    public function favoriteStore(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'contact_id'  => ['nullable', 'integer'],
            'number'      => ['nullable', 'string', 'max:40'],
            'label'       => ['nullable', 'string', 'max:191'],
        ]);

        $contact = null;
        if (!empty($data['contact_id'])) {
            $contact = Contact::where('user_id', $user->id)->with('phones')->find($data['contact_id']);
            if (!$contact) return response()->json(['error' => ['message' => 'Contact not found', 'code' => 'not_found']], 404);
        }

        $e164 = null;
        if (!empty($data['number'])) {
            $e164 = ContactPhone::normalize($data['number']);
        } elseif ($contact) {
            $e164 = $contact->phones->first()?->value_e164 ?: $contact->phones->first()?->value;
        }

        if (!$contact && !$e164) {
            return response()->json(['error' => ['message' => 'Nothing to favorite', 'code' => 'invalid']], 422);
        }

        // Dedupe by contact or number.
        $existing = DialerFavorite::where('user_id', $user->id)
            ->when($contact, fn ($q) => $q->where('contact_id', $contact->id))
            ->when(!$contact && $e164, fn ($q) => $q->where('number_e164', $e164)->whereNull('contact_id'))
            ->first();
        if ($existing) {
            return response()->json(['data' => ['favorite' => DialerData::transformSingleFavorite($existing, $user->id), 'already' => true]]);
        }

        $max = (int) DialerFavorite::where('user_id', $user->id)->max('sort_order');
        $fav = DialerFavorite::create([
            'user_id'     => $user->id,
            'contact_id'  => $contact?->id,
            'number_e164' => $e164,
            'label'       => $data['label'] ?? $contact?->nameForDisplay(),
            'sort_order'  => $max + 1,
        ]);

        return response()->json(['data' => ['favorite' => DialerData::transformSingleFavorite($fav, $user->id)]]);
    }

    public function favoriteDestroy(Request $request, int $favorite)
    {
        $user = $request->user();
        $fav = DialerFavorite::where('user_id', $user->id)->find($favorite);
        if (!$fav) return response()->json(['error' => ['message' => 'Not found', 'code' => 'not_found']], 404);
        $fav->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function favoritesReorder(Request $request)
    {
        $user = $request->user();
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        $ids = DialerFavorite::where('user_id', $user->id)->pluck('id')->all();
        $pos = 0;
        foreach ($data['order'] as $id) {
            if (!in_array((int) $id, $ids, true)) continue;
            DialerFavorite::where('user_id', $user->id)->where('id', $id)->update(['sort_order' => $pos++]);
        }
        return response()->json(['data' => ['favorites' => DialerData::favorites($user->id)]]);
    }

    // ── Spam / block flags ────────────────────────────────────────────

    public function flag(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'number'  => ['required', 'string', 'max:40'],
            'is_spam'    => ['nullable', 'boolean'],
            'is_blocked' => ['nullable', 'boolean'],
        ]);
        $e164 = ContactPhone::normalize($data['number']);
        if (!$e164) return response()->json(['error' => ['message' => 'Invalid number', 'code' => 'invalid']], 422);

        $flag = DialerNumberFlag::firstOrNew(['user_id' => $user->id, 'number_e164' => $e164]);
        if ($request->has('is_spam'))    $flag->is_spam = (bool) $data['is_spam'];
        if ($request->has('is_blocked')) $flag->is_blocked = (bool) $data['is_blocked'];
        $flag->save();

        return response()->json(['data' => [
            'number_e164' => $e164,
            'is_spam'     => (bool) $flag->is_spam,
            'is_blocked'  => (bool) $flag->is_blocked,
        ]]);
    }

    // ── Call log: outcome / note / tag (mini-CRM) ─────────────────────

    public function logCall(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'number'     => ['required', 'string', 'max:40'],
            'contact_id' => ['nullable', 'integer'],
            'outcome'    => ['nullable', 'string', 'in:called,messaged,no_answer,voicemail,busy,wrong_number,completed'],
            'note'       => ['nullable', 'string', 'max:2000'],
            'tag'        => ['nullable', 'string', 'max:50'],
        ]);
        $e164 = ContactPhone::normalize($data['number']);
        if (!$e164) return response()->json(['error' => ['message' => 'Invalid number', 'code' => 'invalid']], 422);

        $contactId = null;
        if (!empty($data['contact_id'])) {
            $contactId = Contact::where('user_id', $user->id)->where('id', $data['contact_id'])->value('id');
        }

        $log = DialerLookup::create([
            'user_id'      => $user->id,
            'number_e164'  => $e164,
            'contact_id'   => $contactId,
            'looked_up_at' => now(),
            'outcome'      => $data['outcome'] ?? null,
            'note'         => $data['note'] ?? null,
            'tag'          => $data['tag'] ?? null,
        ]);

        return response()->json(['data' => ['log' => DialerData::transformLog($log)]]);
    }

    // ── Callback reminders ────────────────────────────────────────────

    public function callbackSet(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'number'      => ['required', 'string', 'max:40'],
            'contact_id'  => ['nullable', 'integer'],
            'callback_at' => ['required', 'date'],
            'note'        => ['nullable', 'string', 'max:2000'],
        ]);
        $e164 = ContactPhone::normalize($data['number']);
        if (!$e164) return response()->json(['error' => ['message' => 'Invalid number', 'code' => 'invalid']], 422);

        $when = Carbon::parse($data['callback_at']);
        if ($when->isPast()) {
            return response()->json(['error' => ['message' => 'Pick a future time', 'code' => 'invalid']], 422);
        }

        $contactId = null;
        if (!empty($data['contact_id'])) {
            $contactId = Contact::where('user_id', $user->id)->where('id', $data['contact_id'])->value('id');
        }

        $log = DialerLookup::create([
            'user_id'              => $user->id,
            'number_e164'          => $e164,
            'contact_id'           => $contactId,
            'looked_up_at'         => now(),
            'note'                 => $data['note'] ?? null,
            'callback_at'          => $when,
            'callback_notified_at' => null,
        ]);

        return response()->json(['data' => ['callback' => DialerData::transformLog($log)]]);
    }

    public function callbackClear(Request $request, int $log)
    {
        $user = $request->user();
        $row = DialerLookup::where('user_id', $user->id)->whereNotNull('callback_at')->find($log);
        if (!$row) return response()->json(['error' => ['message' => 'Not found', 'code' => 'not_found']], 404);
        $row->callback_at = null;
        $row->callback_notified_at = null;
        $row->save();
        return response()->json(['data' => ['cleared' => true]]);
    }

    /**
     * Pre-query suggestions for the dialer search empty state. Returns the
     * same grouped {total, groups[]} contract as search() so the web JS
     * renderer (renderGroups) can display suggestions without extra code.
     */
    public function suggestions(Request $request)
    {
        return response()->json(['data' => DialerSuggestions::forUser($request->user())]);
    }

    /**
     * Universal finder — grouped search across Contacts, People, My links,
     * Followed and Workspaces (see DialerSearch for the shared contract). Fed
     * by BOTH keypad modes (T9 grid + alphanumeric keyboard) and the advanced
     * search box; also powers the REST + mobile surfaces via the same class.
     */
    public function search(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $filters = [
            'filter'      => $request->query('filter'),
            'tag'         => $request->query('tag'),
            'has_biolink' => $request->boolean('has_biolink'),
        ];

        $page     = max(0, (int) $request->query('page', 0));
        $perGroup = max(1, min((int) $request->query('per_group', 12), 60));

        return response()->json([
            'data' => \App\Modules\User\Support\DialerSearch::universal($user, $q, $filters, $page, $perGroup),
        ]);
    }

    /**
     * Shared contact search supporting T9 smart-dial. A pure digit sequence
     * matches phone numbers (substring) OR keypad-spelled names; free text
     * matches names + numbers as before.
     *
     * @return \Illuminate\Support\Collection<int, Contact>
     */
    private function searchContacts(int $userId, string $q)
    {
        $needle = '%' . $q . '%';
        $phoneNeedle = '%' . ContactPhone::normalize($q) . '%';

        // T9: if the user typed a digit sequence, also surface contacts whose
        // name spells the digits on the keypad. Matched in SQL (against the T9
        // encoding of the name) so it never loads up to 300 extra contacts into
        // PHP to loop over with DialerT9::matches().
        $seq = DialerT9::isDigitSequence($q) ? (string) preg_replace('/\D+/', '', $q) : '';

        $base = Contact::where('user_id', $userId)
            ->with(['phones', 'biolinkUser'])
            ->where(function ($w) use ($needle, $phoneNeedle, $seq) {
                $w->where('display_name', 'ilike', $needle)
                  ->orWhere('given_name', 'ilike', $needle)
                  ->orWhere('family_name', 'ilike', $needle)
                  ->orWhereHas('phones', fn ($q2) => $q2->where('value_e164', 'ilike', $phoneNeedle));
                if (strlen($seq) >= 2) {
                    $w->orWhereRaw(DialerT9::sqlEncode(DialerT9::CONTACT_NAME_SQL) . ' LIKE ?', ['%' . $seq . '%']);
                }
            })
            ->orderBy('display_name')
            ->limit(50)->get();

        return $base;
    }

    /**
     * Persist the owner's manual channels / socials / location for a contact.
     * Manual additions are kept deliberately distinct from the auto-pulled
     * biolink data; this only ever writes the `manual_profile` column.
     */
    public function updateManual(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'contact_id'         => ['required', 'integer'],
            'channels'           => ['array'],
            'channels.*.type'    => ['nullable', 'string', 'max:40'],
            'channels.*.label'   => ['nullable', 'string', 'max:80'],
            'channels.*.value'   => ['nullable', 'string', 'max:255'],
            'socials'            => ['array'],
            'socials.*.platform' => ['nullable', 'string', 'max:40'],
            'socials.*.label'    => ['nullable', 'string', 'max:80'],
            'socials.*.url'      => ['nullable', 'string', 'max:255'],
            'location'           => ['nullable', 'array'],
            'location.label'     => ['nullable', 'string', 'max:120'],
            'location.address'   => ['nullable', 'string', 'max:255'],
            'location.lat'       => ['nullable', 'numeric'],
            'location.lng'       => ['nullable', 'numeric'],
        ]);

        $contact = Contact::where('user_id', $user->id)->where('id', $data['contact_id'])->firstOrFail();

        $clean = DialerIdentity::normalizeManual([
            'channels' => $data['channels'] ?? [],
            'socials'  => $data['socials'] ?? [],
            'location' => $data['location'] ?? null,
        ]);

        // Store the raw editable fields (not the derived url/source/maps_url).
        $contact->manual_profile = [
            'channels' => array_map(fn ($c) => [
                'type'  => $c['type'],
                'label' => $c['label'],
                'value' => $c['value'],
            ], $clean['channels']),
            'socials'  => array_map(fn ($s) => [
                'platform' => $s['platform'],
                'label'    => $s['label'],
                'url'      => $s['url'],
            ], $clean['socials']),
            'location' => $clean['location'] ? [
                'label'   => $clean['location']['label'],
                'address' => $clean['location']['address'],
                'lat'     => $clean['location']['lat'],
                'lng'     => $clean['location']['lng'],
            ] : null,
        ];
        $contact->save();

        if ($request->wantsJson()) {
            return response()->json(['data' => ['manual' => $clean]]);
        }

        return back()->with('status', 'Profile updated.');
    }
}
