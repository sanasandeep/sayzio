<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\UserNotification;
use App\Modules\User\Services\FollowerDigestComposer;
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
        return view('user.profile.edit', compact('user', 'timezones', 'digestPreviewHtml', 'digestPreviewIsReal', 'digestPreviewCount', 'handleSuggestions'));
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
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'timezone' => 'required|string',
            'language' => 'required|string|in:en',
            'handle' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/i', Rule::unique('users')->ignore($user->id), new \App\Modules\Admin\Rules\NotBannedName()],
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|max:2048',
            'persona' => ['nullable', 'string', \Illuminate\Validation\Rule::in(\App\Modules\User\Services\PersonaCatalog::slugs())],
            'country' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
        ]);

        // Normalize ISO country code to uppercase for the
        // country_currency lookup. Empty string means "no country set".
        if (!empty($validated['country'])) {
            $validated['country'] = strtoupper($validated['country']);
        } else {
            $validated['country'] = null;
        }

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = '/storage/' . $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        $validated['discoverable'] = $request->boolean('discoverable');
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
                'data'         => ['creator_name' => $user->name, 'creator_avatar' => $user->avatar],
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
            Mail::send(
                ['emails.follower-digest', 'emails.follower-digest-text'],
                $composed['viewData'],
                function ($m) use ($user, $composed) {
                    $m->to($user->email)->subject($composed['subject']);
                }
            );
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
