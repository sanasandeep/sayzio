<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\AppLaunchSignup;
use App\Modules\Common\Support\AppLaunchNotifier;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin view over the mobile-app launch mailing list collected by the public
 * coming-soon modal. List, search, CSV export and per-row delete — mirrors
 * the newsletter-subscribers admin surface.
 */
class AppLaunchSignupController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = AppLaunchSignup::query()->orderByDesc('id');
        if ($q !== '') {
            $query->where('email', 'ilike', '%' . $q . '%');
        }
        $signups = $query->paginate(50)->withQueryString();

        $storeCounts = AppLaunchSignup::query()
            ->selectRaw("COALESCE(NULLIF(store, ''), 'unknown') AS s, COUNT(*) AS c")
            ->groupBy('s')
            ->pluck('c', 's')
            ->all();

        $totals = [
            'all'     => AppLaunchSignup::count(),
            'play'    => (int) ($storeCounts['play'] ?? 0),
            'app'     => (int) ($storeCounts['app'] ?? 0),
            'pending' => AppLaunchSignup::whereNull('notified_at')->whereNull('unsubscribed_at')->count(),
        ];

        $isLaunched = AppLaunchNotifier::isLaunched();

        return view('admin.app-launch.index', compact('signups', 'q', 'totals', 'isLaunched'));
    }

    /**
     * Fire the launch announcement on demand — the UI equivalent of the
     * `app-launch:notify` console command. Emails every un-notified signup the
     * live store link(s) and stamps each so re-clicking sends nothing new.
     *
     * A no-op (with a clear warning) while both store URLs are still empty, so
     * an admin can never blast a "download it now" email with no working link.
     */
    public function notify()
    {
        if (! AppLaunchNotifier::isLaunched()) {
            return back()->with('error', 'No store URL is configured yet. Set a Google Play or App Store URL in Marketing Settings before announcing the launch.');
        }

        $sent = AppLaunchNotifier::notifyIfLaunched();

        if ($sent === 0) {
            return back()->with('success', 'Nothing to send — every signup has already been notified.');
        }

        return back()->with('success', 'Launch announcement sent to ' . number_format($sent) . ' ' . ($sent === 1 ? 'signup' : 'signups') . '.');
    }

    public function export()
    {
        $filename = 'app-launch-signups-' . date('Ymd-His') . '.csv';
        return new StreamedResponse(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'store', 'signed_up_at', 'notified_at', 'unsubscribed_at']);
            AppLaunchSignup::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->email,
                        $r->store,
                        optional($r->created_at)->toIso8601String(),
                        optional($r->notified_at)->toIso8601String(),
                        optional($r->unsubscribed_at)->toIso8601String(),
                    ]);
                }
            });
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function destroy(AppLaunchSignup $signup)
    {
        $signup->delete();
        return back()->with('success', 'Signup removed.');
    }
}
