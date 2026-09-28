<?php

namespace App\Modules\User\Support;

/**
 * What the guest sees the moment their order goes through. One definition
 * for both menu types.
 *
 * Sana, 2026-09-28: "i need option to link any page url foir order
 * confirmation page... also if no page, then option should be there for
 * message like thank you... make it flexible".
 *
 * ---- Why three modes and not a URL field -------------------------------
 *
 * The small build is one nullable `confirm_url`: set it and we redirect,
 * leave it blank and we don't. That reads fine until someone wants to keep
 * the guest on the menu but say something of their own -- a pickup window,
 * a UPI handle, "we'll call you in ten minutes". With a lone URL field the
 * only way to say that is to go build a web page for it, which is not a
 * thing a tiffin service is going to do.
 *
 * So the setting is what HAPPENS, and each mode carries what it needs:
 *
 *   bill      the estimated bill and the live status (what it did before)
 *   message   a line the owner writes, in place of the bill
 *   url       the owner's own page, which the guest is sent to
 *
 * ---- A mode is only real when its payload is ---------------------------
 *
 * `resolve()` falls back to `bill` when the chosen mode has nothing to show
 * -- message mode with an empty message, url mode with a blank or unusable
 * URL. A half-filled setting should leave the guest on the confirmation
 * they have always had, never on a blank sheet or a dead link. The editor
 * can still hold the half-filled state while someone is typing in it; it is
 * the PAGE that refuses to act on it.
 *
 * ---- The URL --------------------------------------------------------
 *
 * http/https only, and parsed rather than pattern-matched, so `javascript:`
 * and friends cannot ride in through a settings field and fire on a public
 * page. Where it points is the owner's business -- it is their menu, their
 * customers and their own site -- so there is no allowlist beyond that.
 *
 * ---- WhatsApp is not one of these modes ---------------------------------
 *
 * The WhatsApp handoff opens in its own tab and is configured by its own
 * number, so it happens under every mode rather than competing with them.
 * A menu can send the guest to a thank-you page AND hand the order to the
 * kitchen's WhatsApp; those are two different people receiving two
 * different things.
 */
class MenuConfirmation
{
    /** The estimated bill and the live status. What the page always did. */
    public const BILL = 'bill';

    /** A line the owner writes, in place of the bill. */
    public const MESSAGE = 'message';

    /** The owner's own page. */
    public const URL = 'url';

    public const DEFAULT = self::BILL;

    /** Long enough for a pickup window and a phone number, short enough to read. */
    public const MESSAGE_MAX = 600;

    public const HEADLINE_MAX = 80;

    /**
     * The modes an owner can pick between, in the order they are offered.
     *
     * @return array<string, array{label: string, hint: string}>
     */
    public static function modes(): array
    {
        return [
            self::BILL => [
                'label' => 'Show the estimated bill',
                'hint'  => 'The itemised total and the live order status. This is the default.',
            ],
            self::MESSAGE => [
                'label' => 'Show a message you write',
                'hint'  => 'Replaces the bill with your own words -- a pickup time, a payment handle, a thank you.',
            ],
            self::URL => [
                'label' => 'Send them to your own page',
                'hint'  => 'The guest is taken to a page you host once the order goes through.',
            ],
        ];
    }

    /** A mode string validated against the catalog, falling back to the default. */
    public static function mode(?string $raw): string
    {
        $key = is_string($raw) ? trim($raw) : '';

        return array_key_exists($key, self::modes()) ? $key : self::DEFAULT;
    }

    /**
     * A usable absolute http/https URL, or null. Parsed rather than
     * pattern-matched: this value ends up in a navigation on a public page,
     * so a scheme we did not intend must not survive the trip.
     */
    public static function url(?string $raw): ?string
    {
        $value = is_string($raw) ? trim($raw) : '';
        if ($value === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }
        if ((string) parse_url($value, PHP_URL_HOST) === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_URL) ? $value : null;
    }

    /** The owner's message, trimmed and capped, or null when there isn't one. */
    public static function message(?string $raw): ?string
    {
        $value = is_string($raw) ? trim($raw) : '';

        return $value === '' ? null : mb_substr($value, 0, self::MESSAGE_MAX);
    }

    /** The owner's heading, trimmed and capped, or null to keep the page's own. */
    public static function headline(?string $raw): ?string
    {
        $value = is_string($raw) ? trim($raw) : '';

        return $value === '' ? null : mb_substr($value, 0, self::HEADLINE_MAX);
    }

    /**
     * What the page should actually do, given a menu's settings.
     *
     * The returned mode is the one that can be carried out: a mode whose
     * payload is missing comes back as `bill` rather than as itself, so the
     * page has no half-configured state to guard against.
     *
     * @param  array<string, mixed>  $settings
     * @return array{mode: string, url: ?string, message: ?string, headline: ?string}
     */
    public static function resolve(array $settings): array
    {
        $raw = $settings['confirmation'] ?? [];
        $raw = is_array($raw) ? $raw : [];

        $url      = self::url(is_string($raw['url'] ?? null) ? $raw['url'] : null);
        $message  = self::message(is_string($raw['message'] ?? null) ? $raw['message'] : null);
        $headline = self::headline(is_string($raw['headline'] ?? null) ? $raw['headline'] : null);

        $mode = self::mode(is_string($raw['mode'] ?? null) ? $raw['mode'] : null);

        if ($mode === self::URL && $url === null) {
            $mode = self::BILL;
        }
        if ($mode === self::MESSAGE && $message === null) {
            $mode = self::BILL;
        }

        return ['mode' => $mode, 'url' => $url, 'message' => $message, 'headline' => $headline];
    }

    /**
     * The settings blob to store, from whatever the editor or the API sent.
     * Unlike `resolve()`, this keeps the owner's chosen mode as chosen --
     * someone who picks "send them to my page" and then saves before typing
     * the URL should find that choice still selected when they come back,
     * not silently reset to the default.
     *
     * @param  array<string, mixed>  $input
     * @return array{mode: string, url: ?string, message: ?string, headline: ?string}
     */
    public static function sanitize(array $input): array
    {
        return [
            'mode'     => self::mode(is_string($input['mode'] ?? null) ? $input['mode'] : null),
            'url'      => self::url(is_string($input['url'] ?? null) ? $input['url'] : null),
            'message'  => self::message(is_string($input['message'] ?? null) ? $input['message'] : null),
            'headline' => self::headline(is_string($input['headline'] ?? null) ? $input['headline'] : null),
        ];
    }
}
