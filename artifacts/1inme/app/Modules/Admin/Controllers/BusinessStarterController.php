<?php
namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Services\BusinessStarterCatalog;
use Illuminate\Http\Request;

class BusinessStarterController extends Controller
{
    public function edit()
    {
        return view('admin.business-starters.edit', ['catalog'=>BusinessStarterCatalog::all()]);
    }

    public function update(Request $request)
    {
        $types = array_keys(BusinessStarterCatalog::all());
        $rules = ['catalog'=>'required|array:'.implode(',', $types)];
        foreach ($types as $type) {
            $rules['catalog.'.$type] = 'required|array|size:5';
        }
        $rules += [
            'catalog.*.*'=>'required|array:key,label,items',
            'catalog.*.*.key'=>'required|string|regex:/^starter-[1-5]$/',
            'catalog.*.*.label'=>'required|string|max:80',
            'catalog.*.*.items'=>'required|array|min:1|max:30',
            'catalog.*.*.items.*'=>'required|array:category,name,description,price,duration,block_type',
            'catalog.*.*.items.*.category'=>'required|string|max:150',
            'catalog.*.*.items.*.name'=>'required|string|max:150',
            'catalog.*.*.items.*.description'=>'required|string|max:800',
            'catalog.*.*.items.*.price'=>'required|numeric|min:0|max:9999999',
            'catalog.*.*.items.*.duration'=>'required|integer|min:5|max:1440',
            'catalog.*.*.items.*.block_type'=>'nullable|in:paragraph,link,donation,tip_jar,contact_form',
        ];
        $data = $request->validate($rules)['catalog'];
        foreach ($data as $type=>$starters) {
            foreach ($starters as $i=>$starter) {
                abort_unless($starter['key'] === 'starter-'.($i+1), 422);
            }
        }
        AppSetting::put('business_starters', $data);
        return back()->with('success','Business starters saved. Existing customer content is unchanged.');
    }
}
