<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Support\ContactPrivacy;
use Illuminate\Http\Request;

/**
 * Task #3497 — lets a creator choose whether strangers (anyone who hasn't
 * already saved them as a contact) can see their phone, email, exact
 * location and socials through the dialer's caller-ID lookup + universal
 * search, plus un-share individual channels. Mirrors the settings-hub
 * pattern used by NotificationController::preferences/updatePreferences.
 */
class ContactPrivacyController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();
        $prefs = ContactPrivacy::forUser($user);
        $candidates = ContactPrivacy::shareableCandidatesFor($user);

        return view('user.settings.privacy', compact('prefs', 'candidates'));
    }

    public function update(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'share_phone'        => ['nullable', 'string'],
            'share_email'        => ['nullable', 'string'],
            'share_location'     => ['nullable', 'string'],
            'share_socials'      => ['nullable', 'string'],
            'hidden_channels'    => ['array'],
            'hidden_channels.*'  => ['string'],
        ]);

        // Tri-state radio group: "" means "not chosen" (shown by default, no
        // forced default). Only fields actually present in the request are
        // touched — an omitted field (e.g. a partial API PUT) leaves the
        // creator's existing choice untouched rather than resetting it.
        $payload = [];
        foreach (['share_phone', 'share_email', 'share_location', 'share_socials'] as $field) {
            if ($request->has($field)) {
                $payload[$field] = ($data[$field] ?? '') === '' ? null : $data[$field];
            }
        }
        if (array_key_exists('hidden_channels', $data)) {
            $payload['hidden_channels'] = $data['hidden_channels'];
        }

        ContactPrivacy::updateFor($user, $payload);

        if ($request->wantsJson()) {
            return response()->json(['data' => ContactPrivacy::forUser($user->fresh())]);
        }

        return back()->with('success', 'Contact privacy preferences updated.');
    }
}
