<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\MenuOrderCoupon;
use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Support\MenuBulkOrder;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Taking a food coupon at the counter.
 *
 * Sana, 2026-10-04: "when coupoon id give, order of that 1 coupon is
 * completed or delivered... also alternatively, search order by phone no
 * also possible".
 *
 * ---- Redeeming is one row, atomically ----------------------------------
 *
 * Two staff on two phones scan the same coupon in the same second. A read
 * followed by a write lets both of them see "issued" and both serve lunch.
 * The update is conditional on the row still being `issued`, and the
 * number of rows it changed is the answer: one means this person just
 * redeemed it, zero means somebody else already did.
 *
 * ---- Why a redeemed coupon is not an error -----------------------------
 *
 * It is the most useful thing the counter can be told, and it is not a
 * failure of the request. So it comes back 200 with the coupon, its status
 * and when it was taken, and the screen says "already collected at 1:14pm"
 * rather than showing a red box with no information in it.
 */
class MenuCouponController extends Controller
{
    /** The menu behind this link, and the string the tables key on. */
    protected function resolve(Link $link, string $kind): array
    {
        abort_if($link->user_id !== workspace_owner_id(), 403);

        if ($kind === 'restaurant') {
            abort_unless($link->type === Link::TYPE_RESTAURANT_MENU, 404);
            $menu = RestaurantMenu::where('link_id', $link->id)->first();
            abort_if(! $menu, 404);

            return [$menu, MenuOrderCoupon::RESTAURANT];
        }

        abort_unless($link->type === Link::TYPE_STORE_MENU, 404);
        $menu = StoreMenu::where('link_id', $link->id)->first();
        abort_if(! $menu, 404);

        return [$menu, MenuOrderCoupon::STORE];
    }

    /**
     * Look a coupon up without taking it.
     *
     * Separate from redeeming because the counter wants to see what it is
     * before committing: a coupon for a dish that is not being served at
     * this counter is a question, not a redemption.
     */
    public function show(Request $request, Link $link, string $code, string $kind = 'restaurant')
    {
        [$menu, $type] = $this->resolve($link, $kind);

        $coupon = $this->find($menu->id, $type, $code);
        if (! $coupon) {
            return response()->json([
                'error' => ['message' => 'No coupon with that code on this menu.', 'code' => 'not_found'],
            ], 404);
        }

        return response()->json(['data' => ['coupon' => $this->shape($coupon, $type)]]);
    }

    /** Take it. */
    public function redeem(Request $request, Link $link, string $code, string $kind = 'restaurant')
    {
        [$menu, $type] = $this->resolve($link, $kind);

        $coupon = $this->find($menu->id, $type, $code);
        if (! $coupon) {
            return response()->json([
                'error' => ['message' => 'No coupon with that code on this menu.', 'code' => 'not_found'],
            ], 404);
        }

        if ($coupon->status === MenuOrderCoupon::VOID) {
            return response()->json(['data' => [
                'coupon'   => $this->shape($coupon, $type),
                'redeemed' => false,
                'message'  => 'This coupon was cancelled.',
            ]]);
        }

        // Conditional on the row still being issued: two phones scanning
        // the same code in the same second must not both serve lunch.
        $took = MenuOrderCoupon::where('id', $coupon->id)
            ->where('status', MenuOrderCoupon::ISSUED)
            ->update([
                'status'      => MenuOrderCoupon::REDEEMED,
                'redeemed_at' => now(),
                'redeemed_by' => $request->user()?->id,
                'updated_at'  => now(),
            ]);

        $coupon->refresh();

        if ($took === 0) {
            return response()->json(['data' => [
                'coupon'   => $this->shape($coupon, $type),
                'redeemed' => false,
                'message'  => 'Already collected at '.$coupon->redeemed_at?->timezone(
                    $link->user?->effectiveTimezone() ?? \App\Support\PlatformTimezone::platformDefault()
                )?->format('g:i a').'.',
            ]]);
        }

        $this->closeIfDone($coupon, $type);

        return response()->json(['data' => [
            'coupon'   => $this->shape($coupon->fresh(), $type),
            'redeemed' => true,
            'message'  => 'Collected.',
        ]]);
    }

