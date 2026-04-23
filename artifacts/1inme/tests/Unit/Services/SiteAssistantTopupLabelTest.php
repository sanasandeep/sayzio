<?php

namespace Tests\Unit\Services;

use App\Services\AI\SiteAssistantSettings;
use Tests\TestCase;

/**
 * Unit coverage for the localised CTA-label resolver on the
 * low-balance bubble. A regression in either
 * {@see SiteAssistantSettings::normalizeTopupLabelLocales()} or
 * {@see SiteAssistantSettings::topupLabelFor()} silently flips
 * non-English visitors back to the built-in "Top up" / "See plans"
 * labels — admins would only find out once customers complain, so we
 * pin every branch here.
 */
class SiteAssistantTopupLabelTest extends TestCase
{
    // ── normalizeTopupLabelLocales ──────────────────────────────────────

    public function test_normalize_canonicalises_locale_codes_to_bcp47_form(): void
    {
        // Mixed-case primary subtag, lowercase region, and underscore
        // separator must all collapse to the canonical `xx-YY` shape so
        // visitor Accept-Language matching is case/separator-insensitive.
        $out = SiteAssistantSettings::normalizeTopupLabelLocales([
            'FR'      => 'Recharger',
            'pt_br'   => 'Recarregar',
            'EN-gb'   => 'Top up now',
        ]);

        $this->assertSame(['en-GB', 'fr', 'pt-BR'], array_keys($out));
        $this->assertSame('Recharger',   $out['fr']);
        $this->assertSame('Recarregar',  $out['pt-BR']);
        $this->assertSame('Top up now',  $out['en-GB']);
    }

    public function test_normalize_strips_blank_labels_and_invalid_codes(): void
    {
        // Blank/whitespace-only labels and codes that don't look like
        // a BCP-47 tag are dropped silently — we'd rather fall back to
        // the default than render a broken (or worse, attacker-injected)
        // label on the bubble.
        $out = SiteAssistantSettings::normalizeTopupLabelLocales([
            'fr'           => '   ',
            ''             => 'Top up',
            '123'          => 'Nope',
            'this-is-not'  => 'Nope',
            'es'           => 'Recargar',
        ]);

        $this->assertSame(['es' => 'Recargar'], $out);
    }

    public function test_normalize_caps_at_50_locale_entries(): void
    {
        // The settings blob ships in every admin page render; a runaway
        // form post must not let it grow without bound. The cap is 50.
        $primaries = ['aa','ab','ae','af','ak','am','an','ar','as','av',
            'ay','az','ba','be','bg','bh','bi','bm','bn','bo','br','bs',
            'ca','ce','ch','co','cr','cs','cu','cv','cy','da','de','dv',
            'dz','ee','el','en','eo','es','et','eu','fa','ff','fi','fj',
            'fo','fr','fy','ga','gd','gl','gn','gu','gv','ha'];

        $in = [];
        foreach ($primaries as $code) $in[$code] = 'Label-' . $code;

        $out = SiteAssistantSettings::normalizeTopupLabelLocales($in);
        $this->assertCount(50, $out);
    }

    public function test_normalize_truncates_each_label_to_60_characters(): void
    {
        // The bubble layout assumes a short button label; a 1KB string
        // would smash the chat UI on mobile. Cap at 60 chars per label.
        $long = str_repeat('é', 200); // multibyte to prove mb_substr usage
        $out = SiteAssistantSettings::normalizeTopupLabelLocales(['fr' => $long]);

        $this->assertArrayHasKey('fr', $out);
        $this->assertSame(60, mb_strlen($out['fr']));
        $this->assertSame(str_repeat('é', 60), $out['fr']);
    }

    // ── topupLabelFor ────────────────────────────────────────────────────

    public function test_topup_label_returns_empty_when_no_overrides_configured(): void
    {
        // Empty default + empty locales = empty string. The runtime
        // treats this as "use the audience-specific built-in default".
        $this->assertSame('', SiteAssistantSettings::topupLabelFor([
            'low_balance_topup_label'         => '',
            'low_balance_topup_label_locales' => [],
        ], 'fr-CA'));
    }

    public function test_topup_label_returns_admin_default_when_no_locale_overrides(): void
    {
        // Admin set a single global label but no per-locale overrides:
        // every visitor — regardless of Accept-Language — sees that one.
        $this->assertSame('Add credits', SiteAssistantSettings::topupLabelFor([
            'low_balance_topup_label'         => 'Add credits',
            'low_balance_topup_label_locales' => [],
        ], 'fr-CA'));
    }

    public function test_topup_label_picks_exact_locale_match(): void
    {
        $cfg = [
            'low_balance_topup_label'         => 'Add credits',
            'low_balance_topup_label_locales' => [
                'fr'    => 'Recharger',
                'pt-BR' => 'Recarregar',
            ],
        ];

        $this->assertSame('Recharger',  SiteAssistantSettings::topupLabelFor($cfg, 'fr'));
        $this->assertSame('Recarregar', SiteAssistantSettings::topupLabelFor($cfg, 'pt-BR'));
    }

    public function test_topup_label_falls_back_to_primary_subtag_match(): void
    {
        // Visitor advertises `fr-CA` but admin only configured `fr` —
        // the primary-subtag fallback must still pick the French label.
        $cfg = [
            'low_balance_topup_label'         => 'Add credits',
            'low_balance_topup_label_locales' => [
                'fr' => 'Recharger',
            ],
        ];

        $this->assertSame('Recharger', SiteAssistantSettings::topupLabelFor($cfg, 'fr-CA'));
    }

    public function test_topup_label_falls_back_to_admin_default_when_locale_missing(): void
    {
        // Visitor speaks German, admin only localised French — fall
        // back to the global admin default, not to the built-in copy.
        $cfg = [
            'low_balance_topup_label'         => 'Add credits',
            'low_balance_topup_label_locales' => [
                'fr' => 'Recharger',
            ],
        ];

        $this->assertSame('Add credits', SiteAssistantSettings::topupLabelFor($cfg, 'de-DE'));
    }

    public function test_topup_label_falls_back_when_accept_language_is_missing(): void
    {
        // No Accept-Language header (e.g. server-side render or curl
        // probe) must not trigger locale picking — return the default.
        $cfg = [
            'low_balance_topup_label'         => 'Add credits',
            'low_balance_topup_label_locales' => [
                'fr' => 'Recharger',
            ],
        ];

        $this->assertSame('Add credits', SiteAssistantSettings::topupLabelFor($cfg, ''));
        $this->assertSame('Add credits', SiteAssistantSettings::topupLabelFor($cfg, null));
    }
}
