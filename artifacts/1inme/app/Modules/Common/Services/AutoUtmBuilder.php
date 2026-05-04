<?php

namespace App\Modules\Common\Services;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\Link;

/**
 * Resolves the final outbound URL for a biolink block click by merging
 * (a) destination URL params, (b) per-block UTM overrides, and
 * (c) biolink-wide Auto-UTM defaults — in that precedence: any param
 * the creator already wrote into the destination URL wins, then any
 * per-block override wins, then the biolink-wide auto-defaults fill
 * what's left. Existing fragments and non-UTM query params are preserved.
 */
class AutoUtmBuilder
{
    public const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    public const BUILTIN_DEFAULTS = [
        'utm_source'   => '1inme',
        'utm_medium'   => 'biolink',
        'utm_campaign' => '{slug}',
        'utm_content'  => '{block}',
    ];

    /**
     * Build the final URL with merged UTM params.
     *
     * @return string  the merged URL (or the original $destinationUrl when nothing applied)
     */
    public function build(string $destinationUrl, Link $link, ?BiolinkBlock $block = null): string
    {
        $resolved = $this->resolveParams($link, $block);
        if (empty($resolved)) {
            return $destinationUrl;
        }

        // Split off the fragment so we never lose it during query rewrites.
        $fragment = '';
        $hashAt   = strpos($destinationUrl, '#');
        $base     = $destinationUrl;
        if ($hashAt !== false) {
            $fragment = substr($destinationUrl, $hashAt);
            $base     = substr($destinationUrl, 0, $hashAt);
        }

        $queryAt   = strpos($base, '?');
        $existing  = [];
        $pathPart  = $base;
        if ($queryAt !== false) {
            $pathPart = substr($base, 0, $queryAt);
            parse_str(substr($base, $queryAt + 1), $existing);
        }

        // Creator-set destination params win — only fill keys that are
        // not already present at all. An explicitly empty creator value
        // (e.g. `?utm_source=`) is still considered creator-set.
        foreach ($resolved as $k => $v) {
            if (!array_key_exists($k, $existing)) {
                $existing[$k] = $v;
            }
        }

        $qs = http_build_query($existing);
        return $pathPart . ($qs !== '' ? ('?' . $qs) : '') . $fragment;
    }

    /**
     * Compute the resolved UTM params for the (link, block) pair, applying
     * biolink-wide auto defaults plus per-block overrides. Returns an
     * associative array of utm_* => value (only non-empty entries).
     */
    public function resolveParams(Link $link, ?BiolinkBlock $block = null): array
    {
        $auto = $this->biolinkAutoUtmConfig($link);
        $perBlock = $this->blockUtmConfig($block);

        // Per-block mode wins over the biolink-wide toggle:
        //   on      => always emit defaults for this block,
        //   off     => never emit defaults (overrides still apply if set),
        //   inherit => follow biolink-wide enabled flag.
        if ($perBlock['enabled'] === 'on') {
            $autoEnabled = true;
        } elseif ($perBlock['enabled'] === 'off') {
            $autoEnabled = false;
        } else {
            $autoEnabled = !empty($auto['enabled']);
        }

        $tokens = $this->buildTokens($link, $block);

        $resolved = [];
        foreach (self::UTM_KEYS as $k) {
            $val = '';
            if ($autoEnabled) {
                $tpl = $auto['defaults'][$k] ?? '';
                if ($tpl === '' && isset(self::BUILTIN_DEFAULTS[$k])) {
                    $tpl = self::BUILTIN_DEFAULTS[$k];
                }
                $val = $this->renderTemplate($tpl, $tokens);
            }
            // Per-block override wins (non-empty string).
            if (isset($perBlock['overrides'][$k]) && $perBlock['overrides'][$k] !== '') {
                $val = $this->renderTemplate((string) $perBlock['overrides'][$k], $tokens);
            }
            if ($val !== '') {
                $resolved[$k] = $val;
            }
        }

        return $resolved;
    }

    /**
     * Same as build() but returns a structured result useful for editor
     * previews — the resolved params and the final URL.
     *
     * @return array{url: string, params: array<string,string>}
     */
    public function preview(string $destinationUrl, Link $link, ?BiolinkBlock $block = null): array
    {
        return [
            'url'    => $this->build($destinationUrl, $link, $block),
            'params' => $this->resolveParams($link, $block),
        ];
    }

    protected function biolinkAutoUtmConfig(Link $link): array
    {
        $cfg = data_get($link->settings, 'biolink.auto_utm', []);
        if (!is_array($cfg)) $cfg = [];
        $defaults = is_array($cfg['defaults'] ?? null) ? $cfg['defaults'] : [];
        return [
            'enabled'  => !empty($cfg['enabled']),
            'defaults' => array_intersect_key($defaults, array_flip(self::UTM_KEYS)),
        ];
    }

    /**
     * Per-block UTM config. We accept BOTH the structured shape
     * (`_link.auto_utm.{enabled,overrides.*}`) AND the legacy flat
     * fields (`_link.utm_source`, etc) the editor already wrote — those
     * existed before Auto-UTM and are treated as overrides.
     */
    protected function blockUtmConfig(?BiolinkBlock $block): array
    {
        $enabled   = 'inherit';
        $overrides = [];
        if ($block) {
            $linkData = (array) ($block->settings['_link'] ?? []);
            $auto     = (array) ($linkData['auto_utm'] ?? []);
            if (isset($auto['enabled'])) {
                $v = $auto['enabled'];
                if ($v === 'on' || $v === 'off' || $v === 'inherit') $enabled = $v;
                elseif (is_bool($v)) $enabled = $v ? 'on' : 'off';
            }
            $structured = (array) ($auto['overrides'] ?? []);
            foreach (self::UTM_KEYS as $k) {
                if (isset($structured[$k]) && $structured[$k] !== '') {
                    $overrides[$k] = (string) $structured[$k];
                } elseif (isset($linkData[$k]) && $linkData[$k] !== '') {
                    // Legacy flat field — still honored as an override.
                    $overrides[$k] = (string) $linkData[$k];
                }
            }
        }
        return ['enabled' => $enabled, 'overrides' => $overrides];
    }

    /**
     * Resolve the token map ({slug}, {alias}, {block}, {block_id},
     * {link_id}) for the given (link, block). Exposed publicly so the
     * editor previews can mirror the redirect-time output without
     * reflecting on protected internals.
     *
     * @return array<string,string>
     */
    public function tokensFor(Link $link, ?BiolinkBlock $block = null): array
    {
        return $this->buildTokens($link, $block);
    }

    protected function buildTokens(Link $link, ?BiolinkBlock $block): array
    {
        $blockName = '';
        if ($block) {
            $s = (array) ($block->settings ?? []);
            foreach (['label', 'title', 'heading', 'text', 'name'] as $k) {
                if (!empty($s[$k]) && is_string($s[$k])) {
                    $blockName = $s[$k];
                    break;
                }
            }
            if ($blockName === '') $blockName = 'block-' . $block->id;
        }
        return [
            'slug'     => (string) ($link->_used_alias ?? $link->alias ?? ''),
            'alias'    => (string) ($link->alias ?? ''),
            'block'    => $this->slugify($blockName),
            'block_id' => $block ? (string) $block->id : '',
            'link_id'  => (string) $link->id,
        ];
    }

    protected function renderTemplate(string $tpl, array $tokens): string
    {
        if ($tpl === '') return '';
        $out = preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($tokens) {
            return $tokens[$m[1]] ?? '';
        }, $tpl);
        return trim((string) $out);
    }

    protected function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }
}
