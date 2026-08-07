<?php

namespace App\Services;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\DialerCallEvent;
use App\Modules\User\Models\DialerDevice;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\StoreOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use Illuminate\Support\Carbon;

/**
 * Task #6772 — real Sayzio usage aggregated for the Marketing Plan
 * Calculator ("Use my Sayzio data" prefill + the Plan vs. Actual view).
 *
 * Everything is scoped to ONE owner + ONE workspace (null = personal) and
 * covers the last 12 calendar months (including the current, partial one):
 *   - visitors  — link clicks (link_clicks.clicked_at — the table has NO
 *                 created_at) across the owner's links in the workspace,
 *   - leads     — form submissions to the owner's forms + new CRM contacts,
 *   - revenue   — completed storefront orders + paid client invoices,
 *                 normalised to INR (USD converted at the calculator's
 *                 default display rate; other currencies at face value),
 *   - AI spend  — last-month AI coin spend from the wallet ledger
 *                 (spend minus linked refunds, meta.ai = true),
 *   - features  — cheap "already in use" signals for chat / CRM / dialer.
 *
 * All queries use explicit owner/workspace predicates (plus
 * withoutGlobalScope('workspace') where a workspace global scope exists)
 * so results are identical inside and outside a bound request workspace.
 */
class MarketingPlanActuals
{
    /** USD→INR normalisation for revenue rows billed in USD. */
    public const USD_INR = 83.0;

    /** @return array<string,mixed> */
    public static function forUser(User $user, ?int $workspaceId): array
    {
        $now    = Carbon::now();
        $start  = $now->copy()->startOfMonth()->subMonths(11);
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $months[$start->copy()->addMonths($i)->format('Y-m')] = ['visitors' => 0, 'leads' => 0, 'revenue' => 0.0];
        }

