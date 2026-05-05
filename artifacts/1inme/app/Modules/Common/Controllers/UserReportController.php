<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\UserReport;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorPostComment;
use App\Modules\User\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Public "report" endpoints for users, comments, and DMs (Task #1211).
 * All three feed the same `user_reports` table with a `target_type`
 * discriminator so the admin moderation queue can list them in one
 * view, ranked by `coalesced_count` so prolific reports float up.
 *
 * Coalescing rules: a report from the same reporter (or, for
 * anonymous, the same IP) on the same target inside a 24h window
 * bumps `coalesced_count` instead of inserting a new row. This is
 * the same rule BiolinkReport already uses for /report on biolinks.
 */
class UserReportController extends Controller
{
    public function reportUser(Request $request, int $creator)
    {
        return $this->store($request, 'user', $creator, fn() => User::find($creator));
    }

    public function reportPost(Request $request, int $post)
    {
        return $this->store($request, 'post', $post, function () use ($post) {
            return CreatorPost::query()->withoutGlobalScope('workspace')
                ->whereKey($post)->whereNotNull('published_at')->first();
        });
    }

    public function reportComment(Request $request, int $comment)
    {
        return $this->store($request, 'comment', $comment, fn() => CreatorPostComment::find($comment));
    }

    public function reportMessage(Request $request, int $message)
    {
        // Inbox messages live in inbox_messages — we look it up by id
        // without scoping, since the public report can come from a DM
        // viewer who isn't a member of the receiving workspace.
        return $this->store($request, 'message', $message, function () use ($message) {
            return DB::table('inbox_messages')->where('id', $message)->first();
        });
    }

    protected function store(Request $request, string $type, int $targetId, \Closure $loader)
    {
        $target = $loader();
        if (!$target) abort(404);

        $data = $request->validate([
            'reason'  => 'required|string|in:' . implode(',', array_keys(UserReport::REASONS)),
            'comment' => 'nullable|string|max:1000',
        ]);

        $viewer = ViewerSession::user() ?? auth()->user();

        // Throttle: 10 reports per hour per (viewer or IP).
        $rateKey = 'rep:' . ($viewer ? "u{$viewer->id}" : 'ip' . $request->ip());
        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            return response()->json(['success' => false, 'message' => 'Too many reports — try again later.'], 429);
        }
        RateLimiter::hit($rateKey, 3600);

        $window = now()->subHours(UserReport::COALESCE_WINDOW_HOURS);
        $existing = UserReport::query()
            ->where('target_type', $type)
            ->where('target_id', $targetId)
            ->where('status', 'pending')
            ->where('created_at', '>=', $window)
            ->where(function ($w) use ($viewer, $request) {
                if ($viewer) {
                    $w->where('reporter_user_id', $viewer->id);
                } else {
                    $w->whereNull('reporter_user_id')->where('reporter_ip', $request->ip());
                }
            })
            ->first();

        if ($existing) {
            $existing->increment('coalesced_count');
            // Replace the comment if the latest report has more detail.
            if (!empty($data['comment']) && empty($existing->comment)) {
                $existing->comment = $data['comment'];
                $existing->save();
            }
            return response()->json(['success' => true, 'coalesced' => true]);
        }

        UserReport::create([
            'target_type'      => $type,
            'target_id'        => $targetId,
            'reporter_user_id' => $viewer?->id,
            'reporter_ip'      => $request->ip(),
            'reason'           => $data['reason'],
            'comment'          => $data['comment'] ?? null,
            'status'           => 'pending',
            'coalesced_count'  => 1,
        ]);
        return response()->json(['success' => true, 'coalesced' => false]);
    }
}
