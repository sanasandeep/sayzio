<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\LinkHealthChecker;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkBackup;
use App\Modules\User\Models\LinkHealthCheck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Web controller for the user-facing "Link Insurance" surfaces.
 *
 *   /user/links/{link}/insurance         GET   per-link settings + backups
 *   /user/links/{link}/insurance         POST  update settings + replace backups
 *   /user/links/{link}/insurance/restore POST  manually flip back to primary
 *   /user/links/{link}/insurance/probe   POST  run an on-demand probe
 *   /user/insurance                      GET   workspace-wide link health dashboard
 */
class LinkInsuranceController extends Controller
{
    public function __construct(
        protected LinkHealthChecker $checker,
    ) {}

    public function settings(Link $link)
    {
        $this->authorizeLink($link);
        $link->load('backups');
        return view('user.links.insurance.settings', [
            'link'             => $link,
            'cadenceOptions'   => LinkHealthChecker::ALLOWED_CADENCES,
            'maxBackups'       => LinkHealthChecker::MAX_BACKUPS_PER_LINK,
            'recentChecks'     => $link->healthChecks()
                ->orderByDesc('checked_at')
                ->limit(20)->get(),
        ]);
    }

    public function update(Request $request, Link $link)
    {
        $this->authorizeLink($link);

        $data = $request->validate([
            'insurance_enabled'             => 'sometimes|boolean',
            'insurance_cadence_minutes'     => 'required|integer|in:'.implode(',', LinkHealthChecker::ALLOWED_CADENCES),
            'insurance_failure_threshold'   => 'required|integer|min:1|max:10',
            'insurance_recovery_threshold'  => 'required|integer|min:1|max:10',
            'insurance_auto_restore'        => 'sometimes|boolean',
            'insurance_fallback_message'    => 'nullable|string|max:500',
            'backups'                       => 'array|max:'.LinkHealthChecker::MAX_BACKUPS_PER_LINK,
            'backups.*.url'                 => 'required_with:backups.*|url|max:2048',
            'backups.*.label'               => 'nullable|string|max:120',
        ]);

        // Belt-and-braces SSRF guard at submission time too — the
        // probe layer already refuses to fire at private/reserved IPs,
        // but rejecting them here means the user gets immediate
        // feedback instead of a silently-blocked check later.
        foreach ($request->input('backups', []) as $i => $b) {
            if (empty($b['url'])) continue;
            if ($why = $this->checker->ssrfReason($b['url'])) {
                return back()->withInput()->withErrors([
                    "backups.$i.url" => "Backup URL is not allowed ($why).",
                ]);
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

            // Replace-the-set semantics: simpler than diffing positions
            // and fine because we keep at most 3 backups per link.
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

        return redirect()
            ->route('user.links.insurance.settings', ['link' => $link->id])
            ->with('status', 'Link Insurance settings saved.');
    }

    /**
     * Manually flip a link out of failover state and back to its
     * primary URL. Useful when the user has fixed the underlying
     * destination and doesn't want to wait for the recovery threshold.
     */
    public function restorePrimary(Link $link)
    {
        $this->doRestore($link);
        return back()->with('status', 'Primary destination restored.');
    }

    /**
     * One-click restore reachable from the failover email and the
     * in-app notification "Restore now" action. Same effect as
     * {@see restorePrimary()} but accepts GET so it can be a plain
     * link in an email — Laravel's auth middleware still enforces
     * the user is signed in, and {@see authorizeLink()} re-checks
     * workspace permission.
     */
    public function restoreFromNotification(Link $link)
    {
        $this->doRestore($link);
        return redirect()
            ->route('user.links.insurance.settings', ['link' => $link->id])
            ->with('status', 'Primary destination restored.');
    }

    protected function doRestore(Link $link): void
    {
        $this->authorizeLink($link);
        if ($link->insurance_state === 'primary') return;
        $link->forceFill([
            'insurance_state'                 => 'primary',
            'insurance_active_url'            => null,
            'insurance_consecutive_failures'  => 0,
            'insurance_consecutive_successes' => 0,
        ])->save();
    }

    /**
     * Run one probe right now (bypasses cadence). Used by the "Test
     * now" button in the settings page so users can immediately see
     * whether their backup URLs are reachable.
     */
    public function probeNow(Link $link)
    {
        $this->authorizeLink($link);

        if (!$link->long_url && $link->backups()->count() === 0) {
            return back()->with('error', 'Nothing to probe — add a destination or backup URL first.');
        }

        $check = $this->checker->checkLink($link);
        $this->checker->recheckPrimaryFromFailover($link->fresh());

        return back()->with(
            'status',
            'Probe complete: '.$check->status.($check->http_code ? " (HTTP {$check->http_code})" : '').'.'
        );
    }

    /**
     * Workspace-wide Link Health dashboard. Lists every insurance-
     * enabled link with its current state, next probe time, and a
     * 7-day rolled-up uptime number.
     */
    public function dashboard(Request $request)
    {
        $links = Link::query()
            ->where('insurance_enabled', true)
            ->orderByRaw("CASE insurance_state WHEN 'down' THEN 0 WHEN 'failover' THEN 1 ELSE 2 END")
            ->orderByDesc('insurance_last_failover_at')
            ->paginate(25);

        // Compute 30-day uptime per shown link in one query so the
        // page doesn't N+1 when a user has dozens of insured links.
        $linkIds = $links->pluck('id')->all();
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

        return view('user.links.insurance.dashboard', [
            'links'  => $links,
            'uptime' => $uptime,
        ]);
    }

    /**
     * Workspace + ownership check. The route's implicit binding
     * already ran the workspace global scope, so the link is
     * guaranteed to live in the current workspace; we just need to
     * confirm the caller has links.edit on it.
     */
    protected function authorizeLink(Link $link): void
    {
        abort_unless($link->exists, 404);
        // Re-use the same gate the rest of LinkController uses.
        if (function_exists('workspace_can') && !workspace_can('links.edit')) {
            abort(403);
        }
    }
}
