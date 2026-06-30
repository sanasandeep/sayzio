<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\RestaurantMenu;
use App\Modules\User\Models\RestaurantOrder;
use App\Modules\User\Models\StoreMenu;
use App\Modules\User\Models\StoreOrder;

/**
 * Builds the optional "Send order via WhatsApp" click-to-chat link for a
 * confirmed restaurant order (Task #3062) or store order request (Task #3072).
 *
 * This is purely a pre-filled `wa.me` URL opened from the customer's own
 * device — there is no WhatsApp Business API, no server-sent messages, and no
 * message templates. The same message format is produced server-side and
 * surfaced to both the web public page and the mobile app so it stays
 * consistent. The builder is shared across the restaurant + store page types
 * via duck-typed union params (both menus expose `settings['whatsapp_number']`
 * and both orders expose items/customer/total).
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

    /** The normalized WhatsApp number configured on a menu, or null. */
    public static function numberFor(RestaurantMenu|StoreMenu $menu): ?string
    {
        $raw = $menu->settings['whatsapp_number'] ?? null;

        return self::normalizeNumber(is_string($raw) ? $raw : null);
    }

    /**
     * Build the WhatsApp number + human-readable message + click-to-chat URL
     * for a confirmed order, or null when no WhatsApp number is configured.
     *
     * @return array{number:string,message:string,url:string}|null
     */
    public static function build(RestaurantMenu|StoreMenu $menu, RestaurantOrder|StoreOrder $order, ?string $linkTitle = null): ?array
    {
        $number = self::numberFor($menu);
        if (!$number) {
            return null;
        }

        $message = self::message($order, $linkTitle);

        return [
            'number'  => $number,
            'message' => $message,
            'url'     => 'https://wa.me/' . $number . '?text=' . rawurlencode($message),
        ];
    }

    /** A short, human-friendly order reference derived from the public token. */
    public static function reference(RestaurantOrder|StoreOrder $order): string
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
}
