<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Pixel;
use Illuminate\Http\Request;

class PixelController extends Controller
{
    public function index(Request $request)
    {
        $pixels = $request->user()->pixels()
            ->withCount('links')
            ->latest()
            ->paginate(15);

        return view('user.pixels.index', compact('pixels'));
    }

    public function create()
    {
        return view('user.pixels.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:facebook,google_analytics,google_tag_manager,linkedin,twitter,pinterest,tiktok,snapchat,custom',
            'pixel_id' => 'required|string|max:255',
        ]);

        $request->user()->pixels()->create($validated);

        return redirect()->route('user.pixels.index')
            ->with('success', 'Tracking pixel created successfully.');
    }

    public function edit(Request $request, Pixel $pixel)
    {
        abort_if($pixel->user_id !== $request->user()->id, 403);

        return view('user.pixels.edit', compact('pixel'));
    }

    public function update(Request $request, Pixel $pixel)
    {
        abort_if($pixel->user_id !== $request->user()->id, 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:facebook,google_analytics,google_tag_manager,linkedin,twitter,pinterest,tiktok,snapchat,custom',
            'pixel_id' => 'required|string|max:255',
        ]);

        $pixel->update($validated);

        return redirect()->route('user.pixels.index')
            ->with('success', 'Tracking pixel updated successfully.');
    }

    public function destroy(Request $request, Pixel $pixel)
    {
        abort_if($pixel->user_id !== $request->user()->id, 403);

        $pixel->delete();

        return redirect()->route('user.pixels.index')
            ->with('success', 'Tracking pixel deleted successfully.');
    }
}
