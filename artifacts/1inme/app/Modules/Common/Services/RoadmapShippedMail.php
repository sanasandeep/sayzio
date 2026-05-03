<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\RoadmapItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * One-off transactional email notifying a fan that the roadmap idea
 * they upvoted (or submitted) has just shipped. We deduplicate
 * recipients up the call chain — this class just delivers a single
 * branded message and swallows transport errors so a flaky SMTP
 * doesn't poison the triage redirect.
 */
class RoadmapShippedMail
{
    public static function dispatchFor(RoadmapItem $item, string $email): bool
    {
        $email = trim($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) return false;

        $alias    = $item->link?->alias ?? '';
        $publicUrl = $alias ? url('/' . $alias) : url('/');
        $appName  = config('app.name');
        $subject  = '[' . $appName . '] Shipped: ' . $item->title;

        $viewData = [
            'subject'   => $subject,
            'appName'   => $appName,
            'title'     => $item->title,
            'desc'      => $item->description,
            'votes'     => (int) $item->votes_count,
            'publicUrl' => $publicUrl,
            'creator'   => $item->link?->user?->name ?? 'The team',
        ];

        try {
            Mail::send('emails.roadmap-shipped', $viewData, function ($m) use ($email, $subject) {
                $m->to($email)->subject($subject);
            });
            return true;
        } catch (\Throwable $e) {
            Log::warning('roadmap_shipped_mail_failed', [
                'item_id' => $item->id,
                'email'   => $email,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
