<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\CreatorTip;
use App\Modules\User\Models\PostUnlock;
use App\Modules\User\Models\SubscriptionPromoCode;
use App\Modules\User\Models\SubscriptionTier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Creator-side dashboard for the monetization surface (Task #1209):
 *  - Tier CRUD (free + paid tiers, perks, badge, color)
 *  - Promo-code CRUD
 *  - Earnings overview
 *  - Subscribers list
 *  - Payments (unified ledger)
 */
class CreatorMonetizationController extends Controller
{
    public function index(Request $request)
    {
        return $this->earnings($request);
    }

    // ─── Earnings ──────────────────────────────────────────────────
    public function earnings(Request $request)
    {
        $user = $request->user();

        $events = CreatorPaymentEvent::query()
            ->where('creator_user_id', $user->id)
            ->orderByDesc('occurred_at')
            ->limit(50)
            ->get();

        $now = now();

        // Rolling-window totals (Task #1209). today / 7d / 30d give the
        // creator a quick pulse; thisMonth / allTime are the long-term
        // anchors. We exclude refunds (negative cents) from these.
        $sumPositive = function (\Carbon\Carbon $from) use ($user) {
            return (int) CreatorPaymentEvent::query()
                ->where('creator_user_id', $user->id)
                ->where('amount_cents', '>', 0)
                ->where('occurred_at', '>=', $from)
                ->sum('amount_cents');
        };
        $today     = $sumPositive($now->copy()->startOfDay());
        $last7     = $sumPositive($now->copy()->subDays(7));
        $last30    = $sumPositive($now->copy()->subDays(30));
        $thisMonth = $sumPositive($now->copy()->startOfMonth());
        $allTime = (int) CreatorPaymentEvent::query()
            ->where('creator_user_id', $user->id)
            ->where('amount_cents', '>', 0)
            ->sum('amount_cents');
        $refundsAllTime = (int) CreatorPaymentEvent::query()
            ->where('creator_user_id', $user->id)
            ->where('amount_cents', '<', 0)
            ->sum('amount_cents');

        $bySource = CreatorPaymentEvent::query()
            ->selectRaw('source, SUM(amount_cents) as total')
            ->where('creator_user_id', $user->id)
            ->groupBy('source')
            ->pluck('total', 'source')
            ->all();

        // Active subscribers + MRR. We normalize yearly subs to a
        // monthly figure (price/12) so the headline is comparable.
        $activeSubs = CreatorSubscription::query()
            ->where('creator_user_id', $user->id)
            ->whereIn('status', [CreatorSubscription::STATUS_ACTIVE, CreatorSubscription::STATUS_TRIALING])
            ->get(['price_cents', 'billing_cycle']);
        $activeSubscribers = $activeSubs->count();
        $mrrCents = (int) $activeSubs->reduce(function ($carry, CreatorSubscription $s) {
            $monthly = $s->billing_cycle === 'yearly'
                ? (int) round(((int) $s->price_cents) / 12)
                : (int) $s->price_cents;
            return $carry + $monthly;
        }, 0);

        // Churn % — % of subs that were active 30d ago who have since
        // canceled. We approximate "active 30d ago" as currently-active
        // + canceled-in-window, since we don't keep a status snapshot.
        $canceled30 = (int) CreatorSubscription::query()
            ->where('creator_user_id', $user->id)
            ->where('canceled_at', '>=', $now->copy()->subDays(30))
            ->count();
        $denom = max(1, $activeSubscribers + $canceled30);
        $churn30 = round(($canceled30 / $denom) * 100, 1);

        // LTV — average total credits per fan who has ever paid us.
        $payingFans = CreatorPaymentEvent::query()
            ->where('creator_user_id', $user->id)
            ->where('amount_cents', '>', 0)
            ->whereNotNull('fan_id')
            ->distinct('fan_id')
            ->count('fan_id');
        $ltvCents = $payingFans > 0 ? (int) round($allTime / $payingFans) : 0;

        // Top-earning posts in the last 90d (PPV unlocks + tips on a
        // specific post). Joins are kept off the hot path: we aggregate
        // the source tables and resolve titles in one batched query.
        $since = $now->copy()->subDays(90);
        $tipsByPost = CreatorTip::query()
            ->where('creator_user_id', $user->id)
            ->whereNotNull('post_id')
            ->where('created_at', '>=', $since)
            ->selectRaw('post_id, SUM(amount_cents) as total')
            ->groupBy('post_id')->pluck('total', 'post_id')->all();
        $unlocksByPost = PostUnlock::query()
            ->whereHas('post', fn ($q) => $q->withoutGlobalScope('workspace')->where('user_id', $user->id))
            ->where('created_at', '>=', $since)
            ->selectRaw('post_id, SUM(price_cents) as total')
            ->groupBy('post_id')->pluck('total', 'post_id')->all();
        $byPost = [];
        foreach ($tipsByPost as $pid => $total)    $byPost[$pid] = ($byPost[$pid] ?? 0) + (int) $total;
        foreach ($unlocksByPost as $pid => $total) $byPost[$pid] = ($byPost[$pid] ?? 0) + (int) $total;
        arsort($byPost);
        $topPostIds = array_slice(array_keys($byPost), 0, 5);
        $postsById = CreatorPost::query()
            ->withoutGlobalScope('workspace')
            ->whereIn('id', $topPostIds)
            ->get(['id', 'title', 'body', 'image'])
            ->keyBy('id');
        $topPosts = collect($topPostIds)
            ->map(fn ($pid) => [
                'post'  => $postsById->get($pid),
                'total' => $byPost[$pid] ?? 0,
            ])
            ->filter(fn ($r) => $r['post'] !== null)
            ->values();

        // 12-week rolling chart series of credits.
        $series = $this->weeklySeries($user->id, 12);

        return view('user.monetization.earnings', compact(
            'events', 'today', 'last7', 'last30', 'thisMonth', 'allTime', 'refundsAllTime',
            'bySource', 'activeSubscribers', 'mrrCents', 'churn30', 'ltvCents',
            'topPosts', 'series',
        ));
    }

    protected function weeklySeries(int $userId, int $weeks): array
    {
        $rows = CreatorPaymentEvent::query()
            ->where('creator_user_id', $userId)
            ->where('amount_cents', '>', 0)
            ->where('occurred_at', '>=', now()->subWeeks($weeks))
            ->get(['amount_cents', 'occurred_at']);
        $buckets = [];
        for ($i = $weeks - 1; $i >= 0; $i--) {
            $start = now()->copy()->subWeeks($i)->startOfWeek();
            $buckets[$start->format('Y-m-d')] = ['label' => $start->format('M j'), 'total' => 0];
        }
        foreach ($rows as $r) {
            $key = $r->occurred_at?->copy()->startOfWeek()->format('Y-m-d');
            if (isset($buckets[$key])) $buckets[$key]['total'] += (int) $r->amount_cents;
        }
        return array_values($buckets);
    }

    // ─── Subscribers ───────────────────────────────────────────────
    public function subscribers(Request $request)
    {
        $user = $request->user();
        $status = $request->input('status', 'active');
        $q = CreatorSubscription::query()
            ->with(['fan:id,name,handle,avatar', 'tier:id,name,color,badge'])
            ->where('creator_user_id', $user->id)
            ->orderByDesc('current_period_start');

        if ($status === 'active') {
            $q->whereIn('status', [CreatorSubscription::STATUS_ACTIVE, CreatorSubscription::STATUS_TRIALING]);
        } elseif ($status === 'past_due') {
            $q->where('status', CreatorSubscription::STATUS_PAST_DUE);
        } elseif ($status === 'canceled') {
            $q->where('status', CreatorSubscription::STATUS_CANCELED);
        }

        $subs = $q->paginate(25)->withQueryString();
        $tiers = SubscriptionTier::where('user_id', $user->id)->orderBy('sort_order')->get();

        // Per-fan LTV (Task #1209): total positive credits from this fan
        // across subs + ppv + tips. Computed in one batched query for the
        // 25 rows on the page so the dashboard scales with subscribers.
        $fanIds = $subs->pluck('fan_user_id')->filter()->unique()->values()->all();
        $ltvMap = $fanIds
            ? CreatorPaymentEvent::query()
                ->where('creator_user_id', $user->id)
                ->whereIn('fan_id', $fanIds)
                ->where('amount_cents', '>', 0)
                ->selectRaw('fan_id, SUM(amount_cents) as total')
                ->groupBy('fan_id')
                ->pluck('total', 'fan_id')
                ->all()
            : [];
        // Last seen — most recent payment event from this fan acts as a
        // proxy for "last active" since the public profile doesn't yet
        // record per-fan view timestamps.
        $lastActiveMap = $fanIds
            ? CreatorPaymentEvent::query()
                ->where('creator_user_id', $user->id)
                ->whereIn('fan_id', $fanIds)
                ->selectRaw('fan_id, MAX(occurred_at) as last_at')
                ->groupBy('fan_id')
                ->pluck('last_at', 'fan_id')
                ->all()
            : [];

        return view('user.monetization.subscribers', compact(
            'subs', 'tiers', 'status', 'ltvMap', 'lastActiveMap',
        ));
    }

    // ─── Payments / ledger ─────────────────────────────────────────
    public function payments(Request $request)
    {
        $user = $request->user();
        $events = CreatorPaymentEvent::query()
            ->with('fan:id,name,handle,avatar')
            ->where('creator_user_id', $user->id)
            ->orderByDesc('occurred_at')
            ->paginate(40);
        return view('user.monetization.payments', compact('events'));
    }

    // ─── Tiers CRUD ────────────────────────────────────────────────
    public function tiers(Request $request)
    {
        $user = $request->user();
        $tiers = SubscriptionTier::where('user_id', $user->id)->orderBy('sort_order')->get();
        // Auto-create the free tier if missing.
        if (!$tiers->firstWhere('is_free', true)) {
            $free = SubscriptionTier::create([
                'user_id' => $user->id,
                'name' => 'Free',
                'slug' => 'free',
                'is_free' => true,
                'is_active' => true,
                'sort_order' => 0,
                'price_monthly_cents' => 0,
                'currency' => $user->preferred_currency ?: 'USD',
                'color' => 'slate',
                'perks' => ['Public posts', 'Reactions and comments'],
            ]);
            $tiers->prepend($free);
        }
        return view('user.monetization.tiers', compact('tiers'));
    }

    public function storeTier(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name'                 => 'required|string|max:80',
            'price_monthly'        => 'required|numeric|min:1|max:1000',
            'price_yearly'         => 'nullable|numeric|min:1|max:10000',
            'currency'             => 'nullable|string|size:3',
            'color'                => 'nullable|string|max:16',
            'badge'                => 'nullable|string|max:32',
            'perks'                => 'nullable|string|max:2000',
        ]);

        $perks = $this->splitPerks($data['perks'] ?? null);
        $sort  = (int) (SubscriptionTier::where('user_id', $user->id)->max('sort_order') ?? 0) + 1;

        SubscriptionTier::create([
            'user_id' => $user->id,
            'name'    => $data['name'],
            'slug'    => SubscriptionTier::makeSlug($user->id, $data['name']),
            'is_free' => false,
            'is_active' => true,
            'sort_order' => $sort,
            'price_monthly_cents' => (int) round(((float) $data['price_monthly']) * 100),
            'price_yearly_cents'  => isset($data['price_yearly']) ? (int) round(((float) $data['price_yearly']) * 100) : null,
            'currency' => strtoupper($data['currency'] ?? ($user->preferred_currency ?: 'USD')),
            'color'    => $data['color'] ?? 'violet',
            'badge'    => $data['badge'] ?? null,
            'perks'    => $perks,
        ]);

        return redirect()->route('user.monetization.tiers')->with('success', 'Tier added.');
    }

    public function updateTier(Request $request, SubscriptionTier $tier)
    {
        $this->authorizeTier($request, $tier);
        $data = $request->validate([
            'name'                 => 'required|string|max:80',
            'price_monthly'        => 'nullable|numeric|min:0|max:1000',
            'price_yearly'         => 'nullable|numeric|min:0|max:10000',
            'currency'             => 'nullable|string|size:3',
            'color'                => 'nullable|string|max:16',
            'badge'                => 'nullable|string|max:32',
            'perks'                => 'nullable|string|max:2000',
            'is_active'            => 'nullable|boolean',
            'sort_order'           => 'nullable|integer|min:0|max:100',
        ]);

        $tier->name = $data['name'];
        if (!$tier->is_free) {
            $tier->price_monthly_cents = (int) round(((float) ($data['price_monthly'] ?? 0)) * 100);
            $tier->price_yearly_cents  = isset($data['price_yearly']) ? (int) round(((float) $data['price_yearly']) * 100) : null;
            $tier->currency            = strtoupper($data['currency'] ?? $tier->currency);
        }
        $tier->color    = $data['color'] ?? $tier->color;
        $tier->badge    = $data['badge'] ?? $tier->badge;
        $tier->perks    = $this->splitPerks($data['perks'] ?? null);
        if (array_key_exists('is_active', $data)) $tier->is_active = (bool) $data['is_active'];
        if (array_key_exists('sort_order', $data)) $tier->sort_order = (int) $data['sort_order'];
        $tier->save();

        return back()->with('success', 'Tier saved.');
    }

    public function destroyTier(Request $request, SubscriptionTier $tier)
    {
        $this->authorizeTier($request, $tier);
        if ($tier->is_free) {
            return back()->with('error', 'You can\'t remove the free tier.');
        }
        $active = CreatorSubscription::where('tier_id', $tier->id)
            ->whereIn('status', [CreatorSubscription::STATUS_ACTIVE, CreatorSubscription::STATUS_TRIALING])
            ->count();
        if ($active > 0) {
            return back()->with('error', "Can't delete a tier with {$active} active subscriber(s). Archive it instead.");
        }
        $tier->delete();
        return back()->with('success', 'Tier removed.');
    }

    // ─── Promo codes ───────────────────────────────────────────────
    public function promos(Request $request)
    {
        $user = $request->user();
        $promos = SubscriptionPromoCode::where('user_id', $user->id)->orderByDesc('created_at')->get();
        $tiers  = SubscriptionTier::where('user_id', $user->id)->where('is_free', false)->orderBy('sort_order')->get();
        return view('user.monetization.promos', compact('promos', 'tiers'));
    }

    public function storePromo(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'code'             => 'required|string|max:40',
            'label'            => 'nullable|string|max:120',
            'kind'             => 'required|in:' . implode(',', SubscriptionPromoCode::KINDS),
            'value'            => 'required|integer|min:0|max:100000',
            'max_redemptions'  => 'nullable|integer|min:1|max:100000',
            'expires_at'       => 'nullable|date|after:now',
            'tier_ids'         => 'nullable|array',
            'tier_ids.*'       => 'integer',
        ]);
        $code = strtoupper(trim($data['code']));
        if (SubscriptionPromoCode::where('user_id', $user->id)->where('code', $code)->exists()) {
            return back()->withErrors(['code' => 'This code already exists.'])->withInput();
        }
        SubscriptionPromoCode::create([
            'user_id'             => $user->id,
            'code'                => $code,
            'label'               => $data['label'] ?? null,
            'kind'                => $data['kind'],
            'value'               => (int) $data['value'],
            'applies_to_tier_ids' => $data['tier_ids'] ?? null,
            'max_redemptions'     => $data['max_redemptions'] ?? null,
            'expires_at'          => $data['expires_at'] ?? null,
            'is_active'           => true,
        ]);
        return back()->with('success', 'Promo code added.');
    }

    public function destroyPromo(Request $request, SubscriptionPromoCode $promo)
    {
        if ((int) $promo->user_id !== (int) $request->user()->id) abort(403);
        $promo->delete();
        return back()->with('success', 'Promo code removed.');
    }

    public function togglePromo(Request $request, SubscriptionPromoCode $promo)
    {
        if ((int) $promo->user_id !== (int) $request->user()->id) abort(403);
        $promo->is_active = !$promo->is_active;
        $promo->save();
        return back();
    }

    // ─── Refund (issued by creator) ────────────────────────────────
    public function refund(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'source'       => 'required|in:tip,ppv,sub',
            'reference_id' => 'required|integer|min:1',
        ]);

        // Authorize: the source row must belong to the current creator.
        $allowed = match ($data['source']) {
            'tip' => (bool) CreatorTip::where('id', $data['reference_id'])->where('creator_user_id', $user->id)->exists(),
            'ppv' => (bool) PostUnlock::where('id', $data['reference_id'])->whereHas('post', fn ($q) => $q->withoutGlobalScope('workspace')->where('user_id', $user->id))->exists(),
            'sub' => (bool) CreatorSubscription::where('id', $data['reference_id'])->where('creator_user_id', $user->id)->exists(),
        };
        if (!$allowed) abort(403);

        $ok = app(\App\Services\Monetization\MonetizationCheckout::class)->refund($data['source'], (int) $data['reference_id']);
        return back()->with($ok ? 'success' : 'error', $ok ? 'Refund issued and access revoked.' : 'Could not issue refund.');
    }

    // ─── helpers ───────────────────────────────────────────────────
    protected function splitPerks(?string $raw): array
    {
        if (!$raw) return [];
        $lines = array_map('trim', preg_split('/\r?\n/', $raw));
        return array_values(array_filter($lines, 'strlen'));
    }

    protected function authorizeTier(Request $request, SubscriptionTier $tier): void
    {
        if ((int) $tier->user_id !== (int) $request->user()->id) abort(403);
    }
}
