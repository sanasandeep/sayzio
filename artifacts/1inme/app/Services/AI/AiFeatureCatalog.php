<?php

namespace App\Services\AI;

/**
 * Read-only catalog of AI feature keys and their friendly labels.
 *
 * AI usage is billed straight from the coin wallet (each wallet
 * transaction is tagged with `meta.feature`). This catalog is the
 * neutral home for the feature key list + labels used by reporting
 * surfaces (e.g. the admin AI usage report). It previously lived on the
 * retired `AiCreditTransaction` model.
 */
class AiFeatureCatalog
{
    /** Known AI features for filtering / reporting. */
    public const FEATURES = ['mind', 'persona', 'companion', 'coach', 'ask_coach', 'voice_stt', 'voice_llm', 'voice_tts', 'card_scan', 'resume_import', 'resume_tailor', 'inbox_agent', 'brand_kit', 'qr_art', 'marketing_strategist', 'marketing_strategist.chat', 'marketing_strategist.report', 'ai_staff_billing', 'ai_staff_contacts', 'ai_staff_general', 'competitor_teardown', 'biolink_builder'];

    /** Friendly labels for ledger / reporting surfaces. */
    public const FEATURE_LABELS = [
        'mind'          => 'AI Note Summarizer',
        'persona'       => 'Persona Generator',
        'companion'     => 'AI Chat',
        'coach'         => 'AI Growth Coach',
        'ask_coach'     => 'Account Assistant',
        'voice_stt'     => 'Voice — Transcription',
        'voice_llm'     => 'Voice — Reasoning',
        'voice_tts'     => 'Voice — Speech',
        'card_scan'     => 'Card / Brochure Scan',
        'resume_import' => 'Resume — Import',
        'resume_tailor' => 'Resume — Tailor to Job',
        'inbox_agent'   => 'Inbox Agent',
        'brand_kit'     => 'Brand Kit',
        'qr_art'        => 'QR — AI Art',
        'marketing_strategist' => 'Marketing Strategist',
        'marketing_strategist.chat' => 'Marketing Strategist — Chat',
        'marketing_strategist.report' => 'Marketing Strategist — Report',
        'ai_staff_billing'      => 'AI Staff — Billing',
        'ai_staff_contacts'     => 'AI Staff — Contacts',
        'ai_staff_general'      => 'AI Staff — General Assistant',
        'competitor_teardown'   => 'Competitor Biolink Teardown',
        'biolink_builder'       => 'AI Link in Bio Builder',
    ];

    public static function featureLabel(?string $feature): string
    {
        if (!$feature) return '—';

        // Exact match on a known feature key.
        if (isset(self::FEATURE_LABELS[$feature])) {
            return self::FEATURE_LABELS[$feature];
        }

        // Spend is often tagged with a dotted sub-reason (e.g.
        // `companion.chat`, `persona.profile`, `coach.suggest`). Resolve the
        // product label from the base key so reporting rows show the new tool
        // name, with the sub-reason appended for context.
        if (str_contains($feature, '.')) {
            [$base, $sub] = explode('.', $feature, 2);
            if (isset(self::FEATURE_LABELS[$base])) {
                $subLabel = ucwords(str_replace(['_', '.'], ' ', $sub));
                return self::FEATURE_LABELS[$base] . ' — ' . $subLabel;
            }
        }

        return ucwords(str_replace('_', ' ', $feature));
    }
}
