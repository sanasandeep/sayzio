<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\EmailTemplateRegistry;
use App\Modules\User\Models\BillingCompany;
use App\Services\Billing\CompanyEmailTemplateSettings;
use App\Services\Billing\CompanyMailSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Bearer-token parity for the web "company SMTP + client email templates"
 * surfaces (the per-billing-company sender config and the invoice/receipt
 * template editor) so a creator can configure their own outbound mail and
 * customise their client-facing accounting emails from the Sayzio Mobile app.
 *
 * Mirrors the web {@see \App\Modules\User\Controllers\BillingCompanyController}
 * SMTP actions and {@see \App\Modules\User\Controllers\CompanyEmailTemplateController}
 * exactly: SMTP persists via {@see CompanyMailSettings} (the encrypted password
 * follows the "blank leaves untouched, explicit clear resets" UX and is never
 * sent back to the device), template overrides persist via
 * {@see CompanyEmailTemplateSettings}, and previews render through the central
 * {@see Emailer} pipeline so they match real sends.
 *
 * Every company is resolved owner-scoped to the authenticated creator (mirrors
 * the user-scoped {@see AccountingController} companies endpoints), so a request
 * can never touch another user's company.
 */
class CompanyMailController extends Controller
{
    use ApiResponses;

    // ---- SMTP transport -------------------------------------------------

    /** Read a company's SMTP config for the editor (password masked, never sent raw). */
    public function smtpShow(Request $request, int $id)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');

