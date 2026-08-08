<?php

namespace App\Services;

use App\Modules\User\Models\Contact;
use App\Modules\User\Models\ContactCallLog;
use App\Modules\User\Models\DialerCallEvent;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\LinkClick;
use App\Modules\User\Models\ProductOrder;
use App\Modules\User\Models\User;
use App\Modules\User\Models\WalletTransaction;
use App\Modules\User\Models\Workspace;
use App\Services\Billing\WalletService;
use Carbon\CarbonImmutable;

/**
 * Task #6772 — real Sayzio actuals behind the Marketing Plan Calculator.
 *
 * Aggregates the workspace's live numbers so plans can start from (and be
 * compared against) reality instead of typed-in guesses:
 *
 *  - visitors  → link/bio-page clicks (link_clicks.clicked_at — the table
 *                has NO created_at) through the owner's links in the
 *                active workspace;
 *  - leads     → non-spam form submissions + newly created contacts;
 *  - customers → paid/fulfilled product-storefront orders + paid client
 *                invoices;
 *  - revenue   → the INR value of those orders/invoices;
 *  - Sayzio row→ the user's current plan slug + last calendar month's
 *                AI-credit (coin) spend converted to ₹;
 *  - features  → whether CRM/chat/dialer are actually in use, to ground
 *                the uplift toggles.
 *
 * Everything degrades gracefully: a metric whose table is missing or empty
 * simply reports 0, and prefill() says when there isn't enough history.
 * No AI spend — pure read-only aggregation.
 */
class MarketingPlanActuals
{
    /** How many complete calendar months of history to aggregate. */
    public const MONTHS = 6;

    /** USD→INR fallback used only for stray non-INR order/invoice rows. */
    protected const USD_INR = 83.0;

    /**
     * Workspace-column scoper. NULL workspace_id rows are legacy/personal
     * data, so when the active workspace is the user's PERSONAL workspace we
     * match both NULL and that id (web requests always resolve a workspace
     * row — data created before workspaces, or via the API, has NULL).
     *
     * @return \Closure(\Illuminate\Contracts\Database\Query\Builder, string): mixed
     */
    protected static function wsScope(User $user, ?int $workspaceId): \Closure
    {
        $includeNull = self::includesPersonal($user, $workspaceId);

        return function ($q, string $column) use ($workspaceId, $includeNull) {
            if ($workspaceId === null) return $q->whereNull($column);
            if (!$includeNull) return $q->where($column, $workspaceId);

            return $q->where(fn ($w) => $w->whereNull($column)->orWhere($column, $workspaceId));
        };
    }

    /**
     * Whether the given scope is the user's personal/account context —
     * either no workspace at all, or their own personal workspace.
     * Account-level metrics with no workspace column (storefront orders,
     * wallet AI spend) are ONLY surfaced in this scope; inside a team
     * workspace they are excluded entirely so one workspace's financial
     * activity never bleeds into another's actuals.
     */
    protected static function includesPersonal(User $user, ?int $workspaceId): bool
    {
        return $workspaceId === null || self::guard(
            fn () => Workspace::query()
                ->whereKey($workspaceId)
                ->where('owner_user_id', $user->id)
                ->where('is_personal', true)
                ->exists()
        ) === true;
    }

