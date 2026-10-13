<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Form;
use App\Modules\User\Services\FormResponseAnalytics;
use App\Modules\User\Services\FormVisitTracker;
use App\Modules\User\Services\FormWebhookDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FormExperienceController extends Controller
{
    private function authorizeForm(Form $form): void
    {
        abort_unless($form->user_id === workspace_owner_id(), 403);
    }

    public function reports(Request $r, Form $form)
    {
        $this->authorizeForm($form);
        if (! workspace_owner()?->getPlanFeature('form_analytics_advanced', false)) {
            return redirect()->route('user.upgrade')->with('error', 'Advanced form reports are not included in your current plan.');
        }
        $r->validate(['from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d|after_or_equal:from', 'fields' => 'nullable|array|max:100', 'fields.*' => 'string|max:100', 'filters' => 'nullable|array|max:20', 'filters.*' => 'nullable|string|max:200', 'export' => 'nullable|in:csv', 'chart' => 'nullable|in:bars,table']);
        $r->merge(['fields' => $r->input('fields') ?? [], 'filters' => $r->input('filters') ?? [], 'chart' => $r->input('chart') ?: 'bars']);
        $from = $r->input('from') ?: now()->subDays(29)->toDateString();
        $to = $r->input('to') ?: now()->toDateString();
        abort_if($from > $to, 422, 'The end date must follow the start date.');
        abort_if(Carbon::parse($from)->diffInDays($to) > 366, 422, 'Choose a range of up to one year.');
        $report = app(FormResponseAnalytics::class)->build($form, $from, $to, $r->input('fields', []), $r->input('filters', []));
        if ($r->input('export') === 'csv') {
            return response()->streamDownload(function () use ($report) {
                $h = fopen('php://output', 'w');
                fputcsv($h, ['Question', 'Type', 'Answered', 'Completion %', 'Value', 'Count', 'Numeric total', 'Numeric average', 'Average rank']);
                foreach ($report['reports'] as $f) {
                    $dist = $f['distribution'] ?: ['' => null];
                    foreach ($dist as $label => $n) {
                        $safe = fn ($v) => is_string($v) && preg_match('/^[=+@\-\t\r]/', $v) ? "'".$v : $v;
                        fputcsv($h, array_map($safe, [$f['label'], $f['type'], $f['filled'], $f['rate'], $label, $n, $f['numeric']['sum'] ?? '', $f['numeric']['average'] ?? '', $f['ranking'][$label] ?? '']));
                    }
                }fclose($h);
            }, 'form-'.$form->id.'-report.csv', ['Content-Type' => 'text/csv']);
        }

        return view('user.forms.reports', compact('form', 'report'));
    }

    public function saveReport(Request $r, Form $form)
    {
        $this->authorizeForm($form);
        abort_unless(workspace_owner()?->getPlanFeature('form_analytics_advanced', false), 403, 'Advanced reports require an eligible plan.');
        $v = $r->validate(['name' => 'required|string|max:80', 'fields' => 'nullable|array|max:100', 'fields.*' => 'string|max:100', 'from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d|after_or_equal:from', 'chart' => 'required|in:bars,table', 'filters' => 'nullable|array|max:20', 'filters.*' => 'nullable|string|max:200']);
        $allowed = collect($form->fields ?? [])->pluck('id')->all();
        abort_if(array_diff($v['fields'] ?? [], $allowed), 422);
        DB::transaction(function () use ($form, $v) {
            $locked = Form::whereKey($form->id)->lockForUpdate()->firstOrFail();
            $s = $locked->settings ?? [];
            $saved = $s['saved_reports'] ?? [];
            abort_if(count($saved) >= 20, 422, 'You can save up to 20 report layouts.');
            $saved[] = $v;
            $s['saved_reports'] = $saved;
            $locked->update(['settings' => $s]);
        });

        return back()->with('success', 'Report layout saved.');
    }

    public function deleteReport(Request $r, Form $form, int $index)
    {
        $this->authorizeForm($form);
        abort_unless(workspace_owner()?->getPlanFeature('form_analytics_advanced', false), 403, 'Advanced reports require an eligible plan.');
        DB::transaction(function () use ($form, $index) {
            $locked = Form::whereKey($form->id)->lockForUpdate()->firstOrFail();
            $settings = $locked->settings ?? [];
            $saved = $settings['saved_reports'] ?? [];
            abort_unless(isset($saved[$index]), 404);
            array_splice($saved, $index, 1);
            $settings['saved_reports'] = $saved;
            $locked->update(['settings' => $settings]);
        });

        return back()->with('success', 'Report layout removed.');
    }

    public function preview(Request $r, Form $form)
    {
        $this->authorizeForm($form);
        // Reuse the production renderer and validation without persisting draft changes.
        $data = $r->validate(['fields' => 'nullable|array|max:200', 'fields.*' => 'array', 'fields.*.id' => 'required|string|max:100', 'fields.*.type' => 'required|string', 'fields.*.label' => 'nullable|string|max:1000', 'fields.*.help' => 'nullable|string|max:2000', 'fields.*.placeholder' => 'nullable|string|max:1000', 'fields.*.options' => 'nullable|array|max:200', 'title' => 'nullable|string|max:200', 'description' => 'nullable|string|max:2000', 'design' => 'nullable|array']);
        $draft = clone $form;
        if (isset($data['fields'])) {
            $allowed = array_keys(Form::fieldTypes());
            $draft->fields = array_values(array_filter($data['fields'], fn ($f) => isset($f['id']) && is_string($f['id']) && preg_match('/^[a-zA-Z0-9_\-]+$/', $f['id']) && in_array($f['type'] ?? '', $allowed, true)));
        }
        if (isset($data['title'])) {
            $draft->title = $data['title'];
        }if (isset($data['description'])) {
            $draft->description = $data['description'];
        }
        $design = array_merge(Form::defaultDesign(), $form->design ?? []);
        foreach ($data['design'] ?? [] as $key => $value) {
            if ($key === 'custom_css' && is_string($value)) {
                $design[$key] = str_ireplace('</style', '', mb_substr($value, 0, 50000));
            } elseif (in_array($key, ['choice_style', 'theme', 'button_style', 'layout', 'input_style', 'input_shape', 'density', 'focus_style', 'card_image_mode'], true)) {
                $sets = ['choice_style' => ['plain', 'cards', 'pills'], 'theme' => ['light', 'dark', 'glass'], 'button_style' => ['gradient', 'solid', 'outline'], 'layout' => ['stacked', 'inline', 'oneq'], 'input_style' => ['soft', 'outline', 'underline', 'filled', 'ghost', 'elevated', 'accent_bar'], 'input_shape' => ['auto', 'pill', 'square'], 'density' => ['comfortable', 'compact'], 'focus_style' => ['glow', 'border', 'fill'], 'card_image_mode' => ['cover', 'contain', 'tile']];
                if (in_array($value, $sets[$key], true)) {
                    $design[$key] = $value;
                }
            } elseif (in_array($key, ['accent', 'background', 'card_color', 'text'], true) && is_string($value) && preg_match('/^#[0-9a-fA-F]{3,8}$/', $value)) {
                $design[$key] = $value;
            } elseif (in_array($key, ['border_radius', 'card_image_opacity'], true)) {
                $design[$key] = max(0, min($key === 'border_radius' ? 48 : 100, (int) $value));
            } elseif (in_array($key, ['button_label', 'font'], true) && is_string($value)) {
                $design[$key] = mb_substr($value, 0, 60);
            } elseif ($key === 'show_branding') {
                $design[$key] = (bool) $value;
            } elseif (in_array($key, ['logo', 'cover', 'card_image'], true) && is_string($value) && (preg_match('#^data:image/(jpeg|png|webp);base64,[a-zA-Z0-9+/=]+$#', $value) || str_starts_with($value, '/') || preg_match('#^https?://#', $value))) {
                $design[$key] = $value;
            }
        }
        $draft->design = $design;
        $settings = $draft->settings ?? [];
        $settings['require_login'] = false;
        $settings['close_after'] = null;
        $settings['submission_limit'] = null;
        $settings['payment']['enabled'] = false;
        $draft->settings = $settings;

        return response()->view('common.form', ['form' => $draft, 'embed' => true, 'preview' => true, 'mathQuestion' => null])->header('Cache-Control', 'no-store');
    }

    public function testWebhook(Request $r, Form $form)
    {
        $this->authorizeForm($form);
        $index = $r->validate(['index' => 'required|integer|min:0|max:20'])['index'];
        $hook = $form->notifications['webhooks'][$index] ?? null;
        abort_unless($hook, 422, 'Save the webhook settings before testing.');
        $result = app(FormWebhookDispatcher::class)->send($form, $hook, ['test' => true, 'form' => ['id' => $form->id, 'title' => $form->title, 'slug' => $form->slug], 'submission' => ['id' => 0, 'data' => ['name' => 'Sample member', 'email' => 'sample@example.com'], 'files' => [], 'created_at' => now()->toIso8601String()]], $index, true);

        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function event(Request $r, string $slug)
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $v = $r->validate(['token' => 'required|string|max:200', 'page' => 'nullable|integer|min:1|max:200']);
        $visit = app(FormVisitTracker::class)->resolve($form, $v['token']);
        abort_unless($visit, 422);
        if (! $visit->completed_at) {
            DB::table('form_visits')->where('id', $visit->id)->update(['started_at' => $visit->started_at ?? now(), 'last_page' => max($visit->last_page, $v['page'] ?? 1), 'updated_at' => now()]);
        }

        return response()->json(['ok' => true]);
    }
}
