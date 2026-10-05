<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\RestaurantTable;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Support\KitchenBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * The screen somebody props up next to the pass.
 *
 * Sana, 2026-10-05: "Need another dashboard like kitchen.... whowing all
 * table names if exists with current order status... aurto refresh also".
 *
 * ---- Why this is not a filter on the orders board ----------------------
 *
 * The orders board is for the owner: newest first, money on every row,
 * dates and exports. A kitchen screen answers "what do I cook next, and
 * who has been waiting?" -- oldest first, no prices, nothing to click
 * except the move a ticket can actually make.
 *
 * Those are opposite sorts and opposite contents, so this is its own
 * screen. It reads the same rows and the same status map, so the two can
 * never disagree about what an order IS.
 *
 * ---- Auto refresh, done so it can be left running all service ----------
 *
 * The board polls its own JSON rather than reloading the page. A full
 * reload on a wall screen loses scroll position and flashes white every
 * ten seconds, which is how a kitchen ends up turning the thing off.
 *
 * One request is in flight at a time. A slow response on a busy Saturday
 * must not queue a second, then a third -- a board that falls behind by
 * stacking requests is worse than one that skips a beat.
 */
class MenuKitchenController extends Controller
{
    /** @return array{0: object, 1: class-string, 2: bool} menu, order class, has tables */
    protected function resolve(Link $link, string $kind): array
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        if ($kind === 'restaurant') {
            abort_unless($link->type === Link::TYPE_RESTAURANT_MENU, 404);
            $menu = RestaurantMenu::where('link_id', $link->id)->first();
            abort_if(! $menu, 404);

            // "if exists" is his own hedge, and the right one. A takeaway
            // page with no tables gets the same board grouped by order
            // rather than a screen that is blank because of a feature they
            // never set up.
            return [$menu, RestaurantOrder::class, RestaurantTable::where('menu_id', $menu->id)->exists()];
        }

        abort_unless($link->type === Link::TYPE_STORE_MENU, 404);
        $menu = StoreMenu::where('link_id', $link->id)->first();
        abort_if(! $menu, 404);

        // A store has no concept of a table, so it is always the order
        // grouping -- same screen, same code path, no second implementation
        // to drift.
        return [$menu, StoreOrder::class, false];
    }

    public function board(Request $request, Link $link, string $kind = 'restaurant')
    {
        [$menu, $model, $hasTables] = $this->resolve($link, $kind);

        return view('user.links.kitchen', [
            'link'      => $link,
            'menu'      => $menu,
            'kind'      => $kind,
            'board'     => KitchenBoard::of($menu, $model, $hasTables),
            'pollUrl'   => route('user.links.'.$kind.'.kitchen.poll', $link),
            'statusUrl' => $kind === 'restaurant'
                ? route('user.links.restaurant.orders.status', ['link' => $link, 'order' => '__ID__'])
                : route('user.links.store.orders.status', ['link' => $link, 'order' => '__ID__']),
            'ordersUrl' => route('user.links.'.$kind.'.orders', $link),
            'warnAfter' => KitchenBoard::WARN_AFTER,
            'lateAfter' => KitchenBoard::LATE_AFTER,
        ]);
    }

    public function poll(Request $request, Link $link, string $kind = 'restaurant'): JsonResponse
    {
        [$menu, $model, $hasTables] = $this->resolve($link, $kind);

        // Deliberately the WHOLE board rather than a delta since a cursor.
        //
        // A kitchen screen is small -- tables and open tickets, never
        // thousands of rows -- and a delta cannot express a removal: an
        // order that was completed on somebody's phone has no "updated
        // since" row to send, so the ticket would sit on the wall forever.
        // Sending the whole board makes a stale ticket impossible.
        return response()->json(['data' => KitchenBoard::of($menu, $model, $hasTables)]);
    }
}
