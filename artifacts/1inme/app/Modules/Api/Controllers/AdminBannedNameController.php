<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\BannedName;
use App\Modules\Admin\Models\BannedNameAcknowledgement;
use App\Modules\Admin\Services\BannedNameChecker;
use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
use App\Modules\User\Models\User;
use Database\Seeders\BannedNamesSeeder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;

/**
 * Admin banned-names / reserved-handles API: mobile parity for the web
 * back-office page (Admin\BannedNameController). Each entry blocks a
 * single exact name (case-insensitive) from being used as a profile
 * handle or any link alias.
 *
 * Gated behind the same `settings.manage` admin-guard permission the web
 * routes use. The Sanctum token's web user is bridged to its back-office
 * Admin (User::adminAccount), mirroring AdminAccessController.
 */
class AdminBannedNameController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        if ($resp = $this->gate($request)) return $resp;

        $items = BannedName::orderBy('name')->get();
        $conflicts = [];
        foreach ($items as $item) {
            $conflicts[$item->id] = $this->countConflicts($item);
        }

        return $this->ok([
            'items'     => $items->map(fn ($i) => $this->transform($i, $conflicts[$i->id] ?? null))->all(),
            'can_manage' => true,
        ]);
    }

    public function store(Request $request)
    {
        if ($resp = $this->gate($request)) return $resp;

        $data = $this->validateInput($request);

        $item = BannedName::create([
            'name'                  => $data['name'],
            'note'                  => $data['note'] ?? null,
            'force_rename_on_login' => $request->boolean('force_rename_on_login'),
            'created_by'            => $this->activeAdmin($request)?->id,
        ]);
        BannedNameChecker::flush($data['name']);

        return $this->created(['item' => $this->transform($item, $this->countConflicts($item))]);
    }

    /**
     * Toggle whether existing accounts/links matching this reserved name are
     * prompted to rename on their next login (web parity: toggleForceRename).
     */
    public function toggleForceRename(Request $request, int $id)
    {
        if ($resp = $this->gate($request)) return $resp;

        $item = BannedName::find($id);
        if (!$item) return $this->notFound('Banned name not found.');

        $item->update(['force_rename_on_login' => !$item->force_rename_on_login]);

        return $this->ok([
            'item'    => $this->transform($item, $this->countConflicts($item)),
            'message' => $item->force_rename_on_login
                ? 'Affected users will be prompted to rename on next login.'
                : 'Force-rename prompt disabled.',
        ]);
    }

    public function bulkStore(Request $request)
    {
        if ($resp = $this->gate($request)) return $resp;

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
            return $this->fail('Paste names or upload a file with at least one name.', 422, 'empty');
        }

        $note    = trim((string) $request->input('note', '')) ?: null;
        $adminId = $this->activeAdmin($request)?->id;

        $tokens = preg_split('/[\r\n,]+/', $raw) ?: [];

        $imported = []; $duplicates = []; $rejected = []; $seen = [];
        $existing = BannedName::pluck('name')->map(fn ($n) => mb_strtolower($n))->flip()->all();

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
            if (isset($seen[$lower])) { $duplicates[] = $name; continue; }
            $seen[$lower] = true;
            if (isset($existing[$lower])) { $duplicates[] = $name; continue; }

            BannedName::create(['name' => $name, 'note' => $note, 'created_by' => $adminId]);
            BannedNameChecker::flush($name);
            $existing[$lower] = true;
            $imported[] = $name;
        }

        return $this->ok([
            'stats' => [
                'imported'   => count($imported),
                'duplicates' => count($duplicates),
                'rejected'   => count($rejected),
            ],
            'imported'   => $imported,
            'duplicates' => $duplicates,
            'rejected'   => $rejected,
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        if ($resp = $this->gate($request)) return $resp;

        $item = BannedName::find($id);
        if (!$item) return $this->notFound('Banned name not found.');

        $name = $item->name;
        $item->delete();
        BannedNameChecker::flush($name);

        return $this->noContent();
    }

    public function restoreDefaults(Request $request)
    {
        if ($resp = $this->gate($request)) return $resp;

        $inserted = BannedNamesSeeder::applyDefaults();

        return $this->ok([
            'inserted' => $inserted,
            'message'  => $inserted === 0
                ? 'Default reserved list is already fully applied.'
                : "Restored defaults: added {$inserted} new reserved name" . ($inserted === 1 ? '' : 's') . '.',
        ]);
    }

    public function conflicts(Request $request, int $id)
    {
        if ($resp = $this->gate($request)) return $resp;

        $item = BannedName::find($id);
        if (!$item) return $this->notFound('Banned name not found.');

        return $this->ok([
            'item' => $this->transform($item, $this->countConflicts($item)),
            'rows' => $this->collectConflicts($item),
        ]);
    }

    public function export(Request $request)
    {
        if ($resp = $this->gate($request)) return $resp;

        $items = BannedName::orderBy('name')->get(['name', 'note', 'created_at']);
        $stamp = now()->format('Ymd-His');

        $rows = $items->map(fn ($i) => [
            'name'       => $i->name,
            'note'       => $i->note,
            'created_at' => optional($i->created_at)->toIso8601String(),
        ])->all();

        $csvHandle = fopen('php://temp', 'r+');
        fputcsv($csvHandle, ['name', 'note', 'created_at']);
        foreach ($rows as $r) {
            fputcsv($csvHandle, [$r['name'], (string) $r['note'], (string) $r['created_at']]);
        }
        rewind($csvHandle);
        $csv = stream_get_contents($csvHandle);
        fclose($csvHandle);

        return $this->ok([
            'filename' => 'banned-names-' . $stamp,
            'count'    => count($rows),
            'items'    => $rows,
            'csv'      => $csv,
        ]);
    }

    // ---- gating ---------------------------------------------------------

    private function gate(Request $request)
    {
        $admin = $this->activeAdmin($request);
        if (!$admin || !$admin->hasPermission('settings.manage')) {
            return $this->forbidden('You do not have permission to manage reserved names.');
        }
        return null;
    }

    private function activeAdmin(Request $request): ?Admin
    {
        $user = $request->user();
        if (!$user instanceof User) return null;
        $admin = $user->adminAccount();
        return ($admin && $admin->status === 'active') ? $admin : null;
    }

    // ---- helpers --------------------------------------------------------

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

        return [
            'users'  => $userIds->diff($ackByType->get('user', []))->count(),
            'links'  => $linkIds->diff($ackByType->get('link', []))->count(),
            'extras' => $extraIds->diff($ackByType->get('extra', []))->count(),
        ];
    }

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
                    'owner'        => $u ? ['id' => $u->id, 'name' => $u->name, 'handle' => $u->handle] : null,
                    'acknowledged' => isset($acks[$key]) ? optional($acks[$key]->acknowledged_at)->toIso8601String() : null,
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
                    'owner'        => $l->user ? ['id' => $l->user->id, 'name' => $l->user->name, 'handle' => $l->user->handle] : null,
                    'acknowledged' => isset($acks[$key]) ? optional($acks[$key]->acknowledged_at)->toIso8601String() : null,
                ];
            })->all();

        $extras = LinkAlias::whereRaw('LOWER(alias) = ?', [$lc])
            ->with(['link:id,user_id,title,alias', 'link.user:id,name,email,handle'])
            ->orderBy('id')
            ->get()
            ->map(function ($a) use ($acks) {
                $key = 'extra:' . $a->id;
                $link = $a->link;
                $owner = $link?->user;
                return [
                    'kind'         => 'extra',
                    'id'           => $a->id,
                    'label'        => '/' . $a->alias,
                    'detail'       => 'Extra alias on ' . ($link?->title ?: ('/' . ($link?->alias ?? '?'))),
                    'owner'        => $owner ? ['id' => $owner->id, 'name' => $owner->name, 'handle' => $owner->handle] : null,
                    'acknowledged' => isset($acks[$key]) ? optional($acks[$key]->acknowledged_at)->toIso8601String() : null,
                ];
            })->all();

        return array_merge($users, $links, $extras);
    }

    private function transform(BannedName $item, ?array $conflicts = null): array
    {
        return [
            'id'                    => $item->id,
            'name'                  => $item->name,
            'note'                  => $item->note,
            'force_rename_on_login' => (bool) $item->force_rename_on_login,
            'created_at'            => optional($item->created_at)->toIso8601String(),
            'conflicts'             => $conflicts,
            'conflict_total'        => $conflicts ? array_sum($conflicts) : null,
        ];
    }
}
