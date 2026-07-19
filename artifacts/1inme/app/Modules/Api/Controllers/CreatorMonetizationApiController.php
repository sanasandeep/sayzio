<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\SubscriptionPromoCode;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Services\Monetization\MonetizationCheckout;
use App\Services\Monetization\PostAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile parity for Task #1209. The Expo app calls these endpoints to
 * surface tiers, subscribe/unlock/tip a creator, and render the
 * earnings/subscribers/payments dashboards.
 *
 * Every checkout endpoint returns a `checkout_url` the app opens via
 * WebBrowser.openBrowserAsync — Apple IAP rules require the system
 * browser for any external billing. The return URL on the web routes
 * back into the app via universal links once the fan completes
 * payment in the browser.
 */
class CreatorMonetizationApiController extends Controller
{
    use ApiResponses;

    // ─── Public surface (per-creator) ──────────────────────────────
    public function tiers(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        $viewer  = $request->user();
        $tiers = SubscriptionTier::where('user_id', $creator->id)
            ->where('is_active', true)
            ->orderBy('sort_order')->get();
        $existing = $viewer
            ? CreatorSubscription::where('fan_user_id', $viewer->id)->where('creator_user_id', $creator->id)->first()
            : null;
        return $this->ok([
            'creator' => [
                'id' => $creator->id, 'handle' => $creator->handle, 'name' => $creator->name,
                'avatar' => \App\Support\PublicStorageUrl::resolve($creator->avatar), 'tagline' => $creator->tagline,
            ],
            'tiers'    => $tiers->map(fn ($t) => $this->tierShape($t))->all(),
            'currency' => $creator->preferred_currency ?: 'USD',
            'subscription' => $existing ? $this->subShape($existing) : null,
        ]);
    }

    public function subscribe(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        if (!$request->user()) return $this->unauthorized('Sign in to subscribe.');
        $data = $request->validate([
            'tier_id'    => 'required|integer',
            'cycle'      => 'nullable|in:monthly,yearly',
            'promo_code' => 'nullable|string|max:40',
            'return_url' => 'nullable|url',
        ]);
        $tier = SubscriptionTier::where('user_id', $creator->id)->whereKey($data['tier_id'])->where('is_active', true)->first();
        if (!$tier) return $this->fail('Tier not found.', 404);
        $promo = null;
        if (!empty($data['promo_code'])) {
            $promo = SubscriptionPromoCode::where('user_id', $creator->id)
                ->where('code', strtoupper(trim($data['promo_code'])))->first();
            if (!$promo || !$promo->isUsable($tier)) {
                return $this->fail('Promo code not valid.', 422);
            }
        }
        $r = app(MonetizationCheckout::class)->startSubscription(
            $request->user(), $creator, $tier,
            $data['cycle'] ?? 'monthly', $promo, $data['return_url'] ?? null,
        );
        return $this->ok([
            'checkout_url' => $r['url'],
            'subscription' => $this->subShape($r['subscription']),
            'free'         => $r['free'] ?? false,
        ]);
    }

    public function unlockPost(Request $request, string $handle, int $post)
    {
        $creator = $this->creatorOr404($handle);
        if (!$request->user()) return $this->unauthorized('Sign in to unlock posts.');
        $p = CreatorPost::query()->withoutGlobalScope('workspace')
            ->where('user_id', $creator->id)->whereKey($post)->first();
        if (!$p) return $this->notFound();
        $r = app(MonetizationCheckout::class)->startPostUnlock($request->user(), $p,
            $request->input('return_url'),
        );
        return $this->ok([
            'checkout_url' => $r['url'],
            'already'      => $r['already'] ?? false,
        ]);
    }

    public function tip(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        if (!$request->user()) return $this->unauthorized('Sign in to tip.');
        $data = $request->validate([
            'amount'     => 'required|numeric|min:1|max:1000',
            'post_id'    => 'nullable|integer',
            'note'       => 'nullable|string|max:280',
            'anonymous'  => 'nullable|boolean',
            'return_url' => 'nullable|url',
        ]);
        $post = null;
        if (!empty($data['post_id'])) {
            $post = CreatorPost::query()->withoutGlobalScope('workspace')
                ->where('user_id', $creator->id)->whereKey($data['post_id'])->first();
        }
        $r = app(MonetizationCheckout::class)->startTip(
            $request->user(), $creator,
            (int) round(((float) $data['amount']) * 100),
            $creator->preferred_currency ?: 'USD',
            $post,
            $data['note'] ?? null,
            (bool) ($data['anonymous'] ?? false),
            $data['return_url'] ?? null,
        );
        return $this->ok([
            'checkout_url' => $r['url'],
            'tip_id'       => $r['tip']->id,
        ]);
    }