        try {
            // The active PERSONAL workspace must also match legacy rows that
            // were written before workspace stamping (workspace_id NULL) —
            // most personal-account data lives there.
            $isPersonal = $workspaceId === null
                || (bool) \App\Modules\User\Models\Workspace::query()
                    ->whereKey($workspaceId)
                    ->where('owner_user_id', $user->id)
                    ->value('is_personal');

            $wsWhere = fn ($q, string $col = 'workspace_id') => $isPersonal
                ? $q->where(fn ($w) => $w->whereNull($col)->when($workspaceId !== null, fn ($w2) => $w2->orWhere($col, $workspaceId)))
                : $q->where($col, $workspaceId);

            // ---- visitors: clicks on the owner's links (clicked_at!) ----
            $linkIds = $wsWhere(
                Link::query()->withoutGlobalScopes()->where('user_id', $user->id)
            )->pluck('id');

            if ($linkIds->isNotEmpty()) {
                // Explicit human-traffic predicate (don't rely on the model's
                // global scope): exclude true bot/throttled rows but keep
                // legacy rows where the flags are NULL.
                LinkClick::query()->withoutGlobalScopes()
                    ->whereIn('link_id', $linkIds)
                    ->where(fn ($q) => $q->whereNull('is_bot')->orWhere('is_bot', false))
                    ->where(fn ($q) => $q->whereNull('is_throttled')->orWhere('is_throttled', false))
                    ->where('clicked_at', '>=', $start)
                    ->selectRaw("to_char(clicked_at, 'YYYY-MM') as ym, count(*) as c")
                    ->groupBy('ym')->get()
                    ->each(function ($r) use (&$months) {
                        if (isset($months[$r->ym])) $months[$r->ym]['visitors'] = (int) $r->c;
                    });
            }

            // ---- leads: form submissions (non-spam) + new contacts ----
            if ($linkIds->isNotEmpty() || true) {
                $wsWhere(FormSubmission::query()->withoutGlobalScope('workspace')
                        ->whereHas('form', fn ($q) => $q->withoutGlobalScope('workspace')->where('user_id', $user->id)))
                    ->where('created_at', '>=', $start)
                    ->where(fn ($q) => $q->whereNull('is_spam')->orWhere('is_spam', false))
                    ->selectRaw("to_char(created_at, 'YYYY-MM') as ym, count(*) as c")
                    ->groupBy('ym')->get()
                    ->each(function ($r) use (&$months) {
                        if (isset($months[$r->ym])) $months[$r->ym]['leads'] += (int) $r->c;
                    });
            }

            $contactsTotal = 0;
            $wsWhere(Contact::query()->withoutGlobalScope('workspace')->where('user_id', $user->id))
                ->where('created_at', '>=', $start)
                ->selectRaw("to_char(created_at, 'YYYY-MM') as ym, count(*) as c")
                ->groupBy('ym')->get()
                ->each(function ($r) use (&$months, &$contactsTotal) {
                    if (isset($months[$r->ym])) $months[$r->ym]['leads'] += (int) $r->c;
                    $contactsTotal += (int) $r->c;
                });

            // ---- revenue: completed store orders + paid invoices (INR) ----
            $inr = fn (float $amount, ?string $currency): float => match (strtoupper((string) $currency)) {
                'USD'   => $amount * self::USD_INR,
                default => $amount,
            };

            if ($linkIds->isNotEmpty()) {
                StoreOrder::query()
                    ->whereIn('link_id', $linkIds)
                    ->where('status', 'completed')
                    ->where('created_at', '>=', $start)
                    ->get(['total', 'currency', 'created_at'])
                    ->each(function ($o) use (&$months, $inr) {
                        $ym = $o->created_at?->format('Y-m');
                        if ($ym !== null && isset($months[$ym])) {
                            $months[$ym]['revenue'] += $inr((float) $o->total, $o->currency);
                        }
                    });
            }

            $wsWhere(Invoice::query()->where('user_id', $user->id))
                ->where('kind', '!=', 'platform')
                ->where(fn ($q) => $q->where('status', 'paid')->orWhereNotNull('paid_at'))
                ->where('created_at', '>=', $start)
                ->get(['grand_total_minor', 'currency', 'paid_at', 'created_at'])
                ->each(function ($iv) use (&$months, $inr) {
                    $ym = ($iv->paid_at ?? $iv->created_at)?->format('Y-m');
                    if ($ym !== null && isset($months[$ym])) {
                        $months[$ym]['revenue'] += $inr(((int) $iv->grand_total_minor) / 100, $iv->currency);
                    }
                });

            // ---- last-month AI coin spend (net of refunds) ----
            $aiRows = WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('meta->ai', true)
                ->whereIn('type', ['spend', 'refund'])
                ->where('created_at', '>=', $now->copy()->subDays(30))
                ->get(['type', 'delta_coins']);
            $aiCoins = 0;
            foreach ($aiRows as $tx) {
                $aiCoins += $tx->type === 'spend' ? abs((int) $tx->delta_coins) : -abs((int) $tx->delta_coins);
            }
            $aiCoins = max(0, $aiCoins);

            // ---- feature-in-use signals (each isolated — a missing table
            // for one feature must never blank the whole aggregate) ----
            $probe = function (callable $q): bool {
                try { return (bool) $q(); } catch (\Throwable) { return false; }
            };
            $features = [
                'chat'   => $probe(fn () => $wsWhere(Link::query()->withoutGlobalScopes()->where('user_id', $user->id))
                    ->whereIn('type', ['ai_chat', 'conversational'])->exists()),
                'crm'    => $contactsTotal > 0
                    || $probe(fn () => $wsWhere(Contact::query()->withoutGlobalScope('workspace')->where('user_id', $user->id))->exists()),
                'dialer' => $probe(fn () => DialerDevice::query()->where('user_id', $user->id)->exists()
                    || DialerCallEvent::query()->where('user_id', $user->id)->exists()),
            ];
        } catch (\Throwable $e) {
            report($e);
            // Distinguishable failure — never present a query error as
            // "no usage data" (the UI shows a retry message instead).
            return self::empty() + ['error' => true];
        }

