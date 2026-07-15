<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Integrations\SystemUpdateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Admin surface for the GitHub→EC2 one-click update feature.
 *
 * Routes:
 *   GET  /admin/system-update          → show()         (page)
 *   POST /admin/system-update/deploy   → triggerDeploy() (action)
 *   GET  /admin/system-update/status   → pollStatus()   (JSON, for polling)
 *   POST /admin/system-update/dismiss  → dismiss()      (cookie dismiss)
 *   POST /admin/system-update/refresh  → refresh()      (flush cache)
 *
 * Every route is gated behind the `settings.manage` permission (same as
 * the Integrations hub) so only elevated admins can see or trigger it.
 *
 * On Replit the page renders in "managed" mode — no deploy button —
 * because Replit handles deploys through its own pipeline.
 */
class SystemUpdateController extends Controller
{
    public function show()
    {
        $isReplit    = SystemUpdateService::isReplit();
        $configured  = SystemUpdateService::isConfigured();
        $status      = $configured && !$isReplit ? SystemUpdateService::cachedStatus() : null;
        $inProgress  = $configured && !$isReplit ? SystemUpdateService::isDeployInProgress() : false;
        $lastAudit   = SystemUpdateService::lastAudit();
        $latestRun   = ($configured && !$isReplit && $inProgress) ? SystemUpdateService::latestDeployRun() : null;

        return view('admin.system-update.show', compact(
            'isReplit', 'configured', 'status', 'inProgress', 'lastAudit', 'latestRun'
        ));
    }

    public function triggerDeploy(Request $request)
    {
        if (SystemUpdateService::isReplit()) {
            return back()->with('error', 'Deploys are managed by Replit on this environment.');
        }
        if (!SystemUpdateService::isConfigured()) {
            return back()->with('error', 'GitHub credentials are not configured.');
        }
        if (SystemUpdateService::isDeployInProgress()) {
            return back()->with('error', 'A deploy is already in progress — please wait for it to finish.');
        }

        $admin = Auth::guard('admin')->user();
        $email = $admin?->email ?? 'unknown';

        $result = SystemUpdateService::triggerDeploy($email);

        if ($result['ok']) {
            return redirect()
                ->route('admin.system-update.show')
                ->with('success', 'Deploy dispatched! The GitHub Actions workflow is now running. This page will update when it completes.');
        }

        return back()->with('error', $result['error']);
    }

    public function pollStatus()
    {
        if (SystemUpdateService::isReplit() || !SystemUpdateService::isConfigured()) {
            return response()->json(['managed' => true]);
        }

        $inProgress = SystemUpdateService::isDeployInProgress();
        $latestRun  = SystemUpdateService::latestDeployRun();

        // If the deploy completed (success/failure), release the lock so the
        // next poll shows the real up-to-date check instead of "in progress".
        if ($inProgress && $latestRun && $latestRun['status'] === 'completed') {
            SystemUpdateService::releaseDeployLock();
            SystemUpdateService::flushCache();
            $inProgress = false;
        }

        $status = $inProgress ? null : SystemUpdateService::cachedStatus();

        return response()->json([
            'in_progress' => $inProgress,
            'latest_run'  => $latestRun,
            'status'      => $status,
        ]);
    }

    public function refresh()
    {
        SystemUpdateService::flushCache();
        return redirect()->route('admin.system-update.show')
            ->with('success', 'Update status refreshed.');
    }

    public function dismiss(Request $request)
    {
        // Store a cookie with the remote SHA we dismissed so the banner
        // doesn't re-appear for the same commit.
        $sha = $request->input('sha', '');
        return redirect()
            ->back()
            ->withCookie(cookie('su_dismissed_sha', $sha, 60 * 24 * 7)); // 1 week
    }
}
