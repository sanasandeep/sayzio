<?php

namespace App\Modules\User\Support;

/**
 * How a price is written on a menu. One definition.
 *
 * Sana, 2026-09-23: "prices should have options like INR, ₹ USD or $ like
 * that... before or after number..."
 *
 * ---- What it looked like before ----------------------------------------
 *
 * The ISO code, a space, then the number with two decimals. Always. Four
 * copies of that one line -- two Blade closures on the public pages, two
 * JavaScript one-liners for the cart -- plus a fifth in the WhatsApp
 * message and a sixth in the owner's notification. Nobody could write
 * "₹120", which is how every menu in India prints a price.
 *
 * Six copies of a format is also how the cart total and the menu price
 * drift apart. They are one function now, and the JavaScript gets its
 * settings from this class rather than carrying its own idea of them.
 *
 * ---- Decimals ----------------------------------------------------------
 *
 * ZERO_DECIMAL exists because "¥1,200.00" is wrong, not a style choice:
 * those currencies have no minor unit. Beyond that, whether to print
 * `.00` is a real preference -- an Indian menu usually does not -- so it
 * is offered, defaulting to what each currency actually does.
 */
class MenuMoney
{
    /**
     * What appears next to the number.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const DISPLAYS = [
        'code' => [
            'label' => 'Code',
            'hint'  => 'INR 120 · USD 12. Unambiguous, and what every menu shows today.',
        ],
        'symbol' => [
            'label' => 'Symbol',
            'hint'  => 'Rs.120 · $12. Shorter, and what a printed card uses.',
        ],
        'none' => [
            'label' => 'Just the number',
            'hint'  => '120 · 12. For a card that states its currency once at the top.',
        ],
    ];

    public const DEFAULT_DISPLAY = 'code';

    /**
     * Which side of the number it sits on.
     *
     * @var array<string, array{label: string, hint: string}>
     */
    public const POSITIONS = [
        'before' => ['label' => 'Before', 'hint' => 'Rs.120 — English, most of Asia, the Americas.'],
        'after'  => ['label' => 'After',  'hint' => '120 Rs. — much of Europe.'],
    ];

    public const DEFAULT_POSITION = 'before';

    /**
     * Symbols for the currencies these pages are actually used in.
     *
     * Deliberately ASCII. A menu page renders in whatever font the creator
     * picked from the Google catalog, and a good many of those have no
     * glyph for the rupee sign or the naira -- the browser then falls back
     * mid-line and the price is set in a different typeface from the item
     * above it. A currency with no entry here falls back to its code,
     * which always renders.
     *
     * @var array<string, string>
     */
    public const SYMBOLS = [
        'USD' => '$',   'EUR' => 'EUR', 'GBP' => '£',    'INR' => 'Rs.',
        'JPY' => '¥',   'CNY' => '¥',    'AUD' => 'A$',   'CAD' => 'C$',
        'NZD' => 'NZ$', 'SGD' => 'S$',   'HKD' => 'HK$',  'ZAR' => 'R',
        'AED' => 'AED','SAR' => 'SAR', 'BRL' => 'R$',   'MXN' => 'MX$',
        'PHP' => 'PHP','THB' => 'THB', 'IDR' => 'Rp',   'MYR' => 'RM',
        'NGN' => 'NGN','KES' => 'KSh',  'PKR' => 'Rs.',  'BDT' => 'BDT',
        'LKR' => 'Rs.', 'NPR' => 'Rs.',  'TRY' => 'TRY', 'RUB' => 'RUB',
        'KRW' => 'KRW','VND' => 'VND', 'CHF' => 'CHF', 'SEK' => 'SEK',
        'NOK' => 'NOK','DKK' => 'DKK', 'PLN' => 'PLN', 'ILS' => 'ILS',
    ];

    /**
     * Currencies with no minor unit. Printing "¥1,200.00" is not a style
     * choice, it is wrong.
     *
     * @var array<int, string>
     */
    public const ZERO_DECIMAL = ['JPY', 'KRW', 'VND', 'IDR', 'CLP', 'ISK', 'HUF', 'PYG', 'RWF', 'UGX', 'VUV', 'XAF', 'XOF', 'XPF'];

    /**
     * Resolve the whole format from a menu's settings.
     *
     * Every key is optional, and an absent one gives exactly what these
     * pages printed before this existed -- the code, before the number,
     * with the decimals the currency actually has.
     *
     * @return array{code: string, display: string, position: string, decimals: int, prefix: string, suffix: string}
     */
    public static function resolve(?string $currency, array $menuSettings = []): array
    {
        $code     = strtoupper(trim((string) $currency)) ?: 'USD';
        $display  = isset(self::DISPLAYS[$menuSettings['price_display'] ?? null])
            ? $menuSettings['price_display'] : self::DEFAULT_DISPLAY;
        $position = isset(self::POSITIONS[$menuSettings['price_position'] ?? null])
            ? $menuSettings['price_position'] : self::DEFAULT_POSITION;

        $natural  = in_array($code, self::ZERO_DECIMAL, true) ? 0 : 2;
        // An explicit false means "no decimals". Anything else -- absent,
        // null, true -- means the currency decides, which is what every
        // existing menu gets.
        $decimals = (($menuSettings['price_decimals'] ?? null) === false) ? 0 : $natural;

        $token = match ($display) {
            'symbol' => self::SYMBOLS[$code] ?? $code.' ',
            'none'   => '',
            default  => $code.' ',
        };

        $token = rtrim($token);

        return [
            'code'     => $code,
            'display'  => $display,
            'position' => $position,
            'decimals' => $decimals,
            'prefix'   => ($position === 'before' && $token !== '') ? $token.(self::needsGap($token) ? ' ' : '') : '',
            'suffix'   => ($position === 'after'  && $token !== '') ? ' '.$token : '',
        ];
    }

    /**
     * Whether a leading token needs a space before the number.
     *
     * Decided by its LAST character, not by whether it contains letters:
     * "Rs." and "A$" end in punctuation and sit tight against the number
     * the way a printed card sets them ("Rs.120"), while "INR" and "KSh"
     * end in a letter and would otherwise run into the digits ("INR120").
     * A trailing token always takes a space.
     */
    private static function needsGap(string $token): bool
    {
        return preg_match('/[A-Za-z]$/', $token) === 1;
    }

    /**
     * Write an amount the way this menu writes amounts.
     *
     * @param  array  $fmt  the array from resolve()
     */
    public static function format(float|int|string|null $amount, array $fmt): string
    {
        return $fmt['prefix']
            . number_format((float) $amount, (int) $fmt['decimals'])
            . $fmt['suffix'];
    }

    /**
     * The same format for a model that carries its own currency, with no
     * menu settings to hand -- an order row in a WhatsApp message, or an
     * owner's notification. Falls back to the plain code form, which is
     * what those surfaces printed before.
     */
    public static function plain(float|int|string|null $amount, ?string $currency): string
    {
        return self::format($amount, self::resolve($currency));
    }
}
