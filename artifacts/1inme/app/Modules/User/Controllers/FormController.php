<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->user()->forms()->with('project')->withCount('submissions');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('slug', 'ilike', "%{$search}%");
            });
        }
        if ($status = $request->get('status')) {
            $query->where('is_active', $status === 'active');
        }
        if ($projectId = $request->get('project_id')) {
            $query->where('project_id', $projectId);
        }

        $forms = $query->latest()->paginate(20)->withQueryString();
        $projects = $request->user()->projects()->orderBy('name')->get();

        return view('user.forms.index', compact('forms', 'projects'));
    }

    public function create(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('name')->get();
        return view('user.forms.create', compact('projects'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:1000',
            'project_id' => ['nullable', \Illuminate\Validation\Rule::exists('projects', 'id')->where('user_id', $request->user()->id)],
            'template' => 'nullable|in:contact,lead,survey,registration,feedback,blank',
        ]);

        $template = $data['template'] ?? 'contact';
        $form = $request->user()->forms()->create([
            'project_id' => $data['project_id'] ?? null,
            'slug' => Form::uniqueSlug($data['title']),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'fields' => $this->templateFields($template),
            'design' => Form::defaultDesign(),
            'settings' => Form::defaultSettings(),
            'notifications' => Form::defaultNotifications(),
            'is_active' => true,
        ]);

        return redirect()->route('user.forms.builder', $form)->with('success', 'Form created. Add or rearrange fields below.');
    }

    public function show(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $form->loadCount('submissions');
        $recent = $form->submissions()->latest()->limit(5)->get();
        $stats = [
            'today' => $form->submissions()->whereDate('created_at', today())->count(),
            'week'  => $form->submissions()->where('created_at', '>=', now()->subDays(7))->count(),
            'month' => $form->submissions()->where('created_at', '>=', now()->subDays(30))->count(),
            'unread' => $form->submissions()->where('is_read', false)->count(),
            'conversion' => $form->total_views > 0 ? round(($form->total_submissions / $form->total_views) * 100, 1) : 0,
        ];
        return view('user.forms.show', compact('form', 'recent', 'stats'));
    }

    public function builder(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $fieldTypes = Form::fieldTypes();
        return view('user.forms.builder', compact('form', 'fieldTypes'));
    }

    public function updateBuilder(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:1000',
            'fields' => 'required|array',
            'fields.*.id' => 'required|string|max:64',
            'fields.*.type' => 'required|string|max:32',
            'fields.*.label' => 'nullable|string|max:200',
            'is_multi_step' => 'sometimes|boolean',
        ]);

        $form->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'fields' => array_values($data['fields']),
            'is_multi_step' => collect($data['fields'])->contains(fn ($f) => ($f['type'] ?? null) === 'page_break'),
        ]);

        if ($request->wantsJson()) return response()->json(['ok' => true]);
        return back()->with('success', 'Form fields saved.');
    }

    public function design(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $design = array_merge(Form::defaultDesign(), $form->design ?? []);
        return view('user.forms.design', compact('form', 'design'));
    }

    public function updateDesign(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate([
            'theme' => 'required|in:light,dark,glass',
            'accent' => 'required|string|max:32',
            'background' => 'required|string|max:32',
            'text' => 'required|string|max:32',
            'border_radius' => 'required|integer|min:0|max:48',
            'button_label' => 'required|string|max:60',
            'button_style' => 'required|in:gradient,solid,outline',
            'layout' => 'required|in:stacked,inline',
            'font' => 'required|string|max:60',
            'show_branding' => 'sometimes|boolean',
            'cover_image' => 'nullable|image|max:4096',
            'remove_cover' => 'sometimes|boolean',
            'logo' => 'nullable|image|max:2048',
            'remove_logo' => 'sometimes|boolean',
            'custom_css' => 'nullable|string|max:50000',
        ]);

        $design = array_merge(Form::defaultDesign(), $form->design ?? [], $data);
        $design['show_branding'] = $request->boolean('show_branding');

        foreach (['cover_image' => 'cover', 'logo' => 'logo'] as $field => $key) {
            if ($request->boolean('remove_' . ($field === 'cover_image' ? 'cover' : 'logo'))) {
                if (!empty($design[$key])) {
                    $rel = ltrim(parse_url($design[$key], PHP_URL_PATH) ?? '', '/');
                    $rel = preg_replace('#^storage/#', '', $rel);
                    Storage::disk('public')->delete($rel);
                }
                $design[$key] = null;
            }
            if ($request->hasFile($field)) {
                $path = $request->file($field)->store("forms/{$form->id}", 'public');
                $design[$key] = Storage::url($path);
            }
        }

        $form->update(['design' => $design]);
        return back()->with('success', 'Design updated.');
    }

    public function notifications(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $notifications = array_replace_recursive(Form::defaultNotifications(), $form->notifications ?? []);
        return view('user.forms.notifications', compact('form', 'notifications'));
    }

    public function updateNotifications(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $n = array_replace_recursive(Form::defaultNotifications(), $form->notifications ?? []);

        $n['email'] = [
            'enabled' => $request->boolean('email_enabled'),
            'to' => trim((string) $request->input('email_to', '')),
            'subject' => $request->input('email_subject') ?: 'New form submission',
            'reply_to_field' => $request->input('email_reply_to_field') ?: 'email',
        ];

        $n['autoresponder'] = [
            'enabled' => $request->boolean('auto_enabled'),
            'subject' => $request->input('auto_subject') ?: 'Thanks for your submission',
            'body' => $request->input('auto_body') ?: 'We received your submission and will get back to you soon.',
            'email_field' => $request->input('auto_email_field') ?: 'email',
        ];

        $n['sms'] = [
            'enabled' => $request->boolean('sms_enabled'),
            'provider' => $request->input('sms_provider', 'twilio'),
            'to' => trim((string) $request->input('sms_to', '')),
            'message' => $request->input('sms_message') ?: 'New form submission on {form_title}',
        ];

        $hooks = [];
        foreach ((array) $request->input('webhook_url', []) as $i => $url) {
            $url = trim((string) $url);
            if ($url === '') continue;
            $hooks[] = [
                'url' => $url,
                'method' => in_array($request->input("webhook_method.$i"), ['GET', 'POST', 'PUT']) ? $request->input("webhook_method.$i") : 'POST',
                'enabled' => filter_var($request->input("webhook_enabled.$i"), FILTER_VALIDATE_BOOL),
                'header_key' => $request->input("webhook_header_key.$i"),
                'header_value' => $request->input("webhook_header_value.$i"),
            ];
        }
        $n['webhooks'] = $hooks;

        $form->update(['notifications' => $n]);
        return back()->with('success', 'Notifications updated.');
    }

    public function embed(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        return view('user.forms.embed', compact('form'));
    }

    public function submissions(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $query = $form->submissions();
        if ($request->get('filter') === 'unread') $query->where('is_read', false);
        if ($request->get('filter') === 'starred') $query->where('is_starred', true);
        if ($request->get('filter') === 'spam') $query->where('is_spam', true);
        else $query->where('is_spam', false);
        $submissions = $query->latest()->paginate(25)->withQueryString();
        return view('user.forms.submissions', compact('form', 'submissions'));
    }

    public function showSubmission(Request $request, Form $form, FormSubmission $submission)
    {
        $this->authorizeForm($request, $form);
        abort_unless($submission->form_id === $form->id, 404);
        if (!$submission->is_read) $submission->update(['is_read' => true]);
        return view('user.forms.submission-show', compact('form', 'submission'));
    }

    public function toggleSubmissionStar(Request $request, Form $form, FormSubmission $submission)
    {
        $this->authorizeForm($request, $form);
        abort_unless($submission->form_id === $form->id, 404);
        $submission->update(['is_starred' => !$submission->is_starred]);
        return back();
    }

    public function destroySubmission(Request $request, Form $form, FormSubmission $submission)
    {
        $this->authorizeForm($request, $form);
        abort_unless($submission->form_id === $form->id, 404);
        $submission->delete();
        return back()->with('success', 'Submission deleted.');
    }

    public function exportSubmissions(Request $request, Form $form): StreamedResponse
    {
        $this->authorizeForm($request, $form);
        $headers = collect($form->fields ?? [])->whereNotIn('type', ['heading', 'paragraph', 'divider', 'page_break'])->pluck('id')->all();
        $filename = 'form-' . $form->slug . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($form, $headers) {
            $h = fopen('php://output', 'w');
            fputcsv($h, array_merge(['submitted_at', 'ip'], $headers));
            $form->submissions()->orderBy('id')->chunk(500, function ($rows) use ($h, $headers) {
                foreach ($rows as $row) {
                    $line = [$row->created_at?->toIso8601String(), $row->ip];
                    foreach ($headers as $key) {
                        $val = $row->data[$key] ?? '';
                        if (is_array($val)) $val = implode(', ', $val);
                        $val = (string) $val;
                        // Mitigate CSV formula injection — prefix risky leading chars with apostrophe
                        if ($val !== '' && in_array($val[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                            $val = "'" . $val;
                        }
                        $line[] = $val;
                    }
                    fputcsv($h, $line);
                }
            });
            fclose($h);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function destroy(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        Storage::disk('public')->deleteDirectory("forms/{$form->id}");
        $form->delete();
        return redirect()->route('user.forms.index')->with('success', 'Form deleted.');
    }

    public function toggleActive(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $form->update(['is_active' => !$form->is_active]);
        return back()->with('success', $form->is_active ? 'Form enabled.' : 'Form disabled.');
    }

    /* ----------------------------- helpers ----------------------------- */

    protected function authorizeForm(Request $request, Form $form): void
    {
        abort_unless($form->user_id === $request->user()->id, 403);
    }

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

    /* ---------------------- public-side handlers ---------------------- */

    public function publicShow(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $form->increment('total_views');
        return view('common.form', ['form' => $form, 'embed' => false]);
    }

    public function publicIframe(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $form->increment('total_views');
        return view('common.form', ['form' => $form, 'embed' => true]);
    }

    public function publicEmbedJs(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $iframeUrl = url('/f/' . $form->slug . '/iframe');
        $js = <<<JS
            (function(){
              var nodes = document.querySelectorAll('div[data-1inme-form="$form->slug"]');
              nodes.forEach(function(n){
                var f = document.createElement('iframe');
                f.src = "$iframeUrl";
                f.style.width = '100%';
                f.style.border = '0';
                f.style.minHeight = '600px';
                f.loading = 'lazy';
                f.setAttribute('allow', 'clipboard-write');
                window.addEventListener('message', function(e){
                  if (e.source === f.contentWindow && e.data && e.data.type === '1inme-form-resize') {
                    f.style.height = e.data.height + 'px';
                  }
                });
                n.appendChild(f);
              });
            })();
        JS;
        return response($js, 200, ['Content-Type' => 'application/javascript', 'Cache-Control' => 'public, max-age=300']);
    }

    public function publicSubmit(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->firstOrFail();

        $rules = $this->buildSubmissionRules($form);
        $validated = $request->validate($rules);

        // Honey-pot
        if (trim((string) $request->input('_hp', '')) !== '') {
            return $this->successResponse($request, $form, true);
        }

        $files = [];
        $data  = [];
        foreach ($form->fields ?? [] as $field) {
            $type = $field['type'] ?? 'text';
            $id = $field['id'] ?? null;
            if (!$id || in_array($type, ['heading', 'paragraph', 'divider', 'page_break'])) continue;

            if ($type === 'file' && $request->hasFile($id)) {
                $path = $request->file($id)->store("forms/{$form->id}/submissions", 'public');
                $files[$id] = Storage::url($path);
                $data[$id] = $request->file($id)->getClientOriginalName();
            } elseif ($type === 'consent') {
                $data[$id] = $request->boolean($id);
            } elseif ($type === 'checkbox') {
                $data[$id] = (array) $request->input($id, []);
            } else {
                $data[$id] = $request->input($id);
            }
        }

        $submission = $form->submissions()->create([
            'data' => $data,
            'files' => $files ?: null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'referrer' => substr((string) $request->headers->get('referer', ''), 0, 500),
        ]);

        $form->increment('total_submissions');
        $this->fireNotifications($form, $submission);

        return $this->successResponse($request, $form, false, $submission);
    }

    protected function buildSubmissionRules(Form $form): array
    {
        $rules = ['_hp' => 'nullable|string|max:200'];
        foreach ($form->fields ?? [] as $field) {
            $type = $field['type'] ?? 'text';
            $id = $field['id'] ?? null;
            if (!$id || in_array($type, ['heading', 'paragraph', 'divider', 'page_break'])) continue;

            $req = !empty($field['required']);
            $base = $req ? 'required' : 'nullable';
            $rules[$id] = match ($type) {
                'email' => "$base|email|max:255",
                'url' => "$base|url|max:500",
                'phone' => "$base|string|max:40",
                'number' => "$base|numeric",
                'date' => "$base|date",
                'time' => "$base|date_format:H:i",
                'rating' => "$base|integer|min:0|max:" . ($field['max'] ?? 5),
                'scale' => "$base|integer|min:" . ($field['min'] ?? 0) . '|max:' . ($field['max'] ?? 10),
                'select', 'radio' => "$base|string|max:255",
                'checkbox' => $req ? 'required|array|min:1' : 'nullable|array',
                'textarea' => "$base|string|max:10000",
                'consent' => $req ? 'accepted' : 'nullable',
                'file' => ($req ? 'required' : 'nullable') . '|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,mp3,mp4,mov',
                'hidden' => 'nullable|string|max:1000',
                default => "$base|string|max:1000",
            };
        }
        return $rules;
    }

    protected function successResponse(Request $request, Form $form, bool $silent, ?FormSubmission $submission = null)
    {
        $settings = array_merge(Form::defaultSettings(), $form->settings ?? []);
        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $settings['success_message'],
                'redirect' => $settings['success_action'] === 'redirect' ? $settings['success_redirect'] : null,
            ]);
        }
        if ($settings['success_action'] === 'redirect' && !empty($settings['success_redirect'])) {
            return redirect()->away($settings['success_redirect']);
        }
        return back()->with('form_success', $settings['success_message']);
    }

    protected function fireNotifications(Form $form, FormSubmission $submission): void
    {
        $n = array_replace_recursive(Form::defaultNotifications(), $form->notifications ?? []);

        // Email to owner
        try {
            if (!empty($n['email']['enabled']) && !empty($n['email']['to'])) {
                $body = "New submission on {$form->title}\n\n";
                foreach ($submission->data as $k => $v) {
                    $body .= ucfirst(str_replace('_', ' ', $k)) . ': ' . (is_array($v) ? implode(', ', $v) : (string) $v) . "\n";
                }
                $replyToRaw = $submission->data[$n['email']['reply_to_field']] ?? null;
                // Strip newlines to prevent header injection, then validate
                $replyTo = $replyToRaw ? preg_replace('/[\r\n\t]+/', '', (string) $replyToRaw) : null;
                $subject = preg_replace('/[\r\n]+/', ' ', str_replace('{form_title}', $form->title, $n['email']['subject']));
                Mail::raw($body, function ($m) use ($n, $subject, $replyTo) {
                    $m->to(array_filter(array_map('trim', explode(',', $n['email']['to']))))->subject($subject);
                    if ($replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                        $m->replyTo($replyTo);
                    }
                });
            }
        } catch (\Throwable $e) { logger()->warning('Form email failed: ' . $e->getMessage()); }

        // Auto-responder
        try {
            if (!empty($n['autoresponder']['enabled'])) {
                $to = $submission->data[$n['autoresponder']['email_field']] ?? null;
                if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    Mail::raw($n['autoresponder']['body'], function ($m) use ($n, $to) {
                        $m->to($to)->subject($n['autoresponder']['subject']);
                    });
                }
            }
        } catch (\Throwable $e) { logger()->warning('Form autoresponder failed: ' . $e->getMessage()); }

        // Webhooks
        foreach ($n['webhooks'] ?? [] as $hook) {
            if (empty($hook['enabled']) || empty($hook['url'])) continue;
            if (!$this->isSafeWebhookUrl($hook['url'])) {
                logger()->warning('Form webhook blocked (unsafe URL): ' . $hook['url']);
                continue;
            }
            try {
                $headers = [];
                if (!empty($hook['header_key']) && !empty($hook['header_value'])) {
                    // Strip control characters from headers to prevent header-injection
                    $hk = preg_replace('/[\r\n\t:\s]+/', '-', trim($hook['header_key']));
                    $hv = preg_replace('/[\r\n]+/', ' ', trim($hook['header_value']));
                    if ($hk !== '') $headers[$hk] = $hv;
                }
                $payload = [
                    'form' => ['id' => $form->id, 'slug' => $form->slug, 'title' => $form->title],
                    'submission' => [
                        'id' => $submission->id,
                        'data' => $submission->data,
                        'files' => $submission->files,
                        'created_at' => $submission->created_at?->toIso8601String(),
                        'ip' => $submission->ip,
                    ],
                ];
                Http::withHeaders($headers)->timeout(5)->{strtolower($hook['method'] ?? 'post')}($hook['url'], $payload);
            } catch (\Throwable $e) { logger()->warning('Form webhook failed: ' . $e->getMessage()); }
        }

        // SMS — provider stubs (only logs unless creds are configured)
        try {
            if (!empty($n['sms']['enabled']) && !empty($n['sms']['to'])) {
                $msg = str_replace(['{form_title}'], [$form->title], $n['sms']['message']);
                logger()->info('Form SMS (' . $n['sms']['provider'] . ') to ' . $n['sms']['to'] . ': ' . $msg);
            }
        } catch (\Throwable $e) { logger()->warning('Form SMS failed: ' . $e->getMessage()); }
    }

    /**
     * Reject webhook URLs that target private / loopback / link-local IPs (SSRF guard).
     */
    protected function isSafeWebhookUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)) return false;
        $host = strtolower($parts['host']);
        if (in_array($host, ['localhost', '0.0.0.0', 'broadcasthost', 'metadata.google.internal'], true)) return false;
        // Resolve to IP(s) and reject any private / reserved range
        $ips = @gethostbynamel($host) ?: [];
        if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return !empty($ips);
    }
}
