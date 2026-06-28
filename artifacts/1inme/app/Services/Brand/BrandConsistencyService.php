<?php

namespace App\Services\Brand;

use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\Link;
use Illuminate\Support\Collection;

/**
 * Brand Consistency Score (Task #2664).
 *
 * Audits a creator's existing biolinks against their saved {@see BrandKit}
 * and produces a 0-100 on-brand score plus per-link findings with a
 * one-click "apply fix" that reuses the existing apply-brand-kit route.
 *
 * PURE, DETERMINISTIC TRANSFORMER: it issues no DB queries of its own (the
 * caller passes the kit + the biolinks with their `settings` loaded) and
 * never calls the AI engine — it simply compares each page's stored
 * appearance against what {@see AiBrandKitService::applyToBiolink()} would
 * write. This is why a page that had the kit applied scores 100, and why the
 * score works (and tests) with the AI engine OFF.
 *
 * The four checked dimensions mirror applyToBiolink() exactly:
 *   - button color  ← palette.primary
 *   - body font     ← fonts.body
 *   - text color    ← darkest palette neutral
 *   - block theme   ← block_theme key
 * A dimension the kit doesn't define is skipped (never counts against a
 * page), so a sparse kit never produces phantom "off-brand" findings.
 */
class BrandConsistencyService
{
    /**
     * Audit a creator's biolinks against a saved kit.
     *
     * @param Collection<int,Link> $biolinks Links with `settings` loaded.
     * @return array{
     *   score:int, grade:string, label:string,
     *   kit_id:int, kit_name:string,
     *   links_total:int, links_on_brand:int,
     *   targets:array<string,?string>,
     *   findings:list<array<string,mixed>>,
     *   links:list<array<string,mixed>>,
     * }
     */
    public function audit(BrandKit $kit, Collection $biolinks): array
    {
        $targets = $this->kitTargets($kit);

        $links   = [];
        $sum     = 0;
        $counted = 0;

        foreach ($biolinks as $link) {
            if (!($link instanceof Link) || $link->type !== 'biolink') {
                continue;
            }
            $row = $this->auditLink($kit, $link, $targets);
            $links[] = $row;
            $sum    += $row['score'];
            $counted++;
        }

        // Worst-first so the most off-brand pages bubble to the top.
        usort($links, fn ($a, $b) => $a['score'] <=> $b['score']);

        $overall = $counted > 0 ? (int) round($sum / $counted) : 100;

        return [
            'score'          => $overall,
            'grade'          => $this->grade($overall),
            'label'          => $this->label($overall),
            'kit_id'         => (int) $kit->id,
            'kit_name'       => (string) $kit->name,
            'links_total'    => $counted,
            'links_on_brand' => count(array_filter($links, fn ($l) => $l['score'] >= 100)),
            'targets'        => $targets,
            'findings'       => array_values(array_filter($links, fn ($l) => $l['score'] < 100)),
            'links'          => $links,
        ];
    }

    /** @param array<string,?string> $targets */
    private function auditLink(BrandKit $kit, Link $link, array $targets): array
    {
        $settings = is_array($link->settings) ? $link->settings : [];
        $bio      = is_array($settings['biolink'] ?? null) ? $settings['biolink'] : [];

        $blockTheme = is_array($bio['block_theme'] ?? null)
            ? ($bio['block_theme']['_template'] ?? null)
            : null;

        $checks = [
            $this->check('button_color', 'Button color', $this->hex($bio['button_color'] ?? null), $targets['button_color']),
            $this->check('font_family',  'Body font',    $this->norm($bio['font_family'] ?? null),  $targets['font_family']),
            $this->check('font_color',   'Text color',   $this->hex($bio['font_color'] ?? null),    $targets['font_color']),
            $this->check('block_theme',  'Block theme',  $this->norm($blockTheme),                  $targets['block_theme']),
        ];

        // Only dimensions the kit actually defines count toward the score.
        $applicable = array_values(array_filter($checks, fn ($c) => $c['target'] !== null));
        $matched    = count(array_filter($applicable, fn ($c) => $c['ok']));
        $count      = count($applicable);
        $score      = $count > 0 ? (int) round($matched / $count * 100) : 100;

        $mismatches = array_values(array_filter($applicable, fn ($c) => !$c['ok']));

        return [
            'link_id'    => (int) $link->id,
            'title'      => (string) ($link->title ?: $link->alias),
            'alias'      => (string) $link->alias,
            'score'      => $score,
            'severity'   => $this->severity($score),
            'headline'   => ($link->title ?: $link->alias) . " is {$score}% on-brand",
            'reason'     => $this->reason($mismatches),
            'mismatches' => array_map(fn ($c) => [
                'key'      => $c['key'],
                'label'    => $c['label'],
                'current'  => $c['current'],
                'expected' => $c['target'],
            ], $mismatches),
            'apply_url'  => route('user.brand-kits.apply.biolink', [$kit->id, $link->id]),
        ];
    }

