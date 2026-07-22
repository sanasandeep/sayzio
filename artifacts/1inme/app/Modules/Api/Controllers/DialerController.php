<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\DialerFavorite;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\DialerNumberFlag;
use App\Modules\User\Models\LinkedIdentifier;
use App\Modules\User\Models\Link;
use App\Modules\User\Support\ContactPrivacy;
use App\Modules\User\Support\DialerChannels;
use App\Modules\User\Support\DialerData;
use App\Modules\User\Support\DialerIdentity;
use App\Modules\User\Support\DialerReachability;
use App\Modules\User\Support\DialerSearch;
use App\Modules\User\Support\DialerSuggestions;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the mobile Dialer page. Mirrors the web everyday-tool surfaces:
 * caller-ID lookup, favorites (speed-dial), grouped recents + frequent
 * strip, per-user spam/block flags, call log (outcome/note/tag), and
 * call-back reminders. All responses use the unified {data}/{error}
 * envelope and read-side helpers are shared with the web controller via
 * DialerData so the two surfaces never drift.
 */
class DialerController extends Controller
{
    use ApiResponses;

    /**
     * Pre-query suggestions for the dialer empty state. Returns the same
     * grouped {total, groups[]} envelope as search() so the mobile renderer
     * needs no changes. Grouped by: Favorites, Recents, New followers,
     * Following, New leads (subscribers + form submissions).
     */
    public function suggestions(Request $request)
    {
        return $this->ok(DialerSuggestions::forUser($request->user()));
    }

    /**
     * Universal finder — grouped search across Contacts, People, My links,
     * Followed and Workspaces. Mirrors the web dialer 1:1 via the shared
     * DialerSearch contract so web/API/mobile never drift.
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

        return $this->ok(DialerSearch::universal($user, $q, $filters));
    }

    public function lookup(Request $request)
    {
        $data = $request->validate([
            'number_e164' => ['required', 'string', 'regex:/^\+[1-9]\d{6,14}$/'],
        ]);
        $userId = $request->user()->id;
        $e164 = $data['number_e164'];

        $contact = null;
        $matchedPhone = null;
        if (Schema::hasTable('contact_phones')) {
            $cp = ContactPhone::where('value_e164', $e164)
                ->whereHas('contact', fn ($q) => $q->where('user_id', $userId))
                ->with('contact.biolinkUser')
                ->first();
            $contact = $cp?->contact;
            $matchedPhone = $cp?->value_e164;
        }

        // Caller-ID: resolve a Sayzio biolink owner from the contact or by
        // phone identity lookup, even when the number isn't saved.
        $matchedUser = $contact?->biolinkUser;
        if (!$matchedUser) {
            $matchedUser = LinkedIdentifier::resolveUser('phone', $e164);
        }
        // Never enrich caller-ID with a creator the searcher can't reach right
        // now (suspended/deactivated, or one that has blocked the searcher).
        $matchedUser = DialerReachability::enrichableCreator($userId, $matchedUser);
        $bio = null;
        if ($matchedUser) {
            $bio = Link::where('user_id', $matchedUser->id)
                ->whereIn('type', Link::BIOLINK_FAMILY)->where('is_active', true)
                ->orderByDesc('id')->first();
        }

        $flag = DialerNumberFlag::where('user_id', $userId)->where('number_e164', $e164)->first();

        // Task #3497: this quick lookup only ever echoes the number the
        // caller already dialed/typed and a bare name/handle — no channels,
        // socials or locations — so there's nothing here for a creator's
        // contact-privacy prefs to strip. The richer profile() endpoint
        // below (backed by DialerIdentity::payload) is where those apply.

        DialerLookup::create([
            'user_id'      => $userId,
            'number_e164'  => $e164,
            'contact_id'   => $contact?->id,
            'looked_up_at' => now(),
        ]);

        return $this->ok([
            'number_e164' => $e164,
            'is_spam'     => (bool) ($flag?->is_spam),
            'is_blocked'  => (bool) ($flag?->is_blocked),
            'is_favorite' => DialerData::isFavorite($userId, $e164, $contact?->id),
            'contact'     => $contact ? [
                'id'           => $contact->id,
                'display_name' => $contact->display_name,
                'organization' => $contact->organization,
                'phone'        => $matchedPhone,
            ] : null,
            'biolink'     => $matchedUser ? [
                'user_id' => $matchedUser->id,
                'name'    => $matchedUser->name,
                'handle'  => $matchedUser->publicHandle(),
                'url'     => $bio ? url('/' . $bio->alias) : null,
                'link_id' => $bio?->id,
            ] : null,
            'activity'    => DialerData::activityFor($userId, $e164, $contact?->id),
        ]);
    }

    /**
     * Rich Identity Profile for a number / contact: matched Sayzio user,
     * auto-pulled socials / locations / channels from their biolink, the
     * owner's manual additions, and a shareable Export-vCard URL.
     */
    public function profile(Request $request)
    {
        $data = $request->validate([
            'number'  => ['nullable', 'string', 'max:40'],
            'contact' => ['nullable', 'integer'],
        ]);

        $user = $request->user();
        $number = trim((string) ($data['number'] ?? ''));
        $contactId = isset($data['contact']) ? (int) $data['contact'] : null;

        if ($number === '' && !$contactId) {
            return $this->fail('Provide a number or contact.', 422, 'invalid_request');
        }

        $resolved = DialerIdentity::resolve($user, $contactId, $number);

        if ($resolved['number'] === '' && !$resolved['contact']) {
            return $this->fail('Nothing to resolve.', 404, 'not_found');
        }

        // Record the lookup (best-effort) when we have a normalized number.
        if ($resolved['needle']) {
            DialerLookup::create([
                'user_id'      => $user->id,
                'number_e164'  => $resolved['needle'],
                'contact_id'   => $resolved['contact']?->id,
                'looked_up_at' => now(),
            ]);
        }

        return $this->ok(DialerIdentity::payload($user, $resolved));
    }

