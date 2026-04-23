<?php

namespace Tests\Unit\Services;

use App\Services\AI\SiteAssistantSettings;
use Tests\TestCase;

/**
 * Unit coverage for the localised greeting bubble. Mirrors
 * {@see SiteAssistantTopupLabelTest} — a regression in either
 * {@see SiteAssistantSettings::normalizeGreetingLocales()} or
 * {@see SiteAssistantSettings::greetingFor()} silently flips
 * non-English visitors back to the default English greeting and
 * admins would only find out from customer complaints.
 */
class SiteAssistantGreetingLocalesTest extends TestCase
{
    // ── normalizeGreetingLocales ────────────────────────────────────────

    public function test_normalize_canonicalises_locale_codes_to_bcp47_form(): void
    {
        $out = SiteAssistantSettings::normalizeGreetingLocales([
            'FR'    => 'Bonjour',
            'pt_br' => 'Olá',
            'EN-gb' => 'Hello there',
        ]);

        $this->assertSame(['en-GB', 'fr', 'pt-BR'], array_keys($out));
        $this->assertSame('Bonjour',     $out['fr']);
        $this->assertSame('Olá',         $out['pt-BR']);
        $this->assertSame('Hello there', $out['en-GB']);
    }

    public function test_normalize_strips_blank_greetings_and_invalid_codes(): void
    {
        $out = SiteAssistantSettings::normalizeGreetingLocales([
            'fr'          => '   ',
            ''            => 'Hi',
            '123'         => 'Nope',
            'this-is-not' => 'Nope',
            'es'          => 'Hola',
        ]);

        $this->assertSame(['es' => 'Hola'], $out);
    }

    public function test_normalize_caps_at_50_locale_entries(): void
    {
        $primaries = ['aa','ab','ae','af','ak','am','an','ar','as','av',
            'ay','az','ba','be','bg','bh','bi','bm','bn','bo','br','bs',
            'ca','ce','ch','co','cr','cs','cu','cv','cy','da','de','dv',
            'dz','ee','el','en','eo','es','et','eu','fa','ff','fi','fj',
            'fo','fr','fy','ga','gd','gl','gn','gu','gv','ha'];

        $in = [];
        foreach ($primaries as $code) $in[$code] = 'Greeting-' . $code;

        $out = SiteAssistantSettings::normalizeGreetingLocales($in);
        $this->assertCount(50, $out);
    }

    public function test_normalize_truncates_each_greeting_to_500_characters(): void
    {
        // Greetings render in a small bubble; cap mirrors the default
        // English copy length budget. Multibyte input proves mb_substr.
        $long = str_repeat('é', 800);
        $out  = SiteAssistantSettings::normalizeGreetingLocales(['fr' => $long]);

        $this->assertArrayHasKey('fr', $out);
        $this->assertSame(500, mb_strlen($out['fr']));
        $this->assertSame(str_repeat('é', 500), $out['fr']);
    }

    // ── greetingFor ──────────────────────────────────────────────────────

    public function test_greeting_picks_exact_locale_match(): void
    {
        $cfg = [
            'greeting'         => 'Hi there',
            'greeting_locales' => [
                'fr'    => 'Bonjour',
                'pt-BR' => 'Olá',
            ],
        ];

        $this->assertSame('Bonjour', SiteAssistantSettings::greetingFor($cfg, 'fr'));
        $this->assertSame('Olá',     SiteAssistantSettings::greetingFor($cfg, 'pt-BR'));
    }

    public function test_greeting_falls_back_to_primary_subtag_match(): void
    {
        $cfg = [
            'greeting'         => 'Hi there',
            'greeting_locales' => ['fr' => 'Bonjour'],
        ];

        $this->assertSame('Bonjour', SiteAssistantSettings::greetingFor($cfg, 'fr-CA'));
    }

    public function test_greeting_falls_back_to_default_when_locale_missing(): void
    {
        $cfg = [
            'greeting'         => 'Hi there',
            'greeting_locales' => ['fr' => 'Bonjour'],
        ];

        $this->assertSame('Hi there', SiteAssistantSettings::greetingFor($cfg, 'de-DE'));
    }

    public function test_greeting_falls_back_when_accept_language_is_missing(): void
    {
        $cfg = [
            'greeting'         => 'Hi there',
            'greeting_locales' => ['fr' => 'Bonjour'],
        ];

        $this->assertSame('Hi there', SiteAssistantSettings::greetingFor($cfg, ''));
        $this->assertSame('Hi there', SiteAssistantSettings::greetingFor($cfg, null));
    }
}
