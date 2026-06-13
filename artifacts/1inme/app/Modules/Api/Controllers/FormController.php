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

    public function submissions(Request $request, int $id)
    {
        $f = Form::where('user_id', $request->user()->id)->find($id);
        if (!$f) return $this->notFound('Form not found');

        $page = FormSubmission::where('form_id', $f->id)
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));
        return $this->ok([
            'items' => collect($page->items())->map(fn ($s) => [
                'id'         => $s->id,
                'data'       => $s->data ?? $s->payload ?? null,
                'ip'         => $s->ip ?? null,
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