    /**
     * The on-brand target value for each checked dimension. Mirrors exactly
     * what AiBrandKitService::applyToBiolink() writes so an applied page
     * scores 100. Null means "the kit does not define this" → skipped.
     *
     * @return array<string,?string>
     */
    private function kitTargets(BrandKit $kit): array
    {
        $palette = $kit->palette();
        $fonts   = $kit->fonts();

        $neutrals = [];
        foreach ((array) ($palette['neutrals'] ?? []) as $n) {
            $h = $this->hex($n);
            if ($h !== null) {
                $neutrals[] = $h;
            }
        }
        $darkNeutral = $neutrals ? end($neutrals) : null;

        $theme = $kit->blockTheme();

        return [
            'button_color' => $this->hex($palette['primary'] ?? null),
            'font_family'  => $this->norm($fonts['body'] ?? null),
            'font_color'   => $darkNeutral,
            'block_theme'  => $this->norm($theme !== '' ? $theme : null),
        ];
    }

    /**
     * @return array{key:string,label:string,current:?string,target:?string,ok:bool}
     */
    private function check(string $key, string $label, ?string $current, ?string $target): array
    {
        // A dimension the kit doesn't define is always "ok" (skipped).
        $ok = $target === null
            ? true
            : ($current !== null && mb_strtolower($current) === mb_strtolower($target));

        return [
            'key'     => $key,
            'label'   => $label,
            'current' => $current,
            'target'  => $target,
            'ok'      => $ok,
        ];
    }

    /** @param list<array<string,mixed>> $mismatches */
    private function reason(array $mismatches): string
    {
        if (!$mismatches) {
            return 'This page matches your Brand Kit.';
        }
        $labels = array_map(fn ($c) => $c['label'], $mismatches);
        $list   = $this->joinNicely(array_map('mb_strtolower', $labels));

        return "The {$list} on this page " . (count($labels) === 1 ? "doesn't" : "don't")
            . ' match your Brand Kit. Apply the kit to bring it on-brand.';
    }

    /** @param list<string> $items */
    private function joinNicely(array $items): string
    {
        $items = array_values($items);
        $n = count($items);
        if ($n === 0) return '';
        if ($n === 1) return $items[0];
        if ($n === 2) return $items[0] . ' and ' . $items[1];
        $last = array_pop($items);
        return implode(', ', $items) . ', and ' . $last;
    }

    private function severity(int $score): string
    {
        return match (true) {
            $score >= 100 => 'win',
            $score >= 75  => 'tip',
            $score >= 50  => 'warning',
            default       => 'critical',
        };
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 40 => 'D',
            default      => 'F',
        };
    }

    private function label(int $score): string
    {
        return match (true) {
            $score >= 90 => 'On-brand',
            $score >= 75 => 'Mostly on-brand',
            $score >= 50 => 'Drifting off-brand',
            default      => 'Off-brand',
        };
    }

    /** Normalize a 6-digit hex to lowercase #rrggbb, or null. */
    private function hex($v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        if (preg_match('/^#?[0-9a-fA-F]{6}$/', $v)) {
            return '#' . strtolower(ltrim($v, '#'));
        }
        return null;
    }

    /** Trim a plain string value to a comparable form, or null when empty. */
    private function norm($v): ?string
    {
        if (!is_string($v)) {
            return null;
        }
        $v = trim($v);
        return $v !== '' ? $v : null;
    }
}
