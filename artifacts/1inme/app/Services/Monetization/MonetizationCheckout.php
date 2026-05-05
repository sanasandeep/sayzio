<?php

namespace App\Services\Monetization;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\CreatorTip;
use App\Modules\User\Models\PostUnlock;
use App\Modules\User\Models\SubscriptionPromoCode;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Services\CreatorPayouts\PayoutProviderRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Routes fan checkouts (subscriptions, per-post unlocks, tips) through
 * the creator's default CreatorPaymentConnection adapter. Records the
 * pending row up front, hands the fan to the provider, and reconciles
 * the row + ledger on return.
 *
 * The platform takes 0% — adapters are expected to omit / zero-out
 * application_fee_amount. This service never inspects net amounts; it
 * stores gross prices only.
 */
class MonetizationCheckout
{
    /**
     * Subscribe a fan to a tier. Returns ['url' => string, 'subscription' => CreatorSubscription].
     *
     * - Free tiers skip the provider entirely and activate immediately.
     * - Promo codes are validated and applied to the price.
     * - Switching tier upserts the existing (fan, creator) row.
     */
    public function startSubscription(
        User $fan,
        User $creator,
        SubscriptionTier $tier,
        string $cycle,
        ?SubscriptionPromoCode $promo = null,
        ?string $returnUrl = null,
    ): array {
        if ($fan->id === $creator->id) {
            abort(422, 'You cannot subscribe to yourself.');
        }

        $cycle = $cycle === CreatorSubscription::CYCLE_YEARLY
            ? CreatorSubscription::CYCLE_YEARLY
            : CreatorSubscription::CYCLE_MONTHLY;

        $price = $tier->priceForCycle($cycle);
        if ($promo && $promo->isUsable($tier)) {
            $price = $promo->applyTo($price);
        } else {
            $promo = null;
        }

        // Free tier (or promo-discounted to $0) — activate immediately.
        if ($tier->is_free || $price === 0) {
            $sub = $this->upsertActiveSubscription($fan, $creator, $tier, $cycle, 0, null, $promo);
            $this->logEvent($creator, $fan, CreatorPaymentEvent::SOURCE_SUB, CreatorPaymentEvent::TYPE_SUB_CREATED, $sub, 0, $tier->currency);
            $this->notifyCreatorOfNewSubscriber($creator, $fan, $tier);
            return [
                'url'          => $returnUrl ?? route('creator-profile.show', ['handle' => $creator->handle ?: $creator->id]),
                'subscription' => $sub,
                'free'         => true,
            ];
        }

        $connection = $creator->defaultPaymentConnection();
        if (!$connection || !$connection->charges_enabled && !env('MONETIZATION_FORCE_PREVIEW', true)) {
            abort(422, 'This creator has not finished connecting a payout provider yet.');
        }
        if (!$connection) {
            // Allow preview-mode flow even without a connection so the
            // dashboard demo works on a fresh install.
            $connection = new CreatorPaymentConnection(['provider' => 'stripe', 'user_id' => $creator->id]);
        }

        $sub = $this->upsertActiveSubscription(
            fan: $fan,
            creator: $creator,
            tier: $tier,
            cycle: $cycle,
            price: $price,
            connection: $connection,
            promo: $promo,
            initialStatus: CreatorSubscription::STATUS_PAST_DUE, // pending until provider confirms
        );

        $token = Str::random(32);
        cache()->put($this->cacheKey('sub', $sub->id, $token), [
            'sub_id'     => $sub->id,
            'fan_id'     => $fan->id,
            'creator_id' => $creator->id,
            'price'      => $price,
            'currency'   => $tier->currency,
            'return_url' => $returnUrl,
            'promo_id'   => $promo?->id,
        ], now()->addMinutes(30));

        $url = PayoutProviderRegistry::adapter($connection->provider)->createSubscriptionCheckout($connection, [
            'reference'  => 'sub_' . $sub->id,
            'token'      => $token,
            'amount'     => $price,
            'currency'   => $tier->currency,
            'cycle'      => $cycle,
            'fan_email'  => $fan->email,
            'return_url' => route('checkout.return', ['kind' => 'subscription', 'reference' => 'sub_' . $sub->id, 'token' => $token]),
        ]);

        return ['url' => $url, 'subscription' => $sub, 'free' => false];
    }

