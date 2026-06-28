<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\User;

/**
 * Presentation helpers for the standalone "Brand / Press Kit" link type
 * (Task #2663).
 *
 * A Brand / Press Kit page turns a creator's saved AI Brand Kit
 * ({@see BrandKit}) into a polished, shareable public page: logo downloads,
 * copy-able colour swatches, the font pairing, brand voice, a press
 * boilerplate, socials and a contact line. AI generation is out of scope —
 * the page *consumes* a kit the user already saved.
 *
 * This class owns three things:
 *   1. A small catalogue of chrome "themes" (the surrounding light/dark page
 *      surface). The brand's own palette + fonts always drive the accents,
 *      swatches and headings; the theme only sets the page background and
 *      text colours so the kit reads well on light or dark.
 *   2. {@see prefillFromKit()} — seed the per-link config from a saved
 *      BrandKit at creation / first editor open.
 *   3. {@see normalize()} — sanitise an editor submission before it is saved
 *      into `links.settings['brand_kit']`.
 *
 * Mirrors the {@see PaidPageTemplates} pattern (catalogue + DEFAULT_ID +
 * ids()/exists()/get()) so the controllers and views stay familiar.
 */
class BrandKitPageTemplates
{
    public const DEFAULT_ID = 'studio';

    /** Section keys the owner can toggle on/off, with their default state. */
    public const SECTION_DEFAULTS = [
        'logos'   => true,
        'colors'  => true,
        'fonts'   => true,
        'voice'   => true,
        'about'   => true,
        'socials' => true,
        'contact' => true,
    ];

    private const MAX_LOGOS   = 8;
    private const MAX_SOCIALS = 12;

    /**
     * The chrome themes. Each entry sets only the page surface; brand accents
     * come from the kit palette at render time.
     *
     * @return array<string, array{name:string, tagline:string, scheme:string, page_bg:string, text:string, text_muted:string, card_bg:string, card_border:string, radius:string}>
     */
    public static function all(): array
    {
        return [
            'studio' => [
                'name'       => 'Studio',
                'tagline'    => 'Deep, premium dark canvas.',
                'scheme'     => 'dark',
                'page_bg'    => '#0b0f1a',
                'text'       => '#f4f6fb',
                'text_muted' => 'rgba(244,246,251,0.62)',
                'card_bg'    => 'rgba(255,255,255,0.05)',
                'card_border'=> 'rgba(255,255,255,0.12)',
                'radius'     => '20px',
            ],
            'daylight' => [
                'name'       => 'Daylight',
                'tagline'    => 'Clean, bright and minimal.',
                'scheme'     => 'light',
                'page_bg'    => '#f6f7fb',
                'text'       => '#0f172a',
                'text_muted' => 'rgba(15,23,42,0.58)',
                'card_bg'    => '#ffffff',
                'card_border'=> 'rgba(15,23,42,0.08)',
                'radius'     => '18px',
            ],
            'editorial' => [
                'name'       => 'Editorial',
                'tagline'    => 'Warm paper, magazine feel.',
                'scheme'     => 'light',
                'page_bg'    => '#f3efe7',
                'text'       => '#211c16',
                'text_muted' => 'rgba(33,28,22,0.55)',
                'card_bg'    => '#fffdf9',
                'card_border'=> 'rgba(33,28,22,0.10)',
                'radius'     => '14px',
            ],
            'mono' => [
                'name'       => 'Mono',
                'tagline'    => 'Stark, neutral, type-forward.',
                'scheme'     => 'light',
                'page_bg'    => '#ffffff',
                'text'       => '#111111',
                'text_muted' => 'rgba(17,17,17,0.55)',
                'card_bg'    => '#fafafa',
                'card_border'=> 'rgba(17,17,17,0.12)',
                'radius'     => '6px',
            ],
        ];
    }

    /** @return list<string> */
    public static function ids(): array
    {
        return array_keys(self::all());
    }

    public static function exists(?string $id): bool
    {
        return $id !== null && array_key_exists($id, self::all());
    }

