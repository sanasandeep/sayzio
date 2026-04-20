<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\TaxJurisdiction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaxController extends Controller
{
    public function index(Request $request)
    {
        $rows = TaxJurisdiction::orderBy('country')->orderBy('region')->orderBy('kind')->paginate(50);
        return view('admin.taxes.index', compact('rows'));
    }

    public function create()
    {
        return view('admin.taxes.create', ['row' => new TaxJurisdiction()]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRow($request);
        TaxJurisdiction::create($data);
        return redirect()->route('admin.taxes.index')->with('success', 'Tax jurisdiction created.');
    }

    public function edit(TaxJurisdiction $tax)
    {
        return view('admin.taxes.create', ['row' => $tax]);
    }

    public function update(Request $request, TaxJurisdiction $tax)
    {
        $data = $this->validateRow($request);
        $tax->update($data);
        return redirect()->route('admin.taxes.index')->with('success', 'Tax jurisdiction updated.');
    }

    public function destroy(TaxJurisdiction $tax)
    {
        $tax->delete();
        return redirect()->route('admin.taxes.index')->with('success', 'Tax jurisdiction removed.');
    }

    private function validateRow(Request $request): array
    {
        $data = $request->validate([
            'country'            => ['required', 'string', 'size:2'],
            'region'             => ['nullable', 'string', 'max:8'],
            'kind'               => ['required', Rule::in(['GST_INTRA', 'GST_INTER', 'VAT', 'SALES', 'NONE'])],
            'label'              => ['nullable', 'string', 'max:255'],
            'rate_percent'       => ['required', 'numeric', 'min:0', 'max:100'],
            'b2b_reverse_charge' => ['nullable', 'boolean'],
            'effective_from'     => ['nullable', 'date'],
            'effective_to'       => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active'          => ['nullable', 'boolean'],
        ]);
        $data['country'] = strtoupper($data['country']);
        $data['region']  = !empty($data['region']) ? strtoupper($data['region']) : null;
        $data['b2b_reverse_charge'] = $request->boolean('b2b_reverse_charge');
        $data['is_active'] = $request->boolean('is_active', true);
        return $data;
    }
}
