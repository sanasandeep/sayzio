<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\EventCategory;
use App\Modules\User\Support\EventCategories;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin CRUD for event categories (Task #3654). Seeded from the previous
 * hardcoded `EventCategories::DEFAULTS` list; disabling/deleting a category
 * here only affects the public /events browse row — events already stored
 * under that slug keep filtering correctly (EventCategories::icon/label()
 * fall back to keyword-guessing for anything no longer in this table).
 *
 * Public-row ordering is derived automatically from live event counts, so
 * admin `sort_order` only affects display order on this management screen.
 */
class EventCategoryController extends Controller
{
    public function index()
    {
        $categories = EventCategory::ordered()->get();

        return view('admin.event-categories.index', compact('categories'));
    }

    public function create()
    {
        $category = new EventCategory([
            'is_enabled' => true,
            'icon'       => 'fa-calendar-star',
            'color_from' => '#3d6bff',
            'color_to'   => '#2342c7',
            'sort_order' => (int) EventCategory::max('sort_order') + 1,
        ]);

        return view('admin.event-categories.create', compact('category'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $category = EventCategory::create($data);
        EventCategories::flush();

        return redirect()->route('admin.event-categories.index')
            ->with('success', "Category \"{$category->name}\" created.");
    }

    public function edit(EventCategory $eventCategory)
    {
        return view('admin.event-categories.edit', ['category' => $eventCategory]);
    }

    public function update(Request $request, EventCategory $eventCategory)
    {
        $data = $this->validated($request, $eventCategory->id);
        $eventCategory->update($data);
        EventCategories::flush();

        return redirect()->route('admin.event-categories.index')
            ->with('success', 'Category updated.');
    }

    public function destroy(EventCategory $eventCategory)
    {
        $name = $eventCategory->name;
        $eventCategory->delete();
        EventCategories::flush();

        return redirect()->route('admin.event-categories.index')
            ->with('success', "Category \"{$name}\" deleted. Events already using it will fall back to a guessed icon/label.");
    }

    public function toggleEnabled(EventCategory $eventCategory)
    {
        $eventCategory->update(['is_enabled' => !$eventCategory->is_enabled]);
        EventCategories::flush();

        return back()->with('success', $eventCategory->name . ' is now ' . ($eventCategory->is_enabled ? 'enabled' : 'disabled') . '.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['slug' => \Illuminate\Support\Str::slug((string) $request->input('slug', $request->input('name')), '_')]);

        $data = $request->validate([
            'name'       => ['required', 'string', 'max:100'],
            'slug'       => [
                'required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                Rule::unique('event_categories', 'slug')->ignore($ignoreId),
            ],
            'icon'       => ['required', 'string', 'max:60', 'regex:/^fa-[a-z0-9-]+$/'],
            'color_from' => ['required', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'color_to'   => ['required', 'string', 'max:20', 'regex:/^#[0-9a-fA-F]{3,8}$/'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        $data['is_enabled'] = $request->boolean('is_enabled');
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}
