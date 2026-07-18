<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\PageTemplate;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Rules\NotBannedName;
use App\Modules\Admin\Services\TemplateService;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use App\Modules\User\Support\BiolinkMergeEngine;
use App\Modules\User\Support\BlockTypeRegistry;
use App\Modules\User\Support\MailMergeSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-create personalized biolink pages from a sheet (mail-merge).
 *
 * One master *blueprint* (a gallery page template OR one of the user's own
 * biolink pages) is combined with per-row data from a CSV / .xlsx / pasted
 * table. `{{token}}` placeholders inside the blueprint are substituted with
 * each row's values — a pure, deterministic string replace (NO AI). The flow
 * mirrors the bulk short-link wizard (Step 1 input → Step 2 preview → Step 3
 * create) but emits full biolink pages instead of redirect links.
 *
 * Plan gating is enforced end-to-end: locked templates can't be used as a
 * blueprint, block types the plan disallows block the whole batch, and the
 * batch is checked against both the link quota (max_links) and the biolink
 * quota (max_biolinks).
 */
class BulkBiolinkController extends Controller
{
    /** Hard cap on rows per batch (matches the bulk short-link wizard). */
    public const MAX_ROWS = 500;

    public function __construct(private TemplateService $templates)
    {
    }

    /** Step 1 — choose a blueprint + provide the data sheet. */
    public function create(Request $request)
    {
        $owner = workspace_owner();
        $userPlanSlug = $owner->plan?->slug;

        $templates = PageTemplate::active()->get()->map(fn (PageTemplate $t) => [
            'id'       => $t->id,
            'name'     => $t->name,
            'category' => $t->category,
            'locked'   => $this->isLocked($t->plan_tier, $userPlanSlug),
        ])->values();

        $pages = $owner->links()
            ->whereIn('type', Link::BIOLINK_FAMILY)
            ->orderByDesc('id')
            ->get(['id', 'title', 'alias', 'type'])
            ->map(fn (Link $l) => [
                'id'    => $l->id,
                'label' => ($l->title ?: $l->alias) . '  ·  /' . $l->alias,
            ])->values();

        return view('user.links.bulk-biolink', [
            'templates' => $templates,
            'pages'     => $pages,
            'projects'  => $owner->projects()->orderBy('name')->get(),
            'domains'   => \App\Modules\User\Models\Domain::availableTo($request->user())->get(),
            'maxRows'   => self::MAX_ROWS,
        ]);
    }

    /** Step 2 — resolve the blueprint, parse + validate the sheet, preview. */
    public function preview(Request $request)
    {
        $owner = workspace_owner();

        $shared = $this->sharedOptions($request);
        $blueprint = $this->resolveBlueprint($request, $owner, $error);
        if ($blueprint === null) {
            return back()->withInput()->with('error', $error);
        }

        $blockError = $this->blueprintBlockGate($blueprint['snapshot'], $owner);
        if ($blockError !== null) {
            return back()->withInput()->with('error', $blockError);
        }

        $sheet = $this->parseSheet($request);
        if (empty($sheet['rows'])) {
            return back()->withInput()->with('error', 'No data rows found. Paste a table or upload a CSV/Excel file with a header row plus at least one data row.');
        }
        if (count($sheet['rows']) > self::MAX_ROWS) {
            return back()->withInput()->with('error', 'Too many rows. The limit is ' . self::MAX_ROWS . ' per batch — you submitted ' . count($sheet['rows']) . '.');
        }

        $tokens = BiolinkMergeEngine::extractTokens($blueprint['snapshot']);
        $dataTokens = $this->dataTokens($tokens);
        $missing = array_values(array_diff($dataTokens, $sheet['headers']));

        $rows = $this->normalizeSheetRows($sheet['rows'], $dataTokens);
        $validated = $this->validateRows($rows, $tokens, $owner);
        $validCount = collect($validated)->where('errors', [])->count();

        if (!empty($missing)) {
            session()->flash('error', 'Your sheet is missing column(s) for: ' . implode(', ', array_map(fn ($t) => '{{' . $t . '}}', $missing)) . '. Add them as columns, or pick a blueprint that doesn\'t use them.');
        } else {
            $this->flashBatchQuota($owner, $validCount);
        }

        return view('user.links.bulk-biolink-preview', [
            'rows'           => $validated,
            'dataTokens'     => $dataTokens,
            'shared'         => $shared,
            'blueprintLabel' => $blueprint['label'],
            'source'         => $blueprint['source'],
            'ref'            => $blueprint['ref'],
            'projects'       => $owner->projects()->orderBy('name')->get(),
            'domains'        => \App\Modules\User\Models\Domain::availableTo($request->user())->get(),
            'validCount'     => $validCount,
        ]);
    }

