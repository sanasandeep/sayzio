<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Support\MenuOrderCode;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Finding an order from the square on a guest's phone.
 *
 * Sana, 2026-10-05: "QR code per order".
 *
 * ---- What this replaces ------------------------------------------------
 *
 * Reading a number off a phone and hunting it on a board. That is fine at
 * four orders an hour and falls apart at forty, in the two ways that cost
 * most: the wrong order handed over, or the right one handed over twice.
 *
 * ---- Lookup only; the move already had an endpoint ---------------------
 *
 * There is one method here and it reads. Moving an order along already has
 * a route that validates the transition server-side
 * (RestaurantMenuController::updateOrderStatus and its store twin), and a
 * second way to change a status is a second place for the two to disagree
 * about what "ready" means. So this hands the counter the order's id and
 * the URL to post to, and the move goes through the door that was already
 * there.
 *
 * For the same reason the allowed next statuses come from the model's own
 * STATUS_TRANSITIONS rather than being listed again here. The counter draws
 * the buttons the server would accept, so a button that cannot work is
 * never drawn -- and if the two ever did drift, the server still refuses.
 *
 * ---- Scoped to this menu, not to this code ----------------------------
 *
 * The code identifies an order globally, so the query is narrowed by the
 * menu behind this link before it is narrowed by anything else. Scanning a
 * neighbouring restaurant's order at your own counter answers "not found",
 * which is both true and the only safe thing to say: the alternative is one
 * canteen reading another's customer names off its own screen.
 */
class MenuOrderScanController extends Controller
{
    /** The menu behind this link, and the order class that belongs to it. */
    protected function resolve(Link $link, string $kind): array
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        if ($kind === 'restaurant') {
            abort_unless($link->type === Link::TYPE_RESTAURANT_MENU, 404);
            $menu = RestaurantMenu::where('link_id', $link->id)->first();
            abort_if(! $menu, 404);

            return [$menu, RestaurantOrder::class];
        }

        abort_unless($link->type === Link::TYPE_STORE_MENU, 404);
        $menu = StoreMenu::where('link_id', $link->id)->first();
        abort_if(! $menu, 404);

        return [$menu, StoreOrder::class];
    }

    /**
     * The order behind a scanned code.
     *
     * A code that is not an order code at all and a code for an order on
     * somebody else's menu both answer the same 404. Telling the two apart
     * would tell a stranger with a scanner which codes exist.
     */
    public function show(Request $request, Link $link, string $code, string $kind = 'restaurant')
    {
        [$menu, $model] = $this->resolve($link, $kind);

        $token = MenuOrderCode::toToken($code);
        if ($token === '') {
            return response()->json([
                'error' => ['message' => 'That is not an order code.', 'code' => 'not_an_order_code'],
            ], 404);
        }

        $order = $model::with('items')
            ->where('menu_id', $menu->id)
            ->where('public_token', $token)
            ->first();

        if (! $order) {
            return response()->json([
                'error' => ['message' => 'No order with that code on this menu.', 'code' => 'not_found'],
            ], 404);
        }

        return response()->json(['data' => ['order' => $this->shape($order, $link, $kind)]]);
    }

    /**
     * What the counter needs to serve the person in front of it.
     *
     * Everything a staff member would otherwise go to the board for: who it
     * is, what they ordered, what it costs, and the buttons that work from
     * here. The board is still a click away for anything else.
     */
    protected function shape(RestaurantOrder|StoreOrder $order, Link $link, string $kind): array
    {
        $model = $order::class;
        $next  = $model::STATUS_TRANSITIONS[$order->status] ?? [];

        return [
            'id'            => $order->id,
            'code'          => MenuOrderCode::of($order->public_token),
            'token_number'  => $order->token_number,
            'status'        => $order->status,
            'status_label'  => $order->status_label,
            'table_label'   => $order->table_label ?? null,
            'customer_name' => $order->customer_name,
            'customer_note' => $order->customer_note,
            'currency'      => $order->currency,
            'total'         => $order->total,
            'placed_at'     => $order->created_at?->toIso8601String(),
            'items'         => $order->items->map(fn ($i) => [
                'name'       => $i->name,
                'quantity'   => $i->quantity,
                'line_total' => $i->line_total,
            ])->values(),
            // Drawn as buttons. Straight from the model, so the counter can
            // only offer moves the server would accept.
            'next_statuses' => array_values(array_map(fn ($s) => [
                'value' => $s,
                'label' => $model::STATUS_LABELS[$s] ?? $s,
            ], $next)),
            // The door that already exists, handed over rather than rebuilt
            // in JavaScript where it would drift from the routes file.
            'status_url'    => route('user.links.'.$kind.'.orders.status', [
                'link'  => $link->id,
                'order' => $order->id,
            ]),
        ];
    }
}