    /**
     * Orders for a phone number.
     *
     * Sana asked for this as an alternative to the code: somebody at the
     * counter who has lost their coupon is still a person with an order.
     */
    public function byPhone(Request $request, Link $link, string $kind = 'restaurant')
    {
        [$menu, $type] = $this->resolve($link, $kind);

        $digits = preg_replace('/[^0-9]/', '', (string) $request->query('phone', ''));
        if (strlen($digits) < 6) {
            return response()->json([
                'error' => ['message' => 'Type at least six digits of the phone number.', 'code' => 'too_short'],
            ], 422);
        }

        // Matched on the last ten digits, so a number saved with a country
        // code still answers to the number somebody reads out.
        $tail = substr($digits, -10);

        $model = $type === MenuOrderCoupon::RESTAURANT ? RestaurantOrder::class : StoreOrder::class;
        $orders = $model::with('items')
            ->where('menu_id', $menu->id)
            ->whereRaw("regexp_replace(coalesce(customer_phone, ''), '[^0-9]', '', 'g') like ?", ['%'.$tail])
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return response()->json(['data' => [
            'orders' => $orders->map(fn ($o) => [
                'id'           => $o->id,
                'token_number' => $o->token_number,
                'status'       => $o->status,
                'status_label' => $o->status_label,
                'customer_name' => $o->customer_name,
                'customer_phone' => $o->customer_phone,
                'total'        => $o->total,
                'currency'     => $o->currency,
                'created_at'   => $o->created_at?->toIso8601String(),
                'coupons'      => MenuBulkOrder::tally($type, $o->id),
            ])->values(),
        ]]);
    }

    protected function find(int $menuId, string $type, string $code): ?MenuOrderCoupon
    {
        $normalized = MenuOrderCoupon::normalize($code);
        if ($normalized === '') {
            return null;
        }

        return MenuOrderCoupon::where('menu_type', $type)
            ->where('menu_id', $menuId)
            ->where('code', $normalized)
            ->first();
    }

    /**
     * Move the order on when the last coupon is in.
     *
     * Sana: "when coupoon id give, order of that 1 coupon is completed or
     * delivered". One coupon completes one serving; the ORDER is finished
     * when nothing is outstanding.
     *
     * ---- Why this does not follow the status flow --------------------
     *
     * STATUS_TRANSITIONS only lets `new` go to `accepted`, so an order
     * whose every coupon has been handed over at the counter sat on `new`
     * forever -- which is what the first run of the test for this showed.
     * The flow is there to stop an illegal tap on the board; this is not a
     * tap, it is the record of food that has physically been given to two
     * hundred people. The last coupon going in IS completion, whatever
     * sequence of buttons nobody got round to pressing.
     *
     * The one thing it will not do is move an order out of a terminal
     * state: a cancelled order does not un-cancel because somebody scanned
     * a coupon from it.
     */
    protected function closeIfDone(MenuOrderCoupon $coupon, string $type): void
    {
        $tally = MenuBulkOrder::tally($type, $coupon->order_id);
        if ($tally['outstanding'] > 0) {
            return;
        }

        $model = $type === MenuOrderCoupon::RESTAURANT ? RestaurantOrder::class : StoreOrder::class;
        $order = $model::find($coupon->order_id);
        if (! $order) {
            return;
        }

        $done = $model::STATUS_COMPLETED;
        $terminal = [$done, $model::STATUS_CANCELLED];
        if (in_array($order->status, $terminal, true)) {
            return;
        }

        $order->update(['status' => $done]);
    }

    protected function shape(MenuOrderCoupon $coupon, string $type): array
    {
        $order = $coupon->order();

        return [
            'code'         => $coupon->display(),
            'item_name'    => $coupon->item_name,
            'status'       => $coupon->status,
            'redeemed_at'  => $coupon->redeemed_at?->toIso8601String(),
            'order_id'     => $coupon->order_id,
            'token_number' => $order?->token_number,
            'customer_name' => $order?->customer_name,
            'order_status' => $order?->status,
            'tally'        => MenuBulkOrder::tally($type, $coupon->order_id),
        ];
    }
}