    /**
     * Resolve a theme by id, falling back to the default. The returned array
     * carries an `id` key for convenience.
     *
     * @return array<string, mixed>
     */
    public static function get(?string $id): array
    {
        $all = self::all();
        $id  = self::exists($id) ? $id : self::DEFAULT_ID;
        return ['id' => $id] + $all[$id];
    }

    /**
     * Seed the per-link Brand / Press Kit config from a saved BrandKit. When
     * no kit exists yet the page still gets a coherent, editable shell so the
     * editor and public render never blow up — the owner can fill it in or
     * generate a kit later.
     *
     * @return array<string, mixed>
     */
    public static function prefillFromKit(?BrandKit $kit, User $owner): array
    {
        $config = is_array($kit?->config) ? $kit->config : [];

        $palette = is_array($config['palette'] ?? null) ? $config['palette'] : [];
        $fonts   = is_array($config['fonts'] ?? null) ? $config['fonts'] : [];
        $voice   = is_array($config['voice'] ?? null) ? $config['voice'] : [];

        // A logo-sourced kit carries the logo URL under `source`.
        $logos = [];
        $source = is_array($config['source'] ?? null) ? $config['source'] : [];
        if (($source['type'] ?? '') === 'logo' && !empty($source['value'])) {
            $logos[] = ['label' => 'Primary logo', 'url' => (string) $source['value']];
        }

        $taglines = [];
        foreach ((array) ($config['taglines'] ?? []) as $t) {
            $t = trim((string) $t);
            if ($t !== '') {
                $taglines[] = $t;
            }
        }

        return self::normalize([
            'kit_id'      => $kit?->id,
            'brand_name'  => (string) ($kit?->name ?: $owner->name ?: 'My Brand'),
            'tagline'     => $taglines[0] ?? '',
            'about'       => (string) ($config['bio'] ?? ''),
            'boilerplate' => '',
            'palette'     => [
                'primary'   => (string) ($palette['primary'] ?? '#3d6bff'),
                'secondary' => (string) ($palette['secondary'] ?? ''),
                'accent'    => (string) ($palette['accent'] ?? ''),
                'neutrals'  => array_values((array) ($palette['neutrals'] ?? [])),
            ],
            'fonts'       => [
                'heading' => (string) ($fonts['heading'] ?? 'Inter'),
                'body'    => (string) ($fonts['body'] ?? 'Inter'),
            ],
            'voice'       => [
                'tone'        => (string) ($voice['tone'] ?? ''),
                'descriptors' => array_values((array) ($voice['descriptors'] ?? [])),
            ],
            'taglines'    => $taglines,
            'logos'       => $logos,
            'socials'     => [],
            'contact_email' => '',
            'contact_url'   => '',
            'template'    => self::DEFAULT_ID,
            'sections'    => self::SECTION_DEFAULTS,
        ]);
    }

