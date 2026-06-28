<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\TaxRule;
use Illuminate\Http\Request;

/** Reusable tax rules (rate in basis points) consumed by InvoiceCalculator. */
class TaxRuleController extends Controller
{
    public function index()
    {
        $rules     = TaxRule::where('user_id', auth()->id())->orderBy('name')->get();
        $companies = BillingCompany::where('user_id', auth()->id())->orderBy('name')->get();
        return view('user.billing.tax_rules.index', compact('rules', 'companies'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = auth()->id();
        TaxRule::create($data);
        return back()->with('success', 'Tax rule created.');
    }

    public function update(Request $request, TaxRule $taxRule)
    {
        $this->authorizeOwn($taxRule);
        $taxRule->update($this->validated($request));
        return back()->with('success', 'Tax rule updated.');
    }

    public function destroy(TaxRule $taxRule)
    {
        $this->authorizeOwn($taxRule);
        $taxRule->delete();
        return back()->with('success', 'Tax rule deleted.');
    }

    protected function validated(Request $request): array
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

    protected function authorizeOwn(TaxRule $rule): void
    {
        abort_unless((int) $rule->user_id === (int) auth()->id(), 404);
    }
}
