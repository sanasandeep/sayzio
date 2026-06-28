<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\CatalogCategory;
use App\Modules\User\Models\CatalogItem;
use App\Modules\User\Models\Expense;
use App\Modules\User\Models\RecurringInvoice;
use App\Modules\User\Models\TaxRule;
use App\Modules\User\Services\WorkspaceContext;
use App\Services\Billing\LedgerReportService;
use App\Services\Billing\RecurringInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/**
 * REST parity for the Invoicing & Accounting Suite: billing companies,
 * tax rules, item catalog, expenses, recurring invoices and the ledger
 * report. All resources are user-scoped (mirrors the web controllers).
 */
class AccountingController extends Controller
{
    use ApiResponses;

    public function __construct(protected WorkspaceContext $ctx) {}

    // ---- Billing companies ----------------------------------------------

    public function companies(Request $request)
    {
        $items = BillingCompany::where('user_id', $request->user()->id)
            ->orderByDesc('is_default')->orderBy('name')->get()
            ->map(fn ($c) => $this->company($c));
        return $this->ok(['items' => $items]);
    }

    public function storeCompany(Request $request)
    {
        $data = $this->companyRules($request);
        $data['user_id'] = $request->user()->id;
        $data['workspace_id'] = optional($this->ctx->resolve($request->user()))->id;
        $company = BillingCompany::create($data);
        $this->syncDefaultCompany($company);
        return $this->created(['company' => $this->company($company)]);
    }

    public function updateCompany(Request $request, int $id)
    {
        $company = BillingCompany::where('user_id', $request->user()->id)->find($id);
        if (!$company) return $this->notFound('Company not found');
        $company->update($this->companyRules($request));
        $this->syncDefaultCompany($company);
        return $this->ok(['company' => $this->company($company->refresh())]);
    }

    public function destroyCompany(Request $request, int $id)
    {
        $company = BillingCompany::where('user_id', $request->user()->id)->find($id);
        if (!$company) return $this->notFound('Company not found');
        $company->delete();
        return $this->noContent();
    }

    // ---- Tax rules ------------------------------------------------------

    public function taxRules(Request $request)
    {
        $items = TaxRule::where('user_id', $request->user()->id)->orderBy('name')->get()
            ->map(fn ($r) => $this->taxRule($r));
        return $this->ok(['items' => $items]);
    }

    public function storeTaxRule(Request $request)
    {
        $data = $this->taxRuleRules($request);
        $data['user_id'] = $request->user()->id;
        $rule = TaxRule::create($data);
        return $this->created(['tax_rule' => $this->taxRule($rule)]);
    }

    public function updateTaxRule(Request $request, int $id)
    {
        $rule = TaxRule::where('user_id', $request->user()->id)->find($id);
        if (!$rule) return $this->notFound('Tax rule not found');
        $rule->update($this->taxRuleRules($request));
        return $this->ok(['tax_rule' => $this->taxRule($rule->refresh())]);
    }

    public function destroyTaxRule(Request $request, int $id)
    {
        $rule = TaxRule::where('user_id', $request->user()->id)->find($id);
        if (!$rule) return $this->notFound('Tax rule not found');
        $rule->delete();
        return $this->noContent();
    }

    // ---- Catalog --------------------------------------------------------