    /**
     * Per-post unlock. Returns ['url'=>string, 'unlock'=>?PostUnlock].
     * If the fan already unlocked the post, returns the existing row
     * and a return URL to the post (no charge).
     */
    public function startPostUnlock(User $fan, CreatorPost $post, ?string $returnUrl = null): array
    {
        if ((int) $fan->id === (int) $post->user_id) abort(422, 'You can\'t unlock your own post.');
        if ($post->visibility !== CreatorPost::VISIBILITY_PPV || !$post->ppv_price_cents) {
            abort(422, 'This post is not pay-per-view.');
        }

        $existing = PostUnlock::where('post_id', $post->id)->where('fan_user_id', $fan->id)->whereNull('refunded_at')->first();
        if ($existing) {
            return ['url' => $returnUrl ?? '#post-' . $post->id, 'unlock' => $existing, 'already' => true];
        }

        $creator = $post->user;
        $connection = $creator?->defaultPaymentConnection();
        if (!$connection) {
            $connection = new CreatorPaymentConnection(['provider' => 'stripe', 'user_id' => $creator->id]);
        }

        $token = Str::random(32);
        cache()->put($this->cacheKey('ppv', $post->id, $token), [
            'fan_id'     => $fan->id,
            'creator_id' => $creator->id,
            'post_id'    => $post->id,
            'price'      => (int) $post->ppv_price_cents,
            'currency'   => $post->ppv_currency ?: 'USD',
            'return_url' => $returnUrl,
        ], now()->addMinutes(30));

        $url = PayoutProviderRegistry::adapter($connection->provider)->createOneTimeCheckout($connection, [
            'kind'      => 'ppv',
            'reference' => 'ppv_' . $post->id . '_' . $fan->id,
            'token'     => $token,
            'amount'    => (int) $post->ppv_price_cents,
            'currency'  => $post->ppv_currency ?: 'USD',
            'fan_email' => $fan->email,
            'return_url' => route('checkout.return', ['kind' => 'ppv', 'reference' => 'ppv_' . $post->id . '_' . $fan->id, 'token' => $token]),
        ]);

        return ['url' => $url, 'unlock' => null, 'already' => false];
    }

    /**
     * Tip the creator (optionally tied to a post).
     */
    public function startTip(
        User $fan,
        User $creator,
        int $amountCents,
        string $currency = 'USD',
        ?CreatorPost $post = null,
        ?string $note = null,
        bool $anonymous = false,
        ?string $returnUrl = null,
    ): array {
        if ($fan->id === $creator->id) abort(422, 'You can\'t tip yourself.');
        if ($amountCents < 100) abort(422, 'Minimum tip is $1.00.');
        if ($amountCents > 1_000_00) abort(422, 'Maximum tip is $1,000.00.');

        $connection = $creator->defaultPaymentConnection();
        if (!$connection) {
            $connection = new CreatorPaymentConnection(['provider' => 'stripe', 'user_id' => $creator->id]);
        }

        $tip = CreatorTip::create([
            'creator_user_id' => $creator->id,
            'fan_user_id'     => $fan->id,
            'post_id'         => $post?->id,
            'amount_cents'    => $amountCents,
            'currency'        => $currency,
            'note'            => $note ? mb_substr($note, 0, 280) : null,
            'anonymous'       => $anonymous,
            'status'          => CreatorTip::STATUS_FAILED, // pending until confirm
            'gateway'         => $connection->provider,
        ]);

        $token = Str::random(32);
        cache()->put($this->cacheKey('tip', $tip->id, $token), [
            'tip_id'     => $tip->id,
            'fan_id'     => $fan->id,
            'creator_id' => $creator->id,
            'amount'     => $amountCents,
            'currency'   => $currency,
            'return_url' => $returnUrl,
        ], now()->addMinutes(30));

        $url = PayoutProviderRegistry::adapter($connection->provider)->createOneTimeCheckout($connection, [
            'kind'      => 'tip',
            'reference' => 'tip_' . $tip->id,
            'token'     => $token,
            'amount'    => $amountCents,
            'currency'  => $currency,
            'fan_email' => $fan->email,
            'return_url' => route('checkout.return', ['kind' => 'tip', 'reference' => 'tip_' . $tip->id, 'token' => $token]),
        ]);

        return ['url' => $url, 'tip' => $tip];
    }

