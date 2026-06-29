<?php

namespace App\Modules\Api\Controllers;

use App\Modules\Api\Controllers\Concerns\ApiResponses;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class FormController extends Controller
{
    use ApiResponses;

    public function index(Request $request)
    {
        $items = Form::where('user_id', $request->user()->id)
            ->orderByDesc('id')->get()
            ->map(fn ($f) => $this->transform($f))->all();
        return $this->ok(['items' => $items]);
    }

    public function show(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');
        return $this->ok(['form' => $this->transform($f)]);
    }

    /**
     * Create a form on the spot from the mobile block editor's special
     * panel. Mirrors the web FormController@store: a title (and optional
     * starter template) is enough — the new form is seeded with the
     * template's fields plus default design/settings/notifications so it
     * is immediately usable and selectable as a `form` block.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'title'    => 'required|string|max:160',
            'template' => 'nullable|in:contact,lead,survey,registration,feedback,blank',
        ]);

        // The sanctum API path doesn't run SetActiveWorkspace, so without an
        // explicit assignment the new form lands with workspace_id = null and
        // is hidden from the workspace-scoped web Forms list. Derive the user's
        // workspace WITHOUT binding `current_workspace` — binding would also
        // activate the BelongsToWorkspace read-side global scope, which would
        // make Form::uniqueSlug() workspace-scoped and break global slug
        // uniqueness required by the public /f/{slug} route. The stateless
        // sanctum request has no session, so this matches WorkspaceContext's
        // own fallback (first accessible → lazily-created personal workspace).
        $ws = $request->user()->accessibleWorkspaces()->first()
            ?? $request->user()->ensureDefaultWorkspace();

        $template = $data['template'] ?? 'contact';
        $form = $request->user()->forms()->make([
            'slug'          => Form::uniqueSlug($data['title']),
            'title'         => $data['title'],
            'fields'        => $this->templateFields($template),
            'design'        => Form::defaultDesign(),
            'settings'      => Form::defaultSettings(),
            'notifications' => Form::defaultNotifications(),
            'is_active'     => true,
        ]);
        if ($ws) {
            $form->workspace_id = $ws->id;
        }
        $form->save();

        return $this->created(['form' => $this->transform($form)]);
    }

    /** Starter field schema by template — mirrors web FormController. */
    protected function templateFields(string $template): array
    {
        return match ($template) {
            'lead' => [
                ['id' => 'name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                ['id' => 'email', 'type' => 'email', 'label' => 'Work Email', 'required' => true],
                ['id' => 'phone', 'type' => 'phone', 'label' => 'Phone', 'required' => false],
                ['id' => 'company', 'type' => 'text', 'label' => 'Company', 'required' => false],
                ['id' => 'budget', 'type' => 'select', 'label' => 'Budget', 'options' => ['< $1k', '$1k–$5k', '$5k–$25k', '$25k+'], 'required' => false],
                ['id' => 'notes', 'type' => 'textarea', 'label' => 'Tell us about your project', 'rows' => 4],
            ],
            'survey' => [
                ['id' => 'satisfaction', 'type' => 'rating', 'label' => 'How satisfied are you?', 'max' => 5, 'required' => true],
                ['id' => 'recommend', 'type' => 'scale', 'label' => 'How likely are you to recommend us?', 'min' => 0, 'max' => 10, 'required' => true],
                ['id' => 'comments', 'type' => 'textarea', 'label' => 'Any additional feedback?', 'rows' => 4],
            ],
            'registration' => [
                ['id' => 'name', 'type' => 'text', 'label' => 'Full Name', 'required' => true],
                ['id' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true],
                ['id' => 'event_date', 'type' => 'date', 'label' => 'Preferred Date', 'required' => true],
                ['id' => 'attendees', 'type' => 'number', 'label' => 'Number of Attendees', 'min' => 1, 'required' => true],
                ['id' => 'consent', 'type' => 'consent', 'label' => 'I agree to the terms and privacy policy', 'required' => true],
            ],
            'feedback' => [
                ['id' => 'rating', 'type' => 'rating', 'label' => 'Rate your experience', 'max' => 5, 'required' => true],
                ['id' => 'category', 'type' => 'radio', 'label' => 'What is this about?', 'options' => ['Bug', 'Suggestion', 'Compliment', 'Other'], 'required' => true],
                ['id' => 'message', 'type' => 'textarea', 'label' => 'Your message', 'required' => true, 'rows' => 4],
                ['id' => 'email', 'type' => 'email', 'label' => 'Email (optional)', 'required' => false],
            ],
            'blank' => [],
            default => Form::defaultFields(),
        };
    }

    /** Read the form's payment config (paid forms — Task #2319). */
    public function payment(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        return $this->ok([
            'payment'        => $f->paymentConfig(),
            'can_paid_forms' => (bool) $request->user()->getPlanFeature('paid_forms', false),
            'has_gateway'    => (bool) $request->user()->defaultPaymentConnection(),
        ]);
    }

    /** Toggle / configure the form's fixed price (Pro and above). */
    public function updatePayment(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        if (! $request->user()->getPlanFeature('paid_forms', false)) {
            return $this->forbidden('Paid forms require a Pro plan or above.');
        }

        $data = $request->validate([
            'enabled'  => 'sometimes|boolean',
            'amount'   => 'nullable|numeric|min:0|max:100000',
            'currency' => 'required|string|size:3',
            'label'    => 'nullable|string|max:60',
        ]);

        $enabled     = (bool) ($data['enabled'] ?? false);
        $amountCents = (int) round(((float) ($data['amount'] ?? 0)) * 100);
        if ($enabled && $amountCents <= 0) {
            return $this->fail('Set a price greater than zero to require payment.', 422);
        }

        if ($enabled && ! $request->user()->defaultPaymentConnection()) {
            return $this->fail('Connect a payment gateway in Payouts before charging customers to submit this form.', 422);
        }

        $settings = array_merge(Form::defaultSettings(), $f->settings ?? []);
        $settings['payment'] = array_merge(
            Form::defaultSettings()['payment'],
            (array) ($settings['payment'] ?? []),
            [
                'enabled'      => $enabled,
                'mode'         => 'fixed',
                'amount_cents' => $amountCents,
                'currency'     => strtoupper($data['currency']),
                'label'        => $data['label'] ?? null,
            ]
        );
        $f->update(['settings' => $settings]);

        return $this->ok(['payment' => $f->fresh()->paymentConfig()]);
    }

    /**
     * Read the form's one-way WhatsApp alert toggle — mobile parity for the
     * web FormController@notifications. Returns whether the owner currently
     * has the alert on, plus whether they even have a verified WhatsApp
     * number (so the client can gate the switch exactly like the web does).
     */
    public function whatsappAlert(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        $n = array_replace_recursive(Form::defaultNotifications(), $f->notifications ?? []);

        return $this->ok([
            'enabled'             => (bool) ($n['whatsapp']['enabled'] ?? false),
            'has_whatsapp_number' => (bool) $request->user()->hasWhatsappNumber(),
        ]);
    }

    /**
     * Toggle the form's one-way WhatsApp alert. Mirrors the web gating: the
     * alert can only ever be stored as enabled when the owner has a verified
     * WhatsApp number on file, so it can never be "enabled but undeliverable".
     * Other notification channels in the JSON are preserved untouched.
     */
    public function updateWhatsappAlert(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        $data = $request->validate(['enabled' => 'required|boolean']);

        $hasNumber = (bool) $request->user()->hasWhatsappNumber();
        $n = array_replace_recursive(Form::defaultNotifications(), $f->notifications ?? []);
        $n['whatsapp'] = ['enabled' => ((bool) $data['enabled']) && $hasNumber];
        $f->update(['notifications' => $n]);

        return $this->ok([
            'enabled'             => (bool) $n['whatsapp']['enabled'],
            'has_whatsapp_number' => $hasNumber,
        ]);
    }

    /** Advanced form analytics (Pro and above) — mobile parity. */
    public function analytics(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        if (! $request->user()->getPlanFeature('form_analytics_advanced', false)) {
            return $this->forbidden('Advanced form analytics require a Pro plan or above.');
        }

        return $this->ok([
            'analytics' => app(\App\Modules\User\Controllers\FormController::class)
                ->buildAdvancedAnalyticsFor($f),
        ]);
    }

    public function submissions(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        $page = FormSubmission::where('form_id', $f->id)
            ->completed()
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));
        return $this->ok([
            'items' => collect($page->items())->map(fn ($s) => [
                'id'             => $s->id,
                'data'           => $s->data ?? $s->payload ?? null,
                'ip'             => $s->ip ?? null,
                'payment_status' => $s->payment_status ?? 'none',
                'amount_cents'   => $s->amount_cents !== null ? (int) $s->amount_cents : null,
                'currency'       => $s->currency,
                'line_items'     => array_values(array_map(fn ($li) => [
                    'field'        => $li['field'] ?? null,
                    'label'        => $li['label'] ?? null,
                    'detail'       => $li['detail'] ?? null,
                    'amount_cents' => (int) ($li['amount_cents'] ?? 0),
                ], (array) ($s->line_items ?? []))),
                'paid_at'        => optional($s->paid_at)->toIso8601String(),
                'created_at' => optional($s->created_at)->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page'     => $page->perPage(),
                'total'        => $page->total(),
                'last_page'    => $page->lastPage(),
            ],
        ]);
    }

    /**
     * Refund a paid form submission (Task #2322). Owner-only — mirrors the
     * web FormController@refundSubmission: reverses the gateway charge,
     * flips payment_status to `refunded`, and writes a negative
     * TYPE_FORM_REFUNDED ledger row via the monetization service.
     */
    public function refundSubmission(Request $request, int $id, int $submissionId)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        $submission = FormSubmission::where('form_id', $f->id)->find($submissionId);
        if (!$submission) return $this->notFound('Submission not found');
        if (!$submission->isRefundable()) {
            return $this->fail('Only paid submissions can be refunded.', 422);
        }

        $ok = app(\App\Services\Monetization\MonetizationCheckout::class)
            ->refundFormSubmission($submission->id);
        if (!$ok) {
            return $this->fail('Could not refund this submission.', 422);
        }

        $submission->refresh();
        return $this->ok([
            'id'             => $submission->id,
            'payment_status' => $submission->payment_status ?? 'none',
            'amount_cents'   => $submission->amount_cents !== null ? (int) $submission->amount_cents : null,
            'currency'       => $submission->currency,
            'refunded_at'    => optional($submission->refunded_at)->toIso8601String(),
        ]);
    }

    public function exportSubmissions(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        $rows = FormSubmission::where('form_id', $f->id)->orderByDesc('id')->get();

        $columns = collect($f->fields ?? [])
            ->filter(fn ($x) => !in_array($x['type'] ?? '', ['heading', 'paragraph', 'divider', 'page_break', 'section'], true))
            ->pluck('id')->all();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_merge(['#', 'submitted_at', 'ip'], $columns));
        foreach ($rows as $s) {
            $data = (array) ($s->data ?? $s->payload ?? []);
            $line = [$s->id, optional($s->created_at)->toIso8601String(), $s->ip ?? ''];
            foreach ($columns as $c) {
                $v = $data[$c] ?? '';
                if (is_array($v)) $v = implode(', ', $v);
                $line[] = (string) $v;
            }
            fputcsv($output, $line);
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="form-' . $f->id . '-submissions.csv"',
        ]);
    }

    protected function transform(Form $f): array
    {
        return [
            'id'         => $f->id,
            'title'      => $f->title,
            'slug'       => $f->slug,
            'fields'     => $f->fields ?? [],
            'is_active'  => (bool) ($f->is_active ?? true),
            'submissions_count' => (int) ($f->submissions_count ?? FormSubmission::where('form_id', $f->id)->count()),
            'public_url' => $f->slug ? url('/f/' . $f->slug) : null,
            'created_at' => optional($f->created_at)->toIso8601String(),
        ];
    }
}
