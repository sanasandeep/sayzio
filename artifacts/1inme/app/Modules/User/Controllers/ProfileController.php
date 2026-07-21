<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Rules\Gstin;
use App\Modules\Admin\Rules\Vatin;
use App\Modules\User\Models\BillingAddress;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Services\FollowerDigestComposer;
use App\Services\TaxCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user();
        $timezones = \DateTimeZone::listIdentifiers();
        $preview = $this->renderDigestPreview($user);
        $digestPreviewHtml = $preview['html'];
        $digestPreviewIsReal = $preview['isReal'];
        $digestPreviewCount = $preview['count'];
        $handleSuggestions = session()->has('force_handle_rename')
            ? \App\Modules\User\Services\HandleSuggester::suggest($user)
            : [];
        $billing = BillingAddress::firstOrNew(['user_id' => $user->id]);
        $inStates = TaxCalculator::IN_STATES;
        $usStates = TaxCalculator::US_STATES;
        return view('user.profile.edit', compact(
            'user', 'timezones', 'digestPreviewHtml', 'digestPreviewIsReal',
            'digestPreviewCount', 'handleSuggestions', 'billing', 'inStates', 'usStates'
        ));
    }

    /**
     * Render the digest email Blade template so the user can preview what
     * the daily digest will look like. When the follower already has
     * unsent `follower_update` notifications queued, those are used so the
     * preview reflects exactly what the next real digest will contain.
     * Otherwise a clearly-labelled mock fallback is shown.
     */
    private function renderDigestPreview($user): array
    {
        $pending = UserNotification::where('user_id', $user->id)
            ->where('type', 'follower_update')
            ->whereNull('emailed_at')
            ->orderBy('created_at')
            ->get();

        if ($pending->isNotEmpty()) {
            $composed = FollowerDigestComposer::compose($user, $pending, true);
            return [
                'html'   => view('emails.follower-digest', $composed['viewData'])->render(),
                'isReal' => true,
                'count'  => (int) ($composed['count'] ?? $pending->count()),
            ];
        }

        $creators = [
            [
                'name'     => 'Ada Lovelace',
                'avatar'   => null,
                'url'      => null,
                'messages' => [
                    ['text' => 'posted a new update: "Just shipped a fresh design"', 'image' => null],
                    ['text' => 'added a new link: Behind the scenes', 'image' => null],
                ],
                'extra'    => 0,
            ],
            [
                'name'     => 'Marcus Chen',
                'avatar'   => null,
                'url'      => null,
                'messages' => [
                    ['text' => 'shared a new photo set from this week', 'image' => null],
                ],
                'extra'    => 0,
            ],
            [
                'name'     => 'Priya Patel',
                'avatar'   => null,
                'url'      => null,
                'messages' => [
                    ['text' => 'updated their profile', 'image' => null],
                    ['text' => 'posted a new update: "Q&A this Friday — bring questions!"', 'image' => null],
                ],
                'extra'    => 2,
            ],
        ];

        $totalUpdates = array_sum(array_map(fn ($c) => count($c['messages']) + $c['extra'], $creators));
        $creatorCount = count($creators);

        return [
            'html' => view('emails.follower-digest', [
                'userName'     => $user->name ?: 'there',
                'subject'      => 'Your daily digest (example)',
                'creators'     => $creators,
                'totalUpdates' => $totalUpdates,
                'creatorCount' => $creatorCount,
                'isSample'     => true,
                'isExample'    => true,
            ])->render(),
            'isReal' => false,
            'count'  => 0,
        ];
    }

    /**
     * Postal-code lookup used by the billing-address form to auto-fill
     * city and state/region. Proxies Zippopotam.us server-side so the
     * page never exposes a third-party API call directly.
     *
     * Returns { city, region, region_code } — all nullable. On any error
     * or unknown code all three are null and the form stays editable.
     * Results are cached for 1 hour to avoid hammering the upstream API.
     */
    public function postalLookup(\Illuminate\Http\Request $request)
    {
        $country = strtoupper(trim((string) $request->input('country', '')));
        $postal  = trim((string) $request->input('postal_code', ''));

        $empty = ['city' => null, 'region' => null, 'region_code' => null];

        if (strlen($country) !== 2 || $postal === '') {
            return response()->json($empty);
        }

        $cacheKey = 'postal_lookup:' . $country . ':' . strtolower($postal);
        $result = \Cache::remember($cacheKey, 3600, function () use ($country, $postal, $empty) {
            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(4)
                    ->get("https://api.zippopotam.us/{$country}/{$postal}");
                if (!$resp->successful()) {
                    return $empty;
                }
                $data  = $resp->json();
                $place = $data['places'][0] ?? null;
                if (!$place) {
                    return $empty;
                }
                return [
                    'city'        => $place['place name']         ?? null,
                    'region'      => $place['state']              ?? null,
                    'region_code' => $place['state abbreviation'] ?? null,
                ];
            } catch (\Throwable $e) {
                return $empty;
            }
        });

        return response()->json($result);
    }

    /**
     * JSON endpoint used by the profile edit page to refresh the live
     * digest preview (badge count + iframe HTML) without a full reload.
     */
    public function digestPreview()
    {
        $user = Auth::user();
        $preview = $this->renderDigestPreview($user);
        return response()->json([
            'isReal' => $preview['isReal'],
            'count'  => $preview['count'],
            'html'   => $preview['html'],
        ]);
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id), function ($attribute, $value, $fail) use ($user) {
                $newEmail = strtolower(trim((string) $value));
                $currentEmail = strtolower(trim((string) $user->email));
                if ($newEmail === $currentEmail) {
                    return;
                }
                $exists = \App\Modules\Admin\Models\Admin::whereRaw('lower(email) = ?', [$newEmail])->exists();
                if ($exists) {
                    $fail('That email address is not available.');
                }
            }],
            'phone' => 'nullable|string|max:30',
            'timezone' => 'required|string',
            'language' => 'required|string|in:en',
            'handle' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/i', Rule::unique('users')->ignore($user->id), new \App\Modules\Admin\Rules\NotBannedName()],
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|max:2048',
            'persona' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Modules\User\Services\PersonaCatalog::slugs())],
            'country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
        ]);

        // Verified users have their display name locked server-side. The
        // edit view hides the input, but ignore any submitted name here too
        // so a direct POST/API call cannot bypass the lock.
        if ($user->isNameAvatarLocked()) {
            unset($validated['name']);
        }

        if ($request->hasFile('avatar') && !$user->isNameAvatarLocked()) {
            $validated['avatar'] = '/storage/' . $request->file('avatar')->store('avatars', 'public');
        } else {
            // Verified users have their profile photo locked too — ignore
            // any uploaded avatar so a direct POST cannot swap the photo.
            unset($validated['avatar']);
        }

        // Normalize ISO country code to uppercase for the
        // country_currency lookup. Empty string means "no country set".
        if (!empty($validated['country'])) {
            $validated['country'] = strtoupper($validated['country']);
        } else {
            $validated['country'] = null;
        }

        // Plan gate: making a creator profile publicly discoverable is a
        // paid feature. Silently coerce to false (and warn) when the user's
        // plan doesn't include `creator_profile_public`.
        $wantsDiscoverable = $request->boolean('discoverable');
        if ($wantsDiscoverable && !$user->planFeatureEnabled('creator_profile_public')) {
            return back()->withInput()->with('error', 'A public, discoverable creator profile isn\'t available on your current plan. Upgrade to publish your profile.');
        }
        $validated['discoverable'] = $wantsDiscoverable;
        $validated['allow_followers'] = $request->boolean('allow_followers');
        $validated['notify_new_follower'] = $request->boolean('notify_new_follower');

        // Three-way preference: instant | digest | off. Keep the legacy
        // boolean in sync (true unless explicitly off) so any code still
        // reading `notify_follower_updates` continues to behave sensibly.
        $mode = $request->input('follower_updates_mode', 'digest');
        if (!in_array($mode, ['instant', 'digest', 'off'], true)) $mode = 'digest';
        $validated['follower_updates_mode'] = $mode;
        $validated['notify_follower_updates'] = $mode !== 'off';

        // Preferred digest send hour in the user's local timezone (0–23).
        // Defaults to 9am if missing or out of range.
        $hour = (int) $request->input('digest_preferred_hour', 9);
        if ($hour < 0 || $hour > 23) $hour = 9;
        $validated['digest_preferred_hour'] = $hour;

        $previousAvatar = $user->avatar;
        $previousName   = $user->name;
        $previousHandle = $user->handle;
        $user->update($validated);

        // Keep the personal workspace name in sync with the profile name,
        // but only while it still carries the auto-generated default (so a
        // workspace the user deliberately renamed is never clobbered). The
        // default is derived exactly as User::ensureDefaultWorkspace() does.
        if ($user->name !== $previousName) {
            $personal = $user->ownedWorkspaces()->where('is_personal', true)->first();
            if ($personal) {
                $autoDefaults = [
                    (($previousName ?: ('User ' . $user->id))) . "'s workspace",
                    'User ' . $user->id . "'s workspace",
                ];
                if (in_array($personal->name, $autoDefaults, true)) {
                    $personal->update([
                        'name' => ($user->name ?: ('User ' . $user->id)) . "'s workspace",
                    ]);
                }
            }

            // Sync the linked admin account's name so the admin sidebar always
            // shows the user's current display name. Only the name is synced —
            // email and all other fields are explicitly out of scope. If the
            // user has no linked admin account this is a no-op.
            $linkedAdmin = \App\Modules\Common\Services\AdminUserBridge::resolveAdminForUser($user);
            if ($linkedAdmin !== null) {
                $linkedAdmin->update(['name' => $user->name]);
            }
        }

        // Persist billing address + tax-id when the form sent any of
        // those fields. We store an empty row when only the country is
        // present so the Invoices PDF still has a snapshot.
        $billingValidated = $request->validate([
            'billing_country'      => ['nullable', 'string', 'size:2'],
            'billing_region'       => ['nullable', 'string', 'max:100'],
            'billing_postal_code'  => ['nullable', 'string', 'max:16'],
            'billing_city'         => ['nullable', 'string', 'max:100'],
            'billing_line1'        => ['nullable', 'string', 'max:255'],
            'billing_line2'        => ['nullable', 'string', 'max:255'],
            'business_name'        => ['nullable', 'string', 'max:255'],
            'tax_id'               => ['nullable', 'string', 'max:32'],
            'tax_id_kind'          => ['nullable', 'string', Rule::in(['GSTIN', 'VATIN', 'OTHER', 'NONE'])],
            'tax_id_label'         => ['nullable', 'string', 'max:100'],
        ]);

        $taxId = strtoupper(trim((string) ($billingValidated['tax_id'] ?? '')));
        $taxIdKind = $billingValidated['tax_id_kind'] ?? null;
        $taxIdLabel = trim((string) ($billingValidated['tax_id_label'] ?? ''));
        // If a tax-id is supplied the kind MUST be declared explicitly. We reject
        // ambiguous combos so the tax engine can rely on `tax_id_kind` to decide
        // reverse-charge eligibility — otherwise a buyer could paste a VATIN
        // string and silently zero out their VAT.
        if ($taxId !== '' && (!$taxIdKind || $taxIdKind === 'NONE')) {
            return back()->withErrors(['tax_id_kind' => 'Select the tax-id type (GSTIN, VATIN, or Other) when providing a number.'])->withInput();
        }
        // Storing a billing address requires a country (taxes are jurisdiction-keyed).
        $effectiveCountry = strtoupper((string) ($billingValidated['billing_country'] ?? $user->country ?? ''));
        $sentBilling = !empty($billingValidated['billing_country']) || !empty($billingValidated['billing_postal_code'])
            || !empty($billingValidated['billing_city']) || !empty($billingValidated['billing_line1'])
            || !empty($billingValidated['business_name']) || $taxId !== '';
        if ($sentBilling && $effectiveCountry === '') {
            return back()->withErrors(['billing_country' => 'Please pick your billing country before saving tax details.'])->withInput();
        }
        if ($taxId !== '' && $taxIdKind === 'GSTIN' && !Gstin::isValid($taxId)) {
            return back()->withErrors(['tax_id' => 'That GSTIN is not valid (15-char format + checksum).'])->withInput();
        }
        if ($taxId !== '' && $taxIdKind === 'VATIN' && !Vatin::isValid($taxId)) {
            return back()->withErrors(['tax_id' => 'That VAT number is not in a recognised format.'])->withInput();
        }
        // Preserve case for free-text region names; uppercase only for IN/US state codes.
        $rawRegion = trim((string) ($billingValidated['billing_region'] ?? ''));
        $savedRegion = $rawRegion === '' ? null
            : (in_array($effectiveCountry, ['IN', 'US'], true) ? strtoupper($rawRegion) : $rawRegion);

        if (!empty($billingValidated['billing_country']) || $taxId !== '') {
            BillingAddress::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'country'       => strtoupper((string) ($billingValidated['billing_country'] ?? $user->country ?? '')) ?: null,
                    'region'        => $savedRegion,
                    'postal_code'   => $billingValidated['billing_postal_code'] ?? null,
                    'city'          => $billingValidated['billing_city'] ?? null,
                    'line1'         => $billingValidated['billing_line1'] ?? null,
                    'line2'         => $billingValidated['billing_line2'] ?? null,
                    'business_name' => $billingValidated['business_name'] ?? null,
                    'tax_id'        => $taxId ?: null,
                    'tax_id_kind'   => $taxId ? ($taxIdKind ?: 'NONE') : null,
                    'tax_id_label'  => ($taxIdKind === 'OTHER' && $taxId !== '') ? ($taxIdLabel ?: null) : null,
                ]
            );
        }

        // If we were forcing this user to rename their handle (admin
        // banned their previous handle and toggled "force rename on
        // next login"), clear the flag now that they've successfully
        // picked something else.
        if (session()->has('force_handle_rename') && $user->handle !== $previousHandle) {
            session()->forget('force_handle_rename');
        }

        // Profile-update feed event (avatar/name changes are notable for followers).
        if ($user->avatar !== $previousAvatar || $user->name !== $previousName) {
            \App\Modules\User\Models\FeedEvent::create([
                'user_id'      => $user->id,
                'type'         => 'profile_update',
                'data'         => ['creator_name' => $user->name, 'creator_avatar' => \App\Support\PublicStorageUrl::resolve($user->creatorAvatarRaw())],
                'occurred_at'  => now(),
            ]);
            \App\Modules\User\Controllers\CreatorPostController::notifyFollowersDebounced($user, 'updated their profile');
        }

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Email the signed-in user a sample digest using their currently
     * pending follower-update notifications, so they can preview what
     * the digest will look like without waiting for the scheduled job.
     * Pending rows are NOT marked as emailed — the next real digest
     * still includes them.
     */
    public function sendSample(Request $request)
    {
        $user = Auth::user();

        // Rate-limit to prevent abuse: max 5 sample digests per user per hour.
        $rateKey = 'digest-sample:' . $user->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $minutes = max(1, (int) ceil($seconds / 60));
            return back()->with('error', "You've sent a few sample digests recently — please try again in about {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.');
        }
        RateLimiter::hit($rateKey, 3600);

        $pending = UserNotification::where('user_id', $user->id)
            ->where('type', 'follower_update')
            ->whereNull('emailed_at')
            ->orderBy('created_at')
            ->get();

        $composed = FollowerDigestComposer::compose($user, $pending, true);

        try {
            \App\Modules\Common\Services\Emailer::send('digests.follower', $user->email, [], [
                'user'      => $user->id,
                'subject'   => $composed['subject'],
                'view_data' => $composed['viewData'],
            ]);
        } catch (\Throwable $e) {
            \Log::warning('sample digest send failed for user ' . $user->id . ': ' . $e->getMessage());
            return back()->with('error', "Couldn't send the sample right now. Please try again in a moment.");
        }

        $msg = $composed['count'] > 0
            ? "Sample digest sent to {$user->email} with {$composed['count']} pending update" . ($composed['count'] === 1 ? '' : 's') . '.'
            : "Sample digest sent to {$user->email}. You don't have any pending updates yet, so it's a placeholder preview.";

        return back()->with('success', $msg);
    }

}
