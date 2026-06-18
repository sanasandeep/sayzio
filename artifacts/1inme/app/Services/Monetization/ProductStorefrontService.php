<?php

namespace App\Services\Monetization;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\ProductOrderItem;
use App\Modules\User\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared storefront logic for the in-page Product block (Task #1761 / #1763).
 *
 * Single source of truth for resolving a purchasable product from a block
 * and for persisting a pending order + items. Used by both the web
 * (session-cart) BiolinkStoreController and the mobile (stateless)
 * BiolinkStoreApiController so the two surfaces can never drift on price,
 * snapshotting, or single-currency rules.
 *
 * Prices/types are ALWAYS re-read from the block here — callers must never
 * trust client-supplied amounts.
 */
class ProductStorefrontService
{
    /**
     * Re-read a Product block and return a normalised, purchasable product
     * array — or null if the block isn't a native-checkout product.
     *
     * @return array<string, mixed>|null
     */
    public function resolveProduct(Link $link, int $blockId): ?array
    {
        $block = BiolinkBlock::where('id', $blockId)
            ->where('link_id', $link->id)
            ->where('type', 'product')
            ->where('is_active', true)
            ->first();
        if (!$block) {
            return null;
        }

        $s = $block->settings ?? [];
        if (empty($s['native_checkout']) || (int) ($s['price_cents'] ?? 0) <= 0) {
            return null;
        }

        return [
            'block_id'         => $block->id,
            'link_id'          => $link->id,
            'name'             => mb_substr(trim((string) ($s['name'] ?? 'Product')), 0, 191) ?: 'Product',
            'price_cents'      => (int) $s['price_cents'],
            'currency'         => strtoupper((string) ($s['currency'] ?? 'USD')),
            'product_type'     => ($s['product_type'] ?? 'digital') === 'physical' ? 'physical' : 'digital',
            'digital_file_url' => $s['digital_file'] ?? null,
            'image_url'        => $s['image'] ?? null,
            'thank_you'        => trim((string) ($s['thank_you_message'] ?? '')),
        ];
    }

    /**
     * Resolve a product purely from its block id, validating that the block
     * belongs to the given creator. Mobile carts post block ids only, so we
     * find the owning Link ourselves rather than trusting a client link id.
     *
     * @return array{0: array<string,mixed>, 1: Link}|null
     */
    public function productLineFromBlock(User $creator, int $blockId): ?array
    {
        $block = BiolinkBlock::where('id', $blockId)
            ->where('type', 'product')
            ->where('is_active', true)
            ->first();
        if (!$block) {
            return null;
        }
        $link = Link::find($block->link_id);
        if (!$link || (int) $link->user_id !== (int) $creator->id) {
            return null;
        }
        $product = $this->resolveProduct($link, $blockId);
        return $product ? [$product, $link] : null;
    }

    /**
     * Persist a pending order + items from resolved product lines.
     *
     * Single-currency order — collapse to the first line's currency and
     * drop any mismatched lines (mixed-currency carts are out of scope).
     *
     * @param array<int, array{0: array<string,mixed>, 1: int}> $lines
     */
    public function createOrder(User $buyer, User $creator, Link $link, array $lines): ProductOrder
    {
        $currency = strtoupper((string) ($lines[0][0]['currency'] ?? 'USD'));

        return DB::transaction(function () use ($buyer, $creator, $link, $lines, $currency) {
            $subtotal = 0;
            $hasDigital = $hasPhysical = false;
            $thankYou = '';
            $prepared = [];

            foreach ($lines as [$product, $qty]) {
                if (strtoupper((string) $product['currency']) !== $currency) {
                    continue;
                }
                $qty = max(1, (int) $qty);
                $subtotal += $product['price_cents'] * $qty;
                $product['product_type'] === 'digital' ? $hasDigital = true : $hasPhysical = true;
                if ($thankYou === '' && $product['thank_you'] !== '') {
                    $thankYou = $product['thank_you'];
                }
                $prepared[] = [$product, $qty];
            }

            $order = ProductOrder::create([
                'buyer_user_id'     => $buyer->id,
                'creator_user_id'   => $creator->id,
                'link_id'           => $link->id,
                'status'            => ProductOrder::STATUS_PENDING,
                'subtotal_cents'    => $subtotal,
                'currency'          => $currency,
                'contains_physical' => $hasPhysical,
                'contains_digital'  => $hasDigital,
                'public_token'      => Str::random(48),
                'metadata'          => ['thank_you_message' => $thankYou],
            ]);

            foreach ($prepared as [$product, $qty]) {
                ProductOrderItem::create([
                    'order_id'         => $order->id,
                    'link_id'          => $product['link_id'],
                    'block_id'         => $product['block_id'],
                    'name'             => $product['name'],
                    'unit_price_cents' => $product['price_cents'],
                    'quantity'         => $qty,
                    'currency'         => $currency,
                    'product_type'     => $product['product_type'],
                    'digital_file_url' => $product['digital_file_url'],
                    'image_url'        => $product['image_url'],
                ]);
            }

            return $order->load('items');
        });
    }
}
