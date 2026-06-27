<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\Common\Services\Emailer;
use App\Modules\Common\Services\EmailTemplateRegistry;
use App\Services\Integrations\EmailTemplateSettings;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

/**
 * Bearer-token parity for the admin "Email Templates" screen so a platform
 * admin can list every templated email grouped by category, edit any one's
 * subject/body override, preview it with sample data, and reset to the
 * built-in default from the Sayzio Mobile app.
 *
 * Mirrors the web {@see \App\Modules\Admin\Controllers\EmailTemplateController}
 * exactly: overrides persist via {@see EmailTemplateSettings}, previews render
 * through the central {@see Emailer} pipeline so they match real sends, and
 * every endpoint is gated behind the same `settings.manage` permission.
 */
class EmailTemplateController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view email templates.');
        }

        $categories = [];
        foreach (EmailTemplateRegistry::byCategory() as $category => $entries) {
            $rows = [];
            foreach ($entries as $key => $entry) {
                $rows[] = [
                    'key'         => $key,
                    'label'       => $entry['label'] ?? $key,
                    'description' => $entry['description'] ?? '',
                    'format'      => $entry['format'] ?? 'html',
                    'overridden'  => EmailTemplateSettings::hasOverride($key),
                ];
            }
            $categories[] = [
                'category' => $category,
                'label'    => EmailTemplateRegistry::categoryLabel($category),
                'templates' => $rows,
            ];
        }

        return $this->ok(['categories' => $categories]);
    }

    public function show(Request $request, string $key)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to view email templates.');
        }

        $entry = EmailTemplateRegistry::get($key);
        if ($entry === null) {
            return $this->notFound('Unknown email template.');
        }

        return $this->ok([
            'key'        => $key,
            'category'   => $entry['category'] ?? '',
            'label'      => $entry['label'] ?? $key,
            'description' => $entry['description'] ?? '',
            'format'     => $entry['format'] ?? 'html',
            'variables'  => $entry['variables'] ?? [],
            'default'    => [
                'subject' => $entry['subject'] ?? '',
                'view'    => $entry['view'] ?? null,
            ],
            'override'   => EmailTemplateSettings::get($key),
            'preview'    => Emailer::preview($key),
        ]);
    }

    public function update(Request $request, string $key)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to edit email templates.');
        }

        $entry = EmailTemplateRegistry::get($key);
        if ($entry === null) {
            return $this->notFound('Unknown email template.');
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:65535'],
            'format'  => ['required', Rule::in(['html', 'text'])],
        ]);

        EmailTemplateSettings::put($key, $data['subject'], $data['body'], $data['format'], $request->user()?->id);

        return $this->ok([
            'override' => EmailTemplateSettings::get($key),
            'preview'  => Emailer::preview($key),
        ]);
    }

    public function reset(Request $request, string $key)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to edit email templates.');
        }

        $entry = EmailTemplateRegistry::get($key);
        if ($entry === null) {
            return $this->notFound('Unknown email template.');
        }

        EmailTemplateSettings::forget($key);

        return $this->ok([
            'override' => null,
            'preview'  => Emailer::preview($key),
        ]);
    }

    public function preview(Request $request, string $key)
    {
        if (!$request->user()->hasPermission('settings.manage')) {
            return $this->forbidden('You are not allowed to preview email templates.');
        }

        $entry = EmailTemplateRegistry::get($key);
        if ($entry === null) {
            return $this->notFound('Unknown email template.');
        }

        $draft = null;
        if ($request->filled('subject') || $request->filled('body')) {
            $draft = [
                'subject' => (string) $request->input('subject', ''),
                'body'    => (string) $request->input('body', ''),
                'format'  => $request->input('format', $entry['format'] ?? 'html') === 'text' ? 'text' : 'html',
            ];
        }

        return $this->ok(Emailer::preview($key, $draft));
    }
}