        // ONE coherent denominator for all three averages: months with any
        // recorded activity at all (a brand-new account shouldn't be dragged
        // to ~0 by 11 empty months, but visitors/leads/revenue must share
        // the same period so ratios stay meaningful).
        $activeMonths = count(array_filter(
            $months,
            fn ($m) => $m['visitors'] > 0 || $m['leads'] > 0 || $m['revenue'] > 0,
        ));
        $totalVisitors = array_sum(array_column($months, 'visitors'));
        $totalLeads    = array_sum(array_column($months, 'leads'));
        $totalRevenue  = array_sum(array_column($months, 'revenue'));

        $monthlyVisitors = $activeMonths > 0 ? $totalVisitors / $activeMonths : 0.0;
        $monthlyLeads    = $activeMonths > 0 ? $totalLeads / $activeMonths : 0.0;
        $monthlyRevenue  = $activeMonths > 0 ? $totalRevenue / $activeMonths : 0.0;

        return [
            'has_data'         => $activeMonths > 0,
            'error'            => false,
            'monthly_visitors' => (int) round($monthlyVisitors),
            'monthly_leads'    => (int) round($monthlyLeads),
            'monthly_revenue'  => round($monthlyRevenue, 2),
            // Observed visitor → lead conversion (%) from period TOTALS.
            'vl_rate'          => $totalVisitors > 0 && $totalLeads > 0
                ? round(min(100, $totalLeads / $totalVisitors * 100), 2)
                : null,
            'ai_coins_30d'     => $aiCoins,
            'plan_slug'        => MarketingPlanDefaults::preselectPlanSlug($user),
            'features'         => $features,
            'months'           => collect($months)->map(fn ($m, $ym) => [
                'ym'       => $ym,
                'visitors' => $m['visitors'],
                'leads'    => $m['leads'],
                'revenue'  => round($m['revenue'], 2),
            ])->values()->all(),
        ];
    }

    /** @return array<string,mixed> */
    public static function empty(): array
    {
        return [
            'has_data' => false, 'error' => false, 'monthly_visitors' => 0, 'monthly_leads' => 0,
            'monthly_revenue' => 0.0, 'vl_rate' => null, 'ai_coins_30d' => 0,
            'plan_slug' => null, 'features' => ['chat' => false, 'crm' => false, 'dialer' => false],
            'months' => [],
        ];
    }

    /**
     * Merge the actuals into an assumption payload ("Use my Sayzio data").
     * Only overwrites what the data actually supports; everything stays
     * editable afterwards.
     *
     * @param  array<string,mixed> $payload
     * @param  array<string,mixed> $actuals
     * @return array<string,mixed>
     */
    public static function applyToPayload(array $payload, array $actuals): array
    {
        if (!($actuals['has_data'] ?? false)) {
            return $payload;
        }

        if (($actuals['monthly_visitors'] ?? 0) > 0) {
            $payload['organic_visitors'] = (int) $actuals['monthly_visitors'];
        }
        if (($actuals['ai_coins_30d'] ?? 0) > 0) {
            $payload['ai_credits'] = (int) $actuals['ai_coins_30d'];
        }
        if (!empty($actuals['plan_slug'])) {
            $payload['plan_slug'] = $actuals['plan_slug'];
        }

        // Observed visitor→lead rate lands on the fixed Sayzio channel row.
        if (($actuals['vl_rate'] ?? null) !== null && isset($payload['channels']) && is_array($payload['channels'])) {
            foreach ($payload['channels'] as $i => $ch) {
                if (($ch['key'] ?? null) === 'sayzio') {
                    $payload['channels'][$i]['vl'] = (float) $actuals['vl_rate'];
                    break;
                }
            }
        }

        return $payload;
    }
}