    /**
     * Reconcile a successful return-from-provider hand-off. The route
     * controller calls this with the kind + token. Returns a redirect
     * URL the user should land on (or null on failure).
     */
    public function confirm(string $kind, string $reference, string $token): ?array
    {
        $key = $this->cacheKeyFromReference($kind, $reference, $token);
        if (!$key) return null;
        $payload = cache()->pull($key);
        if (!$payload) return null;

        return match ($kind) {
            'subscription' => $this->confirmSubscription($payload),
            'ppv'          => $this->confirmPpv($payload),
            'tip'          => $this->confirmTip($payload),
            default        => null,
        };
    }

    protected function confirmSubscription(array $p): array
    {
        $sub = CreatorSubscription::find($p['sub_id']);
        if (!$sub) return ['url' => '/'];
        $sub->status                 = CreatorSubscription::STATUS_ACTIVE;
        $sub->started_at             = $sub->started_at ?: now();
        $sub->current_period_start   = now();
        $sub->current_period_end     = $sub->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth();
        $sub->last_payment_at        = now();
        $sub->gateway_subscription_id = $sub->gateway_subscription_id ?: ('preview_sub_' . $sub->id);
        $sub->save();

        if ($sub->promo_code_id) {
            SubscriptionPromoCode::query()->whereKey($sub->promo_code_id)->increment('redemptions_count');
        }

        $creator = $sub->creator;
        $fan = $sub->fan;
        $tier = $sub->tier;

        $this->logEvent($creator, $fan, CreatorPaymentEvent::SOURCE_SUB, CreatorPaymentEvent::TYPE_SUB_CREATED, $sub, $sub->price_cents, $sub->currency);
        $this->notifyCreatorOfNewSubscriber($creator, $fan, $tier);

        return [
            'url' => $p['return_url']
                ?? route('creator-profile.show', ['handle' => $creator?->handle ?: $creator?->id]),
            'message' => 'Welcome — you\'re now a ' . ($tier?->name ?: 'subscriber') . '.',
        ];
    }

    protected function confirmPpv(array $p): array
    {
        $unlock = PostUnlock::firstOrCreate(
            ['post_id' => $p['post_id'], 'fan_user_id' => $p['fan_id']],
            [
                'price_cents'       => $p['price'],
                'currency'          => $p['currency'],
                'gateway'           => 'preview',
                'gateway_charge_id' => 'preview_ppv_' . Str::random(10),
                'unlocked_at'       => now(),
            ],
        );
        $creator = User::find($p['creator_id']);
        $fan     = User::find($p['fan_id']);
        if ($creator && $fan) {
            $this->logEvent($creator, $fan, CreatorPaymentEvent::SOURCE_PPV, CreatorPaymentEvent::TYPE_PPV_UNLOCKED, $unlock, $p['price'], $p['currency']);
            $this->notifyCreatorOfUnlock($creator, $fan, $p['post_id'], $p['price'], $p['currency']);
        }
        return [
            'url' => $p['return_url']
                ?? route('creator-profile.show', ['handle' => $creator?->handle ?: $creator?->id]) . '#post-' . $p['post_id'],
            'message' => 'Unlocked. Enjoy 🎉',
        ];
    }

