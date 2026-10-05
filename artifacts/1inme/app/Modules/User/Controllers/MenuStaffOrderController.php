<?php

namespace App\Modules\User\Controllers;

use App\Modules\Common\Controllers\PublicRestaurantController;
use App\Modules\Common\Controllers\PublicStoreController;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class MenuStaffOrderController extends Controller
{
    private function authorizeMenu(Request $request, Link $link, string $kind): void
    {
        abort_unless(in_array($kind, ['restaurant', 'store'], true), 404);
        abort_unless($link->user_id === workspace_owner_id(), 403);
        abort_unless($link->type === ($kind === 'restaurant' ? Link::TYPE_RESTAURANT_MENU : Link::TYPE_STORE_MENU), 404);
        abort_unless($link->isAccessible(), 404);
        $menu = $kind === 'restaurant' ? $link->restaurantMenu : $link->storeMenu;
        abort_unless($menu, 404);
        if ($kind === 'store') {
            abort_unless((bool) ($menu->settings['accepting_orders'] ?? true), 422, 'This store is not accepting requests right now.');
        }
        // Internal request state, set only after workspace ownership and
        // the route's links.edit permission have both been checked.
        $request->attributes->set('staff_order_link', (int) $link->id);
    }

    public function show(Request $request, Link $link, string $kind = 'restaurant')
    {
        $this->authorizeMenu($request, $link, $kind);

        return response()->view('common.'.$kind.'-menu', ['link' => $link, 'staffMode' => true])
            ->header('Cache-Control', 'private, no-store');
    }

    public function quote(Request $request, Link $link, string $kind = 'restaurant')
    {
        $this->authorizeMenu($request, $link, $kind);

        return app($kind === 'restaurant' ? PublicRestaurantController::class : PublicStoreController::class)
            ->quote($request, $link->alias);
    }

    public function place(Request $request, Link $link, string $kind = 'restaurant')
    {
        $this->authorizeMenu($request, $link, $kind);

        return app($kind === 'restaurant' ? PublicRestaurantController::class : PublicStoreController::class)
            ->placeOrder($request, $link->alias);
    }
}