    public function catalog(Request $request)
    {
        $uid = $request->user()->id;
        return $this->ok([
            'categories' => CatalogCategory::where('user_id', $uid)->orderBy('name')->get()
                ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'kind' => $c->kind]),
            'items' => CatalogItem::where('user_id', $uid)->orderBy('name')->get()
                ->map(fn ($i) => $this->catalogItem($i)),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'kind' => 'nullable|in:item,expense,both',
        ]);
        $data['user_id'] = $request->user()->id;
        $data['kind'] = $data['kind'] ?? 'item';
        $cat = CatalogCategory::create($data);
        return $this->created(['category' => ['id' => $cat->id, 'name' => $cat->name, 'kind' => $cat->kind]]);
    }

    public function destroyCategory(Request $request, int $id)
    {
        $cat = CatalogCategory::where('user_id', $request->user()->id)->find($id);
        if (!$cat) return $this->notFound('Category not found');
        $cat->delete();
        return $this->noContent();
    }

    public function storeItem(Request $request)
    {
        $data = $this->itemRules($request);
        $data['user_id'] = $request->user()->id;
        $item = CatalogItem::create($data);
        return $this->created(['item' => $this->catalogItem($item)]);
    }

    public function updateItem(Request $request, int $id)
    {
        $item = CatalogItem::where('user_id', $request->user()->id)->find($id);
        if (!$item) return $this->notFound('Item not found');
        $item->update($this->itemRules($request));
        return $this->ok(['item' => $this->catalogItem($item->refresh())]);
    }

    public function destroyItem(Request $request, int $id)
    {
        $item = CatalogItem::where('user_id', $request->user()->id)->find($id);
        if (!$item) return $this->notFound('Item not found');
        $item->delete();
        return $this->noContent();
    }

    // ---- Expenses -------------------------------------------------------

    public function expenses(Request $request)
    {
        $page = Expense::where('user_id', $request->user()->id)
            ->orderByDesc('spent_at')->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));
        return $this->ok([
            'items' => collect($page->items())->map(fn ($e) => $this->expense($e))->all(),
            'meta'  => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function storeExpense(Request $request)
    {
        $data = $this->expenseRules($request);
        $data['user_id'] = $request->user()->id;
        $data['workspace_id'] = optional($this->ctx->resolve($request->user()))->id;
        $expense = Expense::create($data);
        return $this->created(['expense' => $this->expense($expense)]);
    }

    public function updateExpense(Request $request, int $id)
    {
        $expense = Expense::where('user_id', $request->user()->id)->find($id);
        if (!$expense) return $this->notFound('Expense not found');
        $expense->update($this->expenseRules($request));
        return $this->ok(['expense' => $this->expense($expense->refresh())]);
    }

    public function destroyExpense(Request $request, int $id)
    {
        $expense = Expense::where('user_id', $request->user()->id)->find($id);
        if (!$expense) return $this->notFound('Expense not found');
        $expense->delete();
        return $this->noContent();
    }

    // ---- Recurring invoices --------------------------------------------

    public function recurring(Request $request)
    {
        $items = RecurringInvoice::where('user_id', $request->user()->id)
            ->orderByDesc('id')->get()->map(fn ($t) => $this->recurringTemplate($t));
        return $this->ok(['items' => $items]);
    }

    public function storeRecurring(Request $request)
    {
        $data = $this->recurringRules($request);
        $data['user_id'] = $request->user()->id;
        $data['workspace_id'] = optional($this->ctx->resolve($request->user()))->id;
        $data['next_run_date'] = $data['start_date'];
        $tpl = RecurringInvoice::create($data);
        return $this->created(['recurring' => $this->recurringTemplate($tpl)]);
    }

    public function updateRecurring(Request $request, int $id)
    {
        $tpl = RecurringInvoice::where('user_id', $request->user()->id)->find($id);
        if (!$tpl) return $this->notFound('Template not found');
        $data = $this->recurringRules($request);
        if (!$tpl->next_run_date) $data['next_run_date'] = $data['start_date'];
        $tpl->update($data);
        return $this->ok(['recurring' => $this->recurringTemplate($tpl->refresh())]);
    }

    public function destroyRecurring(Request $request, int $id)
    {
        $tpl = RecurringInvoice::where('user_id', $request->user()->id)->find($id);
        if (!$tpl) return $this->notFound('Template not found');
        $tpl->delete();
        return $this->noContent();
    }

    public function runRecurring(Request $request, RecurringInvoiceService $svc, int $id)
    {
        $tpl = RecurringInvoice::where('user_id', $request->user()->id)->find($id);
        if (!$tpl) return $this->notFound('Template not found');
        $invoice = $svc->runOnce($tpl);
        if (!$invoice) return $this->fail('Could not generate invoice.', 422);
        return $this->created(['invoice_id' => $invoice->id, 'number' => $invoice->number]);
    }

    // ---- Ledger ---------------------------------------------------------

    public function ledger(Request $request, LedgerReportService $svc)
    {
        $from = $request->filled('from') ? Carbon::parse($request->query('from')) : now()->startOfYear();
        $to   = $request->filled('to')   ? Carbon::parse($request->query('to'))   : now()->endOfDay();
        $companyId = $request->integer('company') ?: null;
        return $this->ok(['report' => $svc->build((int) $request->user()->id, $from, $to, $companyId)]);
    }

    // ---- Validation rule sets ------------------------------------------

    protected function companyRules(Request $request): array
    {
        return $request->validate([
            'name'                => 'required|string|max:190',
            'legal_name'          => 'nullable|string|max:190',
            'email'               => 'nullable|email|max:190',
            'phone'               => 'nullable|string|max:64',
            'website'             => 'nullable|string|max:190',
            'address_line1'       => 'nullable|string|max:190',
            'address_line2'       => 'nullable|string|max:190',
            'city'                => 'nullable|string|max:120',
            'state'               => 'nullable|string|max:120',
            'postal_code'         => 'nullable|string|max:32',
            'country'             => 'nullable|string|size:2',
            'tax_id_label'        => 'nullable|string|max:64',
            'tax_id_value'        => 'nullable|string|max:64',
            'secondary_tax_label' => 'nullable|string|max:64',
            'secondary_tax_value' => 'nullable|string|max:64',
            'default_currency'    => 'nullable|string|size:3',
            'invoice_prefix'      => 'nullable|string|max:16',
            'default_tax_rule_id' => 'nullable|integer',
            'notes'               => 'nullable|string|max:2000',
            'is_default'          => 'nullable|boolean',
        ]);
    }

    protected function taxRuleRules(Request $request): array
    {
        $data = $request->validate([
            'name'               => 'required|string|max:120',
            'rate_bps'           => 'required|integer|min:0|max:100000',
            'billing_company_id' => 'nullable|integer',
            'inclusive'          => 'nullable|boolean',
            'is_compound'        => 'nullable|boolean',
            'is_default'         => 'nullable|boolean',
            'is_active'          => 'nullable|boolean',
        ]);
        foreach (['inclusive', 'is_compound', 'is_default', 'is_active'] as $b) {
            $data[$b] = (bool) ($data[$b] ?? false);
        }
        return $data;
    }

    protected function itemRules(Request $request): array
    {
        $data = $request->validate([
            'name'               => 'required|string|max:190',
            'description'        => 'nullable|string|max:2000',
            'unit_price_minor'   => 'required|integer|min:0',
            'currency'           => 'nullable|string|size:3',
            'category_id'        => 'nullable|integer',
            'tax_rule_id'        => 'nullable|integer',
            'billing_company_id' => 'nullable|integer',
            'sku'                => 'nullable|string|max:64',
            'unit_label'         => 'nullable|string|max:32',
            'is_active'          => 'nullable|boolean',
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['currency']  = strtoupper($data['currency'] ?? 'USD');
        return $data;
    }

    protected function expenseRules(Request $request): array
    {
        $data = $request->validate([
            'billing_company_id' => 'nullable|integer',
            'category_id'        => 'nullable|integer',
            'vendor'             => 'nullable|string|max:190',
            'description'        => 'nullable|string|max:240',
            'spent_at'           => 'required|date',
            'amount_minor'       => 'required|integer|min:0',
            'tax_minor'          => 'nullable|integer|min:0',
            'currency'           => 'nullable|string|size:3',
            'notes'              => 'nullable|string|max:2000',
        ]);
        $data['tax_minor'] = (int) ($data['tax_minor'] ?? 0);
        $data['currency']  = strtoupper($data['currency'] ?? 'USD');
        return $data;
    }

    protected function recurringRules(Request $request): array
    {
        $data = $request->validate([
            'title'                     => 'nullable|string|max:190',
            'billing_company_id'        => 'nullable|integer',
            'vault_client_id'           => 'nullable|integer',
            'recipient_email'           => 'nullable|email|max:190',
            'currency'                  => 'nullable|string|size:3',
            'discount_minor'            => 'nullable|integer|min:0',
            'tax_rule_id'               => 'nullable|integer',
            'notes_md'                  => 'nullable|string|max:4000',
            'interval'                  => 'required|in:weekly,monthly,quarterly,yearly',
            'interval_count'            => 'nullable|integer|min:1|max:60',
            'start_date'                => 'required|date',
            'end_date'                  => 'nullable|date|after_or_equal:start_date',
            'max_occurrences'           => 'nullable|integer|min:1',
            'auto_send'                 => 'nullable|boolean',
            'status'                    => 'nullable|in:active,paused,cancelled,completed',
            'line_items'                => 'required|array|min:1',
            'line_items.*.label'        => 'required|string|max:240',
            'line_items.*.amount_minor' => 'required|integer|min:0',
            'line_items.*.quantity'     => 'nullable|integer|min:1|max:9999',
            'line_items.*.tax_rate_bps' => 'nullable|integer|min:0|max:100000',
            'line_items.*.tax_name'     => 'nullable|string|max:64',
            'line_items.*.tax_inclusive'=> 'nullable|boolean',
        ]);
        $data['currency']       = strtoupper($data['currency'] ?? 'USD');
        $data['interval_count'] = (int) ($data['interval_count'] ?? 1);
        $data['auto_send']      = (bool) ($data['auto_send'] ?? false);
        $data['discount_minor'] = (int) ($data['discount_minor'] ?? 0);
        $data['line_items']     = array_values($data['line_items']);
        return $data;
    }

    // ---- Transformers ---------------------------------------------------

    protected function company(BillingCompany $c): array
    {
        return [
            'id' => $c->id, 'name' => $c->name, 'legal_name' => $c->legal_name,
            'email' => $c->email, 'phone' => $c->phone, 'website' => $c->website,
            'address_line1' => $c->address_line1, 'address_line2' => $c->address_line2,
            'city' => $c->city, 'state' => $c->state, 'postal_code' => $c->postal_code, 'country' => $c->country,
            'tax_id_label' => $c->tax_id_label, 'tax_id_value' => $c->tax_id_value,
            'secondary_tax_label' => $c->secondary_tax_label, 'secondary_tax_value' => $c->secondary_tax_value,
            'default_currency' => $c->default_currency, 'invoice_prefix' => $c->invoice_prefix,
            'default_tax_rule_id' => $c->default_tax_rule_id, 'notes' => $c->notes,
            'is_default' => (bool) $c->is_default,
        ];
    }

    protected function taxRule(TaxRule $r): array
    {
        return [
            'id' => $r->id, 'name' => $r->name, 'rate_bps' => (int) $r->rate_bps,
            'billing_company_id' => $r->billing_company_id,
            'inclusive' => (bool) $r->inclusive, 'is_compound' => (bool) $r->is_compound,
            'is_default' => (bool) $r->is_default, 'is_active' => (bool) $r->is_active,
        ];
    }

    protected function catalogItem(CatalogItem $i): array
    {
        return [
            'id' => $i->id, 'name' => $i->name, 'description' => $i->description,
            'unit_price_minor' => (int) $i->unit_price_minor, 'currency' => $i->currency,
            'category_id' => $i->category_id, 'tax_rule_id' => $i->tax_rule_id,
            'billing_company_id' => $i->billing_company_id,
            'sku' => $i->sku, 'unit_label' => $i->unit_label, 'is_active' => (bool) $i->is_active,
        ];
    }

    protected function expense(Expense $e): array
    {
        return [
            'id' => $e->id, 'billing_company_id' => $e->billing_company_id, 'category_id' => $e->category_id,
            'vendor' => $e->vendor, 'description' => $e->description,
            'spent_at' => optional($e->spent_at)->toDateString(),
            'amount_minor' => (int) $e->amount_minor, 'tax_minor' => (int) $e->tax_minor,
            'currency' => $e->currency, 'notes' => $e->notes,
        ];
    }

    protected function recurringTemplate(RecurringInvoice $t): array
    {
        return [
            'id' => $t->id, 'title' => $t->title, 'billing_company_id' => $t->billing_company_id,
            'vault_client_id' => $t->vault_client_id, 'recipient_email' => $t->recipient_email,
            'currency' => $t->currency, 'discount_minor' => (int) ($t->discount_minor ?? 0),
            'tax_rule_id' => $t->tax_rule_id, 'notes_md' => $t->notes_md,
            'interval' => $t->interval, 'interval_count' => (int) $t->interval_count,
            'start_date' => $t->start_date ? Carbon::parse($t->start_date)->toDateString() : null,
            'end_date' => $t->end_date ? Carbon::parse($t->end_date)->toDateString() : null,
            'next_run_date' => $t->next_run_date ? Carbon::parse($t->next_run_date)->toDateString() : null,
            'max_occurrences' => $t->max_occurrences, 'occurrences_count' => (int) ($t->occurrences_count ?? 0),
            'auto_send' => (bool) $t->auto_send, 'status' => $t->status,
            'line_items' => is_array($t->line_items) ? $t->line_items : [],
        ];
    }

    protected function syncDefaultCompany(BillingCompany $company): void
    {
        if ($company->is_default) {
            BillingCompany::where('user_id', $company->user_id)
                ->where('id', '!=', $company->id)->update(['is_default' => false]);
        }
    }
}
