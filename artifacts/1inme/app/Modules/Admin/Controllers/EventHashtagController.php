<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\EventHashtag;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for predefined /events directory hashtags (Task #3654).
 * These are shown first in the hashtag row (ahead of auto-computed
 * trending tags) — see EventsDirectoryController::index().
 */
class EventHashtagController extends Controller
{
    public function index()
    {
        $hashtags = EventHashtag::ordered()->get();

        return view('admin.event-hashtags.index', compact('hashtags'));
    }

    public function create()
    {
        $hashtag = new EventHashtag(['sort_order' => (int) EventHashtag::max('sort_order') + 1]);

        return view('admin.event-hashtags.create', compact('hashtag'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        EventHashtag::create($data);

        return redirect()->route('admin.event-hashtags.index')
            ->with('success', "#{$data['tag']} added.");
    }

    public function edit(EventHashtag $eventHashtag)
    {
        return view('admin.event-hashtags.edit', ['hashtag' => $eventHashtag]);
    }

    public function update(Request $request, EventHashtag $eventHashtag)
    {
        $data = $this->validated($request, $eventHashtag->id);
        $eventHashtag->update($data);

        return redirect()->route('admin.event-hashtags.index')
            ->with('success', 'Hashtag updated.');
    }

    public function destroy(EventHashtag $eventHashtag)
    {
        $tag = $eventHashtag->tag;
        $eventHashtag->delete();

        return redirect()->route('admin.event-hashtags.index')
            ->with('success', "#{$tag} removed.");
    }

    /**
     * Move a hashtag up or down one position by swapping sort_order with
     * its nearest neighbour in that direction.
     */
    public function move(Request $request, EventHashtag $eventHashtag)
    {
        $direction = $request->validate(['direction' => 'required|in:up,down'])['direction'];

        $neighbor = $direction === 'up'
            ? EventHashtag::where('sort_order', '<', $eventHashtag->sort_order)->orderByDesc('sort_order')->first()
            : EventHashtag::where('sort_order', '>', $eventHashtag->sort_order)->orderBy('sort_order')->first();

        if ($neighbor) {
            $a = $eventHashtag->sort_order;
            $b = $neighbor->sort_order;
            $eventHashtag->update(['sort_order' => $b]);
            $neighbor->update(['sort_order' => $a]);
        }

        return back();
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['tag' => EventHashtag::normalize((string) $request->input('tag', ''))]);

        $data = $request->validate([
            'tag' => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('event_hashtags', 'tag')->ignore($ignoreId),
            ],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
