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
