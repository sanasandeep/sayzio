<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactPhone;
use App\Modules\User\Models\DialerLookup;
use App\Modules\User\Models\LinkedIdentifier;
use Illuminate\Http\Request;

class DialerController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = trim((string) $request->query('q', ''));
        $contacts = collect();

        if ($q !== '') {
            $needle = '%' . $q . '%';
            $phoneNeedle = '%' . ContactPhone::normalize($q) . '%';
            $contacts = Contact::where('user_id', $user->id)
                ->with(['phones', 'biolinkUser'])
                ->where(function ($w) use ($needle, $phoneNeedle) {
                    $w->where('display_name', 'ilike', $needle)
                      ->orWhere('given_name', 'ilike', $needle)
                      ->orWhere('family_name', 'ilike', $needle)
                      ->orWhereHas('phones', fn ($q2) => $q2->where('value_e164', 'ilike', $phoneNeedle));
                })
                ->orderBy('display_name')
                ->limit(50)->get();
        }

        $recent = DialerLookup::where('user_id', $user->id)
            ->orderByDesc('looked_up_at')->limit(10)
            ->with(['contact.phones'])->get();

        // JSON branch — used by the dialer's live filter (debounced fetch as
        // the user types on the keypad or in the search box).
        if ($request->wantsJson()) {
            return response()->json([
                'q'       => $q,
                'matches' => $contacts->map(fn ($c) => [
                    'id'      => $c->id,
                    'name'    => $c->nameForDisplay(),
                    'initials'=> $c->initials(),
                    'phone'   => $c->phones->first()?->value,
                    'phone_e164' => $c->phones->first()?->value_e164,
                    'biolink' => (bool) $c->biolink_user_id,
                    'profile_url' => $c->phones->first()
                        ? route('user.dialer.profile', ['number' => $c->phones->first()->value_e164 ?: $c->phones->first()->value, 'contact' => $c->id])
                        : route('user.contacts.show', $c),
                ])->values(),
            ]);
        }

        return view('user.dialer.index', compact('q', 'contacts', 'recent'));
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $number = trim((string) ($request->query('number') ?? ''));
        $contactId = $request->query('contact');

        $contact = null; $matchedUser = null; $bio = null;

        if ($contactId) {
            $contact = Contact::where('user_id', $user->id)->where('id', $contactId)
                ->with(['phones', 'emails', 'biolinkUser'])->first();
            if ($contact) {
                if (!$number) $number = $contact->phones->first()?->value_e164 ?? '';
            }
        }

        if ($number === '' && !$contact) {
            return redirect()->route('user.dialer.index')->with('error', 'No number specified.');
        }

        $needle = ContactPhone::normalize($number);

        if (!$contact && $needle) {
            $contact = Contact::where('user_id', $user->id)
                ->whereHas('phones', fn ($q) => $q->where('value_e164', $needle))
                ->with(['phones', 'emails', 'biolinkUser'])
                ->first();
        }

        // Resolve to a 1INME user (from the contact's attached biolink, or by lookup)
        $matchedUser = $contact?->biolinkUser;
        if (!$matchedUser && $needle) {
            $matchedUser = LinkedIdentifier::resolveUser('phone', $needle);
        }
        if ($matchedUser) {
            $bio = \App\Modules\User\Models\Link::where('user_id', $matchedUser->id)
                ->whereIn('type', \App\Modules\User\Models\Link::BIOLINK_FAMILY)->where('is_active', true)
                ->orderByDesc('id')->first();
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

        $payload = [
            'number'    => $number,
            'contact'   => $contact,
            'biolink'   => $matchedUser ? [
                'user_id' => $matchedUser->id,
                'name'    => $matchedUser->name,
                'handle'  => $matchedUser->publicHandle(),
                'url'     => $bio ? url('/' . $bio->alias) : null,
                'link_id' => $bio?->id,
            ] : null,
        ];

        // JSON for future mobile consumers.
        if ($request->wantsJson()) {
            return response()->json($payload);
        }

        // Recent activity for this number/contact (so the profile shows context).
        $recent = DialerLookup::where('user_id', $user->id)
            ->where(function ($q) use ($needle, $contact) {
                if ($needle) $q->where('number_e164', $needle);
                if ($contact) $q->orWhere('contact_id', $contact->id);
            })
            ->orderByDesc('looked_up_at')->limit(10)->get();

        return view('user.dialer.profile', compact('payload', 'contact', 'matchedUser', 'bio', 'number', 'recent'));
    }
}
