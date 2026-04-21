<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterIssueJob;
use App\Modules\Common\Models\NewsletterIssue;
use App\Modules\Common\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $query = NewsletterSubscriber::query()->orderByDesc('id');
        if ($q !== '') {
            $query->where('email', 'ilike', '%' . $q . '%');
        }
        $subscribers = $query->paginate(50)->withQueryString();
        $totals = [
            'all'    => NewsletterSubscriber::count(),
            'active' => NewsletterSubscriber::whereNull('unsubscribed_at')->count(),
        ];

        $optOutWindowDays = 30;
        $windowStart = now()->subDays($optOutWindowDays);
        $sourceCounts = NewsletterSubscriber::query()
            ->whereNotNull('unsubscribed_at')
            ->where('unsubscribed_at', '>=', $windowStart)
            ->selectRaw("COALESCE(NULLIF(unsubscribe_source, ''), 'unknown') AS src, COUNT(*) AS c")
            ->groupBy('src')
            ->pluck('c', 'src')
            ->all();

        $inboxCount   = (int) ($sourceCounts['inbox'] ?? 0);
        $footerCount  = (int) ($sourceCounts['footer'] ?? 0);
        $knownOther   = array_sum(array_map('intval', array_diff_key($sourceCounts, array_flip(['inbox', 'footer']))));
        $unknownCount = $knownOther;
        $optOutTotal  = array_sum(array_map('intval', $sourceCounts));

        $pct = function (int $n) use ($optOutTotal) {
            return $optOutTotal > 0 ? round(($n / $optOutTotal) * 100) : 0;
        };

        $optOutBreakdown = [
            'window_days' => $optOutWindowDays,
            'total'       => $optOutTotal,
            'inbox'       => ['count' => $inboxCount,   'pct' => $pct($inboxCount)],
            'footer'      => ['count' => $footerCount,  'pct' => $pct($footerCount)],
            'unknown'     => ['count' => $unknownCount, 'pct' => $pct($unknownCount)],
        ];

        return view('admin.newsletter.index', compact('subscribers', 'q', 'totals', 'optOutBreakdown'));
    }

    public function destroy(NewsletterSubscriber $subscriber)
    {
        $subscriber->delete();
        return back()->with('success', 'Subscriber removed.');
    }

    public function export()
    {
        $filename = 'newsletter-subscribers-' . date('Ymd-His') . '.csv';
        return new StreamedResponse(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['email', 'source', 'subscribed_at', 'unsubscribed_at', 'unsubscribe_source']);
            NewsletterSubscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->email,
                        $r->source,
                        optional($r->created_at)->toIso8601String(),
                        optional($r->unsubscribed_at)->toIso8601String(),
                        $r->unsubscribe_source,
                    ]);
                }
            });
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function compose(Request $request)
    {
        $activeCount = NewsletterSubscriber::whereNull('unsubscribed_at')->count();

        // Sorting + filtering for the past-issues table.
        // The "warning" threshold here must match the one used in the view (>= 1% of delivered).
        $highRateThreshold = 1.0;

        $sort = $request->query('sort', 'recent');
        $dir  = strtolower((string) $request->query('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        if (!in_array($sort, ['recent', 'unsub_rate'], true)) {
            $sort = 'recent';
        }
        $highOnly = $request->boolean('high_only');

        $query = NewsletterIssue::query();

        if ($highOnly) {
            // Only issues whose unsubscribe rate is at or above the warning threshold.
            // Requires at least one delivered recipient so the rate is meaningful.
            $query->where('sent_count', '>', 0)
                  ->whereRaw('(unsubscribed_count::float / NULLIF(sent_count, 0)) * 100 >= ?', [$highRateThreshold]);
        }

        if ($sort === 'unsub_rate') {
            // Sort by unsubscribe rate. Issues with no delivered recipients
            // have no defined rate and are always pushed to the bottom.
            $query->orderByRaw(
                '(CASE WHEN sent_count > 0 THEN unsubscribed_count::float / sent_count ELSE NULL END) '
                . ($dir === 'asc' ? 'ASC NULLS LAST' : 'DESC NULLS LAST')
            )->orderByDesc('id');
        } else {
            $query->orderByDesc('id');
        }

        $issues = $query->paginate(20)->withQueryString();

        return view('admin.newsletter.compose', compact(
            'activeCount', 'issues', 'sort', 'dir', 'highOnly', 'highRateThreshold'
        ));
    }

    public function send(Request $request)
    {
        $validated = $request->validate([
            'subject'   => 'required|string|max:255',
            'body_html' => 'required|string|max:200000',
        ]);

        $activeCount = NewsletterSubscriber::whereNull('unsubscribed_at')->count();
        if ($activeCount === 0) {
            return back()
                ->withInput()
                ->withErrors(['body_html' => 'There are no active subscribers to send to yet.']);
        }

        $admin = $request->user();
        $issue = NewsletterIssue::create([
            'subject'          => $validated['subject'],
            'body_html'        => $validated['body_html'],
            'status'           => 'queued',
            'recipients_count' => $activeCount,
            'sender_id'        => optional($admin)->id,
            'sender_email'     => optional($admin)->email,
        ]);

        SendNewsletterIssueJob::dispatch($issue->id);

        return redirect()
            ->route('admin.newsletter.compose')
            ->with('success', "Issue queued for {$activeCount} subscriber(s). Delivery runs in the background.");
    }

    /**
     * Email the current draft to the logged-in admin only, so they can
     * preview how it renders in a real client before broadcasting.
     * No NewsletterIssue row is created — this never appears in past issues.
     * Rate-limited to prevent the button being spammed.
     */
    public function sendTest(Request $request)
    {
        $validated = $request->validate([
            'subject'   => 'required|string|max:255',
            'body_html' => 'required|string|max:200000',
        ]);

        $admin = Auth::guard('admin')->user() ?: $request->user();
        if (!$admin || empty($admin->email)) {
            return back()
                ->withInput()
                ->withErrors(['body_html' => 'We could not find an email address on your admin account to send the test to.']);
        }

        $rateKey = 'newsletter-test:' . $admin->id;
        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            $seconds = RateLimiter::availableIn($rateKey);
            $minutes = max(1, (int) ceil($seconds / 60));
            return back()
                ->withInput()
                ->with('error', "You've sent a few test emails recently — please try again in about {$minutes} minute" . ($minutes === 1 ? '' : 's') . '.');
        }
        RateLimiter::hit($rateKey, 600);

        $fromAddress = config('mail.from.address', 'noreply@1inme.com');
        $fromName    = config('mail.from.name', config('app.name'));
        $subject     = '[TEST] ' . $validated['subject'];
        $bodyHtml    = $validated['body_html'];

        try {
            Mail::html($bodyHtml, function ($m) use ($admin, $subject, $fromAddress, $fromName) {
                $m->to($admin->email)
                  ->subject($subject)
                  ->from($fromAddress, $fromName);
            });
        } catch (\Throwable $e) {
            Log::warning('Newsletter test send failed', [
                'admin_id' => $admin->id,
                'error'    => $e->getMessage(),
            ]);
            return back()
                ->withInput()
                ->with('error', 'Could not send the test email. Please try again or check the mail settings.');
        }

        return back()
            ->withInput()
            ->with('success', "Test email sent to {$admin->email}.");
    }
}