        return $this->ok($this->smtpPayload($company));
    }

    /**
     * Persist a company's SMTP config, mirroring the web update(): scalar
     * fields, the encrypted password ("blank keeps, clear resets") and the
     * verification-stamp invalidation when a connection field changed. Runs a
     * lightweight connection check on save when SMTP is configured so typos are
     * caught now, exactly like the web page — the check never blocks the save.
     */
    public function smtpUpdate(Request $request, int $id)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');

        $request->validate([
            'smtp_enabled'      => 'nullable|boolean',
            'smtp_host'         => 'nullable|string|max:255|required_if:smtp_enabled,1,true',
            'smtp_port'         => 'nullable|integer|min:1|max:65535',
            'smtp_encryption'   => ['nullable', Rule::in(CompanyMailSettings::ENCRYPTION_OPTIONS)],
            'smtp_username'     => 'nullable|string|max:255',
            'smtp_password'     => 'nullable|string|max:1024',
            'smtp_clear_password' => 'nullable|boolean',
            'smtp_from_address' => 'nullable|email|max:255',
            'smtp_from_name'    => 'nullable|string|max:190',
        ], [
            'smtp_host.required_if' => 'The SMTP host is required when company SMTP is enabled.',
        ]);

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

        $verify = null;
        if (CompanyMailSettings::for($company)->isConfigured()) {
            $result = CompanyMailSettings::for($company->refresh())->verifyConnection();
            $verify = ['ok' => $result['ok'], 'error' => $result['error']];
        }

        return $this->ok($this->smtpPayload($company->refresh()) + ['verify' => $verify]);
    }

    /** Open an SMTP handshake against this company's transport (no message sent). */
    public function smtpVerify(Request $request, int $id)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');

        $result = CompanyMailSettings::for($company)->verifyConnection();

        return $this->ok($this->smtpPayload($company->refresh()) + [
            'verify' => ['ok' => $result['ok'], 'error' => $result['error']],
        ]);
    }

    /** Send a sample message through this company's SMTP and report the result. */
    public function smtpTest(Request $request, int $id)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');

        $data = $request->validate(['test_email' => 'required|email|max:255']);

        $result = CompanyMailSettings::for($company)->sendTest($data['test_email']);
        if (!$result['ok']) {
            return $this->fail('Test email failed: ' . ($result['error'] ?? 'unknown error'), 422, 'mail_send_failed');
        }

        return $this->ok([
            'sent'    => true,
            'to'      => $data['test_email'],
            'message' => 'Test email sent to ' . $data['test_email'] . '.',
        ]);
    }

    // ---- Client-facing email templates ----------------------------------

    /** List a company's editable client-facing templates with their override state. */
    public function templatesIndex(Request $request, int $id)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');

        $templates = [];
        foreach (CompanyEmailTemplateSettings::editableEntries() as $key => $entry) {
            $templates[] = [
                'key'         => $key,
                'label'       => $entry['label'] ?? $key,
                'description' => $entry['description'] ?? '',
                'format'      => $entry['format'] ?? 'html',
                'overridden'  => CompanyEmailTemplateSettings::hasOverride($company->id, $key),
            ];
        }

        return $this->ok(['templates' => $templates]);
    }

    /** Read one template's registry default, this company's override and a live preview. */
    public function templateShow(Request $request, int $id, string $key)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');
        if (!CompanyEmailTemplateSettings::isEditable($key)) return $this->notFound('Unknown email template.');

        $entry = EmailTemplateRegistry::get($key);
        if ($entry === null) return $this->notFound('Unknown email template.');

        $override = CompanyEmailTemplateSettings::get($company->id, $key);

        return $this->ok([
            'key'         => $key,
            'label'       => $entry['label'] ?? $key,
            'description' => $entry['description'] ?? '',
            'format'      => $entry['format'] ?? 'html',
            'variables'   => $entry['variables'] ?? [],
            'default'     => ['subject' => $entry['subject'] ?? ''],
            'override'    => $override,
            'preview'     => Emailer::preview($key, $override),
        ]);
    }

    /** Save this company's override for a template key. */
    public function templateUpdate(Request $request, int $id, string $key)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');
        if (!CompanyEmailTemplateSettings::isEditable($key)) return $this->notFound('Unknown email template.');
        if (EmailTemplateRegistry::get($key) === null) return $this->notFound('Unknown email template.');

        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body'    => 'required|string|max:65535',
            'format'  => ['required', Rule::in(['html', 'text'])],
        ]);

        CompanyEmailTemplateSettings::put(
            $company->id,
            $key,
            (string) ($data['subject'] ?? ''),
            $data['body'],
            $data['format'],
            (int) $request->user()->id,
        );

        return $this->ok([
            'override' => CompanyEmailTemplateSettings::get($company->id, $key),
            'preview'  => Emailer::preview($key, CompanyEmailTemplateSettings::get($company->id, $key)),
        ]);
    }

    /** Remove this company's override (reset the template to the inherited content). */
    public function templateReset(Request $request, int $id, string $key)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');
        if (!CompanyEmailTemplateSettings::isEditable($key)) return $this->notFound('Unknown email template.');

        CompanyEmailTemplateSettings::forget($company->id, $key);

        return $this->ok([
            'override' => null,
            'preview'  => Emailer::preview($key, null),
        ]);
    }

    /** Live preview of an unsaved draft (subject/body/format) without persisting. */
    public function templatePreview(Request $request, int $id, string $key)
    {
        $company = $this->resolve($request, $id);
        if (!$company) return $this->notFound('Company not found');
        if (!CompanyEmailTemplateSettings::isEditable($key)) return $this->notFound('Unknown email template.');
        if (EmailTemplateRegistry::get($key) === null) return $this->notFound('Unknown email template.');

        $data = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body'    => 'nullable|string|max:65535',
            'format'  => ['nullable', Rule::in(['html', 'text'])],
        ]);

        $draft = null;
        if ($request->filled('subject') || $request->filled('body')) {
            $draft = [
                'subject' => (string) ($data['subject'] ?? ''),
                'body'    => (string) ($data['body'] ?? ''),
                'format'  => ($data['format'] ?? 'html') === 'text' ? 'text' : 'html',
            ];
        }

        return $this->ok(Emailer::preview($key, $draft));
    }

    // ---- Helpers --------------------------------------------------------

    /** Resolve a company owned by the authenticated user, or null. */
    private function resolve(Request $request, int $id): ?BillingCompany
    {
        return BillingCompany::where('user_id', $request->user()->id)->find($id);
    }

    /**
     * SMTP status payload for the editor. Never exposes the stored password —
     * only whether one is set and a masked tail for confirmation.
     */
    private function smtpPayload(BillingCompany $company): array
    {
        $settings = CompanyMailSettings::for($company);

        return [
            'company_id'         => $company->id,
            'company_name'       => $company->name,
            'smtp_enabled'       => (bool) $company->smtp_enabled,
            'smtp_host'          => $company->smtp_host,
            'smtp_port'          => $company->smtp_port,
            'smtp_encryption'    => $company->smtp_encryption ?: 'tls',
            'smtp_username'      => $company->smtp_username,
            'smtp_from_address'  => $company->smtp_from_address,
            'smtp_from_name'     => $company->smtp_from_name,
            'has_password'       => $settings->hasPassword(),
            'masked_password'    => $settings->maskedPassword(),
            'is_configured'      => $settings->isConfigured(),
            'verified_at'        => optional($company->smtp_verified_at)->toIso8601String(),
            'encryption_options' => CompanyMailSettings::ENCRYPTION_OPTIONS,
        ];
    }

    private function nullableTrim($value): ?string
    {
        if ($value === null) return null;
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
