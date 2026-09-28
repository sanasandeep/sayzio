<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\MenuItemMark;
use App\Modules\User\Support\MenuItemMarks;
use App\Modules\User\Support\MenuOptionIcon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The admin-managed vocabulary of dish marks.
 *
 * Sana, 2026-09-28: "these options can be managed in admin... show all
 * possible in admin data".
 *
 * ---- What is deliberately NOT editable here ----------------------------
 *
 * `key` is set once and then fixed. Every dish that wears a mark stores
 * that key, so renaming it would silently strip the mark off every dish
 * carrying it, with no error anywhere and nothing to look at but a menu
 * that used to say "Vegetarian" and now says nothing. The LABEL is
 * editable, which is what anyone actually wants when they say "rename it".
 *
 * ---- Why deleting is discouraged rather than blocked -------------------
 *
 * Same reason, less severe: deleting orphans the key on every dish. The
 * screen says how to avoid it (switch the mark off instead, which stops it
 * being offered and stops it being drawn while leaving the dishes intact),
 * and the delete says how many dishes it will affect before it happens.
 */
class MenuItemMarkController extends Controller
{
    public function index()
    {
        MenuItemMarks::forget();

        $marks = MenuItemMarks::vocabulary()->values();

        return view('admin.menu-item-marks.index', [
            'marks'  => $marks,
            'groups' => MenuItemMark::GROUPS,
            'icons'  => MenuOptionIcon::forPicker(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        MenuItemMark::create($data + [
            'sort_order' => ((int) MenuItemMark::max('sort_order')) + 10,
        ]);
        MenuItemMarks::forget();

        return back()->with('success', 'Mark created.');
    }

    public function update(Request $request, MenuItemMark $mark)
    {
        // The key stays. See the note at the top of this class.
        $data = $this->validated($request, $mark);
        unset($data['key']);

        $mark->update($data);
        MenuItemMarks::forget();

        return back()->with('success', 'Mark updated.');
    }

    public function toggle(MenuItemMark $mark)
    {
        $mark->update(['is_active' => ! $mark->is_active]);
        MenuItemMarks::forget();

        return back()->with('success', $mark->label.($mark->is_active ? ' is offered again.' : ' is switched off.'));
    }

    public function destroy(MenuItemMark $mark)
    {
        $label = $mark->label;
        $mark->delete();
        MenuItemMarks::forget();

        return back()->with('success', 'Deleted "'.$label.'". Any dish that carried it simply stops showing it.');
    }

    protected function validated(Request $request, ?MenuItemMark $mark = null): array
    {
        $data = $request->validate([
            'key' => [
                $mark ? 'nullable' : 'required', 'string', 'max:32',
                Rule::unique('menu_item_marks', 'key')->ignore($mark?->id),
            ],
            'label'     => ['required', 'string', 'max:40'],
            'group'     => ['required', Rule::in(array_keys(MenuItemMark::GROUPS))],
            // Empty means "draw a text chip", which is the right answer for
            // "No garlic" -- there is no legible 14px glyph for it.
            'icon'      => ['nullable', 'string', Rule::in(MenuOptionIcon::keys())],
            'color'     => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'is_graded' => ['sometimes', 'boolean'],
            'max_grade' => ['nullable', 'integer', 'min:1', 'max:'.MenuOptionIcon::MAX_REPEAT],
        ], [
            'color.regex' => 'Use a hex colour like #0a8f3c, or leave it empty.',
            'icon.in'     => 'Pick an icon from the list, or leave it empty for a text chip.',
        ]);

        return [
            'key'       => $mark ? $mark->key : MenuItemMark::normalizeKey($data['key']),
            'label'     => trim($data['label']),
            'group'     => $data['group'],
            'icon'      => $data['icon'] ?: null,
            'color'     => isset($data['color']) && $data['color'] ? strtolower(trim($data['color'])) : null,
            'is_graded' => (bool) ($data['is_graded'] ?? false),
            'max_grade' => max(1, min(MenuOptionIcon::MAX_REPEAT, (int) ($data['max_grade'] ?? MenuOptionIcon::MAX_REPEAT))),
        ];
    }
}
