<?php

namespace Tests\Feature;

use App\Modules\User\Models\CreatorPaymentEvent;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\ProductOrderItem;
use App\Modules\User\Models\User;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductOrderRefundTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(string $prefix): User
    {
        return User::create([
            'name'     => $prefix.' '.Str::random(4),
            'email'    => strtolower($prefix).Str::random(6).'@e.com',
            'password' => bcrypt('secret'),
        ]);
    }

    protected function makePaidOrder(User $creator, User $buyer, bool $digital = true): ProductOrder
    {
        $order = ProductOrder::create([
            'buyer_user_id'     => $buyer->id,
            'creator_user_id'   => $creator->id,
            'status'            => ProductOrder::STATUS_PAID,
            'subtotal_cents'    => 2500,
            'currency'          => 'USD',
            'gateway'           => 'stripe',
            'gateway_charge_id' => 'preview_product_test',
            'contains_physical' => !$digital,
            'contains_digital'  => $digital,
            'public_token'      => Str::random(48),
            'paid_at'           => now(),
        ]);
        ProductOrderItem::create([
            'order_id'         => $order->id,
            'name'             => 'Test Product',
            'unit_price_cents' => 2500,
            'quantity'         => 1,
            'currency'         => 'USD',
            'product_type'     => $digital ? ProductOrderItem::TYPE_DIGITAL : ProductOrderItem::TYPE_PHYSICAL,
            'digital_file_url' => $digital ? 'https://example.com/file.zip' : null,
        ]);
        return $order->load('items');
    }

    public function test_refund_flips_status_and_writes_negative_ledger_row(): void
    {
        $creator = $this->makeUser('Creator');
        $buyer   = $this->makeUser('Buyer');
        $order   = $this->makePaidOrder($creator, $buyer);

        $ok = app(MonetizationCheckout::class)->refundProductOrder($order->id, 'Out of stock');

        $this->assertTrue($ok);
        $fresh = $order->fresh();
        $this->assertSame(ProductOrder::STATUS_REFUNDED, $fresh->status);
        $this->assertNotNull($fresh->refunded_at);
        $this->assertSame('Out of stock', $fresh->refund_reason);
        $this->assertFalse($fresh->isPaid(), 'refunded order is no longer paid, so downloads are revoked');

        $event = CreatorPaymentEvent::where('creator_user_id', $creator->id)
            ->where('type', CreatorPaymentEvent::TYPE_PRODUCT_REFUNDED)
            ->first();
        $this->assertNotNull($event);
        $this->assertSame(-2500, $event->amount_cents, 'refund ledger row is the negative of the sale');
    }

    public function test_refund_is_idempotent(): void
    {
        $creator = $this->makeUser('Creator');
        $buyer   = $this->makeUser('Buyer');
        $order   = $this->makePaidOrder($creator, $buyer);

        $svc = app(MonetizationCheckout::class);
        $this->assertTrue($svc->refundProductOrder($order->id));
        $this->assertFalse($svc->refundProductOrder($order->id), 'second refund is a no-op');

        $this->assertSame(1, CreatorPaymentEvent::where('creator_user_id', $creator->id)
            ->where('type', CreatorPaymentEvent::TYPE_PRODUCT_REFUNDED)->count());
    }

    public function test_creator_can_refund_from_orders_route(): void
    {
        $creator = $this->makeUser('Creator');
        $buyer   = $this->makeUser('Buyer');
        $order   = $this->makePaidOrder($creator, $buyer, digital: false);

        $this->actingAs($creator)
            ->post('/user/monetization/orders/'.$order->id.'/refund', ['refund_reason' => 'Customer changed mind'])
            ->assertRedirect();

        $this->assertSame(ProductOrder::STATUS_REFUNDED, $order->fresh()->status);
    }

    public function test_non_owner_cannot_refund(): void
    {
        $creator  = $this->makeUser('Creator');
        $buyer    = $this->makeUser('Buyer');
        $stranger = $this->makeUser('Stranger');
        $order    = $this->makePaidOrder($creator, $buyer);

        $this->actingAs($stranger)
            ->post('/user/monetization/orders/'.$order->id.'/refund')
            ->assertForbidden();

        $this->assertSame(ProductOrder::STATUS_PAID, $order->fresh()->status);
    }
}
