<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\User;
use App\Services\Monetization\MonetizationCheckout;
use App\Services\Monetization\ProductStorefrontService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Mobile parity for the in-page Product storefront (Task #1763).
 *
 * The web storefront (BiolinkStoreController) keeps the cart in the HTTP
 * session. The Sanctum mobile path has no session, so the cart lives in
 * the app and is posted as line items here. Prices/types are always
 * re-read from the block via ProductStorefrontService — we never trust the
 * client-posted amount.
 *
 * Every purchase endpoint returns a `checkout_url` the app opens via
 * WebBrowser (Apple IAP rules require the system browser for external
 * billing) plus the pending `order` so the app can poll its status and
 * land on a native thank-you / digital-download screen.
 */
class BiolinkStoreApiController extends Controller
{
    use ApiResponses;

    public function __construct(
        private MonetizationCheckout $checkout,
        private ProductStorefrontService $store,
    ) {
    }

    // ─── Buyer surface ─────────────────────────────────────────────

    /** Single-product "Buy Now" — ignores any cart. */
    public function buy(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized('Sign in to buy.', 'login_required');
        }
        [$link, $creator] = $this->resolveLink($alias, $request->getHost());

        $line = $this->store->productLineFromBlock($creator, (int) $request->input('block_id'));
        if (!$line) {
            return $this->fail('This product is not available.', 422);
        }
        [$product, $productLink] = $line;

        $order = $this->store->createOrder($user, $creator, $productLink, [[$product, 1]]);
        $res   = $this->checkout->startProductOrder($user, $creator, $order);

        return $this->ok([
            'checkout_url' => $res['url'],
            'order'        => $this->orderShape($order),
        ]);
    }

    /** Combined checkout of a client-supplied cart (items: [{block_id, quantity}]). */
    public function checkout(Request $request, string $alias)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized('Sign in to check out.', 'login_required');
        }
        [$link, $creator] = $this->resolveLink($alias, $request->getHost());

        $data = $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.block_id'   => 'required|integer',
            'items.*.quantity'   => 'nullable|integer|min:1|max:99',
        ]);

        $lines       = [];
        $primaryLink = $link;
        foreach ($data['items'] as $it) {
            $resolved = $this->store->productLineFromBlock($creator, (int) $it['block_id']);
            if (!$resolved) {
                continue;
            }
            [$product, $productLink] = $resolved;
            $lines[]     = [$product, max(1, (int) ($it['quantity'] ?? 1))];
            $primaryLink = $productLink;
        }
        if (empty($lines)) {
            return $this->fail('Your cart is empty.', 422);
        }

        $order = $this->store->createOrder($user, $creator, $primaryLink, $lines);
        $res   = $this->checkout->startProductOrder($user, $creator, $order);

        return $this->ok([
            'checkout_url' => $res['url'],
            'order'        => $this->orderShape($order),
        ]);
    }

    /**
     * Buyer's view of a single order — used to poll status after returning
     * from the browser checkout and to show the thank-you / download screen.
     */
    public function order(Request $request, ProductOrder $order)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized();
        }
        if ((int) $order->buyer_user_id !== (int) $user->id) {
            return $this->forbidden();
        }
        $order->load('items', 'creator:id,name,handle,avatar');

        return $this->ok($this->orderShape($order, includeDownloads: true));
    }

    // ─── Owner dashboard surface ───────────────────────────────────

    /** Paginated paid/fulfilled/cancelled orders for the signed-in creator. */
    public function ownerOrders(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized();
        }

        $statuses = [ProductOrder::STATUS_PAID, ProductOrder::STATUS_FULFILLED, ProductOrder::STATUS_CANCELLED];
        $filter   = (string) $request->query('status', '');

        $query = ProductOrder::query()
            ->with(['items', 'buyer:id,name,handle,avatar'])
            ->where('creator_user_id', $user->id)
            ->whereIn('status', $statuses);
        if (in_array($filter, $statuses, true)) {
            $query->where('status', $filter);
        }

        $rows = $query->orderByDesc('paid_at')->orderByDesc('id')->paginate(30);

        return $this->ok([
            'items' => collect($rows->items())->map(fn (ProductOrder $o) => $this->ownerOrderShape($o))->all(),
            'meta'  => [
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
                'total'        => $rows->total(),
            ],
        ]);
    }

    /** Mark a paid physical order as fulfilled. */
    public function fulfillOrder(Request $request, ProductOrder $order)
    {
        $user = $request->user();
        if (!$user) {
            return $this->unauthorized();
        }
        if ((int) $order->creator_user_id !== (int) $user->id) {
            return $this->forbidden();
        }
        if ($order->status !== ProductOrder::STATUS_PAID) {
            return $this->fail('Only paid orders can be marked fulfilled.', 422);
        }

        $order->status       = ProductOrder::STATUS_FULFILLED;
        $order->fulfilled_at = now();
        $order->save();

        $order->load(['items', 'buyer:id,name,handle,avatar']);

        return $this->ok($this->ownerOrderShape($order));
    }

    // ─── helpers ───────────────────────────────────────────────────

    /** @return array{0: Link, 1: User} */
    protected function resolveLink(string $alias, ?string $host = null): array
    {
        $link = Link::resolveByAlias($alias, $host);
        abort_if(!$link, 404);
        $creator = $link->user;
        abort_if(!$creator, 404);
        return [$link, $creator];
    }

    /**
     * Buyer-facing order shape. When `includeDownloads` is set and the order
     * is paid, digital items carry a `download_url` pointing at the existing
     * secured web download route (token-gated, redirects to the file).
     *
     * @return array<string, mixed>
     */
    protected function orderShape(ProductOrder $order, bool $includeDownloads = false): array
    {
        $paid = $order->isPaid();

        return [
            'id'                => $order->id,
            'status'            => $order->status,
            'status_label'      => $order->statusLabel(),
            'is_paid'           => $paid,
            'currency'          => $order->currency,
            'subtotal_cents'    => (int) $order->subtotal_cents,
            'contains_physical' => (bool) $order->contains_physical,
            'contains_digital'  => (bool) $order->contains_digital,
            'public_token'      => $order->public_token,
            'thank_you_message' => trim((string) ($order->metadata['thank_you_message'] ?? '')) ?: 'Thanks for your purchase! 🎉',
            'paid_at'           => optional($order->paid_at)->toIso8601String(),
            'created_at'        => optional($order->created_at)->toIso8601String(),
            'creator'           => $order->relationLoaded('creator') && $order->creator ? [
                'id'     => $order->creator->id,
                'name'   => $order->creator->name,
                'handle' => $order->creator->handle,
                'avatar' => \App\Support\PublicStorageUrl::resolve($order->creator->avatar),
            ] : null,
            'items' => $order->items->map(function ($it) use ($order, $paid, $includeDownloads) {
                $isDigital = $it->isDigital();
                return [
                    'id'               => $it->id,
                    'name'             => $it->name,
                    'quantity'         => (int) $it->quantity,
                    'unit_price_cents' => (int) $it->unit_price_cents,
                    'line_total_cents' => $it->lineTotalCents(),
                    'currency'         => $it->currency,
                    'product_type'     => $it->product_type,
                    'image_url'        => $it->image_url,
                    'download_url'     => ($includeDownloads && $paid && $isDigital && $it->digital_file_url)
                        ? route('store.download', ['order' => $order->id, 'item' => $it->id, 'token' => $order->public_token])
                        : null,
                ];
            })->all(),
        ];
    }

    /**
     * Owner-facing order shape — adds the buyer ref for the orders dashboard.
     *
     * @return array<string, mixed>
     */
    protected function ownerOrderShape(ProductOrder $order): array
    {
        return array_merge($this->orderShape($order), [
            'buyer' => $order->relationLoaded('buyer') && $order->buyer ? [
                'id'     => $order->buyer->id,
                'name'   => $order->buyer->name,
                'handle' => $order->buyer->handle,
                'avatar' => \App\Support\PublicStorageUrl::resolve($order->buyer->avatar),
            ] : null,
            'fulfilled_at' => optional($order->fulfilled_at)->toIso8601String(),
        ]);
    }
}
