<?php

namespace App\Console\Commands;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Console\Command;

/**
 * Recurring drift guard that stops the mobile app and the docs from silently
 * falling behind new web features.
 *
 * Problem it prevents: the web app is the source of truth for biolink block
 * types ({@see BiolinkBlock::TYPES}) and page/link types
 * ({@see LinkTypeCategories}). The mobile block editor
 * (`artifacts/1inme-mobile/lib/api/blocks.ts` → `BLOCK_KINDS`) and the docs
 * (`docs/features.md`, `docs/api.md`, `docs/knowledge-base.md`) are maintained
 * BY HAND. Every time web ships a new block/link type, someone must remember to
 * (a) give it a mobile editor entry — or consciously decide it's web-only for
 * now — and (b) mention it in the docs. Nothing used to catch a miss until a
 * manual parity audit, so gaps accumulated silently.
 *
 * This closes the loop with the same pattern as {@see CheckDemoAllowlist} and
 * `check:dialer-sync`: a committed baseline (`docs/mobile-docs-parity.json`)
 * records the triaged state of every current web block/link type. The command:
 *
 *   - FAILS on a NEW web block/link type that has no baseline entry — forcing a
 *     conscious "does this need a mobile editor / a doc mention?" decision at
 *     the moment the type ships, not months later.
 *   - FAILS on a MOBILE REGRESSION — a block that had a mobile editor entry in
 *     the baseline but no longer appears in `BLOCK_KINDS` (accidental removal).
 *   - FAILS on a STALE baseline entry — a type that no longer exists on web
 *     (renamed/removed) so the baseline stays clean.
 *
 * Docs coverage is reported and recorded for every type (matched by slug OR
 * human label), but a docs-only change is NOT a hard failure on its own — label
 * text is fuzzy to match, so docs coverage is surfaced as guidance in the NEW /
 * summary output rather than a flaky gate. The hard gate is: no untriaged type,
 * no stale entry, no lost mobile editor.
 *
 * `--accept` rewrites the baseline to the current computed state. Run it after
 * you've triaged the reported types (added a mobile `BLOCK_KINDS` entry, added
 * a doc mention, or consciously decided the type is web-only for now) so the
 * commit that ships the new web feature also records the parity decision.
 *
 * No database is required — it reads static PHP constants plus a few files, so
 * it runs as a fast pre-merge validation step and as a scheduled audit.
 *
 * Exit codes:
 *   0 — every web block/link type is triaged, nothing stale, no lost mobile UI.
 *   1 — drift: a new/untriaged type, a stale entry, and/or a mobile regression.
 */
class CheckMobileDocsParity extends Command
{
    protected $signature = 'parity:check-mobile-docs {--accept : Rewrite the baseline to the current web/mobile/docs state}';

    protected $description = 'Fail when a new web block/link type is missing a mobile editor entry or a docs mention (mobile/docs parity drift guard).';

    /** Path (relative to base_path) of the mobile block-editor registry. */
    private const MOBILE_BLOCKS = '../1inme-mobile/lib/api/blocks.ts';

    /** Docs scanned for a slug/label mention, relative to base_path. */
    private const DOC_FILES = [
        'docs/features.md',
        'docs/api.md',
        'docs/knowledge-base.md',
    ];

    /** Committed baseline of triaged types, relative to base_path. */
    private const BASELINE = 'docs/mobile-docs-parity.json';

