<?php

use App\Modules\Admin\Models\AppSetting;
use App\Services\AI\AiEngineSettings;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * One-time backfill: append any default AI models (full GPT-5 / GPT-4.1 /
 * GPT-4o chat lineup + embedding) that are missing from the saved
 * `ai.models` app setting, so the new models appear in the admin AI Engine
 * dropdowns on existing installs too.
 *
 * Non-destructive and idempotent: rows the admin already has (matched
 * case-insensitively by model name) are left untouched, only missing
 * defaults are appended. Fresh installs with no saved setting are skipped —
 * they already fall back to AiEngineSettings::defaultModels().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        try {
            $stored = AppSetting::get(AiEngineSettings::KEY_MODELS);
            if (!is_array($stored) || !$stored) {
                // Nothing saved yet — models() already serves the full defaults.
                return;
            }

            $existing = [];
            foreach ($stored as $m) {
                if (is_array($m) && !empty($m['name'])) {
                    $existing[strtolower(trim((string) $m['name']))] = true;
                }
            }

            $appended = false;
            foreach (AiEngineSettings::defaultModels() as $default) {
                if (!isset($existing[strtolower($default['name'])])) {
                    $stored[] = $default;
                    $appended = true;
                }
            }

            if ($appended) {
                AppSetting::put(AiEngineSettings::KEY_MODELS, array_values($stored));
            }
        } catch (\Throwable $e) {
            // Best-effort backfill; admins can add models manually if it fails.
            logger()->warning('AI default-model backfill skipped: ' . $e->getMessage());
        }
    }

    public function down(): void
    {
        // Additive-only data backfill; nothing to revert.
    }
};
