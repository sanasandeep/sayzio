<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Addon;
use App\Modules\Admin\Models\Plan;
use App\Modules\Admin\Models\PlanImportSnapshot;
use App\Modules\Admin\Support\PlanCsvSchema;
use App\Modules\Admin\Support\PlanWriter;
use App\Modules\Common\Support\PlanFormCatalogue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlanController extends Controller
{
    public function __construct(private PlanWriter $writer)
    {
    }

    public function index()
    {
        // Active lineup first (the current 7-plan editor surface). Archived
        // legacy plans are kept for historical subscribers but listed in a
        // separate, collapsed section so the main editor shows exactly the
        // active lineup.
        $plans = Plan::withCount('users')->with('prices')->where('is_archived', false)->ordered()->get();
        $archivedPlans = Plan::withCount('users')->with('prices')->where('is_archived', true)->ordered()->get();

        // Recent CSV imports (undo history). The single most-recent un-reverted
        // one is the only revertable entry (see revertImport()).
        $imports = PlanImportSnapshot::latest('id')->limit(10)->get();
        $revertableId = optional($imports->firstWhere('reverted_at', null))->id;

        return view('admin.plans.index', compact('plans', 'archivedPlans', 'imports', 'revertableId'));
    }

    public function show(Plan $plan)
    {
        $plan->loadCount('users');
        return view('admin.plans.show', compact('plan'));
    }

    public function create()
    {
        $addons = Addon::ordered()->get();
        $attachedAddonIds = [];
        return view('admin.plans.create', compact('addons', 'attachedAddonIds'));
    }

    public function archive(Plan $plan)
    {
        $plan->update(['is_archived' => !$plan->is_archived]);
        \App\Modules\Common\Support\PricingPageCache::flush();
        return back()->with('success', $plan->is_archived
            ? 'Plan archived. Existing subscribers continue, but new signups can no longer pick it.'
            : 'Plan restored.');
    }

    /**
     * Deep-copy an existing plan as an editable starting point. The copy
     * is defensively created as internal + inactive (and never "popular"
     * or "default") so it can never accidentally go live or appear on a
     * public surface before the admin has reviewed it. The features blob
     * and the polymorphic price rows are both carried over.
     */
    public function duplicate(Plan $plan)
    {
        $clone = $this->writer->duplicate($plan);

        return redirect()->route('admin.plans.edit', $clone)
            ->with('success', 'Plan duplicated. The copy is internal (admin-only) and inactive — review and activate it when ready.');
    }

    public function store(Request $request)
    {
        $this->writer->createFromRequest($request);

        return redirect()->route('admin.plans.index')->with('success', 'Plan created successfully.');
    }

    public function edit(Plan $plan)
    {
        $addons = Addon::ordered()->get();
        $attachedAddonIds = $plan->addons()->pluck('addons.id')->all();
        return view('admin.plans.edit', compact('plan', 'addons', 'attachedAddonIds'));
    }

    public function update(Request $request, Plan $plan)
    {
        $this->writer->updateFromRequest($request, $plan);

        return redirect()->route('admin.plans.index')->with('success', 'Plan updated successfully.');
    }

    // ======================== COMPARE & EDIT ========================

    /**
     * Render the side-by-side compare & edit grid for 2–6 selected plans.
     * Plan IDs are passed as ?ids[]=1&ids[]=2 query parameters from the index.
     */
    public function compareView(Request $request): \Illuminate\Http\RedirectResponse|\Illuminate\Contracts\View\View
    {
        $ids = array_values(array_filter(array_map('intval', (array) $request->input('ids', []))));

        if (count($ids) < 2) {
            return redirect()->route('admin.plans.index')->with('error', 'Select at least 2 plans to compare.');
        }
        if (count($ids) > 6) {
            return redirect()->route('admin.plans.index')->with('error', 'Select at most 6 plans to compare at once.');
        }

        $plans = Plan::with(['prices', 'addons'])->whereIn('id', $ids)->orderBy('sort_order')->get();

        if ($plans->count() < 2) {
            return redirect()->route('admin.plans.index')->with('error', 'Could not load the selected plans.');
        }

        return view('admin.plans.compare', compact('plans'));
    }

    /**
     * Accept per-plan changes submitted from the compare & edit grid, validate
     * each through the standard PlanWriter path (same rules, price-sync and
     * cache flush as single-plan editing), and persist all valid plans in one
     * transaction. Returns JSON so the Alpine component can flag problem cells.
     */
    public function bulkSave(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate(['plans' => 'required|array', 'plans.*' => 'array']);

        $errors   = [];
        $okPlans  = [];
        $payloads = [];

        // First pass: load plans + validate — outside the transaction so a bad
        // plan doesn't roll back the already-validated ones.
        foreach ($request->input('plans') as $rawId => $changes) {
            $id   = (int) $rawId;
            $plan = Plan::with(['prices', 'addons'])->find($id);
            if (!$plan) {
                $errors[$id] = ['_plan' => ['Plan not found.']];
                continue;
            }

            // Optimistic-concurrency check: the compare page embeds each
            // plan's updated_at at load time. If the plan has been modified
            // since (by another admin or elsewhere), reject the save for this
            // plan instead of silently clobbering the newer edit.
            $loadedAt  = isset($changes['_loaded_at']) ? (string) $changes['_loaded_at'] : null;
            $currentAt = $plan->updated_at?->toISOString();
            if ($loadedAt !== null && $loadedAt !== '' && $currentAt !== null && $loadedAt !== $currentAt) {
                $errors[$id] = ['_plan' => ['This plan was changed since you loaded the page. Reload to see the latest values before saving.']];
                continue;
            }

            $payload = $this->buildSyntheticPayload($plan, (array) $changes);
            $synReq  = \Illuminate\Http\Request::create('', 'POST', $payload);

            try {
                $synReq->validate($this->writer->rules());
                $okPlans[$id]  = $plan;
                $payloads[$id] = $payload;
            } catch (\Illuminate\Validation\ValidationException $e) {
                $errors[$id] = $e->errors();
            }
        }

        // Second pass: write all valid plans atomically.
        $updated = 0;
        $savedAt = [];
        if (!empty($okPlans)) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($okPlans, $payloads, &$updated, &$savedAt) {
                foreach ($okPlans as $id => $plan) {
                    $synReq = \Illuminate\Http\Request::create('', 'POST', $payloads[$id]);
                    $this->writer->updateFromRequest($synReq, $plan);
                    $savedAt[$id] = $plan->fresh()?->updated_at?->toISOString();
                    $updated++;
                }
            });
            \App\Modules\Common\Support\PricingPageCache::flush();
        }

        return response()->json([
            'ok'       => $updated > 0,
            'updated'  => $updated,
            'errors'   => $errors,
            'saved_at' => $savedAt,
        ], ($errors && !$updated) ? 422 : 200);
    }

    /**
     * Build a complete request payload for one plan by merging the compare-
     * view's submitted changes onto the plan's existing state. The result is
     * suitable for passing to PlanWriter::updateFromRequest(), which expects
     * a full form payload (all required fields present, block/integration
     * settings at the top level, etc.).
     *
     * Keys managed outside the features blob (block_types_allowed,
     * integration_providers_allowed) are extracted from the existing plan and
     * re-emitted at the top level so collectFeatures() finds them correctly.
     */
    private function buildSyntheticPayload(Plan $plan, array $changes): array
    {
        $usdMonthly = (int) round(((float) $plan->monthly_price) * 100);
        $usdAnnual  = (int) round(((float) $plan->annual_price) * 100);
        $inrMonthly = (int) round(((float) $plan->monthly_price_secondary) * 100);
        $inrAnnual  = (int) round(((float) $plan->annual_price_secondary) * 100);

        $existing  = $plan->features ?? [];
        $submitted = array_diff_key(
            (array) ($changes['features'] ?? []),
            array_flip(['block_types_allowed', 'integration_providers_allowed', 'integration_accounts_max'])
        );

        // Merge submitted changes onto the full existing features blob so keys
        // the compare UI does not show (e.g. referral fields, upload_limits)
        // are preserved untouched.
        $merged = array_merge($existing, $submitted);
        unset($merged['block_types_allowed'], $merged['integration_providers_allowed'], $merged['integration_accounts_max']);

        $blockAllowed = $existing['block_types_allowed'] ?? '*';
        $intAllowed   = is_array($existing['integration_providers_allowed'] ?? null)
                        ? $existing['integration_providers_allowed'] : [];
        $blockMode    = ($blockAllowed === '*' || $blockAllowed === null) ? 'all' : 'pick';
        $blockTypes   = is_array($blockAllowed) ? $blockAllowed : [];

        $providerMode = [];
        foreach (array_keys(\App\Modules\User\Support\IntegrationConfigRegistry::kinds()) as $kind) {
            $providerMode[$kind] = (($intAllowed[$kind] ?? '*') === '*') ? 'all' : 'pick';
        }

        return [
            'name'                          => $changes['name']                    ?? $plan->name,
            'description'                   => $changes['description']             ?? ($plan->description ?? ''),
            'monthly_price'                 => $changes['monthly_price']           ?? $usdMonthly,
            'annual_price'                  => $changes['annual_price']            ?? $usdAnnual,
            'monthly_price_secondary'       => $changes['monthly_price_secondary'] ?? $inrMonthly,
            'annual_price_secondary'        => $changes['annual_price_secondary']  ?? $inrAnnual,
            'trial_days'                    => $changes['trial_days']              ?? (int) $plan->trial_days,
            'grace_days'                    => $changes['grace_days']              ?? (int) $plan->grace_days,
            'refund_window_days'            => $changes['refund_window_days']      ?? (int) $plan->refund_window_days,
            'status'                        => $changes['status']                  ?? $plan->status,
            'sort_order'                    => $changes['sort_order']              ?? (int) $plan->sort_order,
            'is_popular'                    => isset($changes['is_popular'])
                                               ? ($changes['is_popular'] ? '1' : '0')
                                               : ($plan->is_popular ? '1' : '0'),
            'is_internal'                   => isset($changes['is_internal'])
                                               ? ($changes['is_internal'] ? '1' : '0')
                                               : ($plan->is_internal ? '1' : '0'),
            'features'                      => $merged,
            'block_mode'                    => $blockMode,
            'block_types_allowed'           => $blockTypes,
            'addon_ids'                     => $plan->addons()->pluck('addons.id')->all(),
            'intro_discount'                => $plan->intro_discount ?? [],
            'provider_mode'                 => $providerMode,
            'integration_providers_allowed' => $intAllowed,
        ];
    }

    // ======================== END COMPARE & EDIT ========================

    public function destroy(Plan $plan)
    {
        if ($plan->users()->count() > 0) {
            return back()->with('error', 'Cannot delete a plan that has active users.');
        }

        $plan->delete();
        \App\Modules\Common\Support\PricingPageCache::flush();
        return redirect()->route('admin.plans.index')->with('success', 'Plan deleted successfully.');
    }

    /**
     * Stream all plans (active + archived) as a single Excel-compatible CSV.
     *
     * Columns: core plan attributes → pricing (USD + INR × monthly + annual
     * from the authoritative `prices` table) → every module flag → every
     * quantity limit → every feature flag → every AI suite flag → AI coin
     * multipliers → referral program fields. Human-readable column headers
     * are sourced from PlanFormCatalogue / PremiumFeatures so the file is
     * understandable without knowing internal keys.
     */
    public function export(): StreamedResponse
    {
        $plans = Plan::with('prices')->ordered()->get();

        // The column specification (headers + value formatting) is shared with
        // the importer via PlanCsvSchema so the exported file round-trips.
        $columns = PlanCsvSchema::columns();

        // ---- Stream the CSV ----
        $safe = static function (mixed $value): string {
            $s = (string) $value;
            if ($s !== '' && in_array($s[0], ['=', '+', '-', '@'], true)) {
                return "'" . $s;
            }
            return $s;
        };

        $filename = 'pricing-plans-' . now()->toDateString() . '.csv';

        return response()->stream(function () use ($columns, $plans, $safe) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel auto-detects the encoding.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, array_column($columns, 'header'));

            foreach ($plans as $plan) {
                $row = [];
                foreach ($columns as $col) {
                    $row[] = $safe(($col['export'])($plan));
                }
                fputcsv($out, $row);
            }

            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }

    /**
     * Parse an uploaded pricing-plans CSV (matching the export format) and
     * render a per-plan diff preview before anything is written. Rows are
     * matched to existing plans by their **Slug** column; unknown slugs are
     * surfaced as error rows. Each recognised cell is validated (required
     * fields, known Yes/No / numeric / select values, numeric ranges) and
     * only cells that actually change a value show up in the diff.
     *
     * Nothing is persisted here — the admin reviews the diff and confirms
     * via {@see importCommit()}. The raw CSV is carried forward in a hidden
     * field so the commit step re-parses and re-validates from scratch
     * (never trusting a client-supplied change set).
     */
    public function importPreview(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:4096'],
        ]);

        $raw = (string) file_get_contents($request->file('file')->getRealPath());

        $result = $this->analyseImportCsv($raw);

        if ($result['fatal']) {
            return back()->with('error', $result['fatal']);
        }

        return view('admin.plans.import', [
            'rows'             => $result['rows'],
            'unknownColumns'   => $result['unknownColumns'],
            'rawCsv'           => $raw,
            'changedCount'     => $result['changedCount'],
            'createCount'      => $result['createCount'],
            'errorCount'       => $result['errorCount'],
            'unknownCount'     => $result['unknownCount'],
            'unchangedCount'   => $result['unchangedCount'],
        ]);
    }

    /**
     * Apply a previously-previewed import. The CSV is re-parsed and
     * re-validated from the hidden `csv` field, then every row that matches a
     * plan by slug and carries at least one valid change is updated inside a
     * single transaction (features merged, prices synced). Rows with unknown
     * slugs or validation errors are skipped.
     */
    public function importCommit(Request $request)
    {
        $request->validate([
            'csv'            => ['required', 'string', 'max:2000000'],
            'create_slugs'   => ['nullable', 'array'],
            'create_slugs.*' => ['string'],
        ]);

        $raw    = (string) $request->input('csv');
        $result = $this->analyseImportCsv($raw);

        if ($result['fatal']) {
            return redirect()->route('admin.plans.index')->with('error', $result['fatal']);
        }

        // Capture the full before-state of EVERY plan (not just the changed
        // rows): applyImportRow() has side-effects — flipping is_popular /
        // is_default OFF on sibling plans — so an undo must be able to restore
        // plans that never appeared in the CSV diff. Plans are few, so this is
        // cheap and keeps revert exact.
        $before = [];
        foreach (Plan::with('prices')->get() as $plan) {
            $before[$plan->id] = $this->snapshotPlanState($plan);
        }

        // Which new-plan rows the admin opted in to create. Creation is
        // opt-in: a "create" row is only persisted when its slug is ticked.
        $createSet = array_flip(array_map('strval', (array) $request->input('create_slugs', [])));

        $updated = 0;
        $created = 0;
        $changed = [];
        \Illuminate\Support\Facades\DB::transaction(function () use ($result, $createSet, &$updated, &$created, &$changed) {
            foreach ($result['rows'] as $row) {
                if ($row['status'] === 'update' && !empty($row['plan'])) {
                    $this->applyImportRow($row['plan'], $row);
                    $updated++;
                    $changed[] = ['slug' => $row['slug'], 'name' => $row['name']];
                } elseif ($row['status'] === 'create' && isset($createSet[$row['slug']])) {
                    $plan = $this->applyCreateRow($row['slug'], $row);
                    $created++;
                    // Record the created plan's id so revert can DELETE it —
                    // created plans aren't in $before, so restoring existing
                    // plans alone would leave them behind.
                    $changed[] = ['slug' => $plan->slug, 'name' => $plan->name, 'created' => true, 'id' => $plan->id];
                }
            }
        });

        $skipped = $result['errorCount'] + $result['unknownCount'];

        if ($updated > 0 || $created > 0) {
            \App\Modules\Common\Support\PricingPageCache::flush();
        }

        // Only record an undo point when something actually changed. Reverting
        // restores every updated plan's prior state AND deletes any plan this
        // import created (tracked via the `created` flag in the change log).
        if ($updated > 0 || $created > 0) {
            $admin = Auth::guard('admin')->user();
            PlanImportSnapshot::create([
                'admin_id'      => $admin?->id,
                'admin_name'    => $admin?->name,
                'plans_updated' => $updated,
                'rows_skipped'  => $skipped,
                'changed'       => array_values($changed),
                'snapshot'      => array_values($before),
            ]);
        }

        $parts = [];
        if ($updated > 0) { $parts[] = "{$updated} plan(s) updated"; }
        if ($created > 0) { $parts[] = "{$created} plan(s) created"; }

        return redirect()->route('admin.plans.index')->with(
            'success',
            !empty($parts)
                ? 'Imported plan changes: ' . implode(', ', $parts)
                    . ($skipped > 0 ? ", {$skipped} row(s) skipped." : '.')
                    . ' You can undo this from Import history below.'
                : 'No plans were updated or created (no matching changes or opted-in new plans).'
        );
    }

    /**
     * Revert the most recent (not-yet-reverted) plan CSV import, restoring
     * every plan to the exact state captured just before that import ran.
     * Each import can be undone only once; the snapshot row is stamped so it
     * cannot be replayed. Reverting the LATEST import only is intentional —
     * older imports may sit on top of state this restore would overwrite.
     */
    public function revertImport(Request $request, PlanImportSnapshot $snapshot)
    {
        if ($snapshot->isReverted()) {
            return redirect()->route('admin.plans.index')
                ->with('error', 'That import has already been reverted.');
        }

        // Guard against reverting a stale import out of order: only the most
        // recent un-reverted snapshot can be undone.
        $latest = PlanImportSnapshot::whereNull('reverted_at')->latest('id')->first();
        if (!$latest || $latest->id !== $snapshot->id) {
            return redirect()->route('admin.plans.index')
                ->with('error', 'Only the most recent import can be reverted.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($snapshot) {
            foreach (($snapshot->snapshot ?? []) as $state) {
                $plan = Plan::find($state['id'] ?? null);
                if (!$plan) {
                    continue; // plan deleted since the import — nothing to restore
                }
                $this->restorePlanState($plan, $state);
            }

            // Delete any plans this import CREATED — they aren't in the
            // before-snapshot, so restoring existing plans can't remove them.
            foreach (($snapshot->changed ?? []) as $entry) {
                if (!empty($entry['created']) && !empty($entry['id'])) {
                    \App\Modules\Admin\Models\Price::where('priceable_type', Plan::class)
                        ->where('priceable_id', $entry['id'])->delete();
                    Plan::where('id', $entry['id'])->delete();
                }
            }

            $admin = Auth::guard('admin')->user();
            $snapshot->forceFill([
                'reverted_at'      => now(),
                'reverted_by'      => $admin?->id,
                'reverted_by_name' => $admin?->name,
            ])->save();
        });

        \App\Modules\Common\Support\PricingPageCache::flush();

        return redirect()->route('admin.plans.index')
            ->with('success', 'Reverted the last import. Plans were restored to their previous values.');
    }

    /**
     * Capture the complete restorable state of one plan: every core column,
     * the features blob, and the four authoritative price rows (minor units).
     * This is the undo payload for a single plan.
     *
     * @return array<string,mixed>
     */
    private function snapshotPlanState(Plan $plan): array
    {
        $prices = [];
        foreach ($plan->prices as $pr) {
            $prices[] = [
                'currency' => $pr->currency,
                'cycle'    => $pr->billing_cycle,
                'minor'    => (int) $pr->amount_minor_units,
            ];
        }

        return [
            'id'   => $plan->id,
            'slug' => $plan->slug,
            'name' => $plan->name,
            'core' => [
                'name'                    => $plan->name,
                'slug'                    => $plan->slug,
                'description'             => $plan->description,
                'status'                  => $plan->status,
                'is_default'              => (bool) $plan->is_default,
                'is_popular'              => (bool) $plan->is_popular,
                'is_internal'             => (bool) $plan->is_internal,
                'is_archived'             => (bool) $plan->is_archived,
                'sort_order'              => (int) $plan->sort_order,
                'monthly_price'           => (string) $plan->monthly_price,
                'annual_price'            => (string) $plan->annual_price,
                'monthly_price_secondary' => (string) $plan->monthly_price_secondary,
                'annual_price_secondary'  => (string) $plan->annual_price_secondary,
            ],
            'features' => $plan->features ?? [],
            'prices'   => $prices,
        ];
    }

    /**
     * Restore one plan from a {@see snapshotPlanState()} payload. Core columns,
     * the features blob and the price table are all written back verbatim. The
     * "only one popular / default" invariant is naturally preserved because the
     * snapshot restores every plan's exact prior flags — so no auto-flip
     * side-effect is applied here (that would corrupt a faithful restore).
     */
    private function restorePlanState(Plan $plan, array $state): void
    {
        $core = $state['core'] ?? [];
        foreach ($core as $key => $value) {
            $plan->{$key} = $value;
        }
        $plan->features = $state['features'] ?? [];
        $plan->save();

        // Rebuild the four required minor-unit price slots from the snapshot,
        // falling back to the restored decimal columns for any missing slot.
        $minor = [];
        foreach (($state['prices'] ?? []) as $pr) {
            $minor[$pr['currency'] . '_' . $pr['cycle']] = (int) $pr['minor'];
        }

        $this->writer->syncPriceTable($plan, [
            'monthly_price'           => $minor['USD_monthly'] ?? (int) round(((float) $plan->monthly_price) * 100),
            'annual_price'            => $minor['USD_annual']  ?? (int) round(((float) $plan->annual_price) * 100),
            'monthly_price_secondary' => $minor['INR_monthly'] ?? (int) round(((float) $plan->monthly_price_secondary) * 100),
            'annual_price_secondary'  => $minor['INR_annual']  ?? (int) round(((float) $plan->annual_price_secondary) * 100),
        ]);
    }

    /**
     * Shared CSV parse + diff engine used by both the preview and the commit
     * steps. Returns the per-plan rows (with resolved Plan models, computed
     * changes and validation errors), plus summary counts and a fatal error
     * message when the file is structurally unusable.
     *
     * @return array{
     *   fatal:?string,
     *   rows:array<int,array<string,mixed>>,
     *   unknownColumns:array<int,string>,
     *   changedCount:int, errorCount:int, unknownCount:int, unchangedCount:int
     * }
     */
    private function analyseImportCsv(string $raw): array
    {
        $empty = [
            'fatal' => null, 'rows' => [], 'unknownColumns' => [],
            'changedCount' => 0, 'createCount' => 0, 'errorCount' => 0, 'unknownCount' => 0, 'unchangedCount' => 0,
        ];

        // Strip a UTF-8 BOM if Excel added one.
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;

        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];
        // Drop trailing blank lines.
        while (!empty($lines) && trim(end($lines)) === '') {
            array_pop($lines);
        }
        if (count($lines) < 2) {
            return array_merge($empty, ['fatal' => 'The file has no data rows. Export the current plans, edit them, then upload.']);
        }

        $header = str_getcsv(array_shift($lines));
        $header = array_map(fn ($h) => trim((string) $h), $header);

        $columns    = PlanCsvSchema::columns();
        $byHeader   = [];
        foreach ($columns as $col) {
            $byHeader[$col['header']] = $col;
        }
        $matchHeader = PlanCsvSchema::matchHeader();

        // Map each CSV column index → schema column (skip the match column and
        // unrecognised headers). Track unknown headers to warn the admin.
        $indexMap       = [];
        $matchIndex     = null;
        $unknownColumns = [];
        foreach ($header as $i => $h) {
            if ($h === '') { continue; }
            if ($h === $matchHeader) { $matchIndex = $i; continue; }
            if (isset($byHeader[$h])) {
                $indexMap[$i] = $byHeader[$h];
            } else {
                $unknownColumns[] = $h;
            }
        }

        if ($matchIndex === null) {
            return array_merge($empty, ['fatal' => "The file is missing the required \"{$matchHeader}\" column. Upload a file exported from this page."]);
        }

        // Snapshot all plans by slug once (active + archived), with prices.
        $plansBySlug = Plan::with('prices')->get()->keyBy('slug');

        $rows           = [];
        $changedCount   = 0;
        $createCount    = 0;
        $errorCount     = 0;
        $unknownCount   = 0;
        $unchangedCount = 0;

        // Slugs already claimed by an earlier "create" row in THIS file, so a
        // duplicate row can be flagged instead of silently colliding on insert.
        $seenCreateSlugs = [];

        foreach ($lines as $lineNo => $line) {
            if (trim($line) === '') { continue; }
            $cells = str_getcsv($line);

            $slugRaw = trim((string) ($cells[$matchIndex] ?? ''));
            $slug    = ltrim($slugRaw, "'");

            $rowName = '';
            // Best-effort name for display (first 'Name' column if present).
            foreach ($indexMap as $i => $col) {
                if ($col['key'] === 'name') {
                    $rowName = trim((string) ($cells[$i] ?? ''));
                    break;
                }
            }

            if ($slug === '') {
                $rows[] = [
                    'status' => 'unknown', 'slug' => '(blank)', 'name' => $rowName,
                    'plan' => null, 'changes' => [], 'errors' => ['Missing slug — cannot match a plan.'],
                ];
                $unknownCount++;
                continue;
            }

            /** @var Plan|null $plan */
            $plan = $plansBySlug->get($slug);
            if (!$plan) {
                // Unmatched slug → offer the row as a brand-new plan (opt-in).
                // Every mapped cell is parsed/validated exactly as for updates;
                // blank optional cells fall back to create-time defaults rather
                // than "leave unchanged". Cell errors (incl. a missing Name)
                // turn the row into an error the admin can fix and re-upload.
                $changes = [];
                $errors  = [];
                $apply   = [];
                $newName = '';
                foreach ($indexMap as $i => $col) {
                    $rawCell = $cells[$i] ?? '';
                    $parsed  = PlanCsvSchema::parseCell($col, $rawCell);

                    if ($parsed['error'] !== null) {
                        $errors[] = "{$col['header']}: {$parsed['error']}";
                        continue;
                    }
                    if ($parsed['skip']) {
                        continue;
                    }
                    if ($col['key'] === 'name') {
                        $newName = (string) $parsed['value'];
                    }
                    $changes[] = [
                        'label' => $col['header'],
                        'old'   => '—',
                        'new'   => $parsed['canonical'],
                    ];
                    $apply[] = ['col' => $col, 'value' => $parsed['value']];
                }

                // A plan cannot be created without a name. parseCell already
                // flags a blank Name cell; this also covers files whose Name
                // column is missing entirely (so no Name cell is ever parsed).
                $hasNameError = false;
                foreach ($errors as $e) {
                    if (str_starts_with($e, 'Name:')) { $hasNameError = true; break; }
                }
                if ($newName === '' && !$hasNameError) {
                    $errors[] = 'Name: is required to create a new plan';
                }

                if (isset($seenCreateSlugs[$slug])) {
                    $errors[] = "Duplicate slug \"{$slug}\" appears more than once in this file.";
                }
                $seenCreateSlugs[$slug] = true;

                if (!empty($errors)) {
                    $rows[] = [
                        'status' => 'error', 'slug' => $slug, 'name' => $newName ?: $rowName,
                        'plan' => null, 'changes' => $changes, 'errors' => $errors, 'apply' => [],
                    ];
                    $errorCount++;
                    continue;
                }

                $rows[] = [
                    'status' => 'create', 'slug' => $slug, 'name' => $newName,
                    'plan' => null, 'changes' => $changes, 'errors' => [], 'apply' => $apply,
                ];
                $createCount++;
                continue;
            }

            $changes = [];
            $errors  = [];
            $apply   = [];
            foreach ($indexMap as $i => $col) {
                $rawCell = $cells[$i] ?? '';
                $parsed  = PlanCsvSchema::parseCell($col, $rawCell);

                if ($parsed['error'] !== null) {
                    $errors[] = "{$col['header']}: {$parsed['error']}";
                    continue;
                }
                if ($parsed['skip']) {
                    continue;
                }

                $old = (string) ($col['export'])($plan);
                if ($parsed['canonical'] === $old) {
                    continue; // no change
                }

                $changes[] = [
                    'label' => $col['header'],
                    'old'   => $old === '' ? '—' : $old,
                    'new'   => $parsed['canonical'],
                ];
                $apply[] = ['col' => $col, 'value' => $parsed['value']];
            }

            if (!empty($errors)) {
                $rows[] = [
                    'status' => 'error', 'slug' => $slug, 'name' => $plan->name,
                    'plan' => $plan, 'changes' => $changes, 'errors' => $errors, 'apply' => [],
                ];
                $errorCount++;
                continue;
            }

            if (empty($changes)) {
                $rows[] = [
                    'status' => 'unchanged', 'slug' => $slug, 'name' => $plan->name,
                    'plan' => $plan, 'changes' => [], 'errors' => [], 'apply' => [],
                ];
                $unchangedCount++;
                continue;
            }

            $rows[] = [
                'status' => 'update', 'slug' => $slug, 'name' => $plan->name,
                'plan' => $plan, 'changes' => $changes, 'errors' => [], 'apply' => $apply,
            ];
            $changedCount++;
        }

        return [
            'fatal'          => null,
            'rows'           => $rows,
            'unknownColumns' => array_values(array_unique($unknownColumns)),
            'changedCount'   => $changedCount,
            'createCount'    => $createCount,
            'errorCount'     => $errorCount,
            'unknownCount'   => $unknownCount,
            'unchangedCount' => $unchangedCount,
        ];
    }

    /**
     * Persist one validated import row onto its plan: core attributes are set
     * directly, feature keys are merged into the existing features blob (so
     * columns the file omits are preserved), and price columns are collected
     * into the four minor-unit slots and synced through PlanWriter. The
     * "only one popular / default plan" invariant is preserved.
     */
    private function applyImportRow(Plan $plan, array $row): void
    {
        $features = $plan->features ?? [];

        // Current price minor units keyed by "CUR_cycle" so untouched price
        // columns keep their existing value.
        $minor = [];
        foreach ($plan->prices as $pr) {
            $minor[$pr->currency . '_' . $pr->billing_cycle] = (int) $pr->amount_minor_units;
        }

        foreach ($row['apply'] as $entry) {
            $col   = $entry['col'];
            $value = $entry['value'];

            if ($col['group'] === 'core') {
                $plan->{$col['key']} = $value;
            } elseif ($col['group'] === 'price') {
                $minor[$col['currency'] . '_' . $col['cycle']] = (int) $value;
            } else { // feature
                if ($col['type'] === 'yesno') {
                    $features[$col['key']] = (bool) $value;
                } else {
                    $features[$col['key']] = $value;
                }
            }
        }

        // Stats-history floor mirrors PlanWriter::collectFeatures().
        if (array_key_exists('stats_retention_days', $features)) {
            $retention = (int) $features['stats_retention_days'];
            if ($retention !== -1 && $retention < 30) {
                $retention = 30;
            }
            $features['stats_retention_days'] = $retention;
        }

        $plan->features = $features;

        // Keep legacy decimal columns in step with the four required prices.
        $monthlyUsd = $minor['USD_monthly'] ?? (int) round(((float) $plan->monthly_price) * 100);
        $annualUsd  = $minor['USD_annual']  ?? (int) round(((float) $plan->annual_price) * 100);
        $monthlyInr = $minor['INR_monthly'] ?? (int) round(((float) $plan->monthly_price_secondary) * 100);
        $annualInr  = $minor['INR_annual']  ?? (int) round(((float) $plan->annual_price_secondary) * 100);

        $plan->monthly_price           = $monthlyUsd / 100;
        $plan->annual_price            = $annualUsd / 100;
        $plan->monthly_price_secondary = $monthlyInr / 100;
        $plan->annual_price_secondary  = $annualInr / 100;

        $plan->save();

        if ($plan->is_popular) {
            Plan::where('id', '!=', $plan->id)->where('is_popular', true)->update(['is_popular' => false]);
        }
        if ($plan->is_default) {
            Plan::where('id', '!=', $plan->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $this->writer->syncPriceTable($plan, [
            'monthly_price'           => $monthlyUsd,
            'annual_price'            => $annualUsd,
            'monthly_price_secondary' => $monthlyInr,
            'annual_price_secondary'  => $annualInr,
        ]);
    }

    /**
     * Create a brand-new plan from an opted-in "create" import row. The row's
     * validated cells are layered over a safe baseline: the features blob
     * starts from {@see defaultFeatures()} so omitted columns get sane values,
     * and — mirroring {@see PlanWriter::duplicate()} — the plan is born
     * inactive + internal so a stray row can never publish a live/public plan
     * unless its Status / Internal columns explicitly say so. Prices are synced
     * through PlanWriter and the single-popular / single-default invariant is
     * preserved.
     */
    private function applyCreateRow(string $slug, array $row): Plan
    {
        $features = $this->defaultFeatures();

        // Safe baseline for the core attributes; the row's own cells override.
        $core = [
            'status'      => 'inactive',
            'is_default'  => false,
            'is_popular'  => false,
            'is_internal' => true,
            'is_archived' => false,
            'sort_order'  => 0,
            'name'        => $row['name'] ?? '',
        ];

        $minor = ['USD_monthly' => 0, 'USD_annual' => 0, 'INR_monthly' => 0, 'INR_annual' => 0];

        foreach ($row['apply'] as $entry) {
            $col   = $entry['col'];
            $value = $entry['value'];

            if ($col['group'] === 'core') {
                $core[$col['key']] = $value;
            } elseif ($col['group'] === 'price') {
                $minor[$col['currency'] . '_' . $col['cycle']] = (int) $value;
            } else { // feature
                $features[$col['key']] = $col['type'] === 'yesno' ? (bool) $value : $value;
            }
        }

        // Stats-history floor mirrors PlanWriter::collectFeatures().
        if (array_key_exists('stats_retention_days', $features)) {
            $retention = (int) $features['stats_retention_days'];
            if ($retention !== -1 && $retention < 30) {
                $retention = 30;
            }
            $features['stats_retention_days'] = $retention;
        }

        $plan = new Plan();
        $plan->name        = (string) $core['name'];
        // The CSV slug is unmatched by construction; uniqueSlug normalises it
        // and guards against an in-transaction collision with a prior create.
        $plan->slug        = $this->writer->uniqueSlug($slug);
        $plan->status      = $core['status'];
        $plan->is_default  = (bool) $core['is_default'];
        $plan->is_popular  = (bool) $core['is_popular'];
        $plan->is_internal = (bool) $core['is_internal'];
        $plan->is_archived = (bool) $core['is_archived'];
        $plan->sort_order  = (int) $core['sort_order'];
        $plan->features    = $features;

        $plan->monthly_price           = $minor['USD_monthly'] / 100;
        $plan->annual_price            = $minor['USD_annual'] / 100;
        $plan->monthly_price_secondary = $minor['INR_monthly'] / 100;
        $plan->annual_price_secondary  = $minor['INR_annual'] / 100;

        $plan->save();

        if ($plan->is_popular) {
            Plan::where('id', '!=', $plan->id)->where('is_popular', true)->update(['is_popular' => false]);
        }
        if ($plan->is_default) {
            Plan::where('id', '!=', $plan->id)->where('is_default', true)->update(['is_default' => false]);
        }

        $this->writer->syncPriceTable($plan, [
            'monthly_price'           => $minor['USD_monthly'],
            'annual_price'            => $minor['USD_annual'],
            'monthly_price_secondary' => $minor['INR_monthly'],
            'annual_price_secondary'  => $minor['INR_annual'],
        ]);

        return $plan;
    }

    /**
     * Sane defaults for a brand-new plan. Mirrors what the seeder writes
     * for the Free tier so admins never get a blank features array.
     */
    private function defaultFeatures(): array
    {
        $modules = [];
        foreach (array_keys(PlanFormCatalogue::modules()) as $mk) {
            $modules[$mk] = true;
        }
        return array_merge($modules, [
            'max_links' => 10,
            'max_biolinks' => 1,
            'max_conversational' => 1,
            'max_slides' => 1,
            'max_ai_chat' => 1,
            'max_restaurant_menu' => 1,
            'max_service_booking' => 1,
            'max_reviews' => 1,
            'max_file_size_mb' => 5,
            'storage_limit_mb' => 100,
            'max_projects' => 3,
            'contacts_max' => 100,
            'contacts_google_sync' => false,
            'max_aliases_per_link' => 0,
            'min_alias_length' => 3,
            'max_alias_length' => 50,
            'max_workspaces' => 1,
            'max_seats_per_workspace' => 1,
            'custom_domains' => false,
            'max_custom_domains' => 0,
            'qr_customization' => false,
            'analytics' => 'basic',
            'pixels' => false,
            'utm_params' => false,
            'link_protection' => false,
            'seo_settings' => false,
            'teams' => false,
            'ecommerce' => false,
            'custom_forms' => false,
            'custom_branding' => false,
            'remove_branding' => false,
            'custom_favicon' => false,
            'custom_code' => false,
            'ai_chatbot' => false,
            'ai_agent' => false,
            'ai_widget' => false,
            'ai_voice_assistant' => false,
            'block_types_allowed' => '*',
            'integration_accounts_max'     => ['payment' => 1, 'sms' => 1, 'email' => 1],
            'integration_providers_allowed' => ['payment' => '*', 'sms' => '*', 'email' => '*'],
        ]);
    }
}
