<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user();
        $timezones = \DateTimeZone::listIdentifiers();
        return view('user.profile.edit', compact('user', 'timezones'));
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
            'handle' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/i', Rule::unique('users')->ignore($user->id)],
            'bio' => 'nullable|string|max:500',
            'avatar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            $validated['avatar'] = '/storage/' . $request->file('avatar')->store('avatars', 'public');
        } else {
            unset($validated['avatar']);
        }

        $validated['discoverable'] = $request->boolean('discoverable');
        $validated['allow_followers'] = $request->boolean('allow_followers');
        $validated['notify_new_follower'] = $request->boolean('notify_new_follower');
        $validated['notify_follower_updates'] = $request->boolean('notify_follower_updates');

        $previousAvatar = $user->avatar;
        $previousName   = $user->name;
        $user->update($validated);

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

}