    /**
     * Full paginated call/lookup history with filters.
     * Query params: outcome, tag, q (text search), page (0-based, 25/page).
     * Also returns recents + frequent for the legacy home tab.
     */
    public function history(Request $request)
    {
        $user    = $request->user();
        $userId  = $user->id;

        $filters = [
            'outcome' => $request->query('outcome') ?: null,
            'tag'     => $request->query('tag')     ?: null,
            'q'       => $request->query('q')       ?: null,
        ];
        $page = max(0, (int) $request->query('page', 0));

        // Full-history mode (any filter or explicit page > 0 or ?full=1)
        if ($request->boolean('full') || $page > 0 || array_filter($filters)) {
            return $this->ok(DialerData::paginatedHistory($userId, $filters, $page));
        }

        // Legacy home-tab mode: grouped recents + frequent (backward-compatible).
        return $this->ok([
            'recents'  => DialerData::groupedRecents($userId),
            'frequent' => DialerData::frequent($userId),
        ]);
    }

    /** Delete a single history entry scoped to the authenticated user. */
    public function historyDestroy(Request $request, int $id)
    {
        $row = DialerLookup::where('user_id', $request->user()->id)->find($id);
        if (!$row) return $this->notFound('History entry not found');
        $row->delete();
        return $this->ok(['deleted' => true]);
    }

    /** Clear all (or filtered) history entries for the user. */
    public function historyClear(Request $request)
    {
        $userId  = $request->user()->id;
        $outcome = $request->query('outcome') ?: null;
        $tag     = $request->query('tag')     ?: null;

        $query = DialerLookup::where('user_id', $userId);
        if ($outcome) $query->where('outcome', $outcome);
        if ($tag)     $query->where('tag', $tag);
        $count = $query->count();
        $query->delete();

        return $this->ok(['cleared' => $count]);
    }

    /** Inline update of outcome / note / tag for a history entry. */
    public function historyUpdate(Request $request, int $id)
    {
        $data = $request->validate([
            'outcome' => ['nullable', 'string', 'in:called,messaged,no_answer,voicemail,busy,wrong_number,completed'],
            'note'    => ['nullable', 'string', 'max:2000'],
            'tag'     => ['nullable', 'string', 'max:50'],
        ]);

        $row = DialerLookup::where('user_id', $request->user()->id)->find($id);
        if (!$row) return $this->notFound('History entry not found');

        if (array_key_exists('outcome', $data)) $row->outcome = $data['outcome'];
        if (array_key_exists('note',    $data)) $row->note    = $data['note'];
        if (array_key_exists('tag',     $data)) $row->tag     = $data['tag'];
        $row->save();

        return $this->ok(['log' => DialerData::transformLog($row)]);
    }