    public function handle(): int
    {
        $blocks = $this->webBlockTypes();
        $linkTypes = $this->webLinkTypes();

        $mobileBlockTypes = $this->mobileBlockTypes();
        $docText = $this->docText();

        // Current, freshly-computed coverage for every web type.
        $currentBlocks = [];
        foreach ($blocks as $slug => $label) {
            $currentBlocks[$slug] = [
                'label' => $label,
                'mobile' => in_array($slug, $mobileBlockTypes, true),
                'docs' => $this->mentioned($docText, $slug, $label),
            ];
        }
        $currentLinks = [];
        foreach ($linkTypes as $slug => $label) {
            $currentLinks[$slug] = [
                'label' => $label,
                'docs' => $this->mentioned($docText, $slug, $label),
            ];
        }

        if ($this->option('accept')) {
            return $this->writeBaseline($currentBlocks, $currentLinks);
        }

        $baseline = $this->readBaseline();
        if ($baseline === null) {
            $this->error('Missing or unreadable baseline: ' . self::BASELINE);
            $this->line('Run `php artisan parity:check-mobile-docs --accept` to create it.');

            return self::FAILURE;
        }

        $baseBlocks = $baseline['blocks'] ?? [];
        $baseLinks = $baseline['linkTypes'] ?? [];

        // ── NEW: web type with no baseline entry (untriaged). ──────────────
        $newBlocks = array_diff_key($currentBlocks, $baseBlocks);
        $newLinks = array_diff_key($currentLinks, $baseLinks);

        // ── STALE: baseline entry whose web type no longer exists. ─────────
        $staleBlocks = array_diff_key($baseBlocks, $currentBlocks);
        $staleLinks = array_diff_key($baseLinks, $currentLinks);

        // ── MOBILE REGRESSION: was mobile:true in baseline, now not. ───────
        $lostMobile = [];
        foreach ($baseBlocks as $slug => $entry) {
            if (! empty($entry['mobile']) && isset($currentBlocks[$slug]) && ! $currentBlocks[$slug]['mobile']) {
                $lostMobile[$slug] = $currentBlocks[$slug]['label'];
            }
        }

        $this->printSummary($currentBlocks, $currentLinks);

        $ok = empty($newBlocks) && empty($newLinks)
            && empty($staleBlocks) && empty($staleLinks)
            && empty($lostMobile);

        if ($ok) {
            $this->info('OK — every web block/link type is triaged; nothing stale; no mobile editor lost.');

            return self::SUCCESS;
        }

        $this->newLine();

        if (! empty($newBlocks) || ! empty($newLinks)) {
            $count = count($newBlocks) + count($newLinks);
            $this->error("Parity drift — {$count} new web type(s) are not yet triaged for mobile/docs:");
            foreach ($newBlocks as $slug => $e) {
                $this->line("  <fg=yellow>block</> {$slug} ({$e['label']})  mobile-editor: " . $this->flag($e['mobile']) . '  docs: ' . $this->flag($e['docs']));
            }
            foreach ($newLinks as $slug => $e) {
                $this->line("  <fg=yellow>link</>  {$slug} ({$e['label']})  docs: " . $this->flag($e['docs']));
            }
            $this->newLine();
            $this->line('For each new type decide, then re-run with --accept:');
            $this->line('  • add a mobile editor entry in artifacts/1inme-mobile/lib/api/blocks.ts (BLOCK_KINDS)');
            $this->line('    — or consciously accept it as web-only for now;');
            $this->line('  • mention it in docs/features.md, docs/api.md and/or docs/knowledge-base.md.');
            $this->line('Then run: php artisan parity:check-mobile-docs --accept');
            $this->newLine();
        }

        if (! empty($lostMobile)) {
            $this->error('Mobile regression — block type(s) lost their mobile editor entry (BLOCK_KINDS):');
            foreach ($lostMobile as $slug => $label) {
                $this->line("  <fg=yellow>block</> {$slug} ({$label})");
            }
            $this->line('Restore the BLOCK_KINDS entry, or run --accept if the removal is intentional.');
            $this->newLine();
        }

        if (! empty($staleBlocks) || ! empty($staleLinks)) {
            $this->error('Stale baseline entries — type no longer exists on web (renamed/removed):');
            foreach (array_keys($staleBlocks) as $slug) {
                $this->line("  <fg=yellow>block</> {$slug}");
            }
            foreach (array_keys($staleLinks) as $slug) {
                $this->line("  <fg=yellow>link</>  {$slug}");
            }
            $this->line('Run --accept to prune them from ' . self::BASELINE . '.');
            $this->newLine();
        }

        return self::FAILURE;
    }

