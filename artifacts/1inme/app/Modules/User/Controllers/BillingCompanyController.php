<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\TaxRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Per-user "billing companies" — the legal entities that issue documents. */
class BillingCompanyController extends Controller
{
    public function index()
    {
        $companies = BillingCompany::where('user_id', auth()->id())
            ->orderByDesc('is_default')->orderBy('name')->get();
        return view('user.billing.companies.index', compact('companies'));
    }

    public function create()
    {
        $company  = new BillingCompany();
        $taxRules = TaxRule::where('user_id', auth()->id())->where('is_active', true)->get();
        return view('user.billing.companies.edit', compact('company', 'taxRules'));
    }

    public function edit(BillingCompany $company)
    {
        $this->authorizeOwn($company);
        $taxRules = TaxRule::where('user_id', auth()->id())->where('is_active', true)->get();
        return view('user.billing.companies.edit', compact('company', 'taxRules'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['user_id'] = auth()->id();
        $data['workspace_id'] = optional(app('current_workspace'))->id;
        $company = DB::transaction(function () use ($data) {
            $c = BillingCompany::create($data);
            $this->syncDefault($c);
            return $c;
        });
        return redirect()->route('user.billing.companies.index')->with('success', 'Company created.');
    }

    public function update(Request $request, BillingCompany $company)
    {
        $this->authorizeOwn($company);
        $data = $this->validated($request);
        DB::transaction(function () use ($company, $data) {
            $company->update($data);
            $this->syncDefault($company);
        });
        return redirect()->route('user.billing.companies.index')->with('success', 'Company updated.');
    }

    public function destroy(BillingCompany $company)
    {
        $this->authorizeOwn($company);
        $company->delete();
        return back()->with('success', 'Company deleted.');
    }

    protected function syncDefault(BillingCompany $company): void
    {
        if ($company->is_default) {
            BillingCompany::where('user_id', $company->user_id)
                ->where('id', '!=', $company->id)->update(['is_default' => false]);
        }
    }

    protected function validated(Request $request): array
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

    protected function authorizeOwn(BillingCompany $company): void
    {
        abort_unless((int) $company->user_id === (int) auth()->id(), 404);
    }
}
