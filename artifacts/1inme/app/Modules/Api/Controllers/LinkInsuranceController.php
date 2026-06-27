<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\LinkHealthChecker;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkBackup;
use App\Modules\User\Models\LinkHealthCheck;
use App\Modules\User\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Sanctum Bearer-token parity for the web "Link Insurance" surfaces
 * (see {@see \App\Modules\User\Controllers\LinkInsuranceController}).
 *
 *   GET    /api/v1/insurance                       workspace dashboard
 *   GET    /api/v1/links/{id}/insurance            per-link settings + backups
 *   PUT    /api/v1/links/{id}/insurance            update settings + replace backups
 *   POST   /api/v1/links/{id}/insurance/restore    flip back to primary
 *   POST   /api/v1/links/{id}/insurance/probe      run an on-demand probe
 *
 * The underlying probe / failover / restore decisions all live in
 * {@see LinkHealthChecker}; this controller only mirrors the web actions
 * so mobile/API callers get the same protection. The signed-email
 * one-click `restoreFromNotification` / `promoteNext` routes are web-only
 * and intentionally not exposed here (the interactive restore covers it).
 *
 * Authorization mirrors the web feature. The Sanctum API is stateless and
 * never runs SetActiveWorkspace, so the {@see BelongsToWorkspace} global
 * scope is inactive here; instead of relying on a bound active workspace we
 * resolve links explicitly across the caller's accessible workspaces:
 *  - links the caller owns (personal links + API-created links, which land
 *    with workspace_id = null, are always reachable by their owner);
 *  - links that live in a workspace the caller belongs to, even when another
 *    member owns them — matching the web dashboard, which lists every link in
 *    the active workspace.
 * Per-link actions then enforce the same role permission the web feature
 * intends — reads need `links.view`, mutations need `links.edit` — via
 * {@see User::canInWorkspace()} (link owner and workspace owner always pass).
 */
class LinkInsuranceController extends Controller
{
    use ApiResponses;

    public function __construct(
        protected LinkHealthChecker $checker,
    ) {}