    /**
     * User-facing, addable web block types: TYPES minus aliases and system /
     * verified entries (those are never shown in the picker, so mobile/docs
     * parity is not expected for them).
     *
     * @return array<string, string> slug => label
     */
    private function webBlockTypes(): array
    {
        $out = [];
        foreach (BiolinkBlock::pickerTypes() as $slug => $meta) {
            if (! empty($meta['system']) || ($meta['category'] ?? null) === 'verified') {
                continue;
            }
            $out[$slug] = $meta['label'] ?? $slug;
        }

        return $out;
    }

    /** @return array<string, string> slug => label */
    private function webLinkTypes(): array
    {
        $out = [];
        foreach (LinkTypeCategories::types() as $slug => $meta) {
            $out[$slug] = $meta['label'] ?? $slug;
        }

        return $out;
    }

    /**
     * Block `type` strings declared in the mobile BLOCK_KINDS registry. Parsed
     * statically so the check needs no Node/TS toolchain.
     *
     * @return list<string>
     */
    private function mobileBlockTypes(): array
    {
        $path = base_path(self::MOBILE_BLOCKS);
        if (! is_file($path)) {
            $this->warn('Mobile block registry not found at ' . self::MOBILE_BLOCKS . ' — treating mobile coverage as empty.');

            return [];
        }

        $src = (string) file_get_contents($path);
        // Only scan the BLOCK_KINDS array, not the whole file, so unrelated
        // `type:` occurrences (other exported types) are never miscounted.
        $start = strpos($src, 'BLOCK_KINDS');
        if ($start !== false) {
            $src = substr($src, $start);
        }
        preg_match_all('/\btype:\s*"([a-z0-9_]+)"/', $src, $m);

        return array_values(array_unique($m[1] ?? []));
    }

    /** Concatenated lowercase text of every scanned doc. */
    private function docText(): string
    {
        $text = '';
        foreach (self::DOC_FILES as $rel) {
            $path = base_path($rel);
            if (is_file($path)) {
                $text .= "\n" . file_get_contents($path);
            }
        }

        return strtolower($text);
    }

    /** A type is "documented" if its slug OR human label appears in any doc. */
    private function mentioned(string $docText, string $slug, string $label): bool
    {
        return str_contains($docText, strtolower($slug))
            || (strlen(trim($label)) >= 3 && str_contains($docText, strtolower(trim($label))));
    }

    /** @return array{blocks?:array<string,mixed>, linkTypes?:array<string,mixed>}|null */
    private function readBaseline(): ?array
    {
        $path = base_path(self::BASELINE);
        if (! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, array{label:string, mobile:bool, docs:bool}>  $blocks
     * @param  array<string, array{label:string, docs:bool}>  $links
     */
    private function writeBaseline(array $blocks, array $links): int
    {
        ksort($blocks);
        ksort($links);

        $payload = [
            '_comment' => 'Baseline for `php artisan parity:check-mobile-docs`. Records the triaged '
                . 'mobile-editor / docs coverage of every web block & link type. A new web type not '
                . 'listed here fails the check until it is triaged and this file is regenerated with '
                . '`--accept`. Do NOT hand-edit unless you know why; regenerate instead.',
            'blocks' => $blocks,
            'linkTypes' => $links,
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents(base_path(self::BASELINE), $json . "\n");

        $this->info('Baseline written to ' . self::BASELINE . ' — '
            . count($blocks) . ' block type(s), ' . count($links) . ' link type(s).');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, array{label:string, mobile:bool, docs:bool}>  $blocks
     * @param  array<string, array{label:string, docs:bool}>  $links
     */
    private function printSummary(array $blocks, array $links): void
    {
        $blockMobile = count(array_filter($blocks, fn ($e) => $e['mobile']));
        $blockDocs = count(array_filter($blocks, fn ($e) => $e['docs']));
        $linkDocs = count(array_filter($links, fn ($e) => $e['docs']));

        $this->line('Web parity coverage:');
        $this->line("  block types: {$blockMobile}/" . count($blocks) . ' have a mobile editor entry; '
            . "{$blockDocs}/" . count($blocks) . ' are documented.');
        $this->line('  link types:  ' . "{$linkDocs}/" . count($links) . ' are documented.');
    }

    private function flag(bool $v): string
    {
        return $v ? '<fg=green>yes</>' : '<fg=red>MISSING</>';
    }
}
