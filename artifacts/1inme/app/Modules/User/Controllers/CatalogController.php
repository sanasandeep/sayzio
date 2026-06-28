<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\CatalogCategory;
use App\Modules\User\Models\CatalogItem;
use App\Modules\User\Models\TaxRule;
use Illuminate\Http\Request;

/** Reusable item/service catalog + categories. */
class CatalogController extends Controller
{
    public function index()
    {
        $items      = CatalogItem::where('user_id', auth()->id())->with('category', 'taxRule')->orderBy('name')->get();
        $categories = CatalogCategory::where('user_id', auth()->id())->orderBy('sort')->orderBy('name')->get();
        $taxRules   = TaxRule::where('user_id', auth()->id())->where('is_active', true)->get();
        $companies  = BillingCompany::where('user_id', auth()->id())->orderBy('name')->get();
        return view('user.billing.catalog.index', compact('items', 'categories', 'taxRules', 'companies'));
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'kind' => 'nullable|in:item,expense,both',
            'sort' => 'nullable|integer|min:0',
        ]);
        $data['user_id'] = auth()->id();
        $data['kind'] = $data['kind'] ?? 'item';
        CatalogCategory::create($data);
        return back()->with('success', 'Category added.');
    }

    public function destroyCategory(CatalogCategory $category)
    {
        abort_unless((int) $category->user_id === (int) auth()->id(), 404);
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    public function storeItem(Request $request)
    {
        $item = new CatalogItem();
        $item->fill($this->validated($request));
        $item->user_id = auth()->id();
        $item->save();
        return back()->with('success', 'Item added.');
    }

    public function updateItem(Request $request, CatalogItem $item)
    {
        abort_unless((int) $item->user_id === (int) auth()->id(), 404);
        $item->update($this->validated($request));
        return back()->with('success', 'Item updated.');
    }

    public function destroyItem(CatalogItem $item)
    {
        abort_unless((int) $item->user_id === (int) auth()->id(), 404);
        $item->delete();
        return back()->with('success', 'Item deleted.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name'             => 'required|string|max:190',
            'description'      => 'nullable|string|max:2000',
            'unit_price_minor' => 'required|integer|min:0',
            'currency'         => 'nullable|string|size:3',
            'category_id'      => 'nullable|integer',
            'tax_rule_id'      => 'nullable|integer',
            'billing_company_id' => 'nullable|integer',
            'sku'              => 'nullable|string|max:64',
            'unit_label'       => 'nullable|string|max:32',
            'is_active'        => 'nullable|boolean',
        ]);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['currency']  = strtoupper($data['currency'] ?? 'USD');
        return $data;
    }
}