    /**
     * Monthly actuals + plan/AI-spend/feature summary for the editor.
     *
     * @return array{
     *   months: array<int,array{ym:string,label:string,visitors:int,leads:int,customers:int,revenue:float}>,
     *   ai_spend_last_month_inr: float,
     *   features: array{crm:bool,chat:bool,dialer:bool},
     *   has_data: bool
     * }
     */
    public static function summary(User $user, ?int $workspaceId): array
    {
        $now   = CarbonImmutable::now()->startOfMonth();
        $start = $now->subMonths(self::MONTHS);

        $ws = self::wsScope($user, $workspaceId);

        $months = [];
        for ($i = 0; $i < self::MONTHS; $i++) {
            $m = $start->addMonths($i);
            $months[$m->format('Y-m')] = [
                'ym'        => $m->format('Y-m'),
                'label'     => $m->format('M Y'),
                'visitors'  => 0,
                'leads'     => 0,
                'customers' => 0,
                'revenue'   => 0.0,
            ];
        }

        // Visitors — link clicks via the owner's links. clicked_at, never
        // created_at (link_clicks has no created_at column).
        self::guard(function () use (&$months, $user, $ws, $start, $now) {
            $rows = LinkClick::query()
                ->join('links', 'links.id', '=', 'link_clicks.link_id')
                ->where('links.user_id', $user->id)
                ->tap(fn ($q) => $ws($q, 'links.workspace_id'))
                ->where('link_clicks.clicked_at', '>=', $start)
                ->where('link_clicks.clicked_at', '<', $now)
                ->selectRaw("to_char(link_clicks.clicked_at, 'YYYY-MM') as ym, count(*) as c")
                ->groupBy('ym')->pluck('c', 'ym');
            foreach ($rows as $ym => $c) {
                if (isset($months[$ym])) $months[$ym]['visitors'] = (int) $c;
            }
        });

        // Leads — non-spam form submissions…
        self::guard(function () use (&$months, $user, $ws, $start, $now) {
            $rows = FormSubmission::query()->withoutGlobalScope('workspace')
                ->join('forms', 'forms.id', '=', 'form_submissions.form_id')
                ->where('forms.user_id', $user->id)
                ->tap(fn ($q) => $ws($q, 'forms.workspace_id'))
                ->where('form_submissions.is_spam', false)
                ->where('form_submissions.created_at', '>=', $start)
                ->where('form_submissions.created_at', '<', $now)
                ->selectRaw("to_char(form_submissions.created_at, 'YYYY-MM') as ym, count(*) as c")
                ->groupBy('ym')->pluck('c', 'ym');
            foreach ($rows as $ym => $c) {
                if (isset($months[$ym])) $months[$ym]['leads'] += (int) $c;
            }
        });

        // …plus newly captured contacts.
        self::guard(function () use (&$months, $user, $ws, $start, $now) {
            $rows = Contact::query()->withoutGlobalScope('workspace')
                ->where('user_id', $user->id)
                ->tap(fn ($q) => $ws($q, 'workspace_id'))
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $now)
                ->selectRaw("to_char(created_at, 'YYYY-MM') as ym, count(*) as c")
                ->groupBy('ym')->pluck('c', 'ym');
            foreach ($rows as $ym => $c) {
                if (isset($months[$ym])) $months[$ym]['leads'] += (int) $c;
            }
        });

        // Customers & revenue — paid/fulfilled storefront orders. Orders are
        // account-level (product_orders has no workspace_id), so they are
        // ONLY included in the personal/account scope — never inside a team
        // workspace, where they would leak another context's revenue.
        $personalScope = self::includesPersonal($user, $workspaceId);
        if ($personalScope) self::guard(function () use (&$months, $user, $start, $now) {
            $rows = ProductOrder::query()
                ->where('creator_user_id', $user->id)
                ->whereIn('status', [ProductOrder::STATUS_PAID, ProductOrder::STATUS_FULFILLED])
                ->whereNotNull('paid_at')
                ->where('paid_at', '>=', $start)
                ->where('paid_at', '<', $now)
                ->selectRaw("to_char(paid_at, 'YYYY-MM') as ym, currency, count(*) as c, sum(subtotal_cents) as cents")
                ->groupBy('ym', 'currency')->get();
            foreach ($rows as $r) {
                if (!isset($months[$r->ym])) continue;
                $months[$r->ym]['customers'] += (int) $r->c;
                $months[$r->ym]['revenue']   += self::toInr(((int) $r->cents) / 100, (string) $r->currency);
            }
        });