    protected function confirmTip(array $p): array
    {
        $tip = CreatorTip::find($p['tip_id']);
        if (!$tip) return ['url' => '/'];
        $tip->status            = CreatorTip::STATUS_SUCCEEDED;
        $tip->gateway_charge_id = $tip->gateway_charge_id ?: ('preview_tip_' . Str::random(10));
        $tip->save();

        $creator = $tip->creator;
        $fan     = $tip->fan;
        if ($creator) {
            $this->logEvent($creator, $fan, CreatorPaymentEvent::SOURCE_TIP, CreatorPaymentEvent::TYPE_TIP_RECEIVED, $tip, $tip->amount_cents, $tip->currency);
            if ($fan) $this->notifyCreatorOfTip($creator, $fan, $tip);
        }
        return [
            'url' => $p['return_url']
                ?? route('creator-profile.show', ['handle' => $creator?->handle ?: $creator?->id]),
            'message' => 'Thanks — your tip went through.',
        ];
    }

    /**
     * Cancel an active subscription. By default cancels at period end
     * (the fan retains access until the period closes); pass $immediate
     * for refunds / admin-initiated cancellation.
     */
    public function cancelSubscription(CreatorSubscription $sub, bool $immediate = false): void
    {
        if ($immediate || !$sub->current_period_end || $sub->current_period_end->isPast()) {
            $sub->status                = CreatorSubscription::STATUS_CANCELED;
            $sub->canceled_at           = now();
            $sub->cancel_at_period_end  = false;
        } else {
            $sub->cancel_at_period_end = true;
            $sub->canceled_at          = now();
        }
        $sub->save();

        $this->logEvent(
            $sub->creator, $sub->fan, CreatorPaymentEvent::SOURCE_SUB,
            CreatorPaymentEvent::TYPE_SUB_CANCELED, $sub, 0, $sub->currency,
        );
        $this->notifyCreatorOfCancellation($sub);
    }

    /**
     * Refund a tip / unlock / subscription charge. Revokes access
     * locally and writes a negative ledger row.
     */
    public function refund(string $source, int $referenceId): bool
    {
        return match ($source) {
            CreatorPaymentEvent::SOURCE_TIP => $this->refundTip($referenceId),
            CreatorPaymentEvent::SOURCE_PPV => $this->refundPpv($referenceId),
            CreatorPaymentEvent::SOURCE_SUB => $this->refundSubscription($referenceId),
            default => false,
        };
    }

    protected function refundTip(int $id): bool
    {
        $tip = CreatorTip::find($id);
        if (!$tip || $tip->status === CreatorTip::STATUS_REFUNDED) return false;
        try {
            if ($tip->gateway && $tip->gateway_charge_id) {
                PayoutProviderRegistry::adapter($tip->gateway)->refundCharge($tip->gateway_charge_id, $tip->amount_cents);
            }
        } catch (\Throwable $e) {
            Log::warning('refund.tip.adapter_failed', ['tip' => $id, 'err' => $e->getMessage()]);
        }
        $tip->status = CreatorTip::STATUS_REFUNDED;
        $tip->refunded_at = now();
        $tip->save();
        $this->logEvent($tip->creator, $tip->fan, CreatorPaymentEvent::SOURCE_TIP, CreatorPaymentEvent::TYPE_TIP_REFUNDED, $tip, -1 * $tip->amount_cents, $tip->currency);
        return true;
    }

    protected function refundPpv(int $id): bool
    {
        $unlock = PostUnlock::find($id);
        if (!$unlock || $unlock->refunded_at) return false;
        try {
            if ($unlock->gateway && $unlock->gateway_charge_id) {
                PayoutProviderRegistry::adapter($unlock->gateway)->refundCharge($unlock->gateway_charge_id, $unlock->price_cents);
            }
        } catch (\Throwable $e) {
            Log::warning('refund.ppv.adapter_failed', ['unlock' => $id, 'err' => $e->getMessage()]);
        }
        $unlock->refunded_at = now();
        $unlock->save();
        $post = $unlock->post;
        $this->logEvent($post?->user, $unlock->fan, CreatorPaymentEvent::SOURCE_PPV, CreatorPaymentEvent::TYPE_PPV_REFUNDED, $unlock, -1 * (int) $unlock->price_cents, $unlock->currency);
        return true;
    }

