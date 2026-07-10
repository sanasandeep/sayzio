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
        $plans = Plan::withCount('users')->where('is_archived', false)->ordered()->get();
        $archivedPlans = Plan::withCount('users')->where('is_archived', true)->ordered()->get();

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

    public function destroy(Plan $plan)
    {
        if ($plan->users()->count() > 0) {
            return back()->with('error', 'Cannot delete a plan that has active users.');
        }

        $plan->delete();
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
            'csv' => ['required', 'string', 'max:2000000'],
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

        $updated = 0;
        $changed = [];
        \Illuminate\Support\Facades\DB::transaction(function () use ($result, &$updated, &$changed) {
            foreach ($result['rows'] as $row) {
                if ($row['status'] !== 'update' || empty($row['plan'])) {
                    continue;
                }
                $this->applyImportRow($row['plan'], $row);
                $updated++;
                $changed[] = ['slug' => $row['slug'], 'name' => $row['name']];
            }
        });

        $skipped = $result['errorCount'] + $result['unknownCount'];

        // Only record an undo point when something actually changed.
        if ($updated > 0) {
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

        return redirect()->route('admin.plans.index')->with(
            'success',
            $updated > 0
                ? "Imported plan changes: {$updated} plan(s) updated"
                    . ($skipped > 0 ? ", {$skipped} row(s) skipped." : '.')
                    . ' You can undo this from Import history below.'
                : 'No plans were updated (no matching rows with changes).'
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

            $admin = Auth::guard('admin')->user();
            $snapshot->forceFill([
                'reverted_at'      => now(),
                'reverted_by'      => $admin?->id,
                'reverted_by_name' => $admin?->name,
            ])->save();
        });

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
            'changedCount' => 0, 'errorCount' => 0, 'unknownCount' => 0, 'unchangedCount' => 0,
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
        $errorCount     = 0;
        $unknownCount   = 0;
        $unchangedCount = 0;

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
                $rows[] = [
                    'status' => 'unknown', 'slug' => $slug, 'name' => $rowName,
                    'plan' => null, 'changes' => [], 'errors' => ["No plan found with slug \"{$slug}\" — skipped."],
                ];
                $unknownCount++;
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
