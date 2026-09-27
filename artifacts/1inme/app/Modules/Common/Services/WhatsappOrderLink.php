<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\ServiceBookingRequest;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;

/**
 * Builds the optional "Send via WhatsApp" click-to-chat link for a confirmed
 * restaurant order (Task #3062), store order request (Task #3072) or service
 * booking request (Task #3102).
 *
 * This is purely a pre-filled `wa.me` URL opened from the customer's own
 * device — there is no WhatsApp Business API, no server-sent messages, and no
 * message templates. The same message format is produced server-side and
 * surfaced to both the web public page and the mobile app so it stays
 * consistent. The builder is shared across the restaurant + store + service
 * booking page types via duck-typed union params (every config exposes
 * `settings['whatsapp_number']` and every order/request exposes
 * items/customer/total).
 */
class WhatsappOrderLink
{
    /**
     * Normalize a raw phone number into the digits-only international form
     * `wa.me` expects (no `+`, spaces, dashes or other punctuation). Returns
     * null when the result isn't a plausible international number so a blank
     * or junk value simply disables the feature.
     */
    public static function normalizeNumber(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // E.164 allows up to 15 digits; require enough to include a country code.
        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    /** The normalized WhatsApp number configured on a menu/config, or null. */
    public static function numberFor(RestaurantMenu|StoreMenu|ServiceBooking $menu): ?string
    {
        $raw = $menu->settings['whatsapp_number'] ?? null;

        return self::normalizeNumber(is_string($raw) ? $raw : null);
    }

    /**
     * Build the WhatsApp number + human-readable message + click-to-chat URL
     * for a confirmed order / booking request, or null when no WhatsApp number
     * is configured.
     *
     * @return array{number:string,message:string,url:string}|null
     */
    public static function build(RestaurantMenu|StoreMenu|ServiceBooking $menu, RestaurantOrder|StoreOrder|ServiceBookingRequest $order, ?string $linkTitle = null): ?array
    {
        $number = self::numberFor($menu);
        if (!$number) {
            return null;
        }

        $message = $order instanceof ServiceBookingRequest
            ? self::bookingMessage($menu, $order, $linkTitle)
            : self::message($order, $linkTitle, $menu);

        return [
            'number'  => $number,
            'message' => $message,
            'url'     => 'https://wa.me/' . $number . '?text=' . rawurlencode($message),
        ];
    }

    /** A short, human-friendly reference derived from the public token. */
    public static function reference(RestaurantOrder|StoreOrder|ServiceBookingRequest $order): string
    {
        $token = str_replace('-', '', (string) $order->public_token);

        return '#' . strtoupper(substr($token, 0, 6));
    }

    /**
     * Format a confirmed order into the pre-filled WhatsApp message body:
     * order reference, table (if scanned), customer name, an itemized list
     * with quantities, the total, and any kitchen note.
     */
    public static function message(RestaurantOrder|StoreOrder $order, ?string $linkTitle = null, RestaurantMenu|StoreMenu|null $menu = null): string
    {
        $lines = [];
        $lines[] = $linkTitle ? "New order · {$linkTitle}" : 'New order';
        $lines[] = 'Order ' . self::reference($order);

        // `table_label` only exists on restaurant orders; store orders carry a
        // free-form customer contact instead. Both are read defensively so the
        // shared formatter works for either model.
        if (!empty($order->table_label)) {
            $lines[] = 'Table: ' . $order->table_label;
        }
        if ($order->customer_name) {
            $lines[] = 'Name: ' . $order->customer_name;
        }
        if (!empty($order->customer_contact)) {
            $lines[] = 'Contact: ' . $order->customer_contact;
        }

        $money = fn ($n) => \App\Modules\User\Support\MenuMoney::plain($n, $order->currency);

        $lines[] = '';
        foreach ($order->items as $item) {
            // With the line prices in, the number at the bottom is one the
            // person reading this on their phone can check.
            $lines[] = $item->quantity . '× ' . $item->name . ' · ' . $money($item->line_total);
        }

        $lines[] = '';

        // THE BUG THIS FIXES: this line said "Total" and printed the
        // SUBTOTAL. Tax and coupons arrived after this message format did,
        // and nothing came back to update it -- so every order with GST on
        // or a discount code applied told the restaurant a smaller number
        // than the guest owed, and the restaurant charged it. The owner's
        // email notification and the booking version of this message both
        // already used `total ?: subtotal`; only this one was left behind.
        $discount = (float) ($order->discount_amount ?? 0);
        $tax      = (float) ($order->tax_amount ?? 0);
        $total    = (float) ($order->total ?: $order->subtotal);

        // The breakdown is only shown when there IS one, so a plain order
        // still reads as three lines rather than a receipt.
        if ($discount > 0 || $tax > 0) {
            $lines[] = 'Subtotal: ' . $money($order->subtotal);

            if ($discount > 0) {
                $label = $order->coupon_code ? ('Discount (' . $order->coupon_code . ')') : 'Discount';
                $lines[] = $label . ': -' . $money($discount);
            }
            if ($tax > 0) {
                // The order row carries the tax AMOUNT but not its NAME --
                // the menu owns that, and "GST" reading as "Tax" on an
                // Indian restaurant's order is the kind of small wrongness
                // that makes an owner distrust the whole message.
                $taxLabel = ($menu instanceof RestaurantMenu) ? $menu->taxLabel() : 'Tax';
                $lines[] = $taxLabel . ': ' . $money($tax)
                    . (($order->tax_inclusive ?? false) ? ' (included)' : '');
            }
        }

        $lines[] = 'Total: ' . $money($total);

        if ($order->customer_note) {
            $lines[] = '';
            $lines[] = 'Note: ' . $order->customer_note;
        }

        return implode("\n", $lines);
    }

    /**
     * Format a service booking request into the pre-filled WhatsApp message
     * body: booking reference, the requested time slot (in the provider's
     * timezone), customer name + contact, an itemized service list, the
     * estimated total, and any note.
     */
    public static function bookingMessage(ServiceBooking $config, ServiceBookingRequest $request, ?string $linkTitle = null): string
    {
        $lines = [];
        $lines[] = $linkTitle ? "Booking request · {$linkTitle}" : 'Booking request';
        $lines[] = 'Booking ' . self::reference($request);

        if ($request->slot_start) {
            $tz = $config->effectiveTimezone();
            $lines[] = 'When: ' . \Carbon\Carbon::parse($request->slot_start)->setTimezone($tz)->format('D, M j · g:i A');
        }
        if ($request->customer_name) {
            $lines[] = 'Name: ' . $request->customer_name;
        }
        if (!empty($request->customer_phone)) {
            $lines[] = 'Phone: ' . $request->customer_phone;
        }
        if (!empty($request->customer_email)) {
            $lines[] = 'Email: ' . $request->customer_email;
        }

        $money = fn ($n) => \App\Modules\User\Support\MenuMoney::plain($n, $request->currency);

        $lines[] = '';
        foreach ($request->items as $item) {
            $lines[] = $item->quantity . '× ' . $item->name . ' · ' . $money($item->line_total);
        }

        $lines[] = '';
        // This one already read `total ?: subtotal` -- it is only routed
        // through the shared formatter so there is no hand-written money
        // format left in this file for the next one to be copied from.
        $lines[] = 'Estimated total: ' . $money((float) ($request->total ?: $request->subtotal));

        if ($request->customer_note) {
            $lines[] = '';
            $lines[] = 'Note: ' . $request->customer_note;
        }

        return implode("\n", $lines);
    }
}
