<?php

/**
 * Regression guard: heavy animated blur glows on marketing pages.
 *
 * Background: the marketing homepage's aurora and AI-zone glows were once
 * continuously-animated `filter: blur()` elements. Live large-radius blurs
 * are extremely expensive to composite — every animation frame re-runs the
 * blur over a huge (often viewport-sized) region, which tanks scrolling on
 * low-end / older phone GPUs and even breaks headless screenshot tooling
 * (see .agents/memory/headless-screenshot-large-blur-artifact.md). They were
 * converted to pre-softened radial gradients, but nothing stopped a future
 * edit from reintroducing the pattern. This guard does.
 *
 * Mechanics: statically scans the <style> blocks of marketing Blade views
 * (resources/views/home.blade.php, resources/views/home/**,
 * resources/views/public/**) plus public/css/marketing-anim.css and fails
 * when an element would run a LARGE live blur CONTINUOUSLY, i.e. when:
 *
 *   1. a single rule declares both `filter: ... blur(>MAX_BLUR_PX)` and an
 *      infinite animation;
 *   2. one rule gives a selector a large blur and another rule animates the
 *      same element infinitely (matched by identical selector or identical
 *      trailing compound, e.g. `.x::after` + `.in-view .x::after`);
 *   3. a @keyframes frame itself tweens `filter: blur(>MAX_BLUR_PX)` and any
 *      rule plays that keyframe infinitely (an animated blur radius is the
 *      worst case — the blur is re-evaluated every frame by definition).
 *
 * What is SAFE (never flagged):
 *   - pre-softened radial-gradient glows (no `filter: blur` at all);
 *   - small glows up to MAX_BLUR_PX (e.g. .btn-glow's 12px conic halo);
 *   - hover/focus-only effects — any selector containing :hover, :focus or
 *     :active is exempt, they only composite while interacted with;
 *   - `backdrop-filter` blurs and `drop-shadow()` (different cost profile,
 *      not this guard's concern);
 *   - finite entrance/exit animations (wordIn/cardOut style), however blurry.
 *
 * Adding a legit exception: append to ALLOWLIST below with a reason — never
 * raise MAX_BLUR_PX to sneak one effect through.
 *
 * Usage:
 *   php scripts/check-marketing-heavy-blur.php [file-or-dir ...]
 *
 * Exit codes:
 *   0  no heavy animated blurs found
 *   1  at least one non-allowlisted heavy animated blur found
 *   2  scanner error (no scannable input found)
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/** Largest blur radius (px) allowed on a continuously-animated element. */
const MAX_BLUR_PX = 16;

/*
 * File (relative to the artifact root) => [selector-or-keyframe => reason].
 * A finding is suppressed only when its exact selector (or keyframe name for
 * kind-3 findings) is allowlisted for that file. Every entry must explain why
 * the effect is cheap enough (tiny element, display:none by default, ...).
 */
const ALLOWLIST = [
    // Example:
    // 'resources/views/home.blade.php' => [
    //     '.tiny-spark' => '8x8px element; blur region is trivially small',
    // ],
];

/*
 * Pre-existing findings grandfathered in when this guard landed. This is a
 * RATCHET baseline: entries may only ever be REMOVED (when the effect is
 * converted to a pre-softened gradient), never added — new heavy animated
 * blurs go through ALLOWLIST with a justification or get fixed. The guard
 * fails if a baseline entry no longer matches anything, so stale entries
 * cannot linger.
 */
const BASELINE = [];

// ---------------------------------------------------------------------------

$targets = array_slice($argv, 1);
$defaultRun = $targets === [];
if ($defaultRun) {
    $targets = [
        'resources/views/home.blade.php',
        'resources/views/home',
        'resources/views/public',
        'public/css/marketing-anim.css',
    ];
}

$files = [];
foreach ($targets as $t) {
    $path = str_starts_with($t, '/') ? $t : $root . '/' . $t;
    if (is_file($path)) {
        $files[] = $path;
    } elseif (is_dir($path)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (preg_match('/\.(blade\.php|css)$/', $f->getFilename())) {
                $files[] = $f->getPathname();
            }
        }
    }
}
sort($files);

if ($files === []) {
    fwrite(STDERR, "check-marketing-heavy-blur: no scannable files found\n");
    exit(2);
}

/**
 * Blank a span with spaces while preserving newlines so byte offsets keep
 * mapping to the correct line numbers (see text-sweep-guard-line-numbers).
 */
function blankPreservingNewlines(string $s): string
{
    return preg_replace('/[^\n]/', ' ', $s);
}

