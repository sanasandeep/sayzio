<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\CatalogCategory;
use App\Modules\User\Models\Expense;
use Illuminate\Http\Request;

/** Business expense tracking, feeding the ledger report. */
class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $q = Expense::where('user_id', auth()->id());
        if ($company = $request->integer('company')) $q->where('billing_company_id', $company);
        $expenses   = $q->orderByDesc('spent_at')->orderByDesc('id')->paginate(30)->withQueryString();
        $categories = CatalogCategory::where('user_id', auth()->id())
            ->whereIn('kind', ['expense', 'both'])->orderBy('name')->get();
        $companies  = BillingCompany::where('user_id', auth()->id())->orderBy('name')->get();
        $totalMinor = (int) (clone $q)->sum('amount_minor') + (int) (clone $q)->sum('tax_minor');
        return view('user.billing.expenses.index', compact('expenses', 'categories', 'companies', 'totalMinor'));
    }

    public function store(Request $request)
    {
        $expense = new Expense();
        $expense->fill($this->validated($request));
        $expense->user_id = auth()->id();
        $expense->workspace_id = optional(app('current_workspace'))->id;
        $expense->save();
        return back()->with('success', 'Expense recorded.');
    }

    public function update(Request $request, Expense $expense)
    {
        $this->authorizeOwn($expense);
        $expense->update($this->validated($request));
        return back()->with('success', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $this->authorizeOwn($expense);
        $expense->delete();
        return back()->with('success', 'Expense deleted.');
    }

    protected function validated(Request $request): array
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

    protected function authorizeOwn(Expense $expense): void
    {
        abort_unless((int) $expense->user_id === (int) auth()->id(), 404);
    }
}
