<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\Admin\Models\Release;
use App\Modules\Admin\Support\VersionRegistry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin "Versions & Releases" hub.
 *
 * One page listing every product surface with its current vs latest version,
 * an up-to-date / update-available / unknown badge, per-surface expandable
 * changelogs (admin-managed CRUD with markdown notes; Zio Browser entries are
 * prefilled from the cached GitHub release feed), plus a Sync Status panel
 * showing the last recorded pass/fail of the parity guards written at
 * CI/post-merge time. Read-only with respect to guards — nothing here ever
 * executes a guard or updates a surface.
 */
class VersionsController extends Controller
{
    public function index()
    {
        $surfaces = VersionRegistry::surfaces();
        $snapshot = VersionRegistry::snapshot();

        $guardState = AppSetting::get(VersionRegistry::GUARD_STATUS_KEY, []);
        $guardState = is_array($guardState) ? $guardState : [];

        $guards = [];
        foreach (VersionRegistry::GUARDS as $key => $label) {
            $entry = is_array($guardState[$key] ?? null) ? $guardState[$key] : [];
            $guards[] = [
                'key'    => $key,
                'label'  => $label,
                'status' => in_array($entry['status'] ?? null, ['pass', 'fail'], true) ? $entry['status'] : null,
                'ran_at' => $entry['ran_at'] ?? null,
                'note'   => $entry['note'] ?? null,
            ];
        }

        return view('admin.versions.index', [
            'surfaces'            => $surfaces,
            'guards'              => $guards,
            'snapshotGeneratedAt' => $snapshot['generated_at'] ?? null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $exists = Release::where('surface', $data['surface'])
            ->where('version', $data['version'])
            ->exists();
        if ($exists) {
            return back()->with('error', 'A release with that version already exists for this surface.');
        }

        Release::create($data + ['source' => 'manual']);

        return back()->with('success', 'Release entry added.');
    }

    public function update(Request $request, Release $release)
    {
        $data = $this->validated($request, $release);

        $release->update($data);

        return back()->with('success', 'Release entry updated.');
    }

    public function destroy(Release $release)
    {
        $release->delete();

        return back()->with('success', 'Release entry deleted.');
    }

    /**
     * @return array{surface:string,version:string,released_at:?string,notes:?string}
     */
    private function validated(Request $request, ?Release $release = null): array
    {
        return $request->validate([
            'surface'     => ['required', Rule::in(array_keys(Release::SURFACES))],
            'version'     => ['required', 'string', 'max:100'],
            'released_at' => ['nullable', 'date'],
            'notes'       => ['nullable', 'string', 'max:20000'],
        ]);
    }
}