    /** Step 3 — re-validate, enforce quotas, create the pages, show results. */
    public function store(Request $request)
    {
        $owner = workspace_owner();
        $shared = $this->sharedOptions($request);

        $blueprint = $this->resolveBlueprint($request, $owner, $error);
        if ($blueprint === null) {
            return redirect()->route('user.links.biolink.bulk')->with('error', $error);
        }

        $blockError = $this->blueprintBlockGate($blueprint['snapshot'], $owner);
        if ($blockError !== null) {
            return redirect()->route('user.links.biolink.bulk')->with('error', $blockError);
        }

        $tokens = BiolinkMergeEngine::extractTokens($blueprint['snapshot']);
        $dataTokens = $this->dataTokens($tokens);

        // Reconstruct rows from the preview form (skip-checked rows dropped).
        $raw = (array) $request->input('rows', []);
        $rows = [];
        foreach ($raw as $r) {
            if (!is_array($r) || !empty($r['skip'])) {
                continue;
            }
            $data = [];
            foreach ($dataTokens as $t) {
                $data[$t] = trim((string) ($r['data'][$t] ?? ''));
            }
            $rows[] = [
                'alias_input' => trim((string) ($r['alias'] ?? '')),
                'title'       => trim((string) ($r['title'] ?? '')),
                'data'        => $data,
            ];
        }

        $renderPreview = function (string $message) use ($request, $rows, $tokens, $dataTokens, $shared, $blueprint, $owner) {
            if (empty($rows)) {
                return redirect()->route('user.links.biolink.bulk')->with('error', $message);
            }
            session()->flash('error', $message);
            $validated = $this->validateRows($rows, $tokens, $owner);
            return response()->view('user.links.bulk-biolink-preview', [
                'rows'           => $validated,
                'dataTokens'     => $dataTokens,
                'shared'         => $shared,
                'blueprintLabel' => $blueprint['label'],
                'source'         => $blueprint['source'],
                'ref'            => $blueprint['ref'],
                'projects'       => $owner->projects()->orderBy('name')->get(),
                'domains'        => \App\Modules\User\Models\Domain::availableTo($request->user())->get(),
                'validCount'     => collect($validated)->where('errors', [])->count(),
            ])->setStatusCode(422);
        };

        if (empty($rows)) {
            return redirect()->route('user.links.biolink.bulk')->with('error', 'No rows to create. Add a data sheet first.');
        }
        if (count($rows) > self::MAX_ROWS) {
            return redirect()->route('user.links.biolink.bulk')->with('error', 'Too many rows. The limit is ' . self::MAX_ROWS . ' per batch.');
        }

        $validated = $this->validateRows($rows, $tokens, $owner);
        $validRows = array_values(array_filter($validated, fn ($r) => empty($r['errors'])));
        if (empty($validRows)) {
            return $renderPreview('None of the rows are valid — fix the highlighted issues and try again.');
        }

        // Quota gate for the whole batch: both total links and biolinks.
        $planFeatures = $owner->plan?->features ?? [];
        $count = count($validRows);

        $maxLinks = $planFeatures['max_links'] ?? 5;
        if ($maxLinks !== -1) {
            $current = $owner->links()->count();
            if (($current + $count) > $maxLinks) {
                $remaining = max(0, $maxLinks - $current);
                return $renderPreview("This batch would exceed your plan's link limit ({$maxLinks}). You can create {$remaining} more — skip some rows or upgrade your plan.");
            }
        }

        $maxBiolinks = $planFeatures['max_biolinks'] ?? 1;
        if ($maxBiolinks !== -1) {
            $currentBio = $owner->links()->whereIn('type', Link::BIOLINK_FAMILY)->count();
            if (($currentBio + $count) > $maxBiolinks) {
                $remaining = max(0, $maxBiolinks - $currentBio);
                return $renderPreview("This batch would exceed your plan's biolink page limit ({$maxBiolinks}). You can create {$remaining} more — skip some rows or upgrade your plan.");
            }
        }

        $snapshot = $blueprint['snapshot'];
        $results = [];
        DB::transaction(function () use ($validRows, $snapshot, $shared, $owner, &$results) {
            foreach ($validRows as $row) {
                $alias = $row['final_alias'];
                $title = $row['title'] !== '' ? $row['title'] : null;

                $link = Link::create([
                    'user_id'    => $owner->id,
                    'project_id' => $shared['project_id'],
                    'domain_id'  => $shared['domain_id'],
                    'type'       => Link::TYPE_BIOLINK,
                    'alias'      => $alias,
                    'title'      => $title,
                    'visibility' => 'public',
                    'is_active'  => true,
                ]);

                // Per-row substitution values: data columns plus the reserved
                // handle/alias/title tokens so a blueprint can interpolate them.
                $values = $row['data'];
                $values['handle'] = $alias;
                $values['alias']  = $alias;
                $values['title']  = $row['title'];

                $merged = BiolinkMergeEngine::substitute($snapshot, $values);
                $this->templates->applyPageToLink($link, $merged, true);

                try {
                    $u = auth()->user();
                    \App\Modules\User\Models\FeedEvent::create([
                        'user_id'      => $link->user_id,
                        'type'         => 'link_published',
                        'subject_id'   => $link->id,
                        'subject_type' => Link::class,
                        'data'         => [
                            'title'          => $link->title,
                            'alias'          => $link->alias,
                            'creator_name'   => $u?->name,
                            'creator_avatar' => \App\Support\PublicStorageUrl::resolve($u?->avatar),
                        ],
                        'occurred_at'  => now(),
                    ]);
                } catch (\Throwable $e) {
                    \Log::warning('bulk biolink feed event failed: ' . $e->getMessage());
                }

                $results[] = [
                    'title'      => $link->title ?: $link->alias,
                    'public_url' => $link->getShortUrl(),
                    'alias'      => $link->alias,
                    'status'     => 'created',
                    'error'      => '',
                ];
            }

            $u = auth()->user();
            if ($u && !empty($results)) {
                try {
                    CreatorPostController::notifyFollowersDebounced(
                        $u,
                        'published ' . count($results) . ' new biolink page(s) in bulk'
                    );
                } catch (\Throwable $e) {
                    \Log::warning('bulk biolink followers ping failed: ' . $e->getMessage());
                }
            }
        });

        // Append skipped rows so the downloadable record is complete.
        foreach ($validated as $r) {
            if (!empty($r['errors'])) {
                $results[] = [
                    'title'      => $r['title'] ?: ($r['alias_input'] ?: '—'),
                    'public_url' => '',
                    'alias'      => $r['alias_input'],
                    'status'     => 'skipped',
                    'error'      => implode('; ', $r['errors']),
                ];
            }
        }

        return view('user.links.bulk-biolink-results', [
            'results' => $results,
            'created' => count(array_filter($results, fn ($r) => $r['status'] === 'created')),
            'skipped' => count(array_filter($results, fn ($r) => $r['status'] !== 'created')),
        ]);
    }

