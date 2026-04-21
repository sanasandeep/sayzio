<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendNewsletterIssueJob;
use App\Modules\Common\Models\NewsletterIssue;
use App\Modules\Common\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
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
        return view('admin.newsletter.index', compact('subscribers', 'q', 'totals'));
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
            fputcsv($out, ['email', 'source', 'subscribed_at', 'unsubscribed_at']);
            NewsletterSubscriber::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $r) {
                    fputcsv($out, [
                        $r->email,
                        $r->source,
                        optional($r->created_at)->toIso8601String(),
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

    public function compose(Request $request)
    {
        $activeCount = NewsletterSubscriber::whereNull('unsubscribed_at')->count();
        $issues = NewsletterIssue::orderByDesc('id')->paginate(20);
        return view('admin.newsletter.compose', compact('activeCount', 'issues'));
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
}