    /**
     * Mobile parity for the `tip_jar` biolink block. Accepts an alias
     * and block_id (rather than @handle) so the mobile app can start a
     * tip from a biolink block and open the returned checkout_url in the
     * system browser.
     */
    public function biolinkTip(Request $request, string $alias)
    {
        if (!$request->user()) return $this->unauthorized('Sign in to tip.');

        $link = \App\Modules\User\Models\Link::where('alias', $alias)->first();
        if (!$link) return $this->notFound();
        $creator = $link->user;
        if (!$creator) return $this->notFound();

        $data = $request->validate([
            'block_id'  => 'required|integer',
            'amount'    => 'required|numeric|min:1|max:1000',
            'note'      => 'nullable|string|max:280',
            'anonymous' => 'nullable|boolean',
            'return_url' => 'nullable|url',
        ]);

        $block = \App\Modules\User\Models\BiolinkBlock::withoutGlobalScope('workspace')
            ->where('link_id', $link->id)
            ->where('type', 'tip_jar')
            ->whereKey((int) $data['block_id'])
            ->first();
        if (!$block) return $this->notFound();

        $connection = $creator->defaultPaymentConnection();
        if (!$connection || !$connection->charges_enabled) {
            return $this->fail('Tips are not available for this creator right now.', 422);
        }

        $r = app(MonetizationCheckout::class)->startTip(
            $request->user(), $creator,
            (int) round(((float) $data['amount']) * 100),
            $creator->preferred_currency ?: 'USD',
            null,
            $data['note'] ?? null,
            (bool) ($data['anonymous'] ?? false),
            $data['return_url'] ?? null,
            CreatorPaymentEvent::SOURCE_TIP_JAR,
        );
        return $this->ok([
            'checkout_url' => $r['url'],
            'tip_id'       => $r['tip']->id,
        ]);
    }

    /**
     * Every creator subscription the signed-in fan holds — the native
     * "manage subscription" screen lists these so a fan can review and
     * cancel/resume each one without leaving the app. Mirrors the web
     * per-creator manage page (CreatorMonetizationPublicController@manage)
     * but returns the whole set keyed by the fan rather than one creator.
     */
    public function mySubscriptions(Request $request)
    {
        if (!$request->user()) return $this->unauthorized();
        $subs = CreatorSubscription::with(['tier', 'creator:id,name,handle,avatar'])
            ->where('fan_user_id', $request->user()->id)
            ->whereIn('status', [
                CreatorSubscription::STATUS_ACTIVE,
                CreatorSubscription::STATUS_TRIALING,
                CreatorSubscription::STATUS_PAST_DUE,
                CreatorSubscription::STATUS_PAUSED,
            ])
            ->orderByDesc('current_period_start')
            ->get();
        return $this->ok([
            'items' => $subs->map(fn ($s) => $this->subShape($s))->all(),
        ]);
    }

    public function mySubscription(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        if (!$request->user()) return $this->unauthorized();
        $sub = CreatorSubscription::with(['tier', 'creator:id,name,handle,avatar'])
            ->where('fan_user_id', $request->user()->id)
            ->where('creator_user_id', $creator->id)->first();
        return $this->ok([
            'subscription' => $sub ? $this->subShape($sub) : null,
        ]);
    }

    public function cancelSubscription(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        if (!$request->user()) return $this->unauthorized();
        $sub = CreatorSubscription::with(['tier', 'creator:id,name,handle,avatar'])
            ->where('fan_user_id', $request->user()->id)->where('creator_user_id', $creator->id)->first();
        if (!$sub) return $this->notFound();
        app(MonetizationCheckout::class)->cancelSubscription($sub, immediate: false);
        return $this->ok(['subscription' => $this->subShape($sub->fresh(['tier', 'creator']))]);
    }

    /**
     * Undo a scheduled cancellation. Mirrors the web
     * CreatorMonetizationPublicController@resume exactly.
     */
    public function resumeSubscription(Request $request, string $handle)
    {
        $creator = $this->creatorOr404($handle);
        if (!$request->user()) return $this->unauthorized();
        $sub = CreatorSubscription::with(['tier', 'creator:id,name,handle,avatar'])
            ->where('fan_user_id', $request->user()->id)->where('creator_user_id', $creator->id)->first();
        if (!$sub) return $this->notFound();
        $sub->cancel_at_period_end = false;
        $sub->canceled_at = null;
        $sub->status = CreatorSubscription::STATUS_ACTIVE;
        $sub->save();
        return $this->ok(['subscription' => $this->subShape($sub->fresh(['tier', 'creator']))]);
    }

