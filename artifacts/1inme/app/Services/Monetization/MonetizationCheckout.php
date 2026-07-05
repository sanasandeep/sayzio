<?php

namespace App\Services\Monetization;

use App\Modules\User\Models\CreatorPaymentConnection;
use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\CreatorPost;
use App\Modules\User\Models\CreatorSubscription;
use App\Modules\User\Models\CreatorTip;
use App\Modules\User\Models\PostUnlock;
use App\Modules\User\Models\ProductOrder;
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
            // Paid DMs (Task #1210): fire welcome rules for new subscribers.
            try { app(\App\Services\Dm\DmDispatcher::class)->triggerNewSubscriber($creator, $fan, $tier); } catch (\Throwable $e) {}
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
     * Buy ticket(s) to a ticketed event (Task #3589). Mirrors startTip:
     * free tiers (price 0) issue the ticket immediately with no provider
     * hop; paid tiers route through the event owner's payout connection.
     */
    public function startEventTicket(
        User $fan,
        \App\Modules\User\Models\EventTicketTier $tier,
        int $quantity,
        array $attendee,
        ?string $returnUrl = null,
    ): array {
        $quantity = max(1, min(20, $quantity));
        if (!$tier->isOnSale()) abort(422, 'Tickets are not currently on sale for this tier.');
        if ($tier->capacity !== null && $tier->remainingCapacity() < $quantity) {
            abort(422, 'Not enough tickets remaining in this tier.');
        }

        $link = $tier->link;
        $owner = $link?->user;
        if (!$owner) abort(404, 'Event not found.');

        $totalCents = (int) $tier->price_cents * $quantity;

        if ($totalCents <= 0) {
            $ticket = $this->issueEventTicket($tier, $fan, $attendee, $quantity, 0, 'free', null, null);
            return [
                'url'    => route('redirect.event.ticket', ['alias' => $link->alias, 'code' => $ticket->code]),
                'ticket' => $ticket,
            ];
        }

        $connection = $owner->defaultPaymentConnection()
            ?: new CreatorPaymentConnection(['provider' => 'stripe', 'user_id' => $owner->id]);

        $token = Str::random(32);
        cache()->put($this->cacheKey('event', $tier->id, $token), [
            'tier_id'   => $tier->id,
            'link_id'   => $link->id,
            'fan_id'    => $fan->id,
            'quantity'  => $quantity,
            'amount'    => $totalCents,
            'currency'  => $tier->currency,
            'attendee'  => $attendee,
            'return_url' => $returnUrl,
        ], now()->addMinutes(30));

        $reference = 'event_ticket_' . $tier->id . '_' . $fan->id;
        $url = PayoutProviderRegistry::adapter($connection->provider)->createOneTimeCheckout($connection, [
            'kind'       => 'event_ticket',
            'reference'  => $reference,
            'token'      => $token,
            'amount'     => $totalCents,
            'currency'   => $tier->currency,
            'fan_email'  => $fan->email,
            'return_url' => route('checkout.return', ['kind' => 'event_ticket', 'reference' => $reference, 'token' => $token]),
        ]);

        return ['url' => $url];
    }

    protected function confirmEventTicket(array $p): array
    {
        $tier = \App\Modules\User\Models\EventTicketTier::find($p['tier_id']);
        $fan  = User::find($p['fan_id']);
        if (!$tier || !$fan) return ['url' => '/'];

        $ticket = $this->issueEventTicket(
            $tier, $fan, $p['attendee'] ?? [], (int) $p['quantity'],
            (int) $p['amount'], 'preview', 'preview_event_' . Str::random(10), $p,
        );

        return [
            'url'     => $p['return_url']
                ?? route('redirect.event.ticket', ['alias' => $tier->link?->alias, 'code' => $ticket->code]),
            'message' => 'You\'re in! Your ticket is ready.',
        ];
    }

    /**
     * Shared ticket-issuing path for both free and paid tiers. Creates the
     * ticket row, bumps the tier's sold_count, logs the ledger row (paid
     * only), and emails the buyer their QR ticket.
     */
    protected function issueEventTicket(
        \App\Modules\User\Models\EventTicketTier $tier,
        User $fan,
        array $attendee,
        int $quantity,
        int $amountCents,
        string $gateway,
        ?string $gatewayChargeId,
        ?array $payload,
    ): \App\Modules\User\Models\EventTicket {
        $ticket = \App\Modules\User\Models\EventTicket::create([
            'tier_id'            => $tier->id,
            'link_id'            => $tier->link_id,
            'buyer_user_id'      => $fan->id,
            'attendee_name'      => $attendee['name'] ?? $fan->name,
            'attendee_email'     => $attendee['email'] ?? $fan->email,
            'attendee_phone'     => $attendee['phone'] ?? null,
            'quantity'           => $quantity,
            'price_cents'        => $amountCents,
            'currency'           => $tier->currency,
            'code'               => \App\Modules\User\Models\EventTicket::generateCode(),
            'status'             => \App\Modules\User\Models\EventTicket::STATUS_VALID,
            'purchase_reference' => $payload['token'] ?? null,
            'gateway'            => $gateway,
            'gateway_charge_id'  => $gatewayChargeId,
        ]);

        $tier->increment('sold_count', $quantity);

        $creator = $tier->link?->user;
        if ($creator && $amountCents > 0) {
            $this->logEvent($creator, $fan, CreatorPaymentEvent::SOURCE_EVENT, CreatorPaymentEvent::TYPE_TICKET_PURCHASED, $ticket, $amountCents, $tier->currency);
        }
        if ($creator) {
            $this->notifyCreatorOfTicketSale($creator, $fan, $ticket, $tier);
        }
        $this->emailTicketConfirmation($ticket, $tier);

        return $ticket;
    }

    protected function notifyCreatorOfTicketSale(User $creator, User $fan, \App\Modules\User\Models\EventTicket $ticket, \App\Modules\User\Models\EventTicketTier $tier): void
    {
        \App\Modules\User\Models\UserNotification::create([
            'user_id'    => $creator->id,
            'type'       => 'event.ticket_sold',
            'data'       => [
                'fan_id'   => $fan->id,
                'fan_name' => $fan->name,
                'tier_name' => $tier->name,
                'quantity' => $ticket->quantity,
                'message'  => $fan->name . ' got ' . $ticket->quantity . ' × ' . $tier->name . ' ticket(s).',
                'link'     => route('user.links.ics.tickets', $tier->link_id),
            ],
            'created_at' => now(),
        ]);
        $this->emailCreatorBestEffort($creator, 'New ticket sale on Sayzio', $fan->name . ' just got ' . $ticket->quantity . ' × ' . $tier->name . ' ticket(s).');
        if ($ticket->price_cents > 0) {
            $this->whatsappPaymentAlert($creator, '🎟️ New ticket sale on Sayzio: ' . $fan->name . ' bought ' . $ticket->quantity . ' × ' . $tier->name . '.');
        }
    }

    protected function emailTicketConfirmation(\App\Modules\User\Models\EventTicket $ticket, \App\Modules\User\Models\EventTicketTier $tier): void
    {
        $email = $ticket->attendee_email;
        if (!$email) return;
        try {
            \App\Modules\Common\Services\Emailer::send('ticketing.confirmation', $email, [
                'event_name' => $tier->link?->title,
                'tier_name'  => $tier->name,
                'quantity'   => $ticket->quantity,
                'ticket_url' => route('redirect.event.ticket', ['alias' => $tier->link?->alias, 'code' => $ticket->code]),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ticketing.confirmation_email.failed', ['ticket' => $ticket->id, 'err' => $e->getMessage()]);
        }
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
            'dm_msg'       => $this->confirmDmPayToMessage($payload),
            'dm_att'       => $this->confirmDmAttachmentUnlock($payload),
            'product'      => $this->confirmProductOrder($payload),
            'form'         => $this->confirmFormPayment($payload),
            'event_ticket' => $this->confirmEventTicket($payload),
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
        // Paid DMs (Task #1210): fire welcome rules for new subscribers.
        try {
            if ($creator && $fan) {
                app(\App\Services\Dm\DmDispatcher::class)->triggerNewSubscriber($creator, $fan, $tier);
            }
        } catch (\Throwable $e) {}

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

    /**
     * Pay-to-message (Task #1210). One-off charge that flips the
     * conversation's `paid_to_message` flag so the fan can keep DMing
     * the creator until they reply.
     */
    public function startDmPayToMessage(
        User $fan,
        User $creator,
        int $amountCents,
        string $currency,
        int $conversationId,
        ?string $returnUrl = null,
    ): array {
        if ($fan->id === $creator->id) abort(422, 'You cannot DM yourself.');
        if ($amountCents < 100)        abort(422, 'Minimum DM fee is $1.00.');
        if ($amountCents > 1_000_00)   abort(422, 'Maximum DM fee is $1,000.00.');

        $connection = $creator->defaultPaymentConnection()
            ?: new CreatorPaymentConnection(['provider' => 'stripe', 'user_id' => $creator->id]);

        $token = Str::random(32);
        $reference = 'dm_msg_' . $conversationId . '_' . $fan->id;
        cache()->put($this->cacheKey('dm_msg', $conversationId, $token), [
            'conversation_id' => $conversationId,
            'fan_id'          => $fan->id,
            'creator_id'      => $creator->id,
            'amount'          => $amountCents,
            'currency'        => $currency,
            'return_url'      => $returnUrl,
        ], now()->addMinutes(30));

        $url = PayoutProviderRegistry::adapter($connection->provider)->createOneTimeCheckout($connection, [
            'kind'       => 'dm_msg',
            'reference'  => $reference,
            'token'      => $token,
            'amount'     => $amountCents,
            'currency'   => $currency,
            'fan_email'  => $fan->email,
            'return_url' => route('checkout.return', ['kind' => 'dm_msg', 'reference' => $reference, 'token' => $token]),
        ]);

        return ['url' => $url];
    }

    /**
     * Per-DM-attachment unlock (Task #1210). One-off charge that drops
     * a viewer_dm_attachment_unlocks row so the fan can see the asset.
     */
    public function startDmAttachmentUnlock(
        User $fan,
        User $creator,
        int $attachmentId,
        int $amountCents,
        string $currency,
        ?string $returnUrl = null,
    ): array {
        if ($fan->id === $creator->id) abort(422, 'You cannot unlock your own DM media.');
        if ($amountCents < 100)        abort(422, 'Minimum unlock is $1.00.');

        $connection = $creator->defaultPaymentConnection()
            ?: new CreatorPaymentConnection(['provider' => 'stripe', 'user_id' => $creator->id]);

        $token = Str::random(32);
        $reference = 'dm_att_' . $attachmentId . '_' . $fan->id;
        cache()->put($this->cacheKey('dm_att', $attachmentId, $token), [
            'attachment_id' => $attachmentId,
            'fan_id'        => $fan->id,
            'creator_id'    => $creator->id,
            'amount'        => $amountCents,
            'currency'      => $currency,
            'return_url'    => $returnUrl,
        ], now()->addMinutes(30));

        $url = PayoutProviderRegistry::adapter($connection->provider)->createOneTimeCheckout($connection, [
            'kind'       => 'dm_att',
            'reference'  => $reference,
            'token'      => $token,
            'amount'     => $amountCents,
            'currency'   => $currency,
            'fan_email'  => $fan->email,
            'return_url' => route('checkout.return', ['kind' => 'dm_att', 'reference' => $reference, 'token' => $token]),
        ]);

        return ['url' => $url];
    }

    protected function confirmDmPayToMessage(array $p): array
    {
        $conv = \App\Modules\Common\Models\ViewerDmConversation::find($p['conversation_id']);
        $creator = User::find($p['creator_id']);
        $fan     = User::find($p['fan_id']);
        if (!$conv || !$creator || !$fan) return ['url' => '/'];

        if (!$conv->paid_to_message) {
            $conv->paid_to_message    = true;
            $conv->paid_amount_cents  = (int) $p['amount'];
            $conv->paid_currency      = (string) $p['currency'];
            $conv->paid_at            = now();
            $conv->save();
        }

        $this->logEvent($creator, $fan, CreatorPaymentEvent::SOURCE_TIP, CreatorPaymentEvent::TYPE_TIP_RECEIVED, $conv, $p['amount'], $p['currency']);

        // System message in the thread so both sides see the receipt.
        try {
            app(\App\Services\Dm\DmDispatcher::class)->send(
                $conv, $fan, 'viewer',
                'Paid $' . number_format($p['amount'] / 100, 2) . ' to start this conversation.',
                [], null, false, true,
            );
        } catch (\Throwable $e) { Log::warning('dm_msg.system.failed', ['err' => $e->getMessage()]); }

        $url = $p['return_url']
            ?: route('creator-profile.show', ['handle' => $creator->handle ?: $creator->id]) . '#dm';

        return ['url' => $url, 'message' => 'Payment received — go ahead and message ' . $creator->name . '.'];
    }

    protected function confirmDmAttachmentUnlock(array $p): array
    {
        $att = \App\Modules\Common\Models\ViewerDmAttachment::find($p['attachment_id']);
        if (!$att) return ['url' => '/'];

        $unlock = \App\Modules\Common\Models\ViewerDmAttachmentUnlock::firstOrCreate(
            ['attachment_id' => $att->id, 'fan_user_id' => $p['fan_id']],
            [
                'creator_user_id'   => $p['creator_id'],
                'price_cents'       => $p['amount'],
                'currency'          => $p['currency'],
                'gateway'           => 'preview',
                'gateway_charge_id' => 'preview_dm_att_' . Str::random(10),
                'unlocked_at'       => now(),
            ],
        );
        $creator = User::find($p['creator_id']);
        $fan     = User::find($p['fan_id']);
        $conv = $att->conversation;
        if ($creator && $fan) {
            $this->logEvent($creator, $fan, CreatorPaymentEvent::SOURCE_PPV, CreatorPaymentEvent::TYPE_PPV_UNLOCKED, $unlock, $p['amount'], $p['currency']);
            if ($conv) {
                try {
                    app(\App\Services\Dm\DmDispatcher::class)->notifyAttachmentUnlocked($creator, $fan, $att, $p['amount'], $p['currency'], $conv);
                } catch (\Throwable $e) { Log::warning('dm_att.notify.failed', ['err' => $e->getMessage()]); }
            }
        }
        return [
            'url' => $p['return_url']
                ?: ($conv ? route('user.inbox.dms.thread', $conv->id) : '/'),
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
            CreatorPaymentEvent::SOURCE_TIP     => $this->refundTip($referenceId),
            CreatorPaymentEvent::SOURCE_PPV     => $this->refundPpv($referenceId),
            CreatorPaymentEvent::SOURCE_SUB     => $this->refundSubscription($referenceId),
            CreatorPaymentEvent::SOURCE_PRODUCT => $this->refundProductOrder($referenceId),
            CreatorPaymentEvent::SOURCE_FORM    => $this->refundFormSubmission($referenceId),
            CreatorPaymentEvent::SOURCE_EVENT   => $this->refundEventTicket($referenceId),
            default => false,
        };
    }

    /**
     * Refund + cancel an event ticket (Task #3589). Reverses the gateway
     * charge (best-effort), flips the ticket to `refunded` (frees its seat
     * back into the tier's remaining capacity), and writes a negative
     * ledger row. Idempotent: a second call on an already-refunded or
     * already-checked-in ticket is a no-op.
     */
    public function refundEventTicket(int $id, ?string $reason = null): bool
    {
        // Idempotency: claim the ticket under a row lock so two concurrent
        // refund requests can't both pass the status check and double-refund.
        // A second call on an already-refunded/cancelled ticket is a no-op.
        $ticket = DB::transaction(function () use ($id) {
            $ticket = \App\Modules\User\Models\EventTicket::whereKey($id)->lockForUpdate()->first();
            if (!$ticket || in_array($ticket->status, [
                \App\Modules\User\Models\EventTicket::STATUS_REFUNDED,
                \App\Modules\User\Models\EventTicket::STATUS_CANCELLED,
            ], true)) {
                return null;
            }
            $ticket->status = \App\Modules\User\Models\EventTicket::STATUS_REFUNDED;
            $ticket->save();
            return $ticket;
        });

        if (!$ticket) {
            return false;
        }

        // Best-effort gateway reversal (works in preview mode). Done outside
        // the lock so a slow provider call doesn't hold the row.
        try {
            if ($ticket->gateway && $ticket->gateway_charge_id && $ticket->price_cents > 0) {
                PayoutProviderRegistry::adapter($ticket->gateway)->refundCharge($ticket->gateway_charge_id, $ticket->price_cents);
            }
        } catch (\Throwable $e) {
            Log::warning('refund.event_ticket.adapter_failed', ['ticket' => $id, 'err' => $e->getMessage()]);
        }

        if ($ticket->tier) {
            $ticket->tier->decrement('sold_count', min($ticket->quantity, $ticket->tier->sold_count));
        }

        $creator = $ticket->link?->user;
        $buyer   = $ticket->buyer;
        if ($creator && $ticket->price_cents > 0) {
            $this->logEvent($creator, $buyer, CreatorPaymentEvent::SOURCE_EVENT, CreatorPaymentEvent::TYPE_TICKET_REFUNDED, $ticket, -1 * $ticket->price_cents, $ticket->currency);
        }

        $this->notifyAttendeeOfTicketRefund($ticket, $reason);

        return true;
    }

    /**
     * Tell the attendee their event ticket was refunded — an email to the
     * (possibly anonymous) attendee_email plus, for registered buyers, an
     * in-app notification. Both are best-effort and never abort the refund.
     */
    protected function notifyAttendeeOfTicketRefund(\App\Modules\User\Models\EventTicket $ticket, ?string $reason = null): void
    {
        $eventName = $ticket->link?->title ?? 'the event';
        $amount    = $this->formatMoney((int) $ticket->price_cents, (string) ($ticket->currency ?: 'USD'));
        $reason    = $reason ? mb_substr(trim($reason), 0, 280) : null;

        if ($ticket->attendee_email) {
            try {
                \App\Modules\Common\Services\Emailer::send('ticketing.refunded', $ticket->attendee_email, [
                    'event_name' => $eventName,
                    'tier_name'  => $ticket->tier?->name ?? 'Ticket',
                    'quantity'   => $ticket->quantity,
                    'amount'     => $amount,
                    'reason'     => $reason ?: '—',
                ]);
            } catch (\Throwable $e) {
                Log::warning('ticketing.refund_email.failed', ['ticket' => $ticket->id, 'err' => $e->getMessage()]);
            }
        }

        $buyer = $ticket->buyer;
        if ($buyer) {
            try {
                \App\Modules\User\Models\UserNotification::create([
                    'user_id'    => $buyer->id,
                    'type'       => 'ticket.refunded',
                    'data'       => [
                        'ticket_id' => $ticket->id,
                        'event'     => $eventName,
                        'amount'    => $amount,
                        'message'   => 'Your ticket for ' . $eventName . ' was refunded — ' . $amount . '.',
                        'link'      => $ticket->link?->alias
                            ? route('redirect.event.ticket', ['alias' => $ticket->link->alias, 'code' => $ticket->code])
                            : null,
                    ],
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('ticketing.refund_notification.failed', ['ticket' => $ticket->id, 'err' => $e->getMessage()]);
            }
        }
    }

    /**
     * Refund a paid form submission (Task #2322). Reverses the gateway
     * charge (best-effort; works in preview mode), flips the submission's
     * payment_status to `refunded`, and writes a negative TYPE_FORM_REFUNDED
     * ledger row against the form owner. Idempotent: a second call on an
     * already-refunded (or never-paid) submission is a no-op. Returns true
     * on success.
     */
    public function refundFormSubmission(int $id): bool
    {
        $submission = \App\Modules\User\Models\FormSubmission::withoutGlobalScope('workspace')->find($id);
        if (!$submission || !$submission->isRefundable()) {
            return false;
        }

        try {
            if ($submission->gateway && $submission->gateway_charge_id) {
                PayoutProviderRegistry::adapter($submission->gateway)
                    ->refundCharge($submission->gateway_charge_id, (int) $submission->amount_cents);
            }
        } catch (\Throwable $e) {
            Log::warning('refund.form.adapter_failed', ['submission' => $id, 'err' => $e->getMessage()]);
        }

        $submission->payment_status = 'refunded';
        $submission->refunded_at    = now();
        $submission->save();

        $form    = \App\Modules\User\Models\Form::withoutGlobalScope('workspace')->find($submission->form_id);
        $creator = $form?->user;

        // Public form submitters are anonymous (no fan_user_id), so the
        // ledger row records the owner-side refund only.
        $this->logEvent(
            $creator, null,
            CreatorPaymentEvent::SOURCE_FORM, CreatorPaymentEvent::TYPE_FORM_REFUNDED,
            $submission, -1 * (int) $submission->amount_cents, (string) ($submission->currency ?: 'USD'),
        );

        return true;
    }

    /**
     * Refund + cancel a paid product order (Task #1764). Reverses the
     * gateway charge (best-effort; works in preview mode), flips the order
     * to `refunded` (which immediately revokes digital download links since
     * those gate on isPaid()), writes a negative ledger row, and notifies
     * the buyer with a DM system message. Idempotent: a second call on an
     * already-refunded order is a no-op. Returns true on success.
     */
    public function refundProductOrder(int $id, ?string $reason = null): bool
    {
        $order = ProductOrder::with('items')->find($id);
        if (!$order || !$order->isRefundable()) {
            return false;
        }

        try {
            if ($order->gateway && $order->gateway_charge_id) {
                PayoutProviderRegistry::adapter($order->gateway)->refundCharge($order->gateway_charge_id, $order->subtotal_cents);
            }
        } catch (\Throwable $e) {
            Log::warning('refund.product.adapter_failed', ['order' => $id, 'err' => $e->getMessage()]);
        }

        $order->status        = ProductOrder::STATUS_REFUNDED;
        $order->refunded_at   = now();
        $order->refund_reason = $reason ? mb_substr(trim($reason), 0, 280) : null;
        $order->save();

        $creator = $order->creator;
        $buyer   = $order->buyer;

        $this->logEvent(
            $creator, $buyer,
            CreatorPaymentEvent::SOURCE_PRODUCT, CreatorPaymentEvent::TYPE_PRODUCT_REFUNDED,
            $order, -1 * (int) $order->subtotal_cents, $order->currency,
        );

        // DM the buyer a system receipt for the refund.
        try {
            $dispatcher = app(\App\Services\Dm\DmDispatcher::class);
            $conv = $order->conversation;
            if (!$conv && $creator && $buyer) {
                $conv = $dispatcher->findOrCreateProfileConversation($creator, $buyer);
                $order->conversation_id = $conv->id;
                $order->save();
            }
            if ($conv && $buyer) {
                $body = "↩️ Order #{$order->id} refunded\n"
                    . "We've refunded " . $this->formatMoney((int) $order->subtotal_cents, $order->currency) . " to your original payment method."
                    . ($order->refund_reason ? "\nReason: " . $order->refund_reason : '')
                    . ($order->contains_digital ? "\nAccess to any digital downloads from this order has been removed." : '');
                $dispatcher->send($conv, $buyer, 'viewer', $body, [], null, false, true);
            }
        } catch (\Throwable $e) {
            Log::warning('refund.product.dm_failed', ['order' => $id, 'err' => $e->getMessage()]);
        }

        if ($buyer) {
            $this->notifyBuyerOfRefund($buyer, $order);
        }

        return true;
    }

    protected function notifyBuyerOfRefund(User $buyer, ProductOrder $order): void
    {
        \App\Modules\User\Models\UserNotification::create([
            'user_id'    => $buyer->id,
            'type'       => 'product.refunded',
            'data'       => [
                'order_id' => $order->id,
                'amount'   => $this->formatMoney((int) $order->subtotal_cents, $order->currency),
                'message'  => 'Your order #' . $order->id . ' was refunded — ' . $this->formatMoney((int) $order->subtotal_cents, $order->currency) . '.',
                'link'     => $order->conversation_id ? route('user.inbox.dms.thread', $order->conversation_id) : null,
            ],
            'created_at' => now(),
        ]);
        $this->emailCreatorBestEffort($buyer, 'Your Sayzio order was refunded', 'Order #' . $order->id . ' has been refunded for ' . $this->formatMoney((int) $order->subtotal_cents, $order->currency) . '.');
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

    /**
     * Start a product checkout (Task #1761). The ProductOrder + items must
     * already be persisted (pending) by the caller — we only attach a
     * gateway, cache the reconciliation payload and return the provider URL.
     *
     * Returns ['url' => string, 'order' => ProductOrder].
     */
    public function startProductOrder(User $buyer, User $creator, ProductOrder $order): array
    {
        $connection = $creator->defaultPaymentConnection();
        if (!$connection) {
            $connection = new CreatorPaymentConnection(['provider' => 'stripe', 'user_id' => $creator->id]);
        }

        $order->gateway = $connection->provider;
        $order->save();

        $token = Str::random(32);
        cache()->put($this->cacheKey('product', $order->id, $token), [
            'order_id'   => $order->id,
            'buyer_id'   => $buyer->id,
            'creator_id' => $creator->id,
            'amount'     => (int) $order->subtotal_cents,
            'currency'   => $order->currency,
        ], now()->addMinutes(30));

        $url = PayoutProviderRegistry::adapter($connection->provider)->createOneTimeCheckout($connection, [
            'kind'       => 'product',
            'reference'  => 'product_' . $order->id,
            'token'      => $token,
            'amount'     => (int) $order->subtotal_cents,
            'currency'   => $order->currency,
            'fan_email'  => $buyer->email,
            'return_url' => route('checkout.return', ['kind' => 'product', 'reference' => 'product_' . $order->id, 'token' => $token]),
        ]);

        return ['url' => $url, 'order' => $order];
    }

    /**
     * Reconcile a paid product order: mark paid, log revenue, open a
     * buyer↔creator DM with a system message, notify the creator. Idempotent.
     */
    protected function confirmProductOrder(array $p): array
    {
        $order = ProductOrder::with('items')->find($p['order_id'] ?? 0);
        if (!$order) return ['url' => '/'];

        $thankYou = route('store.thankyou', ['order' => $order->id, 'token' => $order->public_token]);

        // Idempotent: a re-delivered return hand-off must not double-credit.
        if ($order->isPaid()) {
            return ['url' => $thankYou, 'message' => 'Purchase complete.'];
        }

        $creator = $order->creator;
        $buyer   = $order->buyer;

        $order->status            = ProductOrder::STATUS_PAID;
        $order->paid_at           = now();
        $order->gateway_charge_id = $order->gateway_charge_id ?: ('preview_product_' . $order->id);

        // Open a profile DM between buyer and creator with a system receipt.
        try {
            $dispatcher = app(\App\Services\Dm\DmDispatcher::class);
            $conv = $dispatcher->findOrCreateProfileConversation($creator, $buyer);
            $order->conversation_id = $conv->id;

            $lines = $order->items->map(fn ($it) => '• ' . $it->name . ($it->quantity > 1 ? ' ×' . $it->quantity : ''))->implode("\n");
            $body  = "🛍️ Order #{$order->id} confirmed\n" . $lines
                . "\nTotal: " . $this->formatMoney($order->subtotal_cents, $order->currency);
            $dispatcher->send($conv, $buyer, 'viewer', $body, [], null, false, true);
        } catch (\Throwable $e) {
            Log::warning('product.confirm.dm_failed', ['order' => $order->id, 'err' => $e->getMessage()]);
        }

        $order->save();

        if ($creator) {
            $this->logEvent(
                $creator, $buyer,
                CreatorPaymentEvent::SOURCE_PRODUCT, CreatorPaymentEvent::TYPE_PRODUCT_PURCHASED,
                $order, (int) $order->subtotal_cents, $order->currency,
            );
            $this->notifyCreatorOfProductSale($creator, $buyer, $order);
        }

        return ['url' => $thankYou, 'message' => 'Purchase complete — thank you!'];
    }

    /**
     * Start a paid-form checkout (Task #2319). The FormSubmission must
     * already be persisted as pending by the caller — we attach a gateway,
     * cache the reconciliation payload and return the provider URL. The
     * platform takes 0%; funds flow to the form OWNER's connected gateway.
     *
     * Returns ['url' => string, 'submission' => FormSubmission].
     */
    public function startFormPayment(
        \App\Modules\User\Models\Form $form,
        \App\Modules\User\Models\FormSubmission $submission,
        ?string $fanEmail = null,
        ?int $amountCents = null,
        ?array $lineItems = null,
        ?string $currencyOverride = null
    ): array {
        $creator = $form->user;
        $connection = $creator?->defaultPaymentConnection()
            ?: new CreatorPaymentConnection(['provider' => 'stripe', 'user_id' => $creator?->id]);

        // Variable pricing (Tasks #2321 / #2333): the caller computes the
        // per-submission total from the submitted data; fall back to the form's
        // flat price when not supplied (fixed-mode / legacy callers). The
        // currency override lets the selectable-pricing path pass the form
        // currency explicitly.
        $amount   = $amountCents !== null ? max(0, $amountCents) : $form->paymentAmountCents();
        $currency = $currencyOverride !== null && $currencyOverride !== ''
            ? strtoupper($currencyOverride)
            : $form->paymentCurrency();

        $submission->payment_status = 'pending';
        $submission->amount_cents   = $amount;
        $submission->currency       = $currency;
        $submission->gateway        = $connection->provider;
        if ($lineItems !== null) {
            $submission->line_items = $lineItems;
        }
        $submission->save();

        // Where the customer lands after a successful charge — the form's
        // configured redirect, else back to the form page with a paid flag.
        $settings   = array_merge(\App\Modules\User\Models\Form::defaultSettings(), $form->settings ?? []);
        $successUrl = (($settings['success_action'] ?? 'message') === 'redirect' && !empty($settings['success_redirect']))
            ? $settings['success_redirect']
            : $form->getPublicUrl() . '?paid=1';

        $token     = Str::random(32);
        $reference = 'form_' . $submission->id;
        cache()->put($this->cacheKey('form', $submission->id, $token), [
            'submission_id' => $submission->id,
            'form_id'       => $form->id,
            'creator_id'    => $creator?->id,
            'fan_id'        => auth()->id(),
            'amount'        => $amount,
            'currency'      => $currency,
            'gateway'       => $connection->provider,
            'success_url'   => $successUrl,
        ], now()->addMinutes(30));

        $url = PayoutProviderRegistry::adapter($connection->provider)->createOneTimeCheckout($connection, [
            'kind'       => 'form',
            'reference'  => $reference,
            'token'      => $token,
            'amount'     => $amount,
            'currency'   => $currency,
            'fan_email'  => $fanEmail,
            'return_url' => route('checkout.return', ['kind' => 'form', 'reference' => $reference, 'token' => $token]),
        ]);

        return ['url' => $url, 'submission' => $submission];
    }

    /**
     * Reconcile a paid-form submission: mark paid, log revenue, then fire
     * the owner notifications / autoresponder / webhooks that were held
     * back at submit time. Idempotent — a re-delivered return must not
     * double-count or double-notify.
     */
    protected function confirmFormPayment(array $p): array
    {
        $submission = \App\Modules\User\Models\FormSubmission::withoutGlobalScope('workspace')
            ->find($p['submission_id'] ?? 0);
        $successUrl = $p['success_url'] ?? '/';
        if (!$submission) {
            return ['url' => $successUrl];
        }

        // Idempotent guard.
        if ($submission->payment_status === 'paid') {
            return ['url' => $successUrl, 'message' => 'Payment received — thank you!'];
        }

        $form = \App\Modules\User\Models\Form::withoutGlobalScope('workspace')->find($submission->form_id);

        $submission->payment_status    = 'paid';
        $submission->amount_cents      = (int) ($p['amount'] ?? $submission->amount_cents);
        $submission->currency          = (string) ($p['currency'] ?? $submission->currency);
        $submission->gateway           = (string) ($p['gateway'] ?? $submission->gateway ?? 'preview');
        $submission->gateway_charge_id = $submission->gateway_charge_id ?: ('preview_form_' . Str::random(10));
        $submission->paid_at           = now();
        $submission->save();

        if ($form) {
            $form->increment('total_submissions');

            $creator = $p['creator_id'] ? User::find($p['creator_id']) : $form->user;
            $fan     = !empty($p['fan_id']) ? User::find($p['fan_id']) : null;
            if ($creator) {
                $this->logEvent(
                    $creator, $fan,
                    CreatorPaymentEvent::SOURCE_FORM, CreatorPaymentEvent::TYPE_FORM_PAID,
                    $submission, (int) $submission->amount_cents, (string) $submission->currency,
                );
                $this->whatsappPaymentAlert(
                    $creator,
                    '💳 Paid form submission on Sayzio: ' . $this->formatMoney((int) $submission->amount_cents, (string) $submission->currency)
                        . ' on "' . ($form->title ?: 'your form') . '".',
                );
            }

            // Fire owner notifications + forwarder now that the charge cleared
            // (deliberately skipped at submit time for paid forms).
            if (!$submission->is_spam) {
                try {
                    app(\App\Modules\User\Controllers\FormController::class)
                        ->finalizePaidSubmission($form, $submission);
                } catch (\Throwable $e) {
                    Log::warning('form.confirm.notify_failed', ['submission' => $submission->id, 'err' => $e->getMessage()]);
                }
            }
        }

        return ['url' => $successUrl, 'message' => 'Payment received — thank you!'];
    }

    protected function notifyCreatorOfProductSale(User $creator, ?User $buyer, ProductOrder $order): void
    {
        \App\Modules\User\Models\UserNotification::create([
            'user_id'    => $creator->id,
            'type'       => 'product.purchased',
            'data'       => [
                'order_id'   => $order->id,
                'buyer_id'   => $buyer?->id,
                'buyer_name' => $buyer?->name,
                'amount'     => $this->formatMoney($order->subtotal_cents, $order->currency),
                'message'    => ($buyer?->name ?? 'Someone') . ' purchased ' . $order->items->count() . ' item(s) — ' . $this->formatMoney($order->subtotal_cents, $order->currency) . '.',
                'link'       => route('user.monetization.orders'),
            ],
            'created_at' => now(),
        ]);
        $this->emailCreatorBestEffort($creator, 'New Sayzio product sale', ($buyer?->name ?? 'A customer') . ' just bought from your page.');
    }

    protected function formatMoney(int $cents, string $currency): string
    {
        return strtoupper($currency) . ' ' . number_format($cents / 100, 2);
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
        $this->emailCreatorBestEffort($creator, 'New Sayzio subscriber', $fan->name . ' just joined ' . ($tier?->name ?? 'your page') . '.');

        $cents = (int) ($tier?->price_monthly_cents ?? 0);
        $amount = $cents > 0
            ? ' (' . strtoupper($tier?->currency ?? 'USD') . ' ' . number_format($cents / 100, 2) . '/mo)'
            : '';
        $this->whatsappPaymentAlert($creator, '🎉 New subscriber on Sayzio: ' . $fan->name . ' just joined ' . ($tier?->name ?? 'your page') . $amount . '.');
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
        $this->emailCreatorBestEffort($creator, 'New tip on Sayzio', ($tip->anonymous ? 'Someone' : $fan->name) . ' tipped you ' . $amount . '.');
        $this->whatsappPaymentAlert($creator, '💸 New tip on Sayzio: ' . ($tip->anonymous ? 'Someone' : $fan->name) . ' tipped you ' . $amount . '.');
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
        $this->whatsappPaymentAlert($creator, '🔓 ' . $fan->name . ' unlocked your paid post on Sayzio for $' . number_format($price / 100, 2) . '.');
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

    /**
     * Best-effort WhatsApp ping to the creator about a new payment event (new
     * subscriber, tip, PPV/unlock, paid form). Only fires when the creator
     * opted in via the account-level preference; never throws and degrades to
     * preview-mode logging when delivery credentials are absent (Task #2765).
     */
    protected function whatsappPaymentAlert(?User $creator, string $message): void
    {
        if (!$creator || !$creator->wantsWhatsappPaymentAlerts()) return;
        \App\Services\WhatsApp\WhatsAppAlerts::send($creator, $message);
    }

    protected function emailCreatorBestEffort(User $creator, string $subject, string $body): void
    {
        if (!$creator->email) return;
        try {
            \App\Modules\Common\Services\Emailer::send('monetization.creator_notice', $creator->email, [], [
                'user'    => $creator->id,
                'subject' => $subject,
                'body'    => $body,
            ]);
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
        if ($kind === 'dm_msg' && count($parts) >= 3) {
            // dm_msg_{conversationId}_{fanId}
            return $this->cacheKey('dm_msg', (int) $parts[2], $token);
        }
        if ($kind === 'dm_att' && count($parts) >= 3) {
            // dm_att_{attachmentId}_{fanId}
            return $this->cacheKey('dm_att', (int) $parts[2], $token);
        }
        if ($kind === 'product' && count($parts) === 2) {
            // product_{orderId}
            return $this->cacheKey('product', (int) $parts[1], $token);
        }
        if ($kind === 'form' && count($parts) === 2) {
            // form_{submissionId}
            return $this->cacheKey('form', (int) $parts[1], $token);
        }
        if ($kind === 'event_ticket' && count($parts) >= 3) {
            // event_ticket_{tierId}_{fanId}
            return $this->cacheKey('event', (int) $parts[2], $token);
        }
        return null;
    }
}
