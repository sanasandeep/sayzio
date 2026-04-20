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
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /**
     * Show the bulk-import form.
     */
    public function bulkCreate()
    {
        return view('admin.banned-names.bulk');
    }

    /**
     * Bulk insert names from either a textarea (one per line) or an
     * uploaded CSV/text file. Duplicates (already present, or repeated
     * within the input) are skipped, invalid names are rejected with a
     * reason. Existing single-entry add/edit/delete are unaffected.
     */
    public function bulkStore(Request $request)
    {
        $request->validate([
            'names' => ['nullable', 'string', 'max:200000'],
            'file'  => ['nullable', 'file', 'mimes:csv,txt', 'max:2048'],
            'note'  => ['nullable', 'string', 'max:500'],
        ]);

        $raw = (string) $request->input('names', '');
        if ($request->hasFile('file')) {
            $raw .= "\n" . file_get_contents($request->file('file')->getRealPath());
        }

        if (trim($raw) === '') {
            return redirect()->route('admin.banned-names.bulk')
                ->withErrors(['names' => 'Paste names or upload a file with at least one name.']);
        }

        $note    = trim((string) $request->input('note', '')) ?: null;
        $adminId = Auth::guard('admin')->id();

        // Split on newlines OR commas so a single CSV row works too.
        $tokens = preg_split('/[\r\n,]+/', $raw) ?: [];

        $imported   = [];
        $duplicates = [];
        $rejected   = [];
        $seen       = [];

        // Snapshot existing names once to avoid N queries.
        $existing = BannedName::pluck('name')
            ->map(fn ($n) => mb_strtolower($n))
            ->flip()
            ->all();

        foreach ($tokens as $token) {
            $name = trim($token);
            if ($name === '') continue;

            if (mb_strlen($name) > 100) {
                $rejected[] = ['name' => mb_substr($name, 0, 60) . '…', 'reason' => 'too long (max 100)'];
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $name)) {
                $rejected[] = ['name' => $name, 'reason' => 'invalid characters'];
                continue;
            }

            $lower = mb_strtolower($name);
            if (isset($seen[$lower])) {
                $duplicates[] = $name;
                continue;
            }
            $seen[$lower] = true;

            if (isset($existing[$lower])) {
                $duplicates[] = $name;
                continue;
            }

            BannedName::create([
                'name'       => $name,
                'note'       => $note,
                'created_by' => $adminId,
            ]);
            BannedNameChecker::flush($name);
            $existing[$lower] = true;
            $imported[] = $name;
        }

        $msg = sprintf(
            'Imported %d, skipped %d duplicate%s, rejected %d.',
            count($imported),
            count($duplicates), count($duplicates) === 1 ? '' : 's',
            count($rejected),
        );

        return redirect()->route('admin.banned-names.index')
            ->with('success', $msg)
            ->with('bulk_imported', $imported)
            ->with('bulk_duplicates', $duplicates)
            ->with('bulk_rejected', $rejected);
    }

    /**
     * Stream the current banned list as CSV or JSON for backup/sharing.
     */
    public function export(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        $items  = BannedName::orderBy('name')->get(['name', 'note', 'created_at']);
        $stamp  = now()->format('Ymd-His');

        if ($format === 'json') {
            $payload = $items->map(fn ($i) => [
                'name'       => $i->name,
                'note'       => $i->note,
                'created_at' => optional($i->created_at)->toIso8601String(),
            ])->all();

            return response()->json($payload, 200, [
                'Content-Disposition' => 'attachment; filename="banned-names-' . $stamp . '.json"',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        // Default: CSV
        return new StreamedResponse(function () use ($items) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'note', 'created_at']);
            foreach ($items as $i) {
                fputcsv($out, [
                    $i->name,
                    (string) $i->note,
                    optional($i->created_at)->toIso8601String(),
                ]);
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="banned-names-' . $stamp . '.csv"',
        ]);
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
