<?php

namespace Tests\Unit\Services;

use App\Services\AI\SiteAssistantSettings;
use Tests\TestCase;

/**
 * Unit coverage for the localised starter prompt buttons shown on
 * first open of the chat widget. Mirrors {@see SiteAssistantTopupLabelTest}
 * — regressions in either {@see SiteAssistantSettings::normalizeStarterPromptsLocales()}
 * or {@see SiteAssistantSettings::starterPromptsFor()} silently revert
 * non-English visitors back to the English starter set.
 */
class SiteAssistantStarterPromptsLocalesTest extends TestCase
{
    // ── normalizeStarterPromptsLocales ──────────────────────────────────

    public function test_normalize_canonicalises_locale_codes_to_bcp47_form(): void
    {
        $out = SiteAssistantSettings::normalizeStarterPromptsLocales([
            'FR'    => ['Que puis-je faire ?'],
            'pt_br' => ['O que posso fazer?'],
            'EN-gb' => ['What can I do?'],
        ]);

        $this->assertSame(['en-GB', 'fr', 'pt-BR'], array_keys($out));
        $this->assertSame(['Que puis-je faire ?'], $out['fr']);
        $this->assertSame(['O que posso fazer?'],  $out['pt-BR']);
        $this->assertSame(['What can I do?'],      $out['en-GB']);
    }

    public function test_normalize_strips_blank_prompts_invalid_codes_and_empty_lists(): void
    {
        $out = SiteAssistantSettings::normalizeStarterPromptsLocales([
            'fr'          => ['   ', ''],            // all blank → locale dropped
            ''            => ['x'],                   // missing code → dropped
            '123'         => ['x'],                   // bad BCP-47 → dropped
            'this-is-not' => ['x'],                   // bad BCP-47 → dropped
            'de'          => 'not-an-array',          // wrong shape → dropped
            'es'          => ['Hola', '  ', 'Adiós'], // blanks stripped, kept
        ]);

        $this->assertSame(['es' => ['Hola', 'Adiós']], $out);
    }

    public function test_normalize_caps_each_locale_at_10_prompts(): void
    {
        // Each locale's prompt list is bounded so admins can't blow up
        // the bubble layout (or the settings blob) by pasting hundreds.
        $list = [];
        for ($i = 1; $i <= 25; $i++) $list[] = "Prompt $i";

        $out = SiteAssistantSettings::normalizeStarterPromptsLocales(['fr' => $list]);
        $this->assertCount(10, $out['fr']);
        $this->assertSame('Prompt 1',  $out['fr'][0]);
        $this->assertSame('Prompt 10', $out['fr'][9]);
    }

    public function test_normalize_caps_at_50_locale_entries(): void
    {
        $primaries = ['aa','ab','ae','af','ak','am','an','ar','as','av',
            'ay','az','ba','be','bg','bh','bi','bm','bn','bo','br','bs',
            'ca','ce','ch','co','cr','cs','cu','cv','cy','da','de','dv',
            'dz','ee','el','en','eo','es','et','eu','fa','ff','fi','fj',
            'fo','fr','fy','ga','gd','gl','gn','gu','gv','ha'];

        $in = [];
        foreach ($primaries as $code) $in[$code] = ['Prompt-' . $code];

        $out = SiteAssistantSettings::normalizeStarterPromptsLocales($in);
        $this->assertCount(50, $out);
    }

    public function test_normalize_truncates_each_prompt_to_200_characters(): void
    {
        $long = str_repeat('é', 350);
        $out  = SiteAssistantSettings::normalizeStarterPromptsLocales(['fr' => [$long]]);

        $this->assertSame(200, mb_strlen($out['fr'][0]));
        $this->assertSame(str_repeat('é', 200), $out['fr'][0]);
    }

    // ── starterPromptsFor ────────────────────────────────────────────────

    public function test_starter_prompts_pick_exact_locale_match(): void
    {
        $cfg = [
            'starter_prompts'         => ['What can I do?', 'How does pricing work?'],
            'starter_prompts_locales' => [
                'fr'    => ['Que puis-je faire ?', 'Comment ça marche ?'],
                'pt-BR' => ['O que posso fazer?'],
            ],
        ];

        $this->assertSame(
            ['Que puis-je faire ?', 'Comment ça marche ?'],
            SiteAssistantSettings::starterPromptsFor($cfg, 'fr')
        );
        $this->assertSame(
            ['O que posso fazer?'],
            SiteAssistantSettings::starterPromptsFor($cfg, 'pt-BR')
        );
    }

    public function test_starter_prompts_fall_back_to_primary_subtag_match(): void
    {
        $cfg = [
            'starter_prompts'         => ['What can I do?'],
            'starter_prompts_locales' => [
                'fr' => ['Que puis-je faire ?'],
            ],
        ];

        $this->assertSame(
            ['Que puis-je faire ?'],
            SiteAssistantSettings::starterPromptsFor($cfg, 'fr-CA')
        );
    }

    public function test_starter_prompts_fall_back_to_default_when_locale_missing(): void
    {
        $cfg = [
            'starter_prompts'         => ['What can I do?'],
            'starter_prompts_locales' => ['fr' => ['Que puis-je faire ?']],
        ];

        $this->assertSame(
            ['What can I do?'],
            SiteAssistantSettings::starterPromptsFor($cfg, 'de-DE')
        );
    }

    public function test_starter_prompts_fall_back_when_accept_language_is_missing(): void
    {
        $cfg = [
            'starter_prompts'         => ['What can I do?'],
            'starter_prompts_locales' => ['fr' => ['Que puis-je faire ?']],
        ];

        $this->assertSame(['What can I do?'], SiteAssistantSettings::starterPromptsFor($cfg, ''));
        $this->assertSame(['What can I do?'], SiteAssistantSettings::starterPromptsFor($cfg, null));
    }
}