    protected function refundSubscription(int $id): bool
    {
        $sub = CreatorSubscription::find($id);
        if (!$sub) return false;
        try {
            if ($sub->gateway && $sub->gateway_subscription_id) {
                PayoutProviderRegistry::adapter($sub->gateway)->refundCharge($sub->gateway_subscription_id, $sub->price_cents);
            }
        } catch (\Throwable $e) {
            Log::warning('refund.sub.adapter_failed', ['sub' => $id, 'err' => $e->getMessage()]);
        }
        $sub->status = CreatorSubscription::STATUS_CANCELED;
        $sub->canceled_at = now();
        $sub->save();
        $this->logEvent($sub->creator, $sub->fan, CreatorPaymentEvent::SOURCE_SUB, CreatorPaymentEvent::TYPE_SUB_REFUNDED, $sub, -1 * $sub->price_cents, $sub->currency);
        return true;
    }

    // ─── helpers ────────────────────────────────────────────────────

    protected function upsertActiveSubscription(
        User $fan,
        User $creator,
        SubscriptionTier $tier,
        string $cycle,
        int $price,
        ?CreatorPaymentConnection $connection,
        ?SubscriptionPromoCode $promo = null,
        string $initialStatus = CreatorSubscription::STATUS_ACTIVE,
    ): CreatorSubscription {
        return DB::transaction(function () use ($fan, $creator, $tier, $cycle, $price, $connection, $promo, $initialStatus) {
            $sub = CreatorSubscription::firstOrNew(
                ['fan_user_id' => $fan->id, 'creator_user_id' => $creator->id],
            );
            $sub->tier_id           = $tier->id;
            $sub->billing_cycle     = $cycle;
            $sub->status            = $initialStatus;
            $sub->price_cents       = $price;
            $sub->currency          = $tier->currency;
            $sub->started_at        = $sub->started_at ?: ($initialStatus === CreatorSubscription::STATUS_ACTIVE ? now() : null);
            $sub->current_period_start = $initialStatus === CreatorSubscription::STATUS_ACTIVE ? now() : null;
            $sub->current_period_end   = $initialStatus === CreatorSubscription::STATUS_ACTIVE
                ? ($cycle === CreatorSubscription::CYCLE_YEARLY ? now()->addYear() : now()->addMonth())
                : null;
            $sub->last_payment_at   = $initialStatus === CreatorSubscription::STATUS_ACTIVE ? now() : null;
            $sub->gateway           = $connection?->provider;
            $sub->cancel_at_period_end = false;
            $sub->canceled_at       = null;
            $sub->promo_code_id     = $promo?->id;
            $sub->save();
            return $sub;
        });
    }

    protected function logEvent(?User $creator, ?User $fan, string $source, string $type, $reference, int $amount, string $currency): void
    {
        if (!$creator) return;
        CreatorPaymentEvent::create([
            'creator_user_id' => $creator->id,
            'fan_user_id'     => $fan?->id,
            'source'          => $source,
            'type'            => $type,
            'reference_type'  => $reference ? get_class($reference) : null,
            'reference_id'    => $reference?->id,
            'amount_cents'    => $amount,
            'currency'        => $currency,
            'gateway'         => $reference->gateway ?? null,
            'gateway_event_id'=> 'preview_' . Str::random(8),
            'occurred_at'     => now(),
        ]);
    }

