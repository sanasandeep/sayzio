<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\EmailTemplateRegistry;
use App\Modules\User\Models\BillingCompany;
use App\Services\Billing\CompanyEmailTemplateSettings;
use Illuminate\Http\Request;

/**
 * Creator-facing editor for a billing company's client-facing accounting email
 * templates (the invoice + receipt emails sent to that company's clients).
 * Mirrors the admin EmailTemplateController but scoped to one of the current
 * user's companies and limited to {@see CompanyEmailTemplateSettings::KEYS}.
 *
 * Overrides saved here layer over the admin/global override and the registry
 * default; a reset removes only this company's override. No new email types are
 * introduced and platform/global/admin templates are never touched.
 */
class CompanyEmailTemplateController extends Controller
{
    public function index(BillingCompany $company)
    {
        $this->authorizeOwn($company);

        $templates = [];
        foreach (CompanyEmailTemplateSettings::editableEntries() as $key => $entry) {
            $templates[$key] = [
                'entry'    => $entry,
                'override' => CompanyEmailTemplateSettings::get($company->id, $key),
            ];
        }

        return view('user.billing.companies.emails.index', compact('company', 'templates'));
    }

    public function edit(BillingCompany $company, string $key)
    {
        $this->authorizeOwn($company);
        abort_unless(CompanyEmailTemplateSettings::isEditable($key), 404);

        $entry    = EmailTemplateRegistry::get($key);
        abort_if($entry === null, 404);

        $override = CompanyEmailTemplateSettings::get($company->id, $key);
        $category = EmailTemplateRegistry::categoryFor($key);
        $preview  = Emailer::preview($key, $override);

        return view('user.billing.companies.emails.edit', compact(
            'company', 'key', 'entry', 'override', 'category', 'preview'
        ));
    }

    public function update(Request $request, BillingCompany $company, string $key)
    {
        $this->authorizeOwn($company);
        abort_unless(CompanyEmailTemplateSettings::isEditable($key), 404);
        abort_if(EmailTemplateRegistry::get($key) === null, 404);

        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body'    => 'required|string',
            'format'  => 'required|in:html,text',
        ]);

        CompanyEmailTemplateSettings::put(
            $company->id,
            $key,
            (string) ($data['subject'] ?? ''),
            $data['body'],
            $data['format'],
            (int) auth()->id(),
        );

        return redirect()
            ->route('user.billing.companies.emails.edit', [$company, $key])
            ->with('success', 'Template saved for this company.');
    }

    public function reset(BillingCompany $company, string $key)
    {
        $this->authorizeOwn($company);
        abort_unless(CompanyEmailTemplateSettings::isEditable($key), 404);

        CompanyEmailTemplateSettings::forget($company->id, $key);

        return redirect()
            ->route('user.billing.companies.emails.edit', [$company, $key])
            ->with('success', 'Template reset — this company now uses the inherited content.');
    }

    public function preview(Request $request, BillingCompany $company, string $key)
    {
        $this->authorizeOwn($company);
        abort_unless(CompanyEmailTemplateSettings::isEditable($key), 404);
        abort_if(EmailTemplateRegistry::get($key) === null, 404);

        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body'    => 'nullable|string',
            'format'  => 'nullable|in:html,text',
        ]);

        $draft = [
            'subject' => $data['subject'] ?? '',
            'body'    => $data['body'] ?? '',
            'format'  => $data['format'] ?? 'html',
        ];

        return response()->json(Emailer::preview($key, $draft));
    }

    protected function authorizeOwn(BillingCompany $company): void
    {
        abort_unless((int) $company->user_id === (int) auth()->id(), 404);
    }
}
