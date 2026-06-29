<?php

namespace App\Services\AI;

use App\Modules\Admin\Models\AppSetting;

/**
 * Admin caps + tunables for the AI Mind feature. All knobs live in the
 * shared `app_settings` key/value store so a deployment can ship sane
 * defaults and operators can raise/lower them without a code change.
 *
 * Stored keys:
 *   ai.mind.caps                array — see capsDefault().
 *   ai.mind.chat_model          string — model used for "Test this Mind".
 *   ai.mind.embedding_model     string — embedding model used for chunks.
 *   ai.mind.default_doc_url     string — public URL to seed the platform mind from.
 */
class AiMindSettings
{
    public const KEY_CAPS            = 'ai.mind.caps';
    public const KEY_CHAT_MODEL      = 'ai.mind.chat_model';
    public const KEY_EMBEDDING_MODEL = 'ai.mind.embedding_model';
    public const KEY_OCR_MODEL       = 'ai.mind.ocr_model';

    /** Sensible defaults — operator can override per key in the admin UI. */
    public static function capsDefault(): array
    {
        return [
            'max_minds_per_user'     => 10,
            'max_sources_per_mind'   => 200,
            'max_docs_per_mind'      => 50,
            'max_doc_size_mb'        => 20,
            'max_links_per_mind'     => 50,
            'max_webhooks_per_mind'  => 25,
            'max_connectors_per_mind'=> 25,
            'max_link_refreshes_per_day' => 200,
            'link_refresh_min_minutes'   => 60,
            'max_chunks_per_source'  => 500,
            'max_text_chars'         => 200_000,
            'chunk_chars'            => 1200,
            'chunk_overlap_chars'    => 150,
            'max_ocr_pages_per_source' => 30,
        ];
    }

    /** @return array<string,int> */
    public static function caps(): array
    {
        $stored = AppSetting::get(self::KEY_CAPS);
        $defaults = self::capsDefault();
        if (!is_array($stored)) return $defaults;
        $out = [];
        foreach ($defaults as $k => $v) {
            $out[$k] = isset($stored[$k]) ? max(0, (int) $stored[$k]) : $v;
        }
        return $out;
    }

    public static function setCaps(array $caps): void
    {
        $clean = [];
        foreach (self::capsDefault() as $k => $default) {
            $clean[$k] = isset($caps[$k]) ? max(0, (int) $caps[$k]) : $default;
        }
        AppSetting::put(self::KEY_CAPS, $clean);
    }

    public static function cap(string $name): int
    {
        return self::caps()[$name] ?? 0;
    }

    public static function chatModel(): string
    {
        return (string) AppSetting::get(self::KEY_CHAT_MODEL, 'gpt-4o-mini');
    }

    public static function setChatModel(string $model): void
    {
        AppSetting::put(self::KEY_CHAT_MODEL, trim($model));
    }

    public static function embeddingModel(): string
    {
        return (string) AppSetting::get(self::KEY_EMBEDDING_MODEL, 'text-embedding-3-small');
    }

    public static function setEmbeddingModel(string $model): void
    {
        AppSetting::put(self::KEY_EMBEDDING_MODEL, trim($model));
    }

    /** Vision-capable chat model used to OCR scanned PDF pages. */
    public static function ocrModel(): string
    {
        return (string) AppSetting::get(self::KEY_OCR_MODEL, 'gpt-4o-mini');
    }

    public static function setOcrModel(string $model): void
    {
        AppSetting::put(self::KEY_OCR_MODEL, trim($model));
    }
}
