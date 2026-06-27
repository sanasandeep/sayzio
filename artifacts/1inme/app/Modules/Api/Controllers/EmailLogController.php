<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Models\EmailLog;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\EmailTemplateRegistry;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Bearer-token parity for the admin "Email Log" page so a platform admin can
 * browse, search and filter every outbound email and resend any one of them
 * from the Sayzio Mobile app.
 *
 * Mirrors the web {@see \App\Modules\Admin\Controllers\EmailLogController}:
 * the same filters, the same throttled resend through the central
 * {@see Emailer} pipeline, gated behind `settings.manage`.
 */
class EmailLogController extends Controller
{
    use ApiResponses;

    private const RESEND_MAX = 30;
    private const RESEND_DECAY_SECONDS = 60;

    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view the email log.');
        }

        $query = EmailLog::query()->with('user:id,name,email');

        if (($q = trim((string) $request->query('q', ''))) !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('recipient', 'like', $like)->orWhere('subject', 'like', $like);
            });
        }
        if (($category = (string) $request->query('category', '')) !== '') {
            $query->where('category', $category);
        }
        if (($status = (string) $request->query('status', '')) !== '') {
            $query->where('status', $status);
        }
        if (($key = (string) $request->query('key', '')) !== '') {
            $query->where('email_key', $key);
        }
        if (($from = (string) $request->query('from', '')) !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        if (($to = (string) $request->query('to', '')) !== '') {
            $query->whereDate('created_at', '<=', $to);
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return $this->ok([
            'logs' => collect($logs->items())->map(fn (EmailLog $l) => $this->summary($l))->all(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
            'categories' => EmailTemplateRegistry::CATEGORY_LABELS,
        ]);
    }

    public function show(Request $request, EmailLog $emailLog)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view the email log.');
        }

        $emailLog->load('user:id,name,email');

        return $this->ok($this->detail($emailLog));
    }

    public function resend(Request $request, EmailLog $emailLog)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to resend email.');
        }

        $throttleKey = 'admin-email-resend-api:' . $request->user()->id;
        if (RateLimiter::tooManyAttempts($throttleKey, self::RESEND_MAX)) {
            return $this->fail('Too many resends. Try again shortly.', 429, 'rate_limited');
        }
        RateLimiter::hit($throttleKey, self::RESEND_DECAY_SECONDS);

        if (empty($emailLog->body) && empty($emailLog->subject)) {
            return $this->fail('This log row has no stored content to resend.', 422, 'nothing_to_resend');
        }

        $new = Emailer::resend($emailLog);

        if ($new->status === 'failed') {
            return $this->fail('Resend failed: ' . ($new->error ?: 'unknown error'), 502, 'resend_failed');
        }

        return $this->ok(['resent_to' => $emailLog->recipient, 'log' => $this->summary($new)]);
    }

    private function summary(EmailLog $l): array
    {
        return [
            'id'         => $l->id,
            'recipient'  => $l->recipient,
            'subject'    => $l->subject,
            'email_key'  => $l->email_key,
            'category'   => $l->category,
            'status'     => $l->status,
            'is_resend'  => $l->isResend(),
            'created_at' => optional($l->created_at)->toIso8601String(),
        ];
    }

    private function detail(EmailLog $l): array
    {
        return $this->summary($l) + [
            'format' => $l->format,
            'body'   => $l->body,
            'error'  => $l->error,
            'meta'   => $l->meta,
            'user'   => $l->user ? ['id' => $l->user->id, 'name' => $l->user->name, 'email' => $l->user->email] : null,
        ];
    }
}