    // ─── Owner dashboard surface ───────────────────────────────────
    public function earnings(Request $request)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();
        $now = now();
        return $this->ok([
            'this_month_cents' => (int) CreatorPaymentEvent::query()
                ->where('creator_user_id', $user->id)->where('amount_cents', '>', 0)
                ->where('occurred_at', '>=', $now->copy()->startOfMonth())->sum('amount_cents'),
            'all_time_cents'   => (int) CreatorPaymentEvent::query()
                ->where('creator_user_id', $user->id)->where('amount_cents', '>', 0)->sum('amount_cents'),
            'refunds_cents'    => (int) CreatorPaymentEvent::query()
                ->where('creator_user_id', $user->id)->where('amount_cents', '<', 0)->sum('amount_cents'),
            'active_subscribers' => (int) CreatorSubscription::query()
                ->where('creator_user_id', $user->id)
                ->whereIn('status', [CreatorSubscription::STATUS_ACTIVE, CreatorSubscription::STATUS_TRIALING])
                ->count(),
            'by_source' => CreatorPaymentEvent::query()
                ->selectRaw('source, SUM(amount_cents) as total')
                ->where('creator_user_id', $user->id)
                ->groupBy('source')
                ->pluck('total', 'source')
                ->map(fn ($v) => (int) $v)
                ->all(),
            'currency' => $user->preferred_currency ?: 'USD',
        ]);
    }

    public function ownerSubscribers(Request $request)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();
        $rows = CreatorSubscription::query()
            ->with(['fan:id,name,handle,avatar', 'tier:id,name,color,badge'])
            ->where('creator_user_id', $user->id)
            ->whereIn('status', [CreatorSubscription::STATUS_ACTIVE, CreatorSubscription::STATUS_TRIALING])
            ->orderByDesc('current_period_start')
            ->paginate(50);
        return $this->ok([
            'items' => collect($rows->items())->map(fn ($s) => $this->subShape($s))->all(),
            'meta'  => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    public function ownerPayments(Request $request)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();
        $rows = CreatorPaymentEvent::query()
            ->with(['fan:id,name,handle,avatar'])
            ->where('creator_user_id', $user->id)
            ->orderByDesc('occurred_at')
            ->paginate(50);
        return $this->ok([
            'items' => collect($rows->items())->map(fn ($e) => [
                'id'           => $e->id,
                'type'         => $e->type,
                'source'       => $e->source,
                'label'        => $e->describeShort(),
                'amount_cents' => (int) $e->amount_cents,
                'currency'     => $e->currency,
                'fan'          => $e->fan ? ['id' => $e->fan->id, 'name' => $e->fan->name, 'avatar' => \App\Support\PublicStorageUrl::resolve($e->fan->avatar)] : null,
                'occurred_at'  => optional($e->occurred_at)->toIso8601String(),
            ])->all(),
            'meta'  => ['current_page' => $rows->currentPage(), 'last_page' => $rows->lastPage(), 'total' => $rows->total()],
        ]);
    }

    public function ownerTiers(Request $request)
    {
        $user = $request->user();
        if (!$user) return $this->unauthorized();
        $tiers = SubscriptionTier::where('user_id', $user->id)->orderBy('sort_order')->get();
        return $this->ok([
            'items' => $tiers->map(fn ($t) => $this->tierShape($t))->all(),
        ]);
    }

    // ─── helpers ───────────────────────────────────────────────────
    protected function creatorOr404(string $handle): User
    {
        $h = ltrim($handle, '@');
        $u = User::query()->whereRaw('LOWER(handle) = ?', [strtolower($h)])->first();
        if (!$u) abort(404);
        return $u;
    }

    protected function tierShape(SubscriptionTier $t): array
    {
        return [
            'id'                  => $t->id,
            'name'                => $t->name,
            'slug'                => $t->slug,
            'is_free'             => (bool) $t->is_free,
            'is_active'           => (bool) $t->is_active,
            'price_monthly_cents' => (int) $t->price_monthly_cents,
            'price_yearly_cents'  => $t->price_yearly_cents,
            'currency'            => $t->currency,
            'perks'               => $t->visiblePerks(),
            'color'               => $t->color,
            'badge'               => $t->badge,
            'sort_order'          => (int) $t->sort_order,
            'yearly_discount_percent' => $t->yearlyDiscountPercent(),
        ];
    }

    protected function subShape(CreatorSubscription $s): array
    {
        return [
            'id'                   => $s->id,
            'status'               => $s->status,
            'status_label'         => $s->statusLabel(),
            'is_current'           => $s->isCurrent(),
            'billing_cycle'        => $s->billing_cycle,
            'price_cents'          => (int) $s->price_cents,
            'currency'             => $s->currency,
            'cancel_at_period_end' => (bool) $s->cancel_at_period_end,
            'current_period_end'   => optional($s->current_period_end)->toIso8601String(),
            'started_at'           => optional($s->started_at)->toIso8601String(),
            'tier'                 => $s->tier ? [
                'id' => $s->tier->id, 'name' => $s->tier->name,
                'color' => $s->tier->color, 'badge' => $s->tier->badge,
            ] : null,
            'creator'              => $s->creator ? [
                'id' => $s->creator->id, 'name' => $s->creator->name,
                'handle' => $s->creator->handle, 'avatar' => \App\Support\PublicStorageUrl::resolve($s->creator->avatar),
            ] : null,
            'fan'                  => $s->fan ? [
                'id' => $s->fan->id, 'name' => $s->fan->name,
                'handle' => $s->fan->handle, 'avatar' => \App\Support\PublicStorageUrl::resolve($s->fan->avatar),
            ] : null,
        ];
    }
}
