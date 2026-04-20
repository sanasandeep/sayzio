<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Services\BannedNameChecker;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin CRUD for the banned-names list. Each entry blocks a single
 * exact name (case-insensitive) from being used as a user profile
 * handle or as any link alias. Existing handles/aliases that already
 * match a newly-banned entry are left alone — the index view surfaces
 * a count so the admin can see what's currently in conflict.
 */
class BannedNameController extends Controller
{
    public function index()
    {
        $items = BannedName::orderBy('name')->get();
        $conflicts = [];
        foreach ($items as $item) {
            $conflicts[$item->id] = $this->countConflicts($item->name);
        }

        return view('admin.banned-names.index', compact('items', 'conflicts'));
    }

    public function create()
    {
        return view('admin.banned-names.create');
    }

    public function store(Request $request)
    {
        $data = $this->validateInput($request);

        BannedName::create([
            'name'       => $data['name'],
            'note'       => $data['note'] ?? null,
            'created_by' => Auth::guard('admin')->id(),
        ]);

        BannedNameChecker::flush($data['name']);

        return redirect()->route('admin.banned-names.index')
            ->with('success', "'{$data['name']}' added to the banned list.");
    }

    public function edit(BannedName $bannedName)
    {
        return view('admin.banned-names.edit', ['item' => $bannedName]);
    }

    public function update(Request $request, BannedName $bannedName)
    {
        $data = $this->validateInput($request, $bannedName->id);

        $oldName = $bannedName->name;
        $bannedName->update([
            'name' => $data['name'],
            'note' => $data['note'] ?? null,
        ]);

        BannedNameChecker::flush($oldName);
        BannedNameChecker::flush($data['name']);

        return redirect()->route('admin.banned-names.index')
            ->with('success', 'Banned name updated.');
    }

    public function destroy(BannedName $bannedName)
    {
        $name = $bannedName->name;
        $bannedName->delete();
        BannedNameChecker::flush($name);

        return redirect()->route('admin.banned-names.index')
            ->with('success', "'{$name}' removed from the banned list.");
    }

    private function validateInput(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => [
                'required', 'string', 'min:1', 'max:100',
                'regex:/^[A-Za-z0-9_-]+$/',
                function ($attribute, $value, $fail) use ($ignoreId) {
                    $q = BannedName::whereRaw('LOWER(name) = ?', [mb_strtolower((string) $value)]);
                    if ($ignoreId) $q->where('id', '!=', $ignoreId);
                    if ($q->exists()) {
                        $fail('That name is already on the banned list.');
                    }
                },
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'name.regex' => 'Only letters, numbers, hyphens and underscores are allowed.',
        ]);
    }

    /**
     * Count existing rows that already match a banned name so the admin
     * can see what would have been blocked had the entry existed
     * earlier. Existing values are not retroactively renamed.
     */
    private function countConflicts(string $name): array
    {
        $lc = mb_strtolower($name);

        return [
            'users'  => User::whereRaw('LOWER(handle) = ?', [$lc])->count(),
            'links'  => Link::whereRaw('LOWER(alias) = ?', [$lc])->count(),
            'extras' => LinkAlias::whereRaw('LOWER(alias) = ?', [$lc])->count(),
        ];
    }
}
