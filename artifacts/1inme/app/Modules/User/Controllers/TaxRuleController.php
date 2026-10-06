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
        $data = $this->validated($request);
        if (!$request->exists('components') && (int) $data['rate_bps'] !== (int) $taxRule->rate_bps) $data['components'] = null;
        $taxRule->update($data);
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
            'components' => 'nullable|array|max:8',
            'components.*.name' => 'required|string|max:64',
            'components.*.rate_percent' => 'nullable|numeric|min:0|max:100',
            'components.*.rate_bps' => 'nullable|integer|min:0|max:10000',
            'rate_bps'           => 'nullable|required_without:rate_percent|integer|min:0|max:100000',
            'rate_percent' => 'nullable|numeric|min:0|max:100',
            'billing_company_id' => ['nullable', 'integer', \Illuminate\Validation\Rule::exists('billing_companies', 'id')->where('user_id', auth()->id())],
            'inclusive'          => 'nullable|boolean',
            'is_compound'        => 'nullable|boolean',
            'is_default'         => 'nullable|boolean',
            'is_active'          => 'nullable|boolean',
        ]);
        foreach (['inclusive', 'is_compound', 'is_default', 'is_active'] as $b) {
            $data[$b] = (bool) ($data[$b] ?? false);
        }
        return \App\Services\Billing\TaxComponents::normalize($data);
    }

    protected function authorizeOwn(TaxRule $rule): void
    {
        abort_unless((int) $rule->user_id === (int) auth()->id(), 404);
    }
}
