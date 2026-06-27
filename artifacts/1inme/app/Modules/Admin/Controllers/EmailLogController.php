<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\EmailTemplateRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Admin "Email Log" viewer. Every outbound send is recorded as an
 * {@see EmailLog} row (by the central {@see Emailer} pipeline or the catch-all
 * MessageSent listener), so this gives operators a searchable, filterable
 * ledger of all platform email with a throttled per-row "Resend".
 *
 * Gated by `settings.manage` at the route layer.
 */
class EmailLogController extends Controller
{
    /** Resend throttle: attempts allowed per admin per decay window. */
    private const RESEND_MAX = 30;
    private const RESEND_DECAY_SECONDS = 60;

    public function index(Request $request)
    {
        $filters = [
            'q'        => trim((string) $request->query('q', '')),
            'category' => (string) $request->query('category', ''),
            'status'   => (string) $request->query('status', ''),
            'key'      => (string) $request->query('key', ''),
            'from'     => (string) $request->query('from', ''),
            'to'       => (string) $request->query('to', ''),
        ];

        $query = EmailLog::query()->with('user:id,name,email');

        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $query->where(function ($w) use ($like) {
                $w->where('recipient', 'like', $like)
                  ->orWhere('subject', 'like', $like);
            });
        }
        if ($filters['category'] !== '') {
            $query->where('category', $filters['category']);
        }
        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }
        if ($filters['key'] !== '') {
            $query->where('email_key', $filters['key']);
        }
        if ($filters['from'] !== '') {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if ($filters['to'] !== '') {
            $query->whereDate('created_at', '<=', $filters['to']);
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('admin.email-logs.index', [
            'logs'       => $logs,
            'filters'    => $filters,
            'categories' => EmailTemplateRegistry::CATEGORY_LABELS,
        ]);
    }

    public function show(EmailLog $emailLog)
    {
        $emailLog->load('user:id,name,email');

        return view('admin.email-logs.show', [
            'log' => $emailLog,
        ]);
    }

    public function resend(Request $request, EmailLog $emailLog)
    {
        $throttleKey = 'admin-email-resend:' . ($request->user()?->id ?? 'anon');

        if (RateLimiter::tooManyAttempts($throttleKey, self::RESEND_MAX)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->with('error', "Too many resends. Try again in {$seconds}s.");
        }
        RateLimiter::hit($throttleKey, self::RESEND_DECAY_SECONDS);

        if (empty($emailLog->body) && empty($emailLog->subject)) {
            return back()->with('error', 'This log row has no stored content to resend.');
        }

        $new = Emailer::resend($emailLog);

        $status = $new->status === 'failed'
            ? back()->with('error', 'Resend failed: ' . ($new->error ?: 'unknown error'))
            : back()->with('success', 'Resent to ' . $emailLog->recipient . '.');

        return $status;
    }
}
