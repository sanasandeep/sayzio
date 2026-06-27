<?php

namespace App\Modules\User\Services\Inbox;

use App\Modules\User\Models\InboxThread;
use App\Modules\User\Models\Workspace;

/**
 * Typed accessor for the per-workspace Inbox Agent configuration. Stored
 * inside the workspace's generic `settings` JSON column under the
 * `inbox_agent` key (mirrors the existing `post_approval` pattern) so we
 * don't add a table just for a handful of knobs.
 *
 * Shape (settings.inbox_agent):
 *   [
 *     'ai_triage'            => bool,        // LLM triage vs rule-based
 *     'tone'                 => string,      // see TONES
 *     'persona'              => string,      // free-text creator voice
 *     'signature'            => string,      // appended to AI replies
 *     'autopilot_enabled'    => bool,
 *     'autopilot_categories' => string[],    // subset of InboxThread::CATEGORIES
 *     'confidence_threshold' => float,       // 0..1, autopilot send gate
 *   ]
 */
class InboxAgentSettings
{
    /** Selectable reply tones. `auto` lets the model match the inbound vibe. */
    public const TONES = [
        'auto'         => 'Match the sender',
        'friendly'     => 'Friendly',
        'casual'       => 'Casual',
        'professional' => 'Professional',
        'formal'       => 'Formal',
        'enthusiastic' => 'Enthusiastic',
    ];

    /** Categories that should never be eligible for autopilot. */
    public const AUTOPILOT_FORBIDDEN_CATEGORIES = ['spam'];

    public const MIN_THRESHOLD = 0.5;
    public const MAX_THRESHOLD = 0.99;

    public static function defaults(): array
    {
        return [
            'ai_triage'            => true,
            'tone'                 => 'auto',
            'persona'              => '',
            'signature'            => '',
            'autopilot_enabled'    => false,
            'autopilot_categories' => [],
            'confidence_threshold' => 0.8,
        ];
    }

    /** Resolve the effective (defaults-merged, normalized) config. */
    public static function for(Workspace $ws): array
    {
        $raw = (array) (($ws->settings ?? [])['inbox_agent'] ?? []);
        return self::normalize($raw + self::defaults());
    }

    /** Persist a (partial) config back onto the workspace. Returns effective config. */
    public static function save(Workspace $ws, array $input): array
    {
        $clean = self::normalize($input + self::defaults());

        $settings = (array) ($ws->settings ?? []);
        $settings['inbox_agent'] = $clean;
        $ws->settings = $settings;
        $ws->save();

        return $clean;
    }

    /** Clamp / whitelist every field so persisted state is always valid. */
    public static function normalize(array $cfg): array
    {
        $tone = (string) ($cfg['tone'] ?? 'auto');
        if (!array_key_exists($tone, self::TONES)) {
            $tone = 'auto';
        }

        $cats = array_values(array_intersect(
            array_map('strval', (array) ($cfg['autopilot_categories'] ?? [])),
            array_diff(InboxThread::CATEGORIES, self::AUTOPILOT_FORBIDDEN_CATEGORIES),
        ));

        $threshold = (float) ($cfg['confidence_threshold'] ?? 0.8);
        $threshold = max(self::MIN_THRESHOLD, min(self::MAX_THRESHOLD, $threshold));

        return [
            'ai_triage'            => (bool) ($cfg['ai_triage'] ?? true),
            'tone'                 => $tone,
            'persona'              => trim((string) ($cfg['persona'] ?? '')),
            'signature'            => trim((string) ($cfg['signature'] ?? '')),
            'autopilot_enabled'    => (bool) ($cfg['autopilot_enabled'] ?? false),
            'autopilot_categories' => $cats,
            'confidence_threshold' => round($threshold, 2),
        ];
    }

    /** Human label for the configured tone. */
    public static function toneLabel(string $tone): string
    {
        return self::TONES[$tone] ?? self::TONES['auto'];
    }
}
