<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Models\BannedNameAcknowledgement;
use App\Modules\Admin\Services\BannedNameChecker;
use Database\Seeders\BannedNamesSeeder;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin CRUD for the banned-names list. Each entry blocks a single
 * exact name (case-insensitive) from being used as a user profile
 * handle or as any link alias. Existing handles/aliases that already
 * match a newly-banned entry are left alone — the index view surfaces
 * a count so the admin can see what's currently in conflict, and the
 * conflicts() drill-in lets them act on each one (notify, acknowledge,
 * or force a rename at next login).
 */
class BannedNameController extends Controller
{
    public function index()
    {
        $items = BannedName::orderBy('name')->get();
        $conflicts = [];
        foreach ($items as $item) {
            $conflicts[$item->id] = $this->countConflicts($item);
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

    /**
     * Re-run the curated default reserved-name list. The seeder is
     * idempotent: existing entries (admin-added or already-seeded) are
     * untouched, only missing defaults are inserted. Useful when we
     * later expand the curated list and want existing installs to top
     * up without dropping to the CLI.
     */
    public function restoreDefaults()
    {
        $inserted = BannedNamesSeeder::applyDefaults();

        if ($inserted === 0) {
            $msg = 'Default reserved list is already fully applied — nothing new to add.';
        } else {
            $msg = "Restored defaults: added {$inserted} new reserved name" . ($inserted === 1 ? '' : 's') . '.';
        }

        return redirect()->route('admin.banned-names.index')->with('success', $msg);
    }

    public function destroy(BannedName $bannedName)
    {
        $name = $bannedName->name;
        $bannedName->delete();
        BannedNameChecker::flush($name);

        return redirect()->route('admin.banned-names.index')
            ->with('success', "'{$name}' removed from the banned list.");
    }

    /**
     * Drill-in view: list every existing user/link/extra-alias whose
     * value still matches this banned entry, plus per-row actions.
     */
    public function conflicts(BannedName $bannedName)
    {
        $rows = $this->collectConflicts($bannedName);

        return view('admin.banned-names.conflicts', [
            'item' => $bannedName,
            'rows' => $rows,
        ]);
    }

    /**
     * Send the affected user a system notification asking them to
     * change their handle. Idempotent enough — we don't dedupe across
     * sends so an admin can re-prompt if the first nudge was ignored.
     */
    public function notifyUser(Request $request, BannedName $bannedName, User $user)
    {
        if (mb_strtolower((string) $user->handle) !== mb_strtolower($bannedName->name)) {
            return back()->with('error', "That user's handle no longer matches '{$bannedName->name}'.");
        }

        UserNotification::create([
            'user_id'    => $user->id,
            'type'       => 'handle_rename_requested',
            'data'       => [
                'message'      => "An admin has asked you to change your handle (@{$user->handle}). Please pick a new one in your profile settings.",
                'banned_name'  => $bannedName->name,
                'profile_url'  => route('user.profile.edit'),
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', "Notified @{$user->handle} to pick a new handle.");
    }

    /**
     * Mark a single conflict (user/link/extra) as acknowledged. The
     * row stays visible in the drill-in (dimmed, with a "Re-open"
     * action) so admins keep an audit trail, but it stops counting
     * toward the conflicts badge on the index. Stored per banned-name
     * so the same user can stay flagged on a different entry.
     */
    public function acknowledge(Request $request, BannedName $bannedName)
    {
        $data = $request->validate([
            'conflict_type' => 'required|in:user,link,extra',
            'conflict_id'   => 'required|integer|min:1',
        ]);

        BannedNameAcknowledgement::updateOrCreate(
            [
                'banned_name_id' => $bannedName->id,
                'conflict_type'  => $data['conflict_type'],
                'conflict_id'    => (int) $data['conflict_id'],
            ],
            [
                'acknowledged_by' => Auth::guard('admin')->id(),
                'acknowledged_at' => now(),
            ]
        );

        return back()->with('success', 'Conflict acknowledged.');
    }

    /**
     * Re-open a previously acknowledged conflict so it shows up again.
     */
    public function unacknowledge(Request $request, BannedName $bannedName)
    {
        $data = $request->validate([
            'conflict_type' => 'required|in:user,link,extra',
            'conflict_id'   => 'required|integer|min:1',
        ]);

        BannedNameAcknowledgement::where('banned_name_id', $bannedName->id)
            ->where('conflict_type', $data['conflict_type'])
            ->where('conflict_id', (int) $data['conflict_id'])
            ->delete();

        return back()->with('success', 'Acknowledgement cleared.');
    }

    /**
     * Toggle the "force rename on next login" flag for this entry.
     * When ON, affected users are bounced to their profile-edit page on
     * their next sign-in until their handle no longer matches.
     */
    public function toggleForceRename(Request $request, BannedName $bannedName)
    {
        $bannedName->update([
            'force_rename_on_login' => !$bannedName->force_rename_on_login,
        ]);

        $msg = $bannedName->force_rename_on_login
            ? "Affected users will be prompted to rename on next login."
            : "Force-rename prompt disabled.";

        return back()->with('success', $msg);
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
     * Cheap aggregate for the index page: counts conflicts but excludes
     * any that have already been acknowledged so the badge reflects
     * what still needs attention.
     */
    private function countConflicts(BannedName $item): array
    {
        $lc = mb_strtolower($item->name);

        $ackByType = BannedNameAcknowledgement::where('banned_name_id', $item->id)
            ->get(['conflict_type', 'conflict_id'])
            ->groupBy('conflict_type')
            ->map(fn ($g) => $g->pluck('conflict_id')->all());

        $userIds  = User::whereRaw('LOWER(handle) = ?', [$lc])->pluck('id');
        $linkIds  = Link::whereRaw('LOWER(alias) = ?', [$lc])->pluck('id');
        $extraIds = LinkAlias::whereRaw('LOWER(alias) = ?', [$lc])->pluck('id');

        $users  = $userIds->diff($ackByType->get('user', []))->count();
        $links  = $linkIds->diff($ackByType->get('link', []))->count();
        $extras = $extraIds->diff($ackByType->get('extra', []))->count();

        return ['users' => $users, 'links' => $links, 'extras' => $extras];
    }

    /**
     * Full drill-in payload: each conflicting row with the data the
     * admin needs to act on it (display name, owner, ack state).
     */
    private function collectConflicts(BannedName $item): array
    {
        $lc = mb_strtolower($item->name);

        $acks = BannedNameAcknowledgement::where('banned_name_id', $item->id)
            ->get()
            ->keyBy(fn ($a) => $a->conflict_type . ':' . $a->conflict_id);

        $users = User::whereRaw('LOWER(handle) = ?', [$lc])
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'handle'])
            ->map(function ($u) use ($acks) {
                $key = 'user:' . $u->id;
                return [
                    'kind'         => 'user',
                    'id'           => $u->id,
                    'label'        => '@' . $u->handle,
                    'detail'       => trim(($u->name ?: 'Unnamed user') . ' · ' . ($u->email ?: '')),
                    'owner'        => $u,
                    'acknowledged' => isset($acks[$key]) ? $acks[$key]->acknowledged_at : null,
                ];
            })->all();

        $links = Link::whereRaw('LOWER(alias) = ?', [$lc])
            ->with(['user:id,name,email,handle'])
            ->orderBy('id')
            ->get(['id', 'user_id', 'alias', 'title', 'type'])
            ->map(function ($l) use ($acks) {
                $key = 'link:' . $l->id;
                return [
                    'kind'         => 'link',
                    'id'           => $l->id,
                    'label'        => '/' . $l->alias,
                    'detail'       => 'Primary alias for ' . ($l->title ?: ucfirst((string) $l->type) . ' link'),
                    'owner'        => $l->user,
                    'acknowledged' => isset($acks[$key]) ? $acks[$key]->acknowledged_at : null,
                ];
            })->all();

        $extras = LinkAlias::whereRaw('LOWER(alias) = ?', [$lc])
            ->with(['link:id,user_id,title,alias', 'link.user:id,name,email,handle'])
            ->orderBy('id')
            ->get()
            ->map(function ($a) use ($acks) {
                $key = 'extra:' . $a->id;
                $link = $a->link;
                return [
                    'kind'         => 'extra',
                    'id'           => $a->id,
                    'label'        => '/' . $a->alias,
                    'detail'       => 'Extra alias on ' . ($link?->title ?: ('/' . ($link?->alias ?? '?'))),
                    'owner'        => $link?->user,
                    'acknowledged' => isset($acks[$key]) ? $acks[$key]->acknowledged_at : null,
                ];
            })->all();

        return array_merge($users, $links, $extras);
    }

    /**
     * Resolve a single conflicting row: rename it to a value that's
     * NOT on the banned list, or remove it. The kind of "remove" depends
     * on the row type — a user has its handle cleared, an extra alias
     * is deleted, and a link's primary alias can only be renamed (the
     * link itself is not destroyed from this screen).
     */
    public function resolveConflict(Request $request, BannedName $bannedName)
    {
        $data = $request->validate([
            'type'      => ['required', Rule::in(['user', 'link', 'extra'])],
            'id'        => ['required', 'integer'],
            'action'    => ['required', Rule::in(['rename', 'remove'])],
            'new_value' => ['nullable', 'string', 'max:100'],
        ]);

        $bannedLc = mb_strtolower($bannedName->name);

        if ($data['action'] === 'rename') {
            $new = trim((string) ($data['new_value'] ?? ''));
            if ($new === '') {
                throw ValidationException::withMessages(['new_value' => 'Enter a new name.']);
            }
            if (!preg_match('/^[A-Za-z0-9_-]+$/', $new)) {
                throw ValidationException::withMessages(['new_value' => 'Only letters, numbers, hyphens and underscores are allowed.']);
            }
            if (BannedNameChecker::isBanned($new)) {
                throw ValidationException::withMessages(['new_value' => 'That name is also on the banned list — pick another.']);
            }
            if (mb_strtolower($new) === $bannedLc) {
                throw ValidationException::withMessages(['new_value' => 'The new name still matches the banned entry.']);
            }
        }

        switch ($data['type']) {
            case 'user':
                $user = User::findOrFail($data['id']);
                if (mb_strtolower((string) $user->handle) !== $bannedLc) {
                    return redirect()->route('admin.banned-names.conflicts', $bannedName)
                        ->with('success', 'That handle no longer matches — nothing to do.');
                }
                if ($data['action'] === 'remove') {
                    $user->update(['handle' => null]);
                    $msg = "Cleared handle for user #{$user->id}.";
                } else {
                    $exists = User::whereRaw('LOWER(handle) = ?', [mb_strtolower($new)])
                        ->where('id', '!=', $user->id)->exists();
                    if ($exists) {
                        throw ValidationException::withMessages(['new_value' => 'Another user already has that handle.']);
                    }
                    $user->update(['handle' => $new]);
                    $msg = "Renamed user #{$user->id} handle to '{$new}'.";
                }
                break;

            case 'link':
                $link = Link::findOrFail($data['id']);
                if (mb_strtolower((string) $link->alias) !== $bannedLc) {
                    return redirect()->route('admin.banned-names.conflicts', $bannedName)
                        ->with('success', 'That alias no longer matches — nothing to do.');
                }
                if ($data['action'] === 'remove') {
                    throw ValidationException::withMessages(['action' => 'A primary link alias can only be renamed, not removed.']);
                }
                $taken = Link::whereRaw('LOWER(alias) = ?', [mb_strtolower($new)])
                    ->where('id', '!=', $link->id)->exists()
                    || LinkAlias::whereRaw('LOWER(alias) = ?', [mb_strtolower($new)])->exists();
                if ($taken) {
                    throw ValidationException::withMessages(['new_value' => 'That alias is already in use.']);
                }
                $link->update(['alias' => $new]);
                $msg = "Renamed link #{$link->id} alias to '{$new}'.";
                break;

            case 'extra':
                $extra = LinkAlias::findOrFail($data['id']);
                if (mb_strtolower((string) $extra->alias) !== $bannedLc) {
                    return redirect()->route('admin.banned-names.conflicts', $bannedName)
                        ->with('success', 'That alias no longer matches — nothing to do.');
                }
                if ($data['action'] === 'remove') {
                    $extra->delete();
                    $msg = "Removed extra alias #{$extra->id}.";
                } else {
                    $taken = Link::whereRaw('LOWER(alias) = ?', [mb_strtolower($new)])->exists()
                        || LinkAlias::whereRaw('LOWER(alias) = ?', [mb_strtolower($new)])
                            ->where('id', '!=', $extra->id)->exists();
                    if ($taken) {
                        throw ValidationException::withMessages(['new_value' => 'That alias is already in use.']);
                    }
                    $extra->update(['alias' => $new]);
                    $msg = "Renamed extra alias #{$extra->id} to '{$new}'.";
                }
                break;
        }

        return redirect()->route('admin.banned-names.conflicts', $bannedName)
            ->with('success', $msg);
    }
}
