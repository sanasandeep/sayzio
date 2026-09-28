<?php

namespace App\Modules\User\Support;

/**
 * How an order reaches the person who placed it, and what that adds to the
 * bill. One definition for both menu types.
 *
 * Sana, 2026-09-23: "adding of address form, delivery options and other
 * settings configurable", and earlier, the charges a real restaurant puts
 * on a bill -- parcel, delivery, service.
 *
 * ---- Why charges are a list and not three fields -----------------------
 *
 * The obvious build is `parcel_charge`, `delivery_charge`,
 * `service_charge`: three columns, three inputs, three lines in the
 * calculator. It is also wrong within a week. A restaurant wants a packing
 * charge AND a delivery charge on the same delivery order; a store wants a
 * handling fee; somebody wants a late-night surcharge. Each of those is a
 * fourth field, a fourth input and a fourth branch.
 *
 * So a charge is a row: a name, an amount that is either flat or a
 * percentage, and the fulfilment modes it applies to. "Parcel charge,
 * Rs.20, takeaway and delivery" is data. Three hardcoded fields are the
 * same feature with a ceiling on it.
 *
 * ---- Where charges sit in the bill -------------------------------------
 *
 * subtotal -> discount -> tax -> charges.
 *
 * Charges are added AFTER tax, and a percentage charge is taken on the
 * discounted subtotal rather than on the taxed total. That is the
 * conventional reading of "10% service charge" -- ten percent of the food
 * bill. It is NOT a universal rule: in some jurisdictions GST applies to
 * the delivery charge as well, which would mean charging first and taxing
 * the sum. That is a tax question rather than a software one, so the order
 * is stated here rather than buried, and the estimate the guest sees says
 * "estimate" the way it always has.
 */
class MenuFulfilment
{
    /**
     * The ways an order can be handed over.
     *
     * `takeaway` is the same operation in both page types and is labelled
     * per type, because a store says "Pickup" and a restaurant says
     * "Takeaway" and using one word for both makes one of them read as a
     * translation.
     *
     * @var array<string, array{label: string, store_label: string, restaurant_only: bool, needs_address: bool}>
     */
    public const MODES = [
        'dine_in' => [
            'label'           => 'Dine in',
            'store_label'     => 'Dine in',
            'restaurant_only' => true,
            'needs_address'   => false,
        ],
        'takeaway' => [
            'label'           => 'Takeaway',
            'store_label'     => 'Pickup',
            'restaurant_only' => false,
            'needs_address'   => false,
        ],
        'delivery' => [
            'label'           => 'Delivery',
            'store_label'     => 'Delivery',
            'restaurant_only' => false,
            'needs_address'   => true,
        ],
    ];

    /** Charge shapes. A flat amount, or a percentage of the food bill. */
    public const TYPES = ['fixed', 'percent'];

    /**
     * The modes a menu offers, in a fixed order, already filtered to what
     * the page type supports.
     *
     * An empty choice is not a state this returns: a menu that has turned
     * everything off still has to let somebody order, so it falls back to
     * dine-in for a restaurant and pickup for a store. A guest looking at
     * an order page with no way to say how they want their food is worse
     * than a guess.
     *
     * @return array<int, string>
     */
    public static function modesFor(array $menuSettings, bool $isRestaurant): array
    {
        $chosen = $menuSettings['fulfilment_modes'] ?? null;

        $available = array_keys(array_filter(
            self::MODES,
            fn ($m) => $isRestaurant || ! $m['restaurant_only']
        ));

        // No choice saved: a restaurant is a dine-in place and a store is a
        // pickup place until its owner says otherwise. That is also exactly
        // what every existing menu does today, where none of this exists.
        if (! is_array($chosen)) {
            return $isRestaurant ? ['dine_in'] : ['takeaway'];
        }

        $modes = array_values(array_intersect($available, array_map('strval', $chosen)));

        return $modes ?: ($isRestaurant ? ['dine_in'] : ['takeaway']);
    }

    /** The label this page type uses for a mode. */
    public static function label(string $mode, bool $isRestaurant): string
    {
        $m = self::MODES[$mode] ?? null;

        if (! $m) {
            return ucfirst(str_replace('_', ' ', $mode));
        }

        return $isRestaurant ? $m['label'] : $m['store_label'];
    }

    /** Whether a mode needs somewhere to send the order. */
    public static function needsAddress(?string $mode): bool
    {
        return (bool) (self::MODES[$mode]['needs_address'] ?? false);
    }

    /**
     * The charges a menu has defined, cleaned.
     *
     * Anything malformed is dropped rather than repaired: a charge with no
     * name or a negative amount is not something to guess the intent of,
     * and a charge that silently becomes something else is worse than one
     * that does not appear.
     *
     * @return array<int, array{label: string, type: string, amount: float, modes: array<int, string>}>
     */
    public static function charges(array $menuSettings): array
    {
        $rows = $menuSettings['charges'] ?? [];

        if (! is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label  = trim((string) ($row['label'] ?? ''));
            $type   = in_array($row['type'] ?? null, self::TYPES, true) ? $row['type'] : 'fixed';
            $amount = round((float) ($row['amount'] ?? 0), 2);
            $modes  = is_array($row['modes'] ?? null)
                ? array_values(array_intersect(array_keys(self::MODES), array_map('strval', $row['modes'])))
                : [];

            if ($label === '' || $amount <= 0 || $modes === []) {
                continue;
            }
            // A percentage over 100 is a typo, not a charge.
            if ($type === 'percent' && $amount > 100) {
                continue;
            }

            $out[] = ['label' => $label, 'type' => $type, 'amount' => $amount, 'modes' => $modes];
        }

        return array_slice($out, 0, 8);
    }

    /**
     * The charges that apply to one order, with what each one comes to.
     *
     * @param  float  $base  the discounted subtotal -- the "food bill" a
     *                       percentage charge is conventionally taken on
     * @return array<int, array{label: string, amount: float}>
     */
    public static function applicable(array $menuSettings, ?string $mode, float $base): array
    {
        if ($mode === null) {
            return [];
        }

        $lines = [];

        foreach (self::charges($menuSettings) as $charge) {
            if (! in_array($mode, $charge['modes'], true)) {
                continue;
            }

            $amount = $charge['type'] === 'percent'
                ? round($base * ($charge['amount'] / 100), 2)
                : $charge['amount'];

            if ($amount > 0) {
                $lines[] = ['label' => $charge['label'], 'amount' => $amount];
            }
        }

        return $lines;
    }

    /**
     * Whether charges join the taxable base instead of landing after tax.
     *
     * Sana, 2026-09-28: "before tax or after tax.. can u make it optional
     * via settings without or with?"
     *
     * Both answers are correct somewhere. "10% service charge" ordinarily
     * means ten percent of the food bill, added at the end -- which is what
     * this did, and stays the default so no existing menu's totals move.
     * But in a number of jurisdictions GST applies to the delivery charge
     * as well, and there the charge has to be inside the taxed base or the
     * restaurant under-collects tax on every delivery.
     *
     * This is the owner's call to make, because it is their tax position.
     * The percentage itself is still taken on the discounted food bill
     * either way: a service charge computed on a figure that already
     * contains tax is not a reading anyone asked for.
     */
    public static function chargesBeforeTax(array $menuSettings): bool
    {
        return (bool) ($menuSettings['charges_before_tax'] ?? false);
    }

    /** What the applicable charges add up to. */
    public static function total(array $lines): float
    {
        return round(array_sum(array_column($lines, 'amount')), 2);
    }
}