    /**
     * The user's preferred dialer channels (which of call / SMS / WhatsApp /
     * Telegram / Signal / Viber the one-tap channel row shows) plus the full
     * catalog so mobile can render the picker. Shared source with the web
     * dialer via DialerChannels so the surfaces never drift.
     */
    public function channels(Request $request)
    {
        return $this->ok(DialerChannels::payloadFor($request->user()));
    }

    public function updateChannels(Request $request)
    {
        $data = $request->validate([
            'channels'   => ['present', 'array'],
            'channels.*' => ['string'],
        ]);

        $user = $request->user();
        $enabled = DialerChannels::sanitize($data['channels']);

        $settings = $user->settings ?? [];
        $settings['dialer_channels'] = $enabled;
        $user->settings = $settings;
        $user->save();
        DialerChannels::forget($user->id);

        return $this->ok(DialerChannels::payloadFor($user));
    }

    /**
     * Task #3497 — the caller's current contact-privacy preferences (what
     * strangers may see about them via caller-ID / search) plus the
     * un-shareable channel/social candidates for a picker UI. Mobile parity
     * for the web Settings > Contact Privacy tab.
     */
    public function contactPrivacy(Request $request)
    {
        $user = $request->user();

        return $this->ok([
            'prefs'      => ContactPrivacy::forUser($user),
            'candidates' => ContactPrivacy::shareableCandidatesFor($user),
        ]);
    }

    /** Save the caller's explicit contact-privacy choices. Partial updates only touch the fields present. */
    public function updateContactPrivacy(Request $request)
    {
        $data = $request->validate([
            'share_phone'      => ['nullable', 'string', 'in:,0,1'],
            'share_email'      => ['nullable', 'string', 'in:,0,1'],
            'share_location'   => ['nullable', 'string', 'in:,0,1'],
            'share_socials'    => ['nullable', 'string', 'in:,0,1'],
            'hidden_channels'   => ['sometimes', 'array'],
            'hidden_channels.*' => ['string'],
        ]);

        $user = $request->user();
        $updates = [];
        foreach (['share_phone', 'share_email', 'share_location', 'share_socials'] as $field) {
            if ($request->has($field)) {
                $value = $data[$field] ?? null;
                $updates[$field] = ($value === '' || $value === null) ? null : (bool) $value;
            }
        }
        if (array_key_exists('hidden_channels', $data)) {
            $updates['hidden_channels'] = $data['hidden_channels'];
        }

        $prefs = ContactPrivacy::updateFor($user, $updates);
        ContactPrivacy::markConfigured($user);

        return $this->ok([
            'prefs'      => $prefs,
            'candidates' => ContactPrivacy::shareableCandidatesFor($user),
        ]);
    }

    /**
     * Pollable live-sync endpoint (no sockets). The mobile dialer polls this
     * with its last cursor; the cursor advances whenever favorites, spam/block
     * flags or the call log change on any device, and the fresh favorites +
     * frequent + recents come back only when something actually changed.
     * Mirrors the heatmap "live" cursor pattern.
     */
    public function live(Request $request)
    {
        $since = $request->query('since');
        $since = is_string($since) && $since !== '' ? $since : null;

        return $this->ok(DialerData::liveState($request->user()->id, $since));
    }

    // ── Favorites (speed dial) ────────────────────────────────────────

    public function favorites(Request $request)
    {
        return $this->ok(['items' => DialerData::favorites($request->user()->id)]);
    }

