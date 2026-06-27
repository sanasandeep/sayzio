<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\EmailTemplateRegistry;
use App\Services\Integrations\BillingNotificationSettings;
use App\Services\Integrations\EmailTemplateSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Admin "Email Templates" screen. Lists every templated/transactional email
 * grouped by category, lets a super admin edit each one's subject/body
 * (stored as an override via {@see EmailTemplateSettings}), preview the result
 * with sample data, and reset back to the built-in default.
 *
 * With no override saved, the central pipeline ({@see Emailer}) renders the
 * registry default — identical content to before — so editing is purely
 * opt-in. Gated by `settings.manage` at the route layer.
 */
class EmailTemplateController extends Controller
{
    public function index()
    {
        $grouped = [];
        foreach (EmailTemplateRegistry::byCategory() as $category => $entries) {
            $rows = [];
            foreach ($entries as $key => $entry) {
                $rows[$key] = [
                    'entry'    => $entry,
                    'override' => EmailTemplateSettings::hasOverride($key),
                ];
            }
            $grouped[$category] = [
                'label' => EmailTemplateRegistry::categoryLabel($category),
                'rows'  => $rows,
            ];
        }

        return view('admin.email-templates.index', [
            'grouped'          => $grouped,
            'billingCc'        => BillingNotificationSettings::ccRecipients(),
            'billingCcDefault' => !BillingNotificationSettings::isConfigured(),
        ]);
    }

    /**
     * Save the admin-managed CC list applied to platform billing emails
     * (receipts + payment reminders). Accepts newline/comma separated addresses;
     * each must be a well-formed email. An empty list is allowed and disables CC.
     */
    public function updateBillingCc(Request $request)
    {
        $emails = collect(preg_split('/[\r\n,]+/', (string) $request->input('billing_cc', '')))
            ->map(fn ($e) => trim($e))
            ->filter()
            ->unique()
            ->values()
            ->all();

        Validator::make(
            ['billing_cc' => $emails],
            ['billing_cc.*' => ['email:rfc']],
            ['billing_cc.*.email' => 'Each line must be a valid email address.'],
        )->validate();

        BillingNotificationSettings::put($emails);

        return redirect()
            ->route('admin.email-templates.index')
            ->with('success', 'Billing notification CC list saved. New billing emails will be CC\'d to these addresses.');
    }

    public function edit(string $key)
    {
        $entry = EmailTemplateRegistry::get($key);
        abort_unless($entry !== null, 404);

        $override = EmailTemplateSettings::get($key);
        $preview  = Emailer::preview($key);

        return view('admin.email-templates.edit', [
            'key'      => $key,
            'entry'    => $entry,
            'override' => $override,
            'preview'  => $preview,
            'category' => EmailTemplateRegistry::categoryLabel($entry['category'] ?? ''),
        ]);
    }

    public function update(Request $request, string $key)
    {
        $entry = EmailTemplateRegistry::get($key);
        abort_unless($entry !== null, 404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:65535'],
            'format'  => ['required', Rule::in(['html', 'text'])],
        ]);

        EmailTemplateSettings::put(
            $key,
            $data['subject'],
            $data['body'],
            $data['format'],
            $request->user()?->id,
        );

        return redirect()
            ->route('admin.email-templates.edit', $key)
            ->with('success', 'Template override saved. New sends of "' . ($entry['label'] ?? $key) . '" will use it.');
    }

    public function reset(string $key)
    {
        $entry = EmailTemplateRegistry::get($key);
        abort_unless($entry !== null, 404);

        EmailTemplateSettings::forget($key);

        return redirect()
            ->route('admin.email-templates.edit', $key)
            ->with('success', 'Reset to the built-in default. Sends now use the original content.');
    }

    /**
     * Live preview endpoint: renders the supplied draft subject/body with
     * sample variables (or the saved/default content if no draft is posted)
     * so the editor can show a live preview without saving.
     */
    public function preview(Request $request, string $key)
    {
        $entry = EmailTemplateRegistry::get($key);
        abort_unless($entry !== null, 404);

        $draft = null;
        if ($request->filled('subject') || $request->filled('body')) {
            $draft = [
                'subject' => (string) $request->input('subject', ''),
                'body'    => (string) $request->input('body', ''),
                'format'  => $request->input('format', $entry['format'] ?? 'html') === 'text' ? 'text' : 'html',
            ];
        }

        return response()->json(Emailer::preview($key, $draft));
    }
}
