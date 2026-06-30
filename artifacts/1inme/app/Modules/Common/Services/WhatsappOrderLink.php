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
            : self::message($order, $linkTitle);

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
    public static function message(RestaurantOrder|StoreOrder $order, ?string $linkTitle = null): string
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

        $lines[] = '';
        foreach ($order->items as $item) {
            $lines[] = $item->quantity . '× ' . $item->name;
        }

        $lines[] = '';
        $lines[] = 'Total: ' . $order->currency . ' ' . number_format((float) $order->subtotal, 2);

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

        $lines[] = '';
        foreach ($request->items as $item) {
            $lines[] = $item->quantity . '× ' . $item->name;
        }

        $lines[] = '';
        $estimated = (float) ($request->total ?: $request->subtotal);
        $lines[] = 'Estimated total: ' . $request->currency . ' ' . number_format($estimated, 2);

        if ($request->customer_note) {
            $lines[] = '';
            $lines[] = 'Note: ' . $request->customer_note;
        }

        return implode("\n", $lines);
    }
}