    protected function notifyCreatorOfNewSubscriber(User $creator, User $fan, ?SubscriptionTier $tier): void
    {
        \App\Modules\User\Models\UserNotification::create([
            'user_id'    => $creator->id,
            'type'       => 'subscriber.new',
            'data'       => [
                'fan_id'    => $fan->id,
                'fan_name'  => $fan->name,
                'tier_name' => $tier?->name,
                'message'   => $fan->name . ' subscribed to ' . ($tier?->name ?? 'your page') . '.',
                'link'      => route('user.monetization.subscribers'),
            ],
            'created_at' => now(),
        ]);
        $this->emailCreatorBestEffort($creator, 'New 1INME subscriber', $fan->name . ' just joined ' . ($tier?->name ?? 'your page') . '.');
    }

    protected function notifyCreatorOfTip(User $creator, User $fan, CreatorTip $tip): void
    {
        $amount = '$' . number_format($tip->amount_cents / 100, 2);
        \App\Modules\User\Models\UserNotification::create([
            'user_id'    => $creator->id,
            'type'       => 'tip.received',
            'data'       => [
                'fan_id'    => $fan->id,
                'fan_name'  => $tip->anonymous ? 'Anonymous' : $fan->name,
                'amount'    => $amount,
                'note'      => $tip->note,
                'message'   => ($tip->anonymous ? 'Anonymous' : $fan->name) . ' tipped you ' . $amount . '.',
                'link'      => route('user.monetization.earnings'),
            ],
            'created_at' => now(),
        ]);
        $this->emailCreatorBestEffort($creator, 'New tip on 1INME', ($tip->anonymous ? 'Someone' : $fan->name) . ' tipped you ' . $amount . '.');
    }

    protected function notifyCreatorOfUnlock(User $creator, User $fan, int $postId, int $price, string $currency): void
    {
        \App\Modules\User\Models\UserNotification::create([
            'user_id'    => $creator->id,
            'type'       => 'ppv.unlocked',
            'data'       => [
                'fan_id'   => $fan->id,
                'fan_name' => $fan->name,
                'post_id'  => $postId,
                'amount'   => '$' . number_format($price / 100, 2),
                'message'  => $fan->name . ' unlocked your post for $' . number_format($price / 100, 2) . '.',
                'link'     => route('user.monetization.earnings'),
            ],
            'created_at' => now(),
        ]);
    }

    protected function notifyCreatorOfCancellation(CreatorSubscription $sub): void
    {
        $creator = $sub->creator;
        $fan     = $sub->fan;
        if (!$creator || !$fan) return;
        \App\Modules\User\Models\UserNotification::create([
            'user_id'    => $creator->id,
            'type'       => 'subscriber.canceled',
            'data'       => [
                'fan_id'   => $fan->id,
                'fan_name' => $fan->name,
                'message'  => $fan->name . ' canceled their subscription.',
                'link'     => route('user.monetization.subscribers'),
                'cancel_at_period_end' => $sub->cancel_at_period_end,
                'period_end' => optional($sub->current_period_end)->toIso8601String(),
            ],
            'created_at' => now(),
        ]);
    }

    protected function emailCreatorBestEffort(User $creator, string $subject, string $body): void
    {
        if (!$creator->email) return;
        try {
            \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($creator, $subject) {
                $m->to($creator->email)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('monetization.notify_email.failed', ['err' => $e->getMessage()]);
        }
    }

    protected function cacheKey(string $kind, int $id, string $token): string
    {
        return "monetization_checkout:{$kind}:{$id}:{$token}";
    }

    protected function cacheKeyFromReference(string $kind, string $reference, string $token): ?string
    {
        // reference is "{prefix}_{id}" or "{prefix}_{post}_{fan}"
        $parts = explode('_', $reference);
        if ($kind === 'subscription' && count($parts) === 2) {
            return $this->cacheKey('sub', (int) $parts[1], $token);
        }
        if ($kind === 'ppv' && count($parts) >= 2) {
            return $this->cacheKey('ppv', (int) $parts[1], $token);
        }
        if ($kind === 'tip' && count($parts) === 2) {
            return $this->cacheKey('tip', (int) $parts[1], $token);
        }
        return null;
    }
}