    /**
     * Sanitise a config array (from prefill or an editor submission) into the
     * canonical shape persisted under `links.settings['brand_kit']`.
     *
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    public static function normalize(array $in): array
    {
        $palette = is_array($in['palette'] ?? null) ? $in['palette'] : [];
        $neutrals = [];
        foreach ((array) ($palette['neutrals'] ?? []) as $n) {
            $h = self::hex($n);
            if ($h !== null && count($neutrals) < 8) {
                $neutrals[] = $h;
            }
        }

        $fonts = is_array($in['fonts'] ?? null) ? $in['fonts'] : [];
        $voice = is_array($in['voice'] ?? null) ? $in['voice'] : [];

        $descriptors = [];
        foreach ((array) ($voice['descriptors'] ?? []) as $d) {
            $d = trim((string) $d);
            if ($d !== '' && count($descriptors) < 12) {
                $descriptors[] = mb_substr($d, 0, 40);
            }
        }

        $taglines = [];
        foreach ((array) ($in['taglines'] ?? []) as $t) {
            $t = trim((string) $t);
            if ($t !== '' && count($taglines) < 6) {
                $taglines[] = mb_substr($t, 0, 160);
            }
        }

        $logos = self::normalizeLinkList($in['logos'] ?? [], self::MAX_LOGOS, true);
        $socials = self::normalizeLinkList($in['socials'] ?? [], self::MAX_SOCIALS, false);

        $sections = [];
        $inSections = is_array($in['sections'] ?? null) ? $in['sections'] : [];
        foreach (self::SECTION_DEFAULTS as $key => $default) {
            $sections[$key] = array_key_exists($key, $inSections)
                ? filter_var($inSections[$key], FILTER_VALIDATE_BOOLEAN)
                : (bool) $default;
        }

        $email = trim((string) ($in['contact_email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        $contactUrl = self::url($in['contact_url'] ?? '');
        $kitId = isset($in['kit_id']) && is_numeric($in['kit_id']) ? (int) $in['kit_id'] : null;

        return [
            'kit_id'        => $kitId,
            'brand_name'    => mb_substr(trim((string) ($in['brand_name'] ?? '')), 0, 120) ?: 'My Brand',
            'tagline'       => mb_substr(trim((string) ($in['tagline'] ?? '')), 0, 200),
            'about'         => mb_substr(trim((string) ($in['about'] ?? '')), 0, 2000),
            'boilerplate'   => mb_substr(trim((string) ($in['boilerplate'] ?? '')), 0, 4000),
            'palette'       => [
                'primary'   => self::hex($palette['primary'] ?? null) ?? '#3d6bff',
                'secondary' => self::hex($palette['secondary'] ?? null) ?? '',
                'accent'    => self::hex($palette['accent'] ?? null) ?? '',
                'neutrals'  => $neutrals,
            ],
            'fonts'         => [
                'heading' => self::fontName($fonts['heading'] ?? null) ?: 'Inter',
                'body'    => self::fontName($fonts['body'] ?? null) ?: 'Inter',
            ],
            'voice'         => [
                'tone'        => mb_substr(trim((string) ($voice['tone'] ?? '')), 0, 240),
                'descriptors' => $descriptors,
            ],
            'taglines'      => $taglines,
            'logos'         => $logos,
            'socials'       => $socials,
            'contact_email' => $email,
            'contact_url'   => $contactUrl,
            'template'      => self::exists($in['template'] ?? null) ? (string) $in['template'] : self::DEFAULT_ID,
            'sections'      => $sections,
        ];
    }

    /**
     * Normalise a list of {label, url} rows. When $requireUrl is true a row
     * is dropped unless it carries a valid http(s) URL (logos must point at a
     * downloadable asset); otherwise a row survives on either a label or url.
     *
     * @param  mixed  $rows
     * @return list<array{label:string, url:string}>
     */
    private static function normalizeLinkList($rows, int $max, bool $requireUrl): array
    {
        $out = [];
        foreach ((array) $rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $label = mb_substr(trim((string) ($row['label'] ?? '')), 0, 80);
            $url   = self::url($row['url'] ?? '');
            if ($requireUrl && $url === '') {
                continue;
            }
            if (!$requireUrl && $label === '' && $url === '') {
                continue;
            }
            $out[] = ['label' => $label, 'url' => $url];
            if (count($out) >= $max) {
                break;
            }
        }
        return $out;
    }

    /** Validate a 3/6/8-digit hex colour, returning a normalised #rrggbb-ish string or null. */
    private static function hex($value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (!str_starts_with($value, '#')) {
            $value = '#' . $value;
        }
        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value)
            ? strtolower($value)
            : null;
    }

    /** A safe http(s) URL (used for logos, socials, contact link) or empty string. */
    private static function url($value): string
    {
        $value = trim((string) $value);
        if ($value === '' || mb_strlen($value) > 2048) {
            return '';
        }
        return filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value)
            ? $value
            : '';
    }

    /** Constrain a Google-Fonts-style family name to a safe character set. */
    private static function fontName($value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/[^A-Za-z0-9 \-]/', '', $value) ?? '';
        return mb_substr($value, 0, 60);
    }
}
