<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\CatalogItem;
use App\Modules\User\Models\RecurringInvoice;
use App\Modules\User\Models\TaxRule;
use App\Modules\User\Models\VaultClient;
use App\Modules\User\Models\VaultClientEmail;
use App\Services\Billing\RecurringInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Recurring/subscription invoice templates that auto-generate invoices. */
class RecurringInvoiceController extends Controller
{
    public function index()
    {
        $templates = RecurringInvoice::where('user_id', auth()->id())
            ->with('billingCompany')->orderByDesc('id')->paginate(25);
        return view('user.billing.recurring.index', compact('templates'));
    }

    public function create()
    {
        $template = new RecurringInvoice(['interval' => 'monthly', 'interval_count' => 1, 'start_date' => now()->toDateString(), 'auto_send' => true]);
        return view('user.billing.recurring.edit', $this->formData($template));
    }

    public function edit(RecurringInvoice $recurring)
    {
        $this->authorizeOwn($recurring);
        return view('user.billing.recurring.edit', $this->formData($recurring));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = auth()->id();
        $data['workspace_id'] = optional(app('current_workspace'))->id;
        $data['next_run_date'] = $data['start_date'];
        RecurringInvoice::create($data);
        return redirect()->route('user.billing.recurring.index')->with('success', 'Recurring invoice created.');
    }

    public function update(Request $request, RecurringInvoice $recurring)
    {
        $this->authorizeOwn($recurring);
        $data = $this->validated($request);
        if (!$recurring->next_run_date) $data['next_run_date'] = $data['start_date'];
        $recurring->update($data);
        return redirect()->route('user.billing.recurring.index')->with('success', 'Recurring invoice updated.');
    }

    public function destroy(RecurringInvoice $recurring)
    {
        $this->authorizeOwn($recurring);
        $recurring->delete();
        return back()->with('success', 'Recurring invoice deleted.');
    }

    /** Toggle active/paused. */
    public function toggle(RecurringInvoice $recurring)
    {
        $this->authorizeOwn($recurring);
        $recurring->status = $recurring->status === 'active' ? 'paused' : 'active';
        $recurring->save();
        return back()->with('success', 'Status updated.');
    }

    /** Generate one invoice immediately. */
    public function runNow(RecurringInvoice $recurring, RecurringInvoiceService $svc)
    {
        $this->authorizeOwn($recurring);
        $invoice = $svc->runOnce($recurring);
        if (!$invoice) return back()->with('error', 'Could not generate invoice (no workspace).');
        return redirect()->route('user.client-invoices.edit', $invoice)->with('success', 'Invoice generated.');
    }

    protected function formData(RecurringInvoice $template): array
    {
        $ws = app('current_workspace');
        return [
            'template'  => $template,
            'clients'   => VaultClient::where('workspace_id', $ws->id)->orderBy('name')->get(['id', 'name']),
            'emails'    => VaultClientEmail::where('workspace_id', $ws->id)->get(),
            'companies' => BillingCompany::where('user_id', auth()->id())->orderBy('name')->get(),
            'catalog'   => CatalogItem::where('user_id', auth()->id())->where('is_active', true)->orderBy('name')->get(),
            'taxRules'  => TaxRule::where('user_id', auth()->id())->where('is_active', true)->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request): array
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

    protected function authorizeOwn(RecurringInvoice $recurring): void
    {
        abort_unless((int) $recurring->user_id === (int) auth()->id(), 404);
    }
}