        // …plus paid client invoices (workspace-scoped column exists).
        self::guard(function () use (&$months, $user, $ws, $start, $now) {
            $rows = Invoice::query()->client()
                ->where('user_id', $user->id)
                ->tap(fn ($q) => $ws($q, 'workspace_id'))
                ->whereNotNull('paid_at')
                ->where('amount_paid_minor', '>', 0)
                ->where('paid_at', '>=', $start)
                ->where('paid_at', '<', $now)
                ->selectRaw("to_char(paid_at, 'YYYY-MM') as ym, currency, count(*) as c, sum(amount_paid_minor) as minor")
                ->groupBy('ym', 'currency')->get();
            foreach ($rows as $r) {
                if (!isset($months[$r->ym])) continue;
                $months[$r->ym]['customers'] += (int) $r->c;
                $months[$r->ym]['revenue']   += self::toInr(((int) $r->minor) / 100, (string) $r->currency);
            }
        });

        $months = array_values(array_map(function ($m) {
            $m['revenue'] = round($m['revenue'], 2);
            return $m;
        }, $months));

        $hasData = false;
        foreach ($months as $m) {
            if ($m['visitors'] > 0 || $m['leads'] > 0 || $m['revenue'] > 0) { $hasData = true; break; }
        }

        return [
            'months'                  => $months,
            // Wallet AI spend is account-level too — personal scope only.
            'ai_spend_last_month_inr' => $personalScope ? self::aiSpendLastMonthInr($user) : 0.0,
            'features'                => self::features($user, $workspaceId),
            'has_data'                => $hasData,
        ];
    }

    /**
     * Last complete calendar month's AI-credit (coin) spend, in ₹.
     * Wallet coin spend covers every token-charged AI feature; coins convert
     * at the admin-configured INR rate (default 1 coin = ₹1).
     */
    public static function aiSpendLastMonthInr(User $user): float
    {
        return self::guard(function () use ($user) {
            $end   = CarbonImmutable::now()->startOfMonth();
            $start = $end->subMonth();

            $coins = (int) abs((int) WalletTransaction::query()
                ->where('user_id', $user->id)
                ->where('type', 'spend')
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end)
                ->sum('delta_coins'));

            $rate = WalletService::rateFor('INR');
            return $rate > 0 ? round($coins / $rate, 2) : (float) $coins;
        }) ?? 0.0;
    }

    /**
     * Whether the Sayzio features the uplift toggles model are actually in
     * use for this user, so the CRM/chat/dialer uplifts feel grounded.
     *
     * @return array{crm:bool,chat:bool,dialer:bool}
     */
    public static function features(User $user, ?int $workspaceId): array
    {
        $ws  = self::wsScope($user, $workspaceId);
        $crm = self::guard(fn () => Contact::query()->withoutGlobalScope('workspace')
            ->where('user_id', $user->id)
            ->tap(fn ($q) => $ws($q, 'workspace_id'))
            ->exists()) ?? false;

        // AI companion and dialer activity are account-level (their tables
        // have no workspace column) — same policy as the financials: only
        // surfaced in the personal/account scope, never as a team
        // workspace's own feature usage.
        $chat = $dialer = false;
        if (self::includesPersonal($user, $workspaceId)) {
            $chat = self::guard(fn () => \App\Modules\User\Models\AiCompanion::query()
                ->where('user_id', $user->id)
                ->where('is_disabled', false)
                ->exists()) ?? false;

            $dialer = self::guard(fn () => ContactCallLog::query()->where('user_id', $user->id)->exists()
                || DialerCallEvent::query()->where('user_id', $user->id)->exists()) ?? false;
        }

        return ['crm' => (bool) $crm, 'chat' => (bool) $chat, 'dialer' => (bool) $dialer];
    }

    /**
     * Map the actuals onto calculator assumptions.
     *
     * Derived (each only when its inputs exist, all clamped to the
     * calculator's validation bounds and everything still editable):
     *  - organic_visitors        avg monthly link/bio-page visitors
     *  - Sayzio row vl           leads ÷ visitors  (visitor → lead %)
     *  - Sayzio row lc           customers ÷ leads (lead → customer %)
     *  - Sayzio row acv          revenue ÷ customers (avg customer value ₹)
     *  - ai_credits              last month's real AI coin spend in ₹
     *  - plan_slug               current plan (defaults already do this)
     *
     * @param  array<string,mixed>|null  $summary  reuse a computed summary
     * @return array{payload:array<string,mixed>,filled:array<int,string>,sufficient:bool,summary:array<string,mixed>}
     */
    public static function prefill(User $user, ?int $workspaceId, ?array $summary = null): array
    {
        $summary ??= self::summary($user, $workspaceId);
        $payload = MarketingPlanDefaults::defaults($user);
        $filled  = [];

        $tV = $tL = $tC = 0; $tR = 0.0; $activeMonths = 0;
        foreach ($summary['months'] as $m) {
            $tV += $m['visitors']; $tL += $m['leads']; $tC += $m['customers']; $tR += $m['revenue'];
            if ($m['visitors'] > 0 || $m['leads'] > 0 || $m['revenue'] > 0) $activeMonths++;
        }

        if ($tV > 0 && $activeMonths > 0) {
            $payload['organic_visitors'] = (int) round($tV / $activeMonths);
            $filled[] = 'organic_visitors';
        }

        // The fixed Sayzio channel row.
        $si = null;
        foreach ($payload['channels'] as $i => $c) {
            if (($c['key'] ?? null) === 'sayzio') { $si = $i; break; }
        }
        if ($si !== null) {
            if ($tV > 0 && $tL > 0) {
                $payload['channels'][$si]['vl'] = self::clampPct($tL / $tV * 100);
                $filled[] = 'sayzio_vl';
            }
            if ($tL > 0 && $tC > 0) {
                $payload['channels'][$si]['lc'] = self::clampPct($tC / $tL * 100);
                $filled[] = 'sayzio_lc';
            }
            if ($tC > 0 && $tR > 0) {
                $payload['channels'][$si]['acv'] = round(min($tR / $tC, 1_000_000_000_000), 2);
                $filled[] = 'sayzio_acv';
            }
        }

        $ai = (float) $summary['ai_spend_last_month_inr'];
        if ($ai > 0) {
            $payload['ai_credits'] = round(min($ai, 1_000_000_000_000), 2);
            $filled[] = 'ai_credits';
        }

        // Flat value map so the editor can apply the same prefill client-side.
        $values = [];
        foreach ($filled as $key) {
            $values[$key] = match ($key) {
                'organic_visitors' => $payload['organic_visitors'],
                'sayzio_vl'        => $payload['channels'][$si]['vl'],
                'sayzio_lc'        => $payload['channels'][$si]['lc'],
                'sayzio_acv'       => $payload['channels'][$si]['acv'],
                'ai_credits'       => $payload['ai_credits'],
            };
        }

        return [
            'payload'    => $payload,
            'filled'     => $filled,
            'values'     => $values,
            'sufficient' => $filled !== [],
            'summary'    => $summary,
        ];
    }

    /** Clamp a derived percentage into the calculator's 0–100 bounds. */
    protected static function clampPct(float $v): float
    {
        return round(max(0.0, min(100.0, $v)), 1);
    }

    /** Convert an order/invoice amount to INR (base currency of the model). */
    protected static function toInr(float $amount, string $currency): float
    {
        return strtoupper($currency) === 'USD' ? $amount * self::USD_INR : $amount;
    }

    /**
     * Run an aggregation step, treating EXPECTED missing-schema failures
     * (undefined table/column in a fresh or partially-migrated env) as
     * "no data". Any other failure is reported to the error log so real
     * regressions never masquerade as an empty workspace — but the page
     * still degrades to zeros instead of 500ing.
     */
    protected static function guard(callable $fn)
    {
        try {
            return $fn();
        } catch (\Illuminate\Database\QueryException $e) {
            // 42P01 = undefined_table, 42703 = undefined_column.
            $sqlState = (string) ($e->errorInfo[0] ?? '');
            if (!in_array($sqlState, ['42P01', '42703'], true)) {
                report($e);
            }
            return null;
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }
}
