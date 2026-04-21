<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
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
}
