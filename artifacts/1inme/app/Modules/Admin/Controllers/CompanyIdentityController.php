<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AppSetting;
use App\Modules\User\Support\LinkTypeCategories;
use App\Modules\Common\Support\CompanyIdentity;
use Illuminate\Http\Request;

/**
 * Admin screen for the operating company's legal identity — legal name,
 * registered office, governing-law jurisdiction and the contact mailboxes.
 *
 * Each field is stored as its own {@see AppSetting} key and flows into the
 * long-form legal policy pages (via {{token}} substitution), the public
 * footer and the marketing-site mirror, so legal disclosures stay
 * consistent from one place. Blank fields fall back to the code defaults
 * in {@see CompanyIdentity}.
 */
class CompanyIdentityController extends Controller
{
    public function edit()
    {
        $fields = CompanyIdentity::fields();
        $defaults = CompanyIdentity::defaults();
        $resolved = CompanyIdentity::all();
        $values = [];
        foreach (array_keys($fields) as $key) {
            // Show the admin's stored override (empty if unset) so they can
            // see what's explicitly configured vs. what is falling back.
            $values[$key] = (string) AppSetting::get($key, '');
        }
        $jurisdiction = CompanyIdentity::jurisdiction();
        $linkTypeCountKey      = LinkTypeCategories::MARKETING_COUNT_SETTING;
        $linkTypeCountOverride = AppSetting::get($linkTypeCountKey);
        $linkTypeCatalogue     = LinkTypeCategories::catalogueCount();
        $linkTypeVisible       = LinkTypeCategories::visibleCount();

        return view('admin.company-identity.edit', compact(
            'fields', 'defaults', 'resolved', 'values', 'jurisdiction',
            'linkTypeCountKey', 'linkTypeCountOverride', 'linkTypeCatalogue', 'linkTypeVisible'
        ));
    }

    public function update(Request $request)
    {
        $fields = CompanyIdentity::fields();

        $rules = [];
        foreach ($fields as $key => $meta) {
            $type = $meta['type'] ?? 'text';
            if ($type === 'email') {
                $rules[$key] = ['nullable', 'string', 'max:255', 'email'];
            } elseif ($type === 'url') {
                $rules[$key] = ['nullable', 'string', 'max:500', 'url', 'regex:#^https?://#i'];
            } elseif ($type === 'textarea') {
                $rules[$key] = ['nullable', 'string', 'max:2000'];
            } else {
                $rules[$key] = ['nullable', 'string', 'max:255'];
            }
        }

        // Not a CompanyIdentity field: an optional override for the figure that
        // {{link_type_count}} resolves to. Blank means "use the catalogue".
        $rules[LinkTypeCategories::MARKETING_COUNT_SETTING] = ['nullable', 'integer', 'min:1', 'max:999'];

        $data = $request->validate($rules);

        $override = $data[LinkTypeCategories::MARKETING_COUNT_SETTING] ?? null;
        AppSetting::put(
            LinkTypeCategories::MARKETING_COUNT_SETTING,
            ($override === null || $override === '') ? null : (int) $override
        );

        foreach (array_keys($fields) as $key) {
            $value = trim((string) ($data[$key] ?? ''));
            AppSetting::put($key, $value === '' ? null : $value);
        }

        return back()->with('success', 'Company identity updated.');
    }
}