    public function addFavorite(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'contact_id' => ['nullable', 'integer'],
            'number'     => ['nullable', 'string', 'max:40'],
            'label'      => ['nullable', 'string', 'max:191'],
        ]);

        $contact = null;
        if (!empty($data['contact_id'])) {
            $contact = Contact::where('user_id', $userId)->with('phones')->find($data['contact_id']);
            if (!$contact) return $this->notFound('Contact not found');
        }

        $e164 = null;
        if (!empty($data['number'])) {
            $e164 = ContactPhone::normalize($data['number']);
        } elseif ($contact) {
            $e164 = $contact->phones->first()?->value_e164 ?: $contact->phones->first()?->value;
        }
        if (!$contact && !$e164) {
            return $this->fail('Nothing to favorite', 422, 'invalid');
        }

        $existing = DialerFavorite::where('user_id', $userId)
            ->when($contact, fn ($q) => $q->where('contact_id', $contact->id))
            ->when(!$contact && $e164, fn ($q) => $q->where('number_e164', $e164)->whereNull('contact_id'))
            ->first();
        if ($existing) {
            return $this->ok(['favorite' => DialerData::transformSingleFavorite($existing, $userId), 'already' => true]);
        }

        $max = (int) DialerFavorite::where('user_id', $userId)->max('sort_order');
        $fav = DialerFavorite::create([
            'user_id'     => $userId,
            'contact_id'  => $contact?->id,
            'number_e164' => $e164,
            'label'       => $data['label'] ?? $contact?->nameForDisplay(),
            'sort_order'  => $max + 1,
        ]);

        return $this->created(['favorite' => DialerData::transformSingleFavorite($fav, $userId)]);
    }

    public function removeFavorite(Request $request, int $id)
    {
        $fav = DialerFavorite::where('user_id', $request->user()->id)->find($id);
        if (!$fav) return $this->notFound('Favorite not found');
        $fav->delete();
        return $this->ok(['deleted' => true]);
    }

    public function reorderFavorites(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        $ids = DialerFavorite::where('user_id', $userId)->pluck('id')->all();
        $pos = 0;
        foreach ($data['order'] as $id) {
            if (!in_array((int) $id, $ids, true)) continue;
            DialerFavorite::where('user_id', $userId)->where('id', $id)->update(['sort_order' => $pos++]);
        }
        return $this->ok(['items' => DialerData::favorites($userId)]);
    }

    /**
     * Assign a speed-dial digit (1–9) to a favorite. Clears any prior owner
     * of that digit so the slot is never double-booked.
     */
    public function assignSpeedDial(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'favorite_id' => ['required', 'integer'],
            'digit'       => ['required', 'integer', 'min:1', 'max:9'],
        ]);

        $fav = DialerFavorite::where('user_id', $userId)->find($data['favorite_id']);
        if (!$fav) return $this->notFound('Favorite not found');

        // Release any other favorite that already owns this digit.
        DialerFavorite::where('user_id', $userId)
            ->where('speed_dial_digit', $data['digit'])
            ->where('id', '!=', $fav->id)
            ->update(['speed_dial_digit' => null]);

        $fav->speed_dial_digit = $data['digit'];
        $fav->save();

        return $this->ok(['favorite' => DialerData::transformSingleFavorite($fav, $userId)]);
    }

    /**
     * Unassign the speed-dial digit from a specific favorite or from a
     * digit slot directly (pass either `favorite_id` or `digit`).
     */
    public function unassignSpeedDial(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'favorite_id' => ['nullable', 'integer'],
            'digit'       => ['nullable', 'integer', 'min:1', 'max:9'],
        ]);

        $q = DialerFavorite::where('user_id', $userId);
        if (!empty($data['favorite_id'])) {
            $q->where('id', $data['favorite_id']);
        } elseif (!empty($data['digit'])) {
            $q->where('speed_dial_digit', $data['digit']);
        } else {
            return $this->fail('Provide favorite_id or digit', 422, 'invalid');
        }

        $q->update(['speed_dial_digit' => null]);

        return $this->ok(['items' => DialerData::favorites($userId)]);
    }

    // ── Spam / block flags ────────────────────────────────────────────

    /**
     * All numbers the user flagged as spam and/or blocked. Powers the
     * mobile caller-ID directory sync so the native incoming-call card
     * can show a warning while the JS runtime is dead (display-only —
     * no call is ever blocked or silenced).
     */
    public function flags(Request $request)
    {
        $items = DialerNumberFlag::where('user_id', $request->user()->id)
            ->where(function ($q) {
                $q->where('is_spam', true)->orWhere('is_blocked', true);
            })
            ->orderBy('number_e164')
            ->get(['number_e164', 'is_spam', 'is_blocked'])
            ->map(fn ($f) => [
                'number_e164' => $f->number_e164,
                'is_spam'     => (bool) $f->is_spam,
                'is_blocked'  => (bool) $f->is_blocked,
            ])
            ->values();

        return $this->ok(['items' => $items]);
    }

    public function flag(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'number'     => ['required', 'string', 'max:40'],
            'is_spam'    => ['nullable', 'boolean'],
            'is_blocked' => ['nullable', 'boolean'],
        ]);
        $e164 = ContactPhone::normalize($data['number']);
        if (!$e164) return $this->fail('Invalid number', 422, 'invalid');

        $flag = DialerNumberFlag::firstOrNew(['user_id' => $userId, 'number_e164' => $e164]);
        if ($request->has('is_spam'))    $flag->is_spam = (bool) $data['is_spam'];
        if ($request->has('is_blocked')) $flag->is_blocked = (bool) $data['is_blocked'];
        $flag->save();

        return $this->ok([
            'number_e164' => $e164,
            'is_spam'     => (bool) $flag->is_spam,
            'is_blocked'  => (bool) $flag->is_blocked,
        ]);
    }

    // ── Call log: outcome / note / tag (mini-CRM) ─────────────────────

    public function logCall(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'number'     => ['required', 'string', 'max:40'],
            'contact_id' => ['nullable', 'integer'],
            'outcome'    => ['nullable', 'string', 'in:called,messaged,no_answer,voicemail,busy,wrong_number,completed'],
            'note'       => ['nullable', 'string', 'max:2000'],
            'tag'        => ['nullable', 'string', 'max:50'],
        ]);
        $e164 = ContactPhone::normalize($data['number']);
        if (!$e164) return $this->fail('Invalid number', 422, 'invalid');

        $contactId = null;
        if (!empty($data['contact_id'])) {
            $contactId = Contact::where('user_id', $userId)->where('id', $data['contact_id'])->value('id');
        }

        $log = DialerLookup::create([
            'user_id'      => $userId,
            'number_e164'  => $e164,
            'contact_id'   => $contactId,
            'looked_up_at' => now(),
            'outcome'      => $data['outcome'] ?? null,
            'note'         => $data['note'] ?? null,
            'tag'          => $data['tag'] ?? null,
        ]);

        return $this->created(['log' => DialerData::transformLog($log)]);
    }

    // ── Callback reminders ────────────────────────────────────────────

    public function setCallback(Request $request)
    {
        $userId = $request->user()->id;
        $data = $request->validate([
            'number'      => ['required', 'string', 'max:40'],
            'contact_id'  => ['nullable', 'integer'],
            'callback_at' => ['required', 'date'],
            'note'        => ['nullable', 'string', 'max:2000'],
        ]);
        $e164 = ContactPhone::normalize($data['number']);
        if (!$e164) return $this->fail('Invalid number', 422, 'invalid');

        $when = Carbon::parse($data['callback_at']);
        if ($when->isPast()) return $this->fail('Pick a future time', 422, 'invalid');

        $contactId = null;
        if (!empty($data['contact_id'])) {
            $contactId = Contact::where('user_id', $userId)->where('id', $data['contact_id'])->value('id');
        }

        $log = DialerLookup::create([
            'user_id'              => $userId,
            'number_e164'          => $e164,
            'contact_id'           => $contactId,
            'looked_up_at'         => now(),
            'note'                 => $data['note'] ?? null,
            'callback_at'          => $when,
            'callback_notified_at' => null,
        ]);

        return $this->created(['callback' => DialerData::transformLog($log)]);
    }

    public function clearCallback(Request $request, int $id)
    {
        $row = DialerLookup::where('user_id', $request->user()->id)
            ->whereNotNull('callback_at')->find($id);
        if (!$row) return $this->notFound('Callback not found');
        $row->callback_at = null;
        $row->callback_notified_at = null;
        $row->save();
        return $this->ok(['cleared' => true]);
    }
}