/** Extract CSS chunks (with absolute byte offsets) from a file. */
function cssChunks(string $path, string $content): array
{
    if (str_ends_with($path, '.css')) {
        return [[0, $content]];
    }
    // Blade view: blank Blade comments, then pull out <style> bodies.
    $content = preg_replace_callback('/\{\{--.*?--\}\}/s', fn ($m) => blankPreservingNewlines($m[0]), $content);
    $chunks = [];
    if (preg_match_all('/<style\b[^>]*>(.*?)<\/style>/is', $content, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
        foreach ($m as $set) {
            $chunks[] = [$set[1][1], $set[1][0]];
        }
    }
    return $chunks;
}

function lineAt(string $content, int $offset): int
{
    return substr_count($content, "\n", 0, $offset) + 1;
}

/** Max blur radius in px declared via `filter:` (not backdrop-filter) in a declaration block. */
function maxFilterBlurPx(string $decls): float
{
    $max = 0.0;
    if (preg_match_all('/(?<![-\w])filter\s*:\s*([^;{}]*)/i', $decls, $m)) {
        foreach ($m[1] as $value) {
            if (preg_match_all('/blur\(\s*([\d.]+)px\s*\)/i', $value, $b)) {
                foreach ($b[1] as $px) {
                    $max = max($max, (float) $px);
                }
            }
        }
    }
    return $max;
}

/** Keyframe names referenced by an infinite `animation`/`animation-*` declaration block. */
function infiniteAnimationNames(string $decls): array
{
    $names = [];
    if (preg_match_all('/(?<![-\w])animation\s*:\s*([^;{}]*)/i', $decls, $m)) {
        foreach ($m[1] as $value) {
            // Multiple comma-separated animations possible.
            foreach (explode(',', $value) as $one) {
                if (!preg_match('/\binfinite\b/i', $one)) {
                    continue;
                }
                // Keyframe name = first token that isn't a time/keyword/number.
                foreach (preg_split('/\s+/', trim($one)) as $tok) {
                    if ($tok === '' || preg_match('/^([\d.]+m?s|infinite|linear|ease(-in|-out|-in-out)?|step[s-][^ ]*|cubic-bezier\(.*|steps\(.*|normal|reverse|alternate(-reverse)?|none|forwards|backwards|both|running|paused)$/i', $tok)) {
                        continue;
                    }
                    $names[] = $tok;
                    break;
                }
            }
        }
    }
    // animation-name + animation-iteration-count: infinite split across decls.
    if (preg_match('/animation-iteration-count\s*:\s*[^;{}]*\binfinite\b/i', $decls)
        && preg_match('/animation-name\s*:\s*([^;{}]*)/i', $decls, $m)) {
        foreach (explode(',', $m[1]) as $n) {
            $names[] = trim($n);
        }
    }
    return array_values(array_unique(array_filter($names)));
}

/** Trailing compound of a selector: `.a .b::after` -> `.b::after`. */
function trailingCompound(string $selector): string
{
    $parts = preg_split('/[\s>+~]+/', trim($selector));
    return $parts === [] ? trim($selector) : end($parts);
}

function isInteractionOnly(string $selector): bool
{
    return (bool) preg_match('/:(hover|focus|focus-visible|focus-within|active)\b/i', $selector);
}

function allowlisted(string $relPath, string $key): bool
{
    return isset(ALLOWLIST[$relPath]) && array_key_exists($key, ALLOWLIST[$relPath]);
}

/** Track baseline hits so stale (no-longer-matching) entries fail the run. */
$baselineHits = [];
function baselined(string $relPath, string $key, array &$baselineHits): bool
{
    if (isset(BASELINE[$relPath]) && in_array($key, BASELINE[$relPath], true)) {
        $baselineHits[$relPath][$key] = true;
        return true;
    }
    return false;
}

// ---------------------------------------------------------------------------

$failures = [];

foreach ($files as $path) {
    $content = file_get_contents($path);
    if ($content === false) {
        continue;
    }
    $relPath = str_starts_with($path, $root . '/') ? substr($path, strlen($root) + 1) : $path;

    // Per-file aggregates.
    $bigBlurSelectors = [];   // selector => ['px' => float, 'line' => int, 'names' => [animation names on same rule]]
    $infiniteSelectors = [];  // selector => ['line' => int, 'names' => [keyframe names]]
    $blurKeyframes = [];      // keyframe name => ['px' => float, 'line' => int]

    foreach (cssChunks($path, $content) as [$chunkOffset, $css]) {
        // Blank CSS comments (newline-preserving).
        $css = preg_replace_callback('/\/\*.*?\*\//s', fn ($m) => blankPreservingNewlines($m[0]), $css);

        // @keyframes blocks: record frames that tween a large blur, then blank
        // them so the generic rule scan below doesn't parse frames as rules.
        $css = preg_replace_callback(
            '/@(?:-webkit-)?keyframes\s+([\w-]+)\s*\{((?:[^{}]*\{[^{}]*\})*[^{}]*)\}/is',
            function ($m) use (&$blurKeyframes, $content, $chunkOffset, $css) {
                $name = $m[1];
                $px = maxFilterBlurPx($m[2]);
                if ($px > MAX_BLUR_PX && (!isset($blurKeyframes[$name]) || $blurKeyframes[$name]['px'] < $px)) {
                    // Offset of this keyframes block inside the chunk.
                    $inner = strpos($css, $m[0]);
                    $blurKeyframes[$name] = [
                        'px' => $px,
                        'line' => lineAt($content, $chunkOffset + ($inner === false ? 0 : $inner)),
                    ];
                }
                return blankPreservingNewlines($m[0]);
            },
            $css
        );

        // Flatten @media / @supports wrappers: blank the at-rule prelude and
        // its outer braces, keeping the inner rules scannable in place.
        do {
            $before = $css;
            $css = preg_replace_callback(
                '/@(media|supports|container|layer\s+[\w.,\s-]*)[^{}]*\{((?:[^{}]*\{[^{}]*\})*[^{}]*)\}/is',
                function ($m) {
                    $prefixLen = strpos($m[0], '{') + 1;
                    $prefix = blankPreservingNewlines(substr($m[0], 0, $prefixLen));
                    return $prefix . $m[2] . ' ';
                },
                $css
            );
        } while ($css !== $before);

        // Plain rules: selector { declarations }
        if (preg_match_all('/([^{}]+)\{([^{}]*)\}/s', $css, $rules, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            foreach ($rules as $rule) {
                $selectorRaw = $rule[1][0];
                $decls = $rule[2][0];
                $line = lineAt($content, $chunkOffset + $rule[1][1] + strlen($selectorRaw) - strlen(ltrim($selectorRaw)));

                $blurPx = maxFilterBlurPx($decls);
                $infNames = infiniteAnimationNames($decls);

                foreach (array_map('trim', explode(',', $selectorRaw)) as $selector) {
                    if ($selector === '') {
                        continue;
                    }
                    if ($blurPx > MAX_BLUR_PX && !isInteractionOnly($selector)) {
                        if (!isset($bigBlurSelectors[$selector]) || $bigBlurSelectors[$selector]['px'] < $blurPx) {
                            $bigBlurSelectors[$selector] = ['px' => $blurPx, 'line' => $line, 'names' => $infNames];
                        }
                    }
                    if ($infNames !== [] && !isInteractionOnly($selector)) {
                        $infiniteSelectors[$selector] = ['line' => $line, 'names' => $infNames];
                    }
                }
            }
        }
    }

    // Kind 1 + 2: large-blur selector that is also infinitely animated.
    foreach ($bigBlurSelectors as $selector => $info) {
        $animated = $info['names'] !== [];
        $animLine = $info['line'];
        if (!$animated) {
            $tail = trailingCompound($selector);
            foreach ($infiniteSelectors as $animSel => $animInfo) {
                if ($animSel === $selector || trailingCompound($animSel) === $tail) {
                    $animated = true;
                    $animLine = $animInfo['line'];
                    break;
                }
            }
        }
        if ($animated && !allowlisted($relPath, $selector) && !baselined($relPath, $selector, $baselineHits)) {
            $failures[] = sprintf(
                "%s:%d  `%s` runs filter: blur(%gpx) (> %dpx) while infinitely animated (animation at line %d)",
                $relPath, $info['line'], $selector, $info['px'], MAX_BLUR_PX, $animLine
            );
        }
    }

    // Kind 3: keyframes that tween a large blur, played infinitely anywhere.
    foreach ($blurKeyframes as $name => $kf) {
        foreach ($infiniteSelectors as $animSel => $animInfo) {
            if (in_array($name, $animInfo['names'], true)) {
                if (!allowlisted($relPath, $name) && !baselined($relPath, $name, $baselineHits)) {
                    $failures[] = sprintf(
                        "%s:%d  @keyframes %s tweens filter: blur(%gpx) (> %dpx) and is played infinitely by `%s` (line %d)",
                        $relPath, $kf['line'], $name, $kf['px'], MAX_BLUR_PX, $animSel, $animInfo['line']
                    );
                }
                break;
            }
        }
    }
}

// Ratchet: every BASELINE entry must still match a real finding; otherwise it
// has been fixed and must be removed so the baseline can only shrink. Only
// meaningful when the full default file set was scanned.
foreach ($defaultRun ? BASELINE : [] as $relPath => $keys) {
    foreach ($keys as $key) {
        if (!isset($baselineHits[$relPath][$key])) {
            $failures[] = sprintf(
                "%s  stale BASELINE entry `%s` no longer matches — remove it from scripts/check-marketing-heavy-blur.php (baselines only shrink)",
                $relPath, $key
            );
        }
    }
}

if ($failures !== []) {
    sort($failures);
    fwrite(STDERR, "Heavy animated blur glows found on marketing pages (" . count($failures) . "):\n\n");
    foreach ($failures as $f) {
        fwrite(STDERR, "  {$f}\n");
    }
    fwrite(STDERR, <<<TXT

Continuously-animated large `filter: blur()` regions are re-composited every
frame and slow scrolling to a crawl on older phone GPUs. Replace the live blur
with a pre-softened radial-gradient (see the .aurora blobs in home.blade.php),
keep the blur <= 16px, or make the effect :hover-only. For a genuinely cheap
exception, add it to the ALLOWLIST in scripts/check-marketing-heavy-blur.php
with a reason.

TXT);
    exit(1);
}

echo 'check-marketing-heavy-blur: OK (' . count($files) . " files scanned)\n";
exit(0);
