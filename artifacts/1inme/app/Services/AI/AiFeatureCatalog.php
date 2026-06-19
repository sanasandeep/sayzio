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
    public const FEATURES = ['mind', 'persona', 'companion', 'coach', 'ask_coach', 'voice_stt', 'voice_llm', 'voice_tts', 'card_scan', 'resume_import', 'resume_tailor'];

    /** Friendly labels for ledger / reporting surfaces. */
    public const FEATURE_LABELS = [
        'mind'          => 'AI Mind',
        'persona'       => 'AI Persona',
        'companion'     => 'AI Companion',
        'coach'         => 'AI Coach',
        'ask_coach'     => 'Ask Coach',
        'voice_stt'     => 'Voice — Transcription',
        'voice_llm'     => 'Voice — Reasoning',
        'voice_tts'     => 'Voice — Speech',
        'card_scan'     => 'Card / Brochure Scan',
        'resume_import' => 'Resume — Import',
        'resume_tailor' => 'Resume — Tailor to Job',
    ];

    public static function featureLabel(?string $feature): string
    {
        if (!$feature) return '—';
        return self::FEATURE_LABELS[$feature] ?? ucwords(str_replace('_', ' ', $feature));
    }
}
