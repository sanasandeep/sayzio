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
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

}