    /**
     * Workspace-wide Link Health dashboard. Lists every insurance-enabled
     * link with its current state, last-checked time, and a 30-day uptime
     * ratio — mirroring the web dashboard ordering (down → failover →
     * primary, most-recent failover first).
     */
    public function dashboard(Request $request)
    {
        $user         = $request->user();
        $workspaceIds = $this->viewableWorkspaceIds($user);

        $page = Link::where('insurance_enabled', true)
            ->where(function ($q) use ($user, $workspaceIds) {
                $q->where('user_id', $user->id);
                if (!empty($workspaceIds)) {
                    $q->orWhereIn('workspace_id', $workspaceIds);
                }
            })
            ->orderByRaw("CASE insurance_state WHEN 'down' THEN 0 WHEN 'failover' THEN 1 ELSE 2 END")
            ->orderByDesc('insurance_last_failover_at')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        $linkIds = collect($page->items())->pluck('id')->all();
        $uptime  = collect();
        if ($linkIds) {
            $uptime = LinkHealthCheck::query()
                ->whereIn('link_id', $linkIds)
                ->where('checked_at', '>=', now()->subDays(30))
                ->select('link_id')
                ->selectRaw("SUM(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END)::float / COUNT(*) AS uptime_ratio")
                ->selectRaw('COUNT(*) AS sample_count')
                ->groupBy('link_id')
                ->get()
                ->keyBy('link_id');
        }

        return $this->ok([
            'items' => collect($page->items())->map(function (Link $l) use ($uptime) {
                $u = $uptime->get($l->id);
                return [
                    'id'               => $l->id,
                    'title'            => $l->title,
                    'alias'            => $l->alias,
                    'long_url'         => $l->long_url,
                    'short_url'        => $l->getShortUrl(),
                    'state'            => $l->insurance_state,
                    'active_url'       => $l->insurance_active_url,
                    'last_checked_at'  => optional($l->insurance_last_checked_at)->toIso8601String(),
                    'last_failover_at' => optional($l->insurance_last_failover_at)->toIso8601String(),
                    'uptime_ratio'     => $u && $u->sample_count > 0 ? round((float) $u->uptime_ratio, 4) : null,
                    'uptime_samples'   => $u ? (int) $u->sample_count : 0,
                ];
            })->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    /**
     * Per-link insurance settings + backups + recent probe history.
     */
    public function show(Request $request, int $id)
    {
        $link = $this->findLink($request, $id);
        if (!$link) return $this->notFound('Link not found.');
        if (!$this->canAct($request, $link, 'links.view')) return $this->forbidden();

        return $this->ok($this->settingsPayload($link));
    }

    /**
     * Update settings + replace the backup set. Mirrors the web
     * controller's validation, SSRF guard, and replace-the-set semantics.
     */
    public function update(Request $request, int $id)
    {
        $link = $this->findLink($request, $id);
        if (!$link) return $this->notFound('Link not found.');
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        $data = $request->validate([
            'insurance_enabled'             => 'sometimes|boolean',
            'insurance_cadence_minutes'     => ['required', 'integer', Rule::in(LinkHealthChecker::ALLOWED_CADENCES)],
            'insurance_failure_threshold'   => 'required|integer|min:1|max:10',
            'insurance_recovery_threshold'  => 'required|integer|min:1|max:10',
            'insurance_auto_restore'        => 'sometimes|boolean',
            'insurance_fallback_message'    => 'nullable|string|max:500',
            'backups'                       => 'array|max:'.LinkHealthChecker::MAX_BACKUPS_PER_LINK,
            'backups.*.url'                 => 'nullable|url|max:2048',
            'backups.*.label'               => 'nullable|string|max:120',
        ]);

        // Belt-and-braces SSRF guard at submission time too — gives the
        // caller immediate feedback instead of a silently-blocked probe.
        foreach ($request->input('backups', []) as $i => $b) {
            if (empty($b['url'])) continue;
            if ($why = $this->checker->ssrfReason($b['url'])) {
                return $this->fail(
                    "Backup URL is not allowed ($why).",
                    422,
                    'validation_error',
                    ["backups.$i.url" => ["Backup URL is not allowed ($why)."]],
                );
            }
        }

        DB::transaction(function () use ($link, $request, $data) {
            $link->fill([
                'insurance_enabled'            => (bool) $request->input('insurance_enabled'),
                'insurance_cadence_minutes'    => $data['insurance_cadence_minutes'],
                'insurance_failure_threshold'  => $data['insurance_failure_threshold'],
                'insurance_recovery_threshold' => $data['insurance_recovery_threshold'],
                'insurance_auto_restore'       => (bool) $request->input('insurance_auto_restore', true),
                'insurance_fallback_message'   => $data['insurance_fallback_message'] ?? null,
            ])->save();

            // Replace-the-set semantics — at most 3 backups per link.
            $link->backups()->delete();
            foreach ($request->input('backups', []) as $i => $b) {
                if (empty($b['url'])) continue;
                LinkBackup::create([
                    'link_id'  => $link->id,
                    'position' => $i + 1,
                    'url'      => $b['url'],
                    'label'    => $b['label'] ?? null,
                ]);
            }
        });

        return $this->ok($this->settingsPayload($link->fresh()));
    }

    /**
     * Manually flip a link out of failover state and back to its primary
     * URL. Same effect as the web "Restore primary" button.
     */
    public function restore(Request $request, int $id)
    {
        $link = $this->findLink($request, $id);
        if (!$link) return $this->notFound('Link not found.');
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        if ($link->insurance_state !== 'primary') {
            $link->forceFill([
                'insurance_state'                 => 'primary',
                'insurance_active_url'            => null,
                'insurance_consecutive_failures'  => 0,
                'insurance_consecutive_successes' => 0,
            ])->save();
        }

        return $this->ok([
            'message' => 'Primary destination restored.',
            'link'    => $this->settingsPayload($link->fresh()),
        ]);
    }

    /**
     * Run one probe right now (bypasses cadence). Mirrors the web "Test
     * now" button so callers can immediately see whether the destinations
     * are reachable.
     */
    public function probe(Request $request, int $id)
    {
        $link = $this->findLink($request, $id);
        if (!$link) return $this->notFound('Link not found.');
        if (!$this->canAct($request, $link, 'links.edit')) return $this->forbidden();

        if (!$link->long_url && $link->backups()->count() === 0) {
            return $this->fail(
                'Nothing to probe — add a destination or backup URL first.',
                422,
                'nothing_to_probe',
            );
        }

        $check = $this->checker->checkLink($link);
        $this->checker->recheckPrimaryFromFailover($link->fresh());

        return $this->ok([
            'message' => 'Probe complete: '.$check->status.($check->http_code ? " (HTTP {$check->http_code})" : '').'.',
            'check'   => [
                'status'     => $check->status,
                'http_code'  => $check->http_code,
                'latency_ms' => $check->latency_ms,
                'target_url' => $check->target_url,
                'checked_at' => optional($check->checked_at)->toIso8601String(),
            ],
            'link'    => $this->settingsPayload($link->fresh()),
        ]);
    }

    /**
     * Shared serializer for the per-link settings response.
     */
    protected function settingsPayload(Link $link): array
    {
        $link->load('backups');

        $recent = $link->healthChecks()
            ->orderByDesc('checked_at')
            ->limit(20)
            ->get()
            ->map(fn (LinkHealthCheck $c) => [
                'status'     => $c->status,
                'http_code'  => $c->http_code,
                'latency_ms' => $c->latency_ms,
                'target_url' => $c->target_url,
                'checked_at' => optional($c->checked_at)->toIso8601String(),
            ])->all();

        return [
            'link' => [
                'id'        => $link->id,
                'title'     => $link->title,
                'alias'     => $link->alias,
                'long_url'  => $link->long_url,
                'short_url' => $link->getShortUrl(),
            ],
            'settings' => [
                'insurance_enabled'            => (bool) $link->insurance_enabled,
                'insurance_cadence_minutes'    => (int) ($link->insurance_cadence_minutes ?? 15),
                'insurance_failure_threshold'  => (int) ($link->insurance_failure_threshold ?? 2),
                'insurance_recovery_threshold' => (int) ($link->insurance_recovery_threshold ?? 3),
                'insurance_auto_restore'       => (bool) ($link->insurance_auto_restore ?? true),
                'insurance_fallback_message'   => $link->insurance_fallback_message,
            ],
            'state' => [
                'insurance_state'      => $link->insurance_state,
                'insurance_active_url' => $link->insurance_active_url,
                'last_checked_at'      => optional($link->insurance_last_checked_at)->toIso8601String(),
                'last_failover_at'     => optional($link->insurance_last_failover_at)->toIso8601String(),
            ],
            'backups' => $link->backups->map(fn (LinkBackup $b) => [
                'id'              => $b->id,
                'position'        => $b->position,
                'url'             => $b->url,
                'label'           => $b->label,
                'last_status'     => $b->last_status,
                'last_http_code'  => $b->last_http_code,
                'last_checked_at' => optional($b->last_checked_at)->toIso8601String(),
            ])->all(),
            'recent_checks' => $recent,
            'options' => [
                'cadences'    => LinkHealthChecker::ALLOWED_CADENCES,
                'max_backups' => LinkHealthChecker::MAX_BACKUPS_PER_LINK,
            ],
        ];
    }

    /**
     * Resolve a link the authenticated user may reach, or null. The API is
     * stateless (no active workspace bound), so we can't lean on the
     * {@see BelongsToWorkspace} global scope; we resolve explicitly:
     *  1. links the user owns directly (personal + API-created links whose
     *     workspace_id is null are only reachable this way), then
     *  2. links living in any workspace the user belongs to (collaborator
     *     parity with the web dashboard). Per-link permission is enforced
     *     separately by {@see canAct()}.
     */
    protected function findLink(Request $request, int $id): ?Link
    {
        $user = $request->user();

        $link = Link::where('user_id', $user->id)->find($id);
        if ($link) return $link;

        $workspaceIds = $this->accessibleWorkspaceIds($user);
        if (empty($workspaceIds)) return null;

        return Link::whereIn('workspace_id', $workspaceIds)->find($id);
    }

    /**
     * IDs of every workspace the user can access (owned + member-of), or an
     * empty array when the links table predates the workspace column. Used to
     * resolve a single link before {@see canAct()} applies the fine-grained
     * permission check.
     */
    protected function accessibleWorkspaceIds($user): array
    {
        if (!Schema::hasColumn('links', 'workspace_id')) return [];

        return $user->accessibleWorkspaces()->pluck('id')->all();
    }

    /**
     * IDs of the workspaces whose links the user may *view*. The dashboard
     * enumerates links across workspaces, so it must filter up front to the
     * memberships that grant `links.view` (the workspace owner always passes)
     * — owned personal links are added separately by the caller.
     */
    protected function viewableWorkspaceIds($user): array
    {
        if (!Schema::hasColumn('links', 'workspace_id')) return [];

        return $user->accessibleWorkspaces()
            ->filter(fn ($ws) => $user->canInWorkspace($ws, 'links.view'))
            ->pluck('id')
            ->all();
    }

    /**
     * Enforce the same role permission the web feature intends (`links.view`
     * on reads, `links.edit` on mutations). The link's own creator always
     * passes (covers personal/API links with no workspace_id); for links the
     * caller only reaches as a collaborator, {@see User::canInWorkspace()}
     * lets the workspace owner through and consults the member's role
     * otherwise.
     */
    protected function canAct(Request $request, Link $link, string $permission): bool
    {
        $user = $request->user();

        if ((int) $link->user_id === (int) $user->id) return true;
        if (empty($link->workspace_id)) return true;

        $workspace = Workspace::find($link->workspace_id);
        if (!$workspace) return true;

        return $user->canInWorkspace($workspace, $permission);
    }
}
