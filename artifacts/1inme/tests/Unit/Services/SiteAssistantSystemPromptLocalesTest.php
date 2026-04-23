<?php

namespace Tests\Unit\Services;

use App\Services\AI\SiteAssistantSettings;
use Tests\TestCase;

/**
 * Unit coverage for the localised assistant system prompt. Mirrors
 * {@see SiteAssistantTopupLabelTest} — regressions in either
 * {@see SiteAssistantSettings::normalizeSystemPromptLocales()} or
 * {@see SiteAssistantSettings::systemPromptFor()} silently steer
 * non-English visitors with the English system prompt instead of the
 * locale-tuned one admins configured.
 */
class SiteAssistantSystemPromptLocalesTest extends TestCase
{
    // ── normalizeSystemPromptLocales ────────────────────────────────────

    public function test_normalize_canonicalises_locale_codes_to_bcp47_form(): void
    {
        $out = SiteAssistantSettings::normalizeSystemPromptLocales([
            'FR'    => 'Tu es un assistant.',
            'pt_br' => 'Você é um assistente.',
            'EN-gb' => 'You are an assistant.',
        ]);

        $this->assertSame(['en-GB', 'fr', 'pt-BR'], array_keys($out));
        $this->assertSame('Tu es un assistant.',    $out['fr']);
        $this->assertSame('Você é um assistente.',  $out['pt-BR']);
        $this->assertSame('You are an assistant.',  $out['en-GB']);
    }

    public function test_normalize_strips_blank_prompts_and_invalid_codes(): void
    {
        $out = SiteAssistantSettings::normalizeSystemPromptLocales([
            'fr'          => '   ',
            ''            => 'x',
            '123'         => 'x',
            'this-is-not' => 'x',
            'es'          => 'Eres un asistente.',
        ]);

        $this->assertSame(['es' => 'Eres un asistente.'], $out);
    }

    public function test_normalize_caps_at_50_locale_entries(): void
    {
        $primaries = ['aa','ab','ae','af','ak','am','an','ar','as','av',
            'ay','az','ba','be','bg','bh','bi','bm','bn','bo','br','bs',
            'ca','ce','ch','co','cr','cs','cu','cv','cy','da','de','dv',
            'dz','ee','el','en','eo','es','et','eu','fa','ff','fi','fj',
            'fo','fr','fy','ga','gd','gl','gn','gu','gv','ha'];

        $in = [];
        foreach ($primaries as $code) $in[$code] = 'Prompt-' . $code;

        $out = SiteAssistantSettings::normalizeSystemPromptLocales($in);
        $this->assertCount(50, $out);
    }

    public function test_normalize_truncates_each_prompt_to_8000_characters(): void
    {
        // System prompts can be long but we still cap to mirror the
        // default `system_prompt` field budget. Multibyte input proves
        // mb_substr usage so we don't slice mid-codepoint.
        $long = str_repeat('é', 12000);
        $out  = SiteAssistantSettings::normalizeSystemPromptLocales(['fr' => $long]);

        $this->assertArrayHasKey('fr', $out);
        $this->assertSame(8000, mb_strlen($out['fr']));
        $this->assertSame(str_repeat('é', 8000), $out['fr']);
    }

    // ── systemPromptFor ──────────────────────────────────────────────────

    public function test_system_prompt_picks_exact_locale_match(): void
    {
        $cfg = [
            'system_prompt'         => 'You are an assistant.',
            'system_prompt_locales' => [
                'fr'    => 'Tu es un assistant.',
                'pt-BR' => 'Você é um assistente.',
            ],
        ];

        $this->assertSame('Tu es un assistant.',   SiteAssistantSettings::systemPromptFor($cfg, 'fr'));
        $this->assertSame('Você é um assistente.', SiteAssistantSettings::systemPromptFor($cfg, 'pt-BR'));
    }

    public function test_system_prompt_falls_back_to_primary_subtag_match(): void
    {
        $cfg = [
            'system_prompt'         => 'You are an assistant.',
            'system_prompt_locales' => ['fr' => 'Tu es un assistant.'],
        ];

        $this->assertSame(
            'Tu es un assistant.',
            SiteAssistantSettings::systemPromptFor($cfg, 'fr-CA')
        );
    }

    public function test_system_prompt_falls_back_to_default_when_locale_missing(): void
    {
        $cfg = [
            'system_prompt'         => 'You are an assistant.',
            'system_prompt_locales' => ['fr' => 'Tu es un assistant.'],
        ];

        $this->assertSame(
            'You are an assistant.',
            SiteAssistantSettings::systemPromptFor($cfg, 'de-DE')
        );
    }

    public function test_system_prompt_falls_back_when_accept_language_is_missing(): void
    {
        $cfg = [
            'system_prompt'         => 'You are an assistant.',
            'system_prompt_locales' => ['fr' => 'Tu es un assistant.'],
        ];

        $this->assertSame('You are an assistant.', SiteAssistantSettings::systemPromptFor($cfg, ''));
        $this->assertSame('You are an assistant.', SiteAssistantSettings::systemPromptFor($cfg, null));
    }
}