    /** Download a starter CSV (header row + one example row) for a blueprint. */
    public function sampleSheet(Request $request)
    {
        $owner = workspace_owner();
        $blueprint = $this->resolveBlueprint($request, $owner, $error);

        $tokens = $blueprint !== null
            ? $this->dataTokens(BiolinkMergeEngine::extractTokens($blueprint['snapshot']))
            : [];

        // Always offer the reserved columns so the user can set alias/title.
        $headers = array_values(array_unique(array_merge(['handle', 'title'], $tokens)));
        $example = array_map(function ($h) {
            return match ($h) {
                'handle' => 'jane-doe',
                'title'  => 'Jane Doe',
                default  => 'Example ' . $h,
            };
        }, $headers);

        $csv = $this->csvLine($headers) . "\r\n" . $this->csvLine($example) . "\r\n";

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="biolink-mail-merge-sample.csv"',
        ]);
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * Resolve the chosen blueprint into a page snapshot. Returns
     * `['snapshot'=>[], 'label'=>'', 'source'=>'template|page', 'ref'=>id]`
     * or null with $error set.
     */
    private function resolveBlueprint(Request $request, User $owner, ?string &$error = null): ?array
    {
        $error = null;
        $source = (string) $request->input('source');

        if ($source === 'template') {
            $id = (int) $request->input('template_id');
            $tpl = $id ? PageTemplate::active()->find($id) : null;
            if (!$tpl) {
                $error = 'Pick a template to use as your blueprint.';
                return null;
            }
            if ($this->isLocked($tpl->plan_tier, $owner->plan?->slug)) {
                $error = 'That template is locked on your current plan. Pick another or upgrade.';
                return null;
            }
            $snapshot = (array) ($tpl->snapshot ?? []);
            if (empty($snapshot['blocks'])) {
                $error = 'That template has no content to personalize.';
                return null;
            }
            return ['snapshot' => $snapshot, 'label' => $tpl->name, 'source' => 'template', 'ref' => $tpl->id];
        }

        if ($source === 'page') {
            $id = (int) $request->input('link_id');
            $link = $id
                ? $owner->links()->whereIn('type', Link::BIOLINK_FAMILY)->find($id)
                : null;
            if (!$link) {
                $error = 'Pick one of your biolink pages to use as your blueprint.';
                return null;
            }
            $snapshot = $this->templates->captureFromLink($link);
            if (empty($snapshot['blocks'])) {
                $error = 'That page has no blocks to personalize yet — add some first.';
                return null;
            }
            return ['snapshot' => $snapshot, 'label' => ($link->title ?: $link->alias), 'source' => 'page', 'ref' => $link->id];
        }

        $error = 'Choose a blueprint to personalize — a template or one of your pages.';
        return null;
    }

    /** Reject the batch up-front if the blueprint uses plan-locked blocks. */
    private function blueprintBlockGate(array $snapshot, User $owner): ?string
    {
        $disallowed = [];
        foreach (BiolinkMergeEngine::collectBlockTypes($snapshot) as $type) {
            if (!$owner->userCanUseBlockType($type)) {
                $disallowed[$type] = true;
                continue;
            }
            if (BlockTypeRegistry::canonical($type) === 'product'
                && !$owner->hasPermission('user.plan_limits.bypass')
                && !$owner->planFeatureEnabled('ecommerce')) {
                $disallowed[$type] = true;
            }
        }
        if (empty($disallowed)) {
            return null;
        }
        return 'This blueprint uses block type(s) not available on your plan: ' . implode(', ', array_keys($disallowed)) . '. Pick another blueprint or upgrade.';
    }

    /** Tokens minus the reserved handle/alias ones (those map to columns). */
    private function dataTokens(array $tokens): array
    {
        return array_values(array_filter($tokens, fn ($t) => !in_array($t, ['handle', 'alias'], true)));
    }

    /** Parse the request's sheet input (paste / CSV / XLSX). */
    private function parseSheet(Request $request): array
    {
        if ($request->hasFile('sheet_file')) {
            return MailMergeSheet::fromUpload($request->file('sheet_file'));
        }
        return MailMergeSheet::fromPaste((string) $request->input('sheet_text', ''));
    }

    /** Map header-keyed sheet rows into the common validation shape. */
    private function normalizeSheetRows(array $sheetRows, array $dataTokens): array
    {
        $rows = [];
        foreach ($sheetRows as $row) {
            $data = [];
            foreach ($dataTokens as $t) {
                $data[$t] = trim((string) ($row[$t] ?? ''));
            }
            $rows[] = [
                'alias_input' => trim((string) ($row['handle'] ?? $row['alias'] ?? '')),
                'title'       => trim((string) ($row['title'] ?? '')),
                'data'        => $data,
            ];
        }
        return $rows;
    }

    /**
     * Per-row validation: alias format/length/banned/uniqueness (vs existing
     * links AND within the batch) plus required-token presence. Auto-generates
     * an alias when the row left it blank. Mirrors LinkController::validateBulkRows.
     */
    private function validateRows(array $rows, array $tokens, User $owner): array
    {
        $limits = $owner->getAliasLengthLimits();
        $aliasPattern = \App\Modules\User\Rules\AliasFormat::REGEX;

        $providedAliases = collect($rows)
            ->pluck('alias_input')
            ->filter(fn ($a) => $a !== '')
            ->map(fn ($a) => strtolower($a))
            ->all();
        $existing = !empty($providedAliases)
            ? Link::whereIn(DB::raw('LOWER(alias)'), $providedAliases)->pluck('alias')->map(fn ($a) => strtolower($a))->all()
            : [];
        $existingSet = array_flip($existing);

        $banned = new NotBannedName();
        $usedInBatch = [];
        $requiredTokens = array_values(array_filter($tokens, fn ($t) => !in_array($t, ['handle', 'alias'], true)));
        $out = [];

        foreach ($rows as $r) {
            $errors = [];
            $aliasInput = trim((string) ($r['alias_input'] ?? ''));
            $title = trim((string) ($r['title'] ?? ''));
            $data = (array) ($r['data'] ?? []);

            if ($title !== '' && mb_strlen($title) > 255) {
                $errors[] = 'Title is too long (max 255 characters).';
            }

            foreach ($requiredTokens as $t) {
                if ($t === 'title') {
                    if ($title === '') {
                        $errors[] = 'Missing value for {{title}}.';
                    }
                    continue;
                }
                if (trim((string) ($data[$t] ?? '')) === '') {
                    $errors[] = 'Missing value for {{' . $t . '}}.';
                }
            }

            $finalAlias = $aliasInput;
            if ($aliasInput !== '') {
                if (!preg_match($aliasPattern, $aliasInput)) {
                    $errors[] = 'Handle may only contain letters, numbers, dots, dashes and underscores.';
                } elseif (mb_strlen($aliasInput) < $limits['min'] || mb_strlen($aliasInput) > $limits['max']) {
                    $errors[] = "Handle must be {$limits['min']}–{$limits['max']} characters.";
                } else {
                    $bannedHit = null;
                    $banned->validate('alias', $aliasInput, function ($msg) use (&$bannedHit) { $bannedHit = $msg; });
                    if ($bannedHit) {
                        $errors[] = 'This handle is reserved and can\'t be used.';
                    } elseif (isset($existingSet[strtolower($aliasInput)])) {
                        $errors[] = 'Handle is already taken.';
                    } elseif (isset($usedInBatch[strtolower($aliasInput)])) {
                        $errors[] = 'Duplicate handle within this batch.';
                    } else {
                        $usedInBatch[strtolower($aliasInput)] = true;
                    }
                }
            } else {
                $finalAlias = Link::generateAlias();
                while (isset($usedInBatch[strtolower($finalAlias)])) {
                    $finalAlias = Link::generateAlias();
                }
                $usedInBatch[strtolower($finalAlias)] = true;
            }

            $out[] = [
                'alias_input' => $aliasInput,
                'title'       => $title,
                'data'        => $data,
                'final_alias' => $finalAlias,
                'errors'      => $errors,
            ];
        }

        return $out;
    }

    /** Normalize the shared options panel (project + domain). */
    private function sharedOptions(Request $request): array
    {
        $owner = workspace_owner();

        $projectId = $request->input('project_id') ? (int) $request->input('project_id') : null;
        if ($projectId && !$owner->projects()->where('id', $projectId)->exists()) {
            $projectId = null;
        }

        $domainId = $request->input('domain_id') ? (int) $request->input('domain_id') : null;
        if ($domainId) {
            $allowed = \App\Modules\User\Models\Domain::availableTo($request->user())->pluck('id')->all();
            if (!in_array($domainId, $allowed, true)) {
                $domainId = null;
            }
        }

        return ['project_id' => $projectId, 'domain_id' => $domainId];
    }

    /** Flash a soft warning when the batch would exceed plan quotas. */
    private function flashBatchQuota(User $owner, int $validCount): void
    {
        if ($validCount <= 0) {
            return;
        }
        $planFeatures = $owner->plan?->features ?? [];

        $maxLinks = $planFeatures['max_links'] ?? 5;
        if ($maxLinks !== -1) {
            $current = $owner->links()->count();
            if (($current + $validCount) > $maxLinks) {
                $remaining = max(0, $maxLinks - $current);
                session()->flash('error', "This batch would exceed your plan's link limit ({$maxLinks}). You can create at most {$remaining} more — skip rows or upgrade.");
                return;
            }
        }

        $maxBiolinks = $planFeatures['max_biolinks'] ?? 1;
        if ($maxBiolinks !== -1) {
            $currentBio = $owner->links()->whereIn('type', Link::BIOLINK_FAMILY)->count();
            if (($currentBio + $validCount) > $maxBiolinks) {
                $remaining = max(0, $maxBiolinks - $currentBio);
                session()->flash('error', "This batch would exceed your plan's biolink page limit ({$maxBiolinks}). You can create at most {$remaining} more — skip rows or upgrade.");
            }
        }
    }

    /**
     * Whether a template's required plan tier outranks the user's plan.
     * Mirrors LinkTemplateController::isLocked (compare by Plan sort_order).
     */
    private function isLocked(?string $required, ?string $userPlan): bool
    {
        if (empty($required)) {
            return false;
        }
        $ranks = Plan::pluck('sort_order', 'slug');
        $req = $ranks[$required] ?? PHP_INT_MAX;
        $cur = $userPlan ? ($ranks[$userPlan] ?? -1) : -1;
        return $cur < $req;
    }

    /** RFC-4180-ish CSV line builder for the sample download. */
    private function csvLine(array $fields): string
    {
        return implode(',', array_map(function ($v) {
            $s = (string) $v;
            return preg_match('/[",\r\n]/', $s) ? '"' . str_replace('"', '""', $s) . '"' : $s;
        }, $fields));
    }
}
