<?php

namespace App\Modules\Common\Controllers;

use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\ProductOrderItem;
use App\Modules\User\Models\User;
use App\Services\Monetization\MonetizationCheckout;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * In-page storefront for biolink Product blocks (Task #1761). Carts are
 * session-backed and scoped per creator (multi-creator carts are out of
 * scope). Prices/types are ALWAYS re-read from the block at checkout — we
 * never trust client-supplied amounts. Purchases require a logged-in
 * ViewerSession; guests get a JSON {login_required} so the page can open
 * the existing viewer-login modal.
 */
class BiolinkStoreController extends Controller
{
    public function __construct(private MonetizationCheckout $checkout)
    {
    }

    public function addToCart(Request $request, string $alias)
    {
        [$link, $creator] = $this->resolveLink($request, $alias);
        $blockId  = (int) $request->input('block_id');
        $product  = $this->resolveProduct($link, $blockId);
        if (!$product) {
            return response()->json(['error' => 'This product is not available.'], 422);
        }

        $cart = $this->cart();
        $key  = $link->id . ':' . $blockId;
        $cart[$creator->id][$key] = ($cart[$creator->id][$key] ?? 0) + 1;
        $this->saveCart($cart);

        return response()->json($this->cartPayload($creator));
    }

    public function updateCart(Request $request, string $alias)
    {
        [$link, $creator] = $this->resolveLink($request, $alias);
        $blockId = (int) $request->input('block_id');
        $qty     = max(0, (int) $request->input('quantity', 0));

        $cart = $this->cart();
        $key  = $link->id . ':' . $blockId;
        if ($qty <= 0) {
            unset($cart[$creator->id][$key]);
        } else {
            $cart[$creator->id][$key] = min(99, $qty);
        }
        if (empty($cart[$creator->id] ?? [])) {
            unset($cart[$creator->id]);
        }
        $this->saveCart($cart);

        return response()->json($this->cartPayload($creator));
    }

    /** Single-product "Buy Now" — ignores the cart. */
    public function buy(Request $request, string $alias)
    {
        [$link, $creator] = $this->resolveLink($request, $alias);
        $viewer = $this->viewerOrJson($creator);
        if (!$viewer instanceof User) {
            return $viewer; // JSON login_required response
        }

        $blockId = (int) $request->input('block_id');
        $product = $this->resolveProduct($link, $blockId);
        if (!$product) {
            return response()->json(['error' => 'This product is not available.'], 422);
        }

        $order = $this->createOrder($viewer, $creator, $link, [[$product, 1]]);
        $res   = $this->checkout->startProductOrder($viewer, $creator, $order);

        return response()->json(['url' => $res['url']]);
    }

    /** Combined checkout of the per-creator cart. */
    public function checkout(Request $request, string $alias)
    {
        [$link, $creator] = $this->resolveLink($request, $alias);
        $viewer = $this->viewerOrJson($creator);
        if (!$viewer instanceof User) {
            return $viewer;
        }

        $cart = $this->cart()[$creator->id] ?? [];
        if (empty($cart)) {
            return response()->json(['error' => 'Your cart is empty.'], 422);
        }

        $lines = [];
        foreach ($cart as $key => $qty) {
            [$lid, $bid] = array_pad(explode(':', (string) $key), 2, null);
            $itemLink = (int) $lid === $link->id ? $link : Link::find((int) $lid);
            if (!$itemLink || $itemLink->user_id !== $creator->id) {
                continue;
            }
            $product = $this->resolveProduct($itemLink, (int) $bid);
            if ($product) {
                $lines[] = [$product, max(1, (int) $qty)];
            }
        }
        if (empty($lines)) {
            return response()->json(['error' => 'Your cart is empty.'], 422);
        }

        $order = $this->createOrder($viewer, $creator, $link, $lines);
        $res   = $this->checkout->startProductOrder($viewer, $creator, $order);

        return response()->json(['url' => $res['url']]);
    }

    public function thankYou(Request $request, ProductOrder $order)
    {
        abort_unless(hash_equals($order->public_token, (string) $request->query('token')), 404);
        $order->load('items', 'creator');

        // Clear the cart for this creator now that checkout is complete.
        $cart = $this->cart();
        unset($cart[$order->creator_user_id]);
        $this->saveCart($cart);

        $viewer    = ViewerSession::user();
        $isBuyer   = $viewer && $viewer->id === $order->buyer_user_id;
        $message   = trim((string) ($order->metadata['thank_you_message'] ?? ''))
            ?: 'Thanks for your purchase! 🎉';

        return view('public.store.thankyou', compact('order', 'isBuyer', 'message'));
    }

    public function download(Request $request, ProductOrder $order, ProductOrderItem $item)
    {
        abort_unless($item->order_id === $order->id, 404);
        abort_unless($order->isPaid(), 403);
        abort_unless($item->isDigital() && $item->digital_file_url, 404);

        $viewer  = ViewerSession::user();
        $isBuyer = $viewer && $viewer->id === $order->buyer_user_id;
        $byToken = hash_equals($order->public_token, (string) $request->query('token'));
        abort_unless($isBuyer || $byToken, 403);

        return redirect()->away($item->digital_file_url);
    }

    // ─── helpers ────────────────────────────────────────────────────

    /** @return array{0: Link, 1: User} */
    protected function resolveLink(Request $request, string $alias): array
    {
        $link = Link::resolveByAlias($alias, $request->getHost());
        abort_if(!$link, 404);
        $creator = $link->user;
        abort_if(!$creator, 404);
        return [$link, $creator];
    }

    /**
     * Re-read a Product block and return a normalised, purchasable product
     * array — or null if the block isn't a native-checkout product.
     *
     * @return array<string, mixed>|null
     */
    protected function resolveProduct(Link $link, int $blockId): ?array
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
     * Persist a pending order + items from resolved product lines.
     *
     * @param array<int, array{0: array<string,mixed>, 1: int}> $lines
     */
    protected function createOrder(User $buyer, User $creator, Link $link, array $lines): ProductOrder
    {
        // Single-currency order — collapse to the first line's currency and
        // drop any mismatched lines (mixed-currency carts are out of scope).
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

    /** @return User|\Illuminate\Http\JsonResponse */
    protected function viewerOrJson(User $creator)
    {
        $viewer = ViewerSession::user() ?: request()->user();
        if (!$viewer) {
            return response()->json(['login_required' => true, 'creator_id' => $creator->id], 401);
        }
        return $viewer;
    }

    /** @return array<int, array<string, int>> */
    protected function cart(): array
    {
        return (array) session('product_cart', []);
    }

    protected function saveCart(array $cart): void
    {
        session(['product_cart' => $cart]);
    }

    /** @return array<string, mixed> */
    protected function cartPayload(User $creator): array
    {
        $cart  = $this->cart()[$creator->id] ?? [];
        $items = [];
        $count = 0;
        $total = 0;
        $currency = 'USD';

        foreach ($cart as $key => $qty) {
            [$lid, $bid] = array_pad(explode(':', (string) $key), 2, null);
            $link = Link::find((int) $lid);
            if (!$link || $link->user_id !== $creator->id) {
                continue;
            }
            $product = $this->resolveProduct($link, (int) $bid);
            if (!$product) {
                continue;
            }
            $qty = max(1, (int) $qty);
            $count += $qty;
            $total += $product['price_cents'] * $qty;
            $currency = $product['currency'];
            $items[] = [
                'block_id'    => $product['block_id'],
                'alias'       => $link->alias,
                'name'        => $product['name'],
                'price_cents' => $product['price_cents'],
                'quantity'    => $qty,
                'image_url'   => $product['image_url'],
            ];
        }

        return [
            'ok'       => true,
            'count'    => $count,
            'total'    => $total,
            'currency' => $currency,
            'items'    => $items,
        ];
    }
}
