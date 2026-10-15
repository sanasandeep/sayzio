<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persistent, AI-generated brand identity owned by a creator (Task #2662).
 *
 * The structured payload (palette / fonts / voice / taglines / bio /
 * recommended block theme + the source it was generated from) lives in the
 * `config` array so the shape can evolve without migrations. Helpers here
 * give callers a typed view onto the most-used slices without re-reading the
 * raw array everywhere.
 */
class BrandKit extends Model
{
    use \App\Modules\User\Concerns\BelongsToWorkspace;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'name',
        'slug',
        'config',
        'is_default',
    ];

    protected $casts = [
        'config'     => 'array',
        'is_default' => 'bool',
    ];

    protected static function booted(): void
    {
        static::creating(function ($kit) {
            if (!$kit->workspace_id && $kit->user_id) {
                $kit->workspace_id = User::find($kit->user_id)?->ensureDefaultWorkspace()->id;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{primary?:string,secondary?:string,accent?:string,neutrals?:list<string>} */
    public function palette(): array
    {
        return is_array($this->config['palette'] ?? null) ? $this->config['palette'] : [];
    }

    /** @return array{heading?:string,body?:string} */
    public function fonts(): array
    {
        return is_array($this->config['fonts'] ?? null) ? $this->config['fonts'] : [];
    }

    public function blockTheme(): string
    {
        return (string) ($this->config['block_theme'] ?? '');
    }

    /**
     * The brand's logo/mark image reference (URL or storage path) for use in
     * branded exports. Falls back to the source image the kit was generated
     * from. Empty string when the kit carries neither. Task #3302.
     */
    public function logo(): string
    {
        foreach (['logo', 'logo_url', 'source', 'source_url'] as $key) {
            $raw = $this->config[$key] ?? '';
            if (is_array($raw)) $raw = ($raw['type'] ?? '') === 'logo' ? ($raw['value'] ?? '') : '';
            $val = is_string($raw) ? trim($raw) : '';
            if ($val !== '' && !preg_match('~^(https?://|/(?!/))~i', $val)) continue;
            if ($val !== '') {
                return $val;
            }
        }
        return '';
    }

    /** @return array{tone?:string,descriptors?:list<string>} */
    public function voice(): array
    {
        return is_array($this->config['voice'] ?? null) ? $this->config['voice'] : [];
    }

    /** @return list<string> */
    public function taglines(): array
    {
        $taglines = (array) ($this->config['taglines'] ?? []);
        return array_values(array_filter(array_map(
            fn ($t) => trim((string) $t),
            $taglines
        )));
    }

    /**
     * The creator's explicitly selected default identity for new AI drafts. Used by On-Brand AI (Task #2664) to ground the
     * biolink builder and AI Companion in the saved identity.
     */
    public static function defaultFor(int $userId): ?self
    {
        return static::where('user_id', $userId)->where('is_default',true)->whereNull('config->archived_at')
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * A compact, plain-English directive block describing this brand's
     * voice/tone (and optionally palette + fonts) for injection into an AI
     * system/user prompt. Returns '' when the kit carries nothing usable so
     * callers can cheaply skip injection.
     *
     * @param bool $includeColors Include palette/fonts (on for the page
     *                            builder, off for chat where color is moot).
     */
    public function promptDirectives(bool $includeColors = true): string
    {
        $lines = [];

        $name = trim((string) $this->name);
        if ($name !== '') {
            $lines[] = "Brand: {$name}.";
        }

        $voice = $this->voice();
        $tone  = trim((string) ($voice['tone'] ?? ''));
        if ($tone !== '') {
            $lines[] = "Voice & tone: {$tone}.";
        }

        $descriptors = array_values(array_filter(array_map(
            fn ($d) => trim((string) $d),
            (array) ($voice['descriptors'] ?? [])
        )));
        if ($descriptors) {
            $lines[] = 'Brand personality: ' . implode(', ', array_slice($descriptors, 0, 10)) . '.';
        }

        $taglines = $this->taglines();
        if ($taglines) {
            $lines[] = 'Echo the spirit of taglines like: "' . implode('", "', array_slice($taglines, 0, 3)) . '".';
        }

        foreach (['audience'=>'Audience','instructions'=>'Brand instructions','exclusions'=>'Avoid'] as $key=>$label) {
            $value=trim((string)($this->config[$key]??''));if($value!=='')$lines[]=$label.': '.mb_substr($value,0,2000);
        }

        if ($includeColors) {
            $palette = $this->palette();
            $colors  = array_values(array_filter([
                isset($palette['primary'])   ? "primary {$palette['primary']}"     : null,
                isset($palette['secondary']) ? "secondary {$palette['secondary']}" : null,
                isset($palette['accent'])    ? "accent {$palette['accent']}"       : null,
            ]));
            if ($colors) {
                $lines[] = 'Use the brand palette — ' . implode(', ', $colors)
                    . ' — for page.theme_color and accents.';
            }

            $fonts = $this->fonts();
            if (!empty($fonts['heading']) || !empty($fonts['body'])) {
                $lines[] = 'Brand fonts: headings ' . ($fonts['heading'] ?? '—')
                    . ', body ' . ($fonts['body'] ?? '—') . '.';
            }
        }

        if (!$lines) {
            return '';
        }

        return "Keep everything ON-BRAND for this creator's saved Brand Kit:\n- "
            . implode("\n- ", $lines);
    }
}
