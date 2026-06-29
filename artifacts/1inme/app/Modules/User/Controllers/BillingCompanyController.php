<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\BillingCompany;
use App\Modules\User\Models\TaxRule;
use App\Services\Billing\CompanyMailSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
        $company    = new BillingCompany();
        $taxRules   = TaxRule::where('user_id', auth()->id())->where('is_active', true)->get();
        $smtpWarning = null;
        return view('user.billing.companies.edit', compact('company', 'taxRules', 'smtpWarning'));
    }

    public function edit(BillingCompany $company)
    {
        $this->authorizeOwn($company);
        $taxRules    = TaxRule::where('user_id', auth()->id())->where('is_active', true)->get();
        $smtpWarning = CompanyMailSettings::for($company)->deliveryWarning();
        return view('user.billing.companies.edit', compact('company', 'taxRules', 'smtpWarning'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $this->validateSmtp($request);
        $this->validateLogo($request);
        $data['user_id'] = auth()->id();
        $data['workspace_id'] = optional(app('current_workspace'))->id;
        $company = DB::transaction(function () use ($data, $request) {
            $c = BillingCompany::create($data);
            $this->applyLogo($c, $request);
            $this->applySmtp($c, $request);
            $this->syncDefault($c);
            return $c;
        });
        return redirect()->route('user.billing.companies.edit', $company)->with('success', 'Company created.');
    }

    public function update(Request $request, BillingCompany $company)
    {
        $this->authorizeOwn($company);
        $data = $this->validated($request);
        $this->validateSmtp($request);
        $this->validateLogo($request);
        DB::transaction(function () use ($company, $data, $request) {
            $company->update($data);
            $this->applyLogo($company, $request);
            $this->applySmtp($company, $request);
            $this->syncDefault($company);
        });
        return redirect()->route('user.billing.companies.edit', $company)->with('success', 'Company updated.');
    }

    /**
     * Open an SMTP handshake against this company's transport (no message sent)
     * and stamp it verified on success. Mirrors the admin Mail settings action.
     */
    public function verifySmtp(BillingCompany $company)
    {
        $this->authorizeOwn($company);
        $res = CompanyMailSettings::for($company)->verifyConnection();
        return $res['ok']
            ? back()->with('success', 'SMTP connection verified successfully.')
            : back()->with('error', 'SMTP check failed: ' . $res['error']);
    }

    /**
     * Send a sample message through this company's SMTP and report the result.
     *
     * The recipient is restricted to an address the creator already controls
     * (their own account email, this company's contact email, or its configured
     * sender address) so the test send can't be abused as a spam relay to
     * arbitrary third parties.
     */
    public function testSmtp(Request $request, BillingCompany $company)
    {
        $this->authorizeOwn($company);
        $data = $request->validate(['test_email' => 'required|email']);

        if (!$company->allowsTestRecipient($data['test_email'], $request->user())) {
            return back()->withErrors([
                'test_email' => 'Test emails can only be sent to your own account email, this company\'s contact email, or its sender (from) address.',
            ])->withInput();
        }

        $res = CompanyMailSettings::for($company)->sendTest($data['test_email']);
        return $res['ok']
            ? back()->with('success', 'Test email sent to ' . $data['test_email'] . '.')
            : back()->with('error', 'Test email failed: ' . $res['error']);
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

    /** Validate the optional logo upload (an image, ≤2MB). */
    protected function validateLogo(Request $request): void
    {
        $request->validate([
            'logo'        => 'nullable|image|mimes:jpeg,jpg,png,gif,webp,svg|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);
    }

    /**
     * Persist (or clear) the company logo on the public disk so the invoice /
     * receipt PDF renderer can inline it (ClientInvoicePdfRenderer::logoDataUri
     * checks the public disk first). Replacing or removing a logo deletes the
     * previous file so orphans don't accumulate.
     */
    protected function applyLogo(BillingCompany $company, Request $request): void
    {
        if ($request->boolean('remove_logo') && $company->logo_path) {
            $this->deleteLogoFile($company->logo_path);
            $company->logo_path = null;
            $company->save();
            return;
        }

        if ($request->hasFile('logo')) {
            $old = $company->logo_path;
            $company->logo_path = $request->file('logo')->store('billing/company-logos', 'public');
            $company->save();
            if ($old) {
                $this->deleteLogoFile($old);
            }
        }
    }

    /** Best-effort delete of a stored logo file from the public disk. */
    private function deleteLogoFile(string $path): void
    {
        try {
            Storage::disk('public')->delete($path);
        } catch (\Throwable $e) {
            // ignore — the row no longer references it
        }
    }

    /** Validate the per-company SMTP fields (secrets handled separately). */
    protected function validateSmtp(Request $request): void
    {
        $request->validate([
            'smtp_enabled'      => 'nullable|boolean',
            'smtp_host'         => 'nullable|string|max:255|required_if:smtp_enabled,1',
            'smtp_port'         => 'nullable|integer|min:1|max:65535',
            'smtp_encryption'   => 'nullable|in:tls,ssl,none',
            'smtp_username'     => 'nullable|string|max:255',
            'smtp_password'     => 'nullable|string|max:1024',
            'smtp_from_address' => 'nullable|email|max:255',
            'smtp_from_name'    => 'nullable|string|max:190',
        ]);
    }

    /**
     * Persist the per-company SMTP settings: scalar fields by forceFill, the
     * password via CompanyMailSettings (Crypt), and invalidate a prior
     * verification stamp whenever a connection-affecting field changed.
     */
    protected function applySmtp(BillingCompany $company, Request $request): void
    {
        $company->forceFill([
            'smtp_enabled'      => $request->boolean('smtp_enabled'),
            'smtp_host'         => $this->nullableTrim($request->input('smtp_host')),
            'smtp_port'         => $request->input('smtp_port') ?: null,
            'smtp_encryption'   => $request->input('smtp_encryption') ?: null,
            'smtp_username'     => $this->nullableTrim($request->input('smtp_username')),
            'smtp_from_address' => $this->nullableTrim($request->input('smtp_from_address')),
            'smtp_from_name'    => $this->nullableTrim($request->input('smtp_from_name')),
        ]);

        $settings = CompanyMailSettings::for($company);
        if ($request->boolean('smtp_clear_password')) {
            $settings->setPassword(null);
        } elseif (filled($request->input('smtp_password'))) {
            $settings->setPassword($request->input('smtp_password'));
        }

        if ($company->isDirty(['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password_enc'])) {
            $company->smtp_verified_at = null;
        }

        $company->save();
    }

    private function nullableTrim($value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    protected function authorizeOwn(BillingCompany $company): void
    {
        abort_unless((int) $company->user_id === (int) auth()->id(), 404);
    }
}
