<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\AdminActionLogger;
use App\Modules\User\Models\SubscriptionCreditReview;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Admin review of leftover plan value forfeited on a full-price upgrade.
 *
 * When a user upgrades mid-cycle they pay the full new-plan price and the
 * leftover days/add-on time on their old plan are NOT auto-credited. Each
 * upgrade flags a pending {@see SubscriptionCreditReview}. Here an admin
 * can either approve it — extending the new subscription's expiry (and the
 * shared add-on period) by a chosen number of days — or dismiss it. Every
 * decision is recorded in the admin audit trail.
 */
class CreditReviewController extends Controller
{
    public function __construct(protected AdminActionLogger $audit)
    {
    }

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['pending', 'approved', 'dismissed'], true)
            ? $request->query('status')
            : 'pending';

        $reviews = SubscriptionCreditReview::with(['user', 'oldPlan', 'newPlan', 'actionedBy', 'subscription'])
            ->where('status', $status)
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $counts = [
            'pending'   => SubscriptionCreditReview::where('status', 'pending')->count(),
            'approved'  => SubscriptionCreditReview::where('status', 'approved')->count(),
            'dismissed' => SubscriptionCreditReview::where('status', 'dismissed')->count(),
        ];

        return view('admin.credit-reviews.index', compact('reviews', 'status', 'counts'));
    }

    public function approve(Request $request, SubscriptionCreditReview $review)
    {
        if ($review->status !== 'pending') {
            return back()->with('error', 'This review has already been actioned.');
        }

        $data = $request->validate([
            'granted_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'note'         => ['nullable', 'string', 'max:1000'],
        ]);

        $grantedDays = (int) ($data['granted_days'] ?? $review->leftover_days);
        if ($grantedDays < 1) {
            return back()->with('error', 'Nothing to credit: leftover days is zero. Dismiss instead.');
        }

        DB::transaction(function () use ($review, $grantedDays, $data) {
            $subscription = $review->subscription()->with('user')->first();

            // Extend the new subscription period (add-on duration shares the
            // subscription period, so this credits add-on time too) and keep
            // the user's plan_expires_at mirror in sync.
            if ($subscription) {
                if ($subscription->current_period_end) {
                    $subscription->current_period_end = Carbon::parse($subscription->current_period_end)
                        ->addDays($grantedDays);
                    $subscription->save();
                }

                $user = $subscription->user;
                if ($user && $user->plan_expires_at) {
                    $user->plan_expires_at = Carbon::parse($user->plan_expires_at)->addDays($grantedDays);
                    $user->save();
                }
            }

            $review->status       = 'approved';
            $review->granted_days = $grantedDays;
            $review->actioned_by  = Auth::guard('admin')->id();
            $review->actioned_at  = now();
            $review->note         = $data['note'] ?? $review->note;
            $review->save();
        });

        $this->audit->log(AdminActionLogger::CREDIT_REVIEW_APPROVED, $review->user, [
            'review_id'    => $review->id,
            'granted_days' => $grantedDays,
            'leftover_days' => $review->leftover_days,
            'old_plan_id'  => $review->old_plan_id,
            'new_plan_id'  => $review->new_plan_id,
        ]);

        return back()->with('success', "Granted {$grantedDays} day(s) of credit.");
    }

    public function dismiss(Request $request, SubscriptionCreditReview $review)
    {
        if ($review->status !== 'pending') {
            return back()->with('error', 'This review has already been actioned.');
        }

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $review->status      = 'dismissed';
        $review->granted_days = 0;
        $review->actioned_by = Auth::guard('admin')->id();
        $review->actioned_at = now();
        $review->note        = $data['note'] ?? $review->note;
        $review->save();

        $this->audit->log(AdminActionLogger::CREDIT_REVIEW_DISMISSED, $review->user, [
            'review_id'   => $review->id,
            'old_plan_id' => $review->old_plan_id,
            'new_plan_id' => $review->new_plan_id,
        ]);

        return back()->with('success', 'Credit review dismissed.');
    }
}
