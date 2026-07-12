<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Form;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\FormSubmission;
use App\Modules\User\Models\Project;
use App\Modules\User\Models\Domain;
use App\Modules\User\Models\User;
use App\Modules\User\Services\SpamChecker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FormController extends Controller
{
    public function index(Request $request)
    {
        $query = workspace_owner()->forms()->with('project')->withCount('submissions');

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
        $projects = workspace_owner()->projects()->orderBy('name')->get();

        return view('user.forms.index', compact('forms', 'projects'));
    }

    /**
     * Build a Validation rule that constrains domain_id to a domain the
     * user can actually attach: their own verified+active domains plus
     * admin-global active domains tagged for their plan (or untagged
     * globals open to every plan). Mirrors LinkController::availableDomainRule.
     */
    protected function availableDomainRule(User $user): \Closure
    {
        return function ($attribute, $value, $fail) use ($user) {
            if (empty($value)) return;
            $allowed = Domain::availableTo($user)->pluck('id')->all();
            if (!in_array((int) $value, $allowed, true)) {
                $fail('That domain is not available on your plan.');
            }
        };
    }

    public function create(Request $request)
    {
        $projects = workspace_owner()->projects()->orderBy('name')->get();
        $domains  = Domain::availableTo($request->user())->get();

        $groups = \App\Modules\User\Services\FormTemplateCatalog::grouped();
        $templatesFlat = [];
        foreach ($groups as $catKey => $templates) {
            foreach ($templates as $key => $tpl) {
                $templatesFlat[] = [
                    'key'      => $key,
                    'label'    => $tpl['label'],
                    'desc'     => $tpl['desc'],
                    'icon'     => $tpl['icon'],
                    'category' => $catKey,
                ];
            }
        }

        // A deep link may arrive with ?template=<key> — e.g. a bookmark or a
        // curated link straight into a specific starting point. If that key
        // still resolves, pre-select it; if it was retired from the catalog,
        // fall back to Contact and surface a small notice so the removal is
        // visible instead of silently ignored.
        $requestedTemplate = $request->query('template');
        $requestedTemplate = is_string($requestedTemplate) ? trim($requestedTemplate) : '';
        $templateResolves  = $requestedTemplate !== ''
            && \App\Modules\User\Services\FormTemplateCatalog::isValid($requestedTemplate);
        $initialTemplate   = $templateResolves ? $requestedTemplate : 'contact';
        $templateUnavailable = $requestedTemplate !== '' && !$templateResolves;

        return view('user.forms.create', [
            'projects'            => $projects,
            'domains'             => $domains,
            'defaultDomainId'     => $domains->firstWhere('is_primary', true)?->id,
            'templateGroups'      => $groups,
            'templateCategories'  => \App\Modules\User\Services\FormTemplateCatalog::categories(),
            'templatesFlat'       => $templatesFlat,
            'initialTemplate'     => $initialTemplate,
            'requestedTemplate'   => $requestedTemplate,
            'templateUnavailable' => $templateUnavailable,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:1000',
            'project_id' => ['nullable', \Illuminate\Validation\Rule::exists('projects', 'id')->where('user_id', workspace_owner_id())],
            'domain_id' => ['nullable', $this->availableDomainRule($request->user())],
            'template' => ['nullable', \Illuminate\Validation\Rule::in(\App\Modules\User\Services\FormTemplateCatalog::keys())],
        ]);

        $template = $data['template'] ?? 'contact';
        $form = workspace_owner()->forms()->create([
            'project_id' => $data['project_id'] ?? null,
            'domain_id' => $data['domain_id'] ?? null,
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
        $recent = $form->submissions()->completed()->latest()->limit(5)->get();
        $stats = [
            'today' => $form->submissions()->completed()->whereDate('created_at', today())->count(),
            'week'  => $form->submissions()->completed()->where('created_at', '>=', now()->subDays(7))->count(),
            'month' => $form->submissions()->completed()->where('created_at', '>=', now()->subDays(30))->count(),
            'unread' => $form->submissions()->completed()->where('is_read', false)->count(),
            'conversion' => $form->total_views > 0 ? round(($form->total_submissions / $form->total_views) * 100, 1) : 0,
        ];

        // Advanced form analytics (Task #2319) — Pro-and-above only. Built
        // from data already captured per submission; degrades gracefully
        // where signals are missing (e.g. geo without a resolver).
        $advancedAnalytics = (bool) (workspace_owner()?->getPlanFeature('form_analytics_advanced', false));
        $analytics = $advancedAnalytics ? $this->buildAdvancedAnalytics($form) : null;

        return view('user.forms.show', compact('form', 'recent', 'stats', 'advancedAnalytics', 'analytics'));
    }

    /**
     * Deeper per-form analytics for the Overview tab: a 30-day submission
     * trend, per-field completion / drop-off, device + geo breakdowns and
     * paid-form revenue. All derived from already-stored submission rows.
     */
    /** Public entry point so the mobile API can reuse the same analytics. */
    public function buildAdvancedAnalyticsFor(Form $form): array
    {
        return $this->buildAdvancedAnalytics($form);
    }

    protected function buildAdvancedAnalytics(Form $form): array
    {
        $submissions = $form->submissions()
            ->where('is_spam', false)
            // Paid forms: an unpaid / abandoned checkout is not a completed
            // submission and must not inflate the trend, field, device or geo
            // metrics. Free-form rows are always kept (see scopeCompleted).
            ->completed()
            ->orderBy('created_at')
            ->get(['id', 'data', 'user_agent', 'country', 'payment_status', 'amount_cents', 'currency', 'created_at']);

        // 30-day submission trend (daily buckets).
        $trend = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $trend[$day] = 0;
        }
        foreach ($submissions as $s) {
            $day = optional($s->created_at)->format('Y-m-d');
            if ($day !== null && array_key_exists($day, $trend)) {
                $trend[$day]++;
            }
        }
        $trendSeries = [];
        foreach ($trend as $day => $count) {
            $trendSeries[] = ['label' => \Illuminate\Support\Carbon::parse($day)->format('M j'), 'count' => $count];
        }

        // Per-field completion / drop-off across all submissions.
        $total = $submissions->count();
        $fieldStats = [];
        foreach ($form->fields ?? [] as $field) {
            $type = $field['type'] ?? 'text';
            $id   = $field['id'] ?? null;
            if (!$id || in_array($type, ['heading', 'paragraph', 'divider', 'page_break', 'section'], true)) {
                continue;
            }
            $filled = 0;
            foreach ($submissions as $s) {
                $v = $s->data[$id] ?? null;
                $has = is_array($v) ? count($v) > 0 : ($v !== null && $v !== '' && $v !== false);
                if ($has) $filled++;
            }
            $fieldStats[] = [
                'label' => $field['label'] ?? $id,
                'filled' => $filled,
                'rate' => $total > 0 ? round($filled / $total * 100) : 0,
            ];
        }

        // Device breakdown from the user-agent string.
        $devices = ['Desktop' => 0, 'Mobile' => 0, 'Tablet' => 0, 'Unknown' => 0];
        foreach ($submissions as $s) {
            $ua = strtolower((string) $s->user_agent);
            if ($ua === '') { $devices['Unknown']++; continue; }
            if (str_contains($ua, 'ipad') || (str_contains($ua, 'tablet') && !str_contains($ua, 'mobile'))) {
                $devices['Tablet']++;
            } elseif (str_contains($ua, 'mobi') || str_contains($ua, 'android') || str_contains($ua, 'iphone')) {
                $devices['Mobile']++;
            } else {
                $devices['Desktop']++;
            }
        }
        $devices = array_filter($devices, fn ($n) => $n > 0);

        // Geo breakdown from the (best-effort) country column.
        $geo = [];
        foreach ($submissions as $s) {
            $c = $s->country ? strtoupper($s->country) : null;
            if (!$c) continue;
            $geo[$c] = ($geo[$c] ?? 0) + 1;
        }
        arsort($geo);
        $geo = array_slice($geo, 0, 8, true);

        // Paid-form revenue.
        $paid = $submissions->where('payment_status', 'paid');
        $revenue = [
            'paid_count'   => $paid->count(),
            'gross_cents'  => (int) $paid->sum('amount_cents'),
            'currency'     => strtoupper((string) ($paid->first()->currency ?? $form->paymentCurrency())),
            'pending'      => $form->submissions()->where('payment_status', 'pending')->count(),
        ];

        $views = (int) ($form->total_views ?? 0);

        return [
            'trend'      => $trendSeries,
            'fields'     => $fieldStats,
            'devices'    => $devices,
            'geo'        => $geo,
            'revenue'    => $revenue,
            'total'      => $total,
            'views'      => $views,
            'conversion' => $views > 0 ? round($total / $views * 100, 1) : 0.0,
            'is_paid'    => $form->isPaid(),
        ];
    }

    public function builder(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $fieldTypes = Form::fieldTypes();
        // Pricing editor context for BOTH per-field pricing (Task #2321) and the
        // Pricing / Package field (Task #2333). canPrice/payment/priceCurrency
        // drive the per-field price inputs; paymentCurrency/canPaidForms/
        // hasGateway drive the pricing-field panel + its "captured but won't
        // charge" note (plan allows paid forms AND a gateway is connected).
        $canPrice        = (bool) (workspace_owner()?->getPlanFeature('paid_forms', false));
        $payment         = $form->paymentConfig();
        $priceCurrency   = $form->paymentCurrency();
        $paymentCurrency = $priceCurrency;
        $canPaidForms    = $canPrice;
        $hasGateway      = (bool) $form->user?->defaultPaymentConnection();
        return view('user.forms.builder', compact('form', 'fieldTypes', 'canPrice', 'payment', 'priceCurrency', 'paymentCurrency', 'canPaidForms', 'hasGateway'));
    }

    public function updateBuilder(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string|max:1000',
            'fields' => 'required|array',
            'fields.*.id' => 'required|string|max:64|regex:/^[a-zA-Z0-9_-]+$/',
            'fields.*.type' => 'required|string|max:32',
            'fields.*.label' => 'nullable|string|max:300',
        ]);

        $allowedTypes = array_keys(Form::fieldTypes());
        $allowedWidths = [4, 6, 8, 12]; // 1/3, 1/2, 2/3, full of a 12-col grid

        $clean = [];
        foreach ((array) $request->input('fields', []) as $f) {
            if (!is_array($f)) continue;
            $type = (string) ($f['type'] ?? 'text');
            if (!in_array($type, $allowedTypes, true)) continue;

            $row = [
                'id'    => (string) ($f['id'] ?? ''),
                'type'  => $type,
                'label' => (string) ($f['label'] ?? ''),
                'required' => filter_var($f['required'] ?? false, FILTER_VALIDATE_BOOL),
            ];
            // Optional common props
            foreach (['placeholder', 'help', 'error_message', 'pattern', 'pattern_message', 'file_types', 'value', 'parent'] as $k) {
                if (isset($f[$k]) && $f[$k] !== '') $row[$k] = (string) $f[$k];
            }
            // Numeric props
            foreach (['rows', 'min', 'max', 'min_length', 'max_length', 'file_max_kb'] as $k) {
                if (isset($f[$k]) && $f[$k] !== '' && is_numeric($f[$k])) $row[$k] = (int) $f[$k];
            }
            // Float numeric props for new typed fields
            foreach (['step', 'default_val'] as $k) {
                if (isset($f[$k]) && $f[$k] !== '' && is_numeric($f[$k])) $row[$k] = (float) $f[$k];
            }
            // String props for new typed fields
            foreach (['auto_collect', 'auto_collect_param', 'unit', 'currency_code', 'first_label', 'last_label', 'min_date', 'max_date'] as $k) {
                if (isset($f[$k]) && (string) $f[$k] !== '') $row[$k] = (string) $f[$k];
            }
            // Image choice: [{label, url}] pairs
            if ($type === 'image_choice') {
                $ios = [];
                foreach ((array) ($f['image_options'] ?? []) as $io) {
                    $lbl = trim((string) ($io['label'] ?? ''));
                    $url = trim((string) ($io['url'] ?? ''));
                    if ($lbl !== '') $ios[] = ['label' => $lbl, 'url' => $url];
                }
                $row['image_options'] = $ios;
            }
            // Width (4=1/3, 6=1/2, 8=2/3, 12=full)
            $w = (int) ($f['width'] ?? 12);
            $row['width'] = in_array($w, $allowedWidths, true) ? $w : 12;
            // Options
            if (isset($f['options']) && is_array($f['options'])) {
                $row['options'] = array_values(array_filter(array_map(
                    fn ($o) => trim((string) $o),
                    $f['options']
                ), fn ($o) => $o !== ''));
            }
            // Per-field pricing (Task #2321). Persisted for priceable types
            // regardless of plan — the runtime charge is plan-gated via
            // Form::isPaid(), so a downgrade reverts to free without losing
            // the owner's configured prices. Values arrive in cents.
            if (in_array($type, Form::PRICED_FIELD_TYPES, true)) {
                if (isset($f['price_cents']) && is_numeric($f['price_cents']) && (int) $f['price_cents'] > 0) {
                    $row['price_cents'] = max(0, (int) $f['price_cents']);
                }
                if (isset($f['option_prices']) && is_array($f['option_prices'])) {
                    $op = [];
                    foreach ($f['option_prices'] as $optLabel => $cents) {
                        $optLabel = trim((string) $optLabel);
                        if ($optLabel !== '' && is_numeric($cents) && (int) $cents > 0) {
                            $op[$optLabel] = max(0, (int) $cents);
                        }
                    }
                    if ($op) $row['option_prices'] = $op;
                }
            }
            // Pricing / Package (Task #2333): option list (radio) + addon list
            // (checkboxes). Each entry is {label, price} with the price stored as
            // a dollar amount; empty-label rows are dropped and prices clamped.
            if ($type === 'pricing') {
                $row['price_options'] = $this->cleanPriceList($f['price_options'] ?? []);
                $row['addons']        = $this->cleanPriceList($f['addons'] ?? []);
            }
            // Repeatable section config — persisted only for section fields.
            if ($type === 'section') {
                $row['repeatable'] = filter_var($f['repeatable'] ?? false, FILTER_VALIDATE_BOOL);
                if ($row['repeatable']) {
                    $addLabel = mb_substr(trim((string) ($f['repeat_add_label'] ?? 'Add another')), 0, 60);
                    $row['repeat_add_label'] = $addLabel ?: 'Add another';
                    // Cap to 10 — the UI cannot render more than 10 copies.
                    $rm = isset($f['repeat_min']) && is_numeric($f['repeat_min']) ? min(10, max(1, (int) $f['repeat_min'])) : 1;
                    $rM = isset($f['repeat_max']) && is_numeric($f['repeat_max']) && (int) $f['repeat_max'] > 0
                        ? min(10, (int) $f['repeat_max']) : null;
                    if ($rM !== null && $rM < $rm) $rM = $rm;
                    $row['repeat_min'] = $rm;
                    if ($rM !== null) $row['repeat_max'] = $rM;
                }
            }
            $clean[] = $row;
        }

        // ---- Sanitise parent pointers ----
        // A field's `parent` may only reference an existing section field's id.
        // Sections themselves cannot be nested (parent on a section is dropped).
        // Orphan parents (pointing at a non-existent or non-section field) are
        // silently nulled so the field renders at top level.
        $sectionIds = [];
        foreach ($clean as $f) {
            if (($f['type'] ?? null) === 'section') $sectionIds[(string) $f['id']] = true;
        }
        foreach ($clean as &$f) {
            if (($f['type'] ?? null) === 'section') {
                unset($f['parent']);                  // no nested sections
                $f['required'] = false;               // structural — never validatable
                continue;
            }
            if (isset($f['parent']) && !isset($sectionIds[(string) $f['parent']])) {
                unset($f['parent']);                  // orphan → drop
            }
        }
        unset($f);

        $form->update([
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'fields' => $clean,
            'is_multi_step' => collect($clean)->contains(fn ($f) => ($f['type'] ?? null) === 'page_break'),
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
        // Allow #hex (3/4/6/8 chars), rgb()/rgba(), hsl()/hsla() — reject
        // arbitrary CSS that could escape the inline-style context.
        $colorRule = ['regex:/^(#(?:[0-9a-fA-F]{3,4}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})|rgba?\(\s*[\d.\s,%\/]+\)|hsla?\(\s*[\d.\s,%\/]+\))$/'];
        $data = $request->validate([
            'theme' => 'required|in:light,dark,glass',
            'accent' => array_merge(['required', 'string', 'max:32'], $colorRule),
            'background' => array_merge(['required', 'string', 'max:32'], $colorRule),
            'card_color' => array_merge(['nullable', 'string', 'max:32'], $colorRule),
            'card_image_mode' => 'nullable|in:cover,contain,tile',
            'card_image_opacity' => 'nullable|integer|min:0|max:100',
            'text' => array_merge(['required', 'string', 'max:32'], $colorRule),
            'border_radius' => 'required|integer|min:0|max:48',
            'button_label' => 'required|string|max:60',
            'button_style' => 'required|in:gradient,solid,outline',
            'layout' => 'required|in:stacked,inline,oneq',
            'font' => 'required|string|max:60',
            'show_branding' => 'sometimes|boolean',
            'cover_image' => \App\Services\UploadPolicy::rule('forms.cover', $request->user()),
            'remove_cover' => 'sometimes|boolean',
            'card_image' => \App\Services\UploadPolicy::rule('forms.card_image', $request->user()),
            'remove_card_image' => 'sometimes|boolean',
            'logo' => \App\Services\UploadPolicy::rule('forms.logo', $request->user()),
            'remove_logo' => 'sometimes|boolean',
            'custom_css' => 'nullable|string|max:50000',
        ]);

        $design = array_merge(Form::defaultDesign(), $form->design ?? [], $data);
        $design['show_branding'] = $request->boolean('show_branding');

        // File-backed design assets — uploaded file form-key => stored design key.
        // Photo-style assets (cover / card) are downscaled+re-encoded; the
        // brand logo is stored as-is to preserve crisp edges.
        $assetMap = [
            'cover_image' => ['key' => 'cover',      'compress' => ['max_width' => 1600, 'max_height' => 1600]],
            'logo'        => ['key' => 'logo',       'compress' => null],
            'card_image'  => ['key' => 'card_image', 'compress' => ['max_width' => 1200, 'max_height' => 1200]],
        ];
        foreach ($assetMap as $field => $cfg) {
            $key = $cfg['key'];
            $removeKey = match ($field) {
                'cover_image' => 'remove_cover',
                'card_image'  => 'remove_card_image',
                default       => 'remove_logo',
            };
            if ($request->boolean($removeKey)) {
                if (!empty($design[$key])) {
                    $rel = ltrim(parse_url($design[$key], PHP_URL_PATH) ?? '', '/');
                    $rel = preg_replace('#^storage/#', '', $rel);
                    Storage::disk('public')->delete($rel);
                }
                $design[$key] = null;
            }
            if ($request->hasFile($field)) {
                try {
                    $opts = [];
                    if (!empty($cfg['compress'])) {
                        $opts['compress_image'] = true;
                        $opts['max_width']  = (int) $cfg['compress']['max_width'];
                        $opts['max_height'] = (int) $cfg['compress']['max_height'];
                        $opts['quality']    = 85;
                    }
                    $userFile = UserFile::createFromUpload($request->file($field), $request->user(), $opts);
                    $design[$key] = $userFile->url;
                } catch (\RuntimeException $e) {
                    return back()->withInput()->with('error', $e->getMessage());
                }
            }
        }

        $form->update(['design' => $design]);
        return back()->with('success', 'Design updated.');
    }

    public function settings(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $captcha = $form->captchaConfig();
        return view('user.forms.settings', compact('form', 'captcha'));
    }

    public function updateSettings(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $request->validate([
            'captcha_provider' => 'required|in:honeypot,math,recaptcha_v2,recaptcha_v3,hcaptcha,turnstile',
            'site_key'         => 'nullable|string|max:255',
            'secret_key'       => 'nullable|string|max:255',
            'score_threshold'  => 'nullable|numeric|min:0.1|max:1.0',
        ]);

        $cap = $form->captchaConfig();
        $cap['provider']        = $request->input('captcha_provider');
        $cap['site_key']        = trim((string) $request->input('site_key', '')) ?: null;
        $cap['score_threshold'] = (float) $request->input('score_threshold', 0.5);

        $newSecret = trim((string) $request->input('secret_key', ''));
        if ($newSecret !== '') {
            $cap['secret_key'] = encrypt($newSecret);
        } elseif ($request->boolean('clear_secret')) {
            $cap['secret_key'] = null;
        }
        if (in_array($cap['provider'], ['honeypot', 'math'], true)) {
            $cap['secret_key'] = null;
            $cap['site_key']   = null;
        }

        $settings = array_merge(Form::defaultSettings(), $form->settings ?? []);
        $settings['captcha'] = $cap;
        $form->update(['settings' => $settings]);

        return back()->with('success', 'Settings saved.');
    }

    public function notifications(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $notifications = array_replace_recursive(Form::defaultNotifications(), $form->notifications ?? []);
        $hasWhatsappNumber = (bool) $form->user?->hasWhatsappNumber();
        return view('user.forms.notifications', compact('form', 'notifications', 'hasWhatsappNumber'));
    }

    public function updateNotifications(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $n = array_replace_recursive(Form::defaultNotifications(), $form->notifications ?? []);

        // Helper: only accept config_id values that belong to this user and match the expected kind.
        $resolveConfigId = function (?string $raw, string $kind) use ($request): ?int {
            if ($raw === null || $raw === '') return null;
            if (! ctype_digit($raw)) return null;
            $found = \App\Modules\User\Models\IntegrationConfig::where('id', (int) $raw)
                ->where('user_id', workspace_owner_id())
                ->kind($kind)
                ->value('id');
            return $found ?: null;
        };

        $n['email'] = [
            'enabled' => $request->boolean('email_enabled'),
            'to' => trim((string) $request->input('email_to', '')),
            'subject' => $request->input('email_subject') ?: 'New form submission',
            'reply_to_field' => $request->input('email_reply_to_field') ?: 'email',
            'config_id' => $resolveConfigId($request->input('email_config_id'), 'email'),
        ];

        $n['autoresponder'] = [
            'enabled' => $request->boolean('auto_enabled'),
            'subject' => $request->input('auto_subject') ?: 'Thanks for your submission',
            'body' => $request->input('auto_body') ?: 'We received your submission and will get back to you soon.',
            'email_field' => $request->input('auto_email_field') ?: 'email',
            'config_id' => $resolveConfigId($request->input('auto_config_id'), 'email'),
        ];

        $n['sms'] = [
            'enabled' => $request->boolean('sms_enabled'),
            'to' => trim((string) $request->input('sms_to', '')),
            'message' => $request->input('sms_message') ?: 'New form submission on {form_title}',
            'config_id' => $resolveConfigId($request->input('sms_config_id'), 'sms'),
        ];

        // WhatsApp — a one-way alert to the form owner's own verified WhatsApp
        // number. Can only be turned ON when the owner actually has a verified
        // number on file; otherwise it is forced off so the toggle can never be
        // "enabled but undeliverable".
        $n['whatsapp'] = [
            'enabled' => $request->boolean('whatsapp_enabled') && (bool) $form->user?->hasWhatsappNumber(),
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
        $domains = Domain::availableTo($request->user())->get();
        $form->load('domain');
        return view('user.forms.embed', [
            'form'            => $form,
            'domains'         => $domains,
            'defaultDomainId' => $domains->firstWhere('is_primary', true)?->id,
        ]);
    }

    /**
     * Persist the global/custom domain a form's public + embed links use.
     * Plan-gated via availableDomainRule: an empty value clears the domain
     * (links revert to the platform URL), and a domain_id the user can no
     * longer attach is rejected by validation. Separately, if a previously
     * stored domain later becomes unavailable, Form::baseUrl() falls back to
     * the platform URL at render time without mutating the stored value.
     */
    public function updateDomain(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $data = $request->validate([
            'domain_id' => ['nullable', $this->availableDomainRule($request->user())],
        ]);
        $form->update(['domain_id' => $data['domain_id'] ?? null]);
        return back()->with('success', 'Form domain updated.');
    }

    public function submissions(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $query = $form->submissions();
        if ($request->get('filter') === 'unread') $query->where('is_read', false);
        if ($request->get('filter') === 'starred') $query->where('is_starred', true);
        if ($request->get('filter') === 'spam') {
            // The Spam tab is the one place unpaid attempts may surface (a paid
            // form's spam is stored 'pending' and never charged).
            $query->where('is_spam', true);
        } else {
            $query->where('is_spam', false)->completed();
        }
        $submissions = $query->latest()->paginate(25)->withQueryString();
        return view('user.forms.submissions', compact('form', 'submissions'));
    }

    public function showSubmission(Request $request, Form $form, FormSubmission $submission)
    {
        $this->authorizeForm($request, $form);
        abort_unless($submission->form_id === $form->id, 404);
        if (!$submission->is_read) $submission->update(['is_read' => true]);

        $replyTo = null;
        $data = $submission->data ?? [];
        foreach (['email', 'Email', 'e_mail', 'email_address'] as $k) {
            if (!empty($data[$k]) && is_string($data[$k]) && filter_var($data[$k], FILTER_VALIDATE_EMAIL)) {
                $replyTo = $data[$k];
                break;
            }
        }
        if (!$replyTo) {
            foreach ($data as $v) {
                if (is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $replyTo = $v;
                    break;
                }
            }
        }

        $replies = \App\Modules\User\Models\InboxReply::where('user_id', workspace_owner_id())
            ->where('item_type', 'form_submission')
            ->where('item_id', $submission->id)
            ->orderByDesc('created_at')
            ->get();

        return view('user.forms.submission-show', compact('form', 'submission', 'replyTo', 'replies'));
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

    /**
     * Refund a paid form submission (Task #2322). Owner-only — reverses the
     * gateway charge, flips payment_status to `refunded`, and writes a
     * negative TYPE_FORM_REFUNDED ledger row via the monetization service.
     */
    public function refundSubmission(Request $request, Form $form, FormSubmission $submission)
    {
        $this->authorizeForm($request, $form);
        abort_unless($submission->form_id === $form->id, 404);
        abort_unless($submission->isRefundable(), 422, 'Only paid submissions can be refunded.');

        $ok = app(\App\Services\Monetization\MonetizationCheckout::class)
            ->refundFormSubmission($submission->id);

        return back()->with(
            $ok ? 'success' : 'error',
            $ok ? 'Submission payment refunded.' : 'Could not refund this submission.',
        );
    }

    public function exportSubmissions(Request $request, Form $form): StreamedResponse
    {
        $this->authorizeForm($request, $form);
        // Build export header ids: standard non-structural fields, plus repeatable sections.
        // New submissions store repeatable data under the section id as {_repeatable_group,copies}.
        // Old submissions (created before the section was made repeatable) still store values under
        // child field ids. We track child ids per repeatable section so we can fall back gracefully.
        $fields = $form->fields ?? [];
        $repeatableSectionIdsForExport = []; // sectionId => true
        $repeatableSectionChildIds = [];     // sectionId => [childId, ...]
        foreach ($fields as $ef) {
            if (($ef['type'] ?? null) === 'section' && !empty($ef['repeatable']) && !empty($ef['id'])) {
                $repeatableSectionIdsForExport[(string) $ef['id']] = true;
                $repeatableSectionChildIds[(string) $ef['id']] = [];
            }
        }
        foreach ($fields as $ef) {
            $parent = (string) ($ef['parent'] ?? '');
            if ($parent !== '' && isset($repeatableSectionChildIds[$parent]) && !empty($ef['id'])) {
                $repeatableSectionChildIds[$parent][] = (string) $ef['id'];
            }
        }
        $headers = collect($fields)->filter(function ($f) use ($repeatableSectionIdsForExport) {
            $t = $f['type'] ?? 'text';
            if (in_array($t, ['heading', 'paragraph', 'divider', 'page_break'])) return false;
            // Non-repeatable sections are structural wrappers only; skip them.
            if ($t === 'section') return !empty($f['repeatable']);
            // Children of repeatable sections: data lives under the parent section id; exclude them.
            $parent = (string) ($f['parent'] ?? '');
            if ($parent !== '' && isset($repeatableSectionIdsForExport[$parent])) return false;
            return true;
        })->pluck('id')->all();
        $filename = 'form-' . $form->slug . '-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($form, $headers, $repeatableSectionIdsForExport, $repeatableSectionChildIds) {
            $h = fopen('php://output', 'w');
            fputcsv($h, array_merge(['submitted_at', 'ip'], $headers));
            $form->submissions()->completed()->orderBy('id')->chunk(500, function ($rows) use ($h, $headers, $repeatableSectionIdsForExport, $repeatableSectionChildIds) {
                foreach ($rows as $row) {
                    $line = [$row->created_at?->toIso8601String(), $row->ip];
                    foreach ($headers as $key) {
                        $val = $row->data[$key] ?? '';
                        if (is_array($val) && !empty($val['_pricing'])) {
                            // Flatten a pricing-field breakdown into a single human-
                            // readable cell: "Option + Addon, Addon = TOTAL CUR".
                            $cur   = $val['currency'] ?? 'USD';
                            $parts = [];
                            if (!empty($val['option']['label'])) $parts[] = $val['option']['label'];
                            foreach (($val['addons'] ?? []) as $ad) {
                                if (!empty($ad['label'])) $parts[] = $ad['label'];
                            }
                            $total = number_format(((int) ($val['total_cents'] ?? 0)) / 100, 2);
                            $val   = implode(' + ', $parts) . ' = ' . $total . ' ' . $cur;
                        } elseif (isset($repeatableSectionIdsForExport[$key])) {
                            // Repeatable section column. Two possible shapes:
                            // (a) New submission: {_repeatable_group:true, copies:[{fieldId:val,...},...]}
                            // (b) Legacy submission (before repeatable was enabled): child values
                            //     stored under their own field ids — data[$key] is empty/missing.
                            //     Fall back to collecting child field values as a single copy.
                            if (is_array($val) && !empty($val['_repeatable_group'])) {
                                $rParts = [];
                                foreach (array_values($val['copies'] ?? []) as $rci => $rcopy) {
                                    $copyLabel = '[Copy ' . ($rci + 1) . ']';
                                    $fieldParts = [];
                                    foreach ((array) $rcopy as $rck => $rcv) {
                                        $flat = is_array($rcv) ? implode(', ', $rcv) : ($rcv === null ? '' : (string) $rcv);
                                        if ($flat === '') continue;
                                        $fieldParts[] = ucfirst(str_replace('_', ' ', (string) $rck)) . ': ' . $flat;
                                    }
                                    $rParts[] = $copyLabel . ' ' . implode(' | ', $fieldParts);
                                }
                                $val = implode(' || ', $rParts);
                            } else {
                                // Legacy shape: collect values from child field ids directly.
                                $childIds = $repeatableSectionChildIds[$key] ?? [];
                                $fieldParts = [];
                                foreach ($childIds as $cid) {
                                    $cv = $row->data[$cid] ?? '';
                                    $flat = is_array($cv) ? implode(', ', $cv) : ($cv === null ? '' : (string) $cv);
                                    if ($flat === '') continue;
                                    $fieldParts[] = $flat;
                                }
                                $val = implode(' | ', $fieldParts);
                            }
                        } elseif (is_array($val)) {
                            $val = implode(', ', $val);
                        }
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

    /**
     * Erase every form submission tied to a single submitter (by email or
     * any other identifier present in the submission's data) across ALL
     * forms owned by the current creator. Mirrors the poll-vote eraser
     * for GDPR-style takedown requests.
     *
     * Form submissions don't have a dedicated user_id/fingerprint column,
     * so we scan the JSONB `data` payload for any value that exactly
     * matches the supplied identifier (case-insensitive). This catches
     * the email field regardless of which key it lives under (email,
     * Email, contact_email, etc.) and also matches numeric ids or
     * fingerprints if a form happens to capture them.
     */
    public function eraseSubmitter(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);

        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $needle = trim($data['identifier']);

        // Reach across every workspace owned by this creator (mirrors the
        // poll-vote eraser, which also crosses workspace boundaries).
        $ownedFormIds = Form::query()->withoutGlobalScope('workspace')
            ->where('user_id', workspace_owner_id())
            ->pluck('id');

        $query = FormSubmission::query()
            ->withoutGlobalScope('workspace')
            ->whereIn('form_id', $ownedFormIds)
            ->whereRaw(
                "EXISTS (SELECT 1 FROM jsonb_each_text(data) WHERE LOWER(value) = ?)",
                [mb_strtolower($needle)]
            );

        $count = (clone $query)->count();

        if ($count === 0) {
            return back()->with('error', 'No submissions matched “' . e($needle) . '”.');
        }

        $query->delete();

        Log::info('form submitter erased', [
            'creator_id' => workspace_owner_id(),
            'identifier' => $needle,
            'removed'    => $count,
            'from_form'  => $form->id,
        ]);

        return back()->with('success', "Erased {$count} submission(s) tied to “{$needle}”.");
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
        abort_unless($form->user_id === workspace_owner_id(), 403);
    }

    protected function templateFields(string $template): array
    {
        if (\App\Modules\User\Services\FormTemplateCatalog::isValid($template)) {
            return \App\Modules\User\Services\FormTemplateCatalog::fieldsFor($template);
        }

        return Form::defaultFields();
    }

    /* ---------------------- public-side handlers ---------------------- */

    public function payment(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);
        $payment      = $form->paymentConfig();
        $canPaidForms = (bool) (workspace_owner()?->getPlanFeature('paid_forms', false));
        $connection   = $form->user?->defaultPaymentConnection();
        $hasGateway   = (bool) $connection;
        return view('user.forms.payment', compact('form', 'payment', 'canPaidForms', 'hasGateway', 'connection'));
    }

    public function updatePayment(Request $request, Form $form)
    {
        $this->authorizeForm($request, $form);

        if (! workspace_owner()?->getPlanFeature('paid_forms', false)) {
            return back()->with('error', 'Paid forms require a Pro plan or above.');
        }

        $data = $request->validate([
            'enabled'  => 'sometimes|boolean',
            'mode'     => 'nullable|in:fixed,per_field',
            'amount'   => 'nullable|numeric|min:0|max:100000',
            'currency' => 'required|string|size:3',
            'label'    => 'nullable|string|max:60',
        ]);

        $enabled     = $request->boolean('enabled');
        $mode        = ($data['mode'] ?? 'fixed') === 'per_field' ? 'per_field' : 'fixed';
        $amountCents = (int) round(((float) ($data['amount'] ?? 0)) * 100);

        // Fixed mode needs a positive flat price; per_field mode draws its
        // charge from priced fields, so the base fee here is optional (0 ok).
        if ($enabled && $mode === 'fixed' && $amountCents <= 0) {
            return back()->withInput()->with('error', 'Set a price greater than zero to require payment.');
        }

        if ($enabled && $mode === 'per_field' && $amountCents <= 0 && ! $form->hasPricedFields()) {
            return back()->withInput()->with('error', 'Add a price to at least one field (in the builder) or set a base fee before enabling per-field pricing.');
        }

        if ($enabled && ! $form->user?->defaultPaymentConnection()) {
            return back()->withInput()->with('error', 'Connect a payment gateway in Payouts before charging customers to submit this form.');
        }

        $settings = array_merge(Form::defaultSettings(), $form->settings ?? []);
        $settings['payment'] = array_merge(
            Form::defaultSettings()['payment'],
            (array) ($settings['payment'] ?? []),
            [
                'enabled'      => $enabled,
                'mode'         => $mode,
                'amount_cents' => $amountCents,
                'currency'     => strtoupper($data['currency']),
                'label'        => $data['label'] ?? null,
            ]
        );
        $form->update(['settings' => $settings]);

        return back()->with('success', 'Payment settings saved.');
    }

    public function publicShow(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $form->increment('total_views');
        $mathQuestion = $this->seedMathCaptcha($form);
        return view('common.form', ['form' => $form, 'embed' => false, 'mathQuestion' => $mathQuestion]);
    }

    public function publicIframe(Request $request, string $slug)
    {
        $form = Form::where('slug', $slug)->where('is_active', true)->firstOrFail();
        $form->increment('total_views');
        $mathQuestion = $this->seedMathCaptcha($form);
        return view('common.form', ['form' => $form, 'embed' => true, 'mathQuestion' => $mathQuestion]);
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

        // Files attached inside repeatable copies can't be flashed back by old()
        // on a 422 (browsers never resubmit a file input's value and Laravel
        // doesn't flash uploads). Before validation may redirect us back,
        // remember which repeatable copies carried an upload so the re-render
        // can prompt the visitor to re-attach instead of silently dropping it.
        $this->flashPendingRepeatableFiles($request, $form);

        $validated = $request->validate($rules, $this->customMessages);

        // Verify captcha before processing any submission data
        $captchaError = $this->verifyCaptcha($request, $form);
        if ($captchaError !== null) {
            if ($request->expectsJson()) {
                return response()->json(['error' => ['message' => $captchaError, 'code' => 'captcha_failed']], 422);
            }
            return back()->withInput()->withErrors(['_captcha' => $captchaError]);
        }

        // Pre-identify repeatable sections and their supported child fields so
        // we can collect structured copies from the rep_{id}[idx][fieldId] inputs.
        $repeatableSecIds = [];
        foreach ($form->fields ?? [] as $f) {
            if (($f['type'] ?? null) === 'section' && !empty($f['repeatable']) && !empty($f['id'])) {
                $repeatableSecIds[(string) $f['id']] = true;
            }
        }
        $repSecChildren = [];
        // Excluded types that cannot live inside a repeatable group at collect time.
        // File children are collected separately (they arrive in the uploaded-files
        // array, not ->input()), so they get their own map below.
        $repExcluded = ['file', 'signature', 'pricing', 'heading', 'paragraph', 'divider', 'page_break', 'section', 'hidden'];
        $repFileChildren = [];
        foreach ($form->fields ?? [] as $f) {
            $fParent = (string) ($f['parent'] ?? '');
            if ($fParent === '' || !isset($repeatableSecIds[$fParent])) continue;
            $ftype = $f['type'] ?? 'text';
            if ($ftype === 'file') {
                $repFileChildren[$fParent][] = $f;
            } elseif (!in_array($ftype, $repExcluded, true)) {
                $repSecChildren[$fParent][] = $f;
            }
        }

        $files = [];
        $data  = [];
        foreach ($form->fields ?? [] as $field) {
            $type = $field['type'] ?? 'text';
            $id = $field['id'] ?? null;

            // Repeatable section: collect structured copies from rep_{id}[idx][childId] inputs.
            if ($type === 'section' && $id && !empty($field['repeatable'])) {
                $repKey    = 'rep_' . $id;
                $rawCopies = $request->input($repKey, []);
                $repFiles  = $request->file($repKey, []);
                if (!is_array($rawCopies)) $rawCopies = [];
                if (!is_array($repFiles))  $repFiles  = [];

                // A copy index can appear in the text inputs, the uploaded files,
                // or both. Iterate the union (preserving each original index) so an
                // uploaded file lines up with the text siblings in its own copy.
                $copyIndices = array_values(array_unique(array_merge(
                    array_map('intval', array_keys($rawCopies)),
                    array_map('intval', array_keys($repFiles))
                )));
                sort($copyIndices);

                $copies = [];
                foreach ($copyIndices as $ci) {
                    $copyData  = is_array($rawCopies[$ci] ?? null) ? $rawCopies[$ci] : [];
                    $copyFiles = is_array($repFiles[$ci] ?? null) ? $repFiles[$ci] : [];
                    $copy = [];
                    foreach ($repSecChildren[$id] ?? [] as $child) {
                        $cid   = (string) ($child['id'] ?? '');
                        $ctype = $child['type'] ?? 'text';
                        if ($cid === '') continue;
                        if ($ctype === 'consent') {
                            $copy[$cid] = filter_var($copyData[$cid] ?? false, FILTER_VALIDATE_BOOL);
                        } elseif ($ctype === 'checkbox') {
                            $copy[$cid] = array_values(array_filter(
                                (array) ($copyData[$cid] ?? []),
                                fn ($v) => is_scalar($v)
                            ));
                        } else {
                            $raw = $copyData[$cid] ?? null;
                            $copy[$cid] = is_scalar($raw) ? (string) $raw : null;
                        }
                    }
                    // File children arrive in the uploaded-files array, not
                    // ->input(). Store each under the form OWNER's vault (visitors
                    // are anonymous) and keep a downloadable reference plus the
                    // original filename in the copy. The attachment key uses the
                    // final copies-array position so the owner UI can resolve the
                    // filename back from the stored group.
                    $pos = count($copies);
                    foreach ($repFileChildren[$id] ?? [] as $child) {
                        $cid = (string) ($child['id'] ?? '');
                        if ($cid === '') continue;
                        $up = $copyFiles[$cid] ?? null;
                        if ($up instanceof \Illuminate\Http\UploadedFile && $up->isValid()) {
                            try {
                                $userFile = UserFile::createFromUpload($up, $form->user, [
                                    'enforce_allowlist' => false,
                                ]);
                                $files["{$id}.{$pos}.{$cid}"] = $userFile->url;
                                $copy[$cid] = $up->getClientOriginalName();
                            } catch (\RuntimeException $e) {
                                $copy[$cid] = '[upload failed: ' . $e->getMessage() . ']';
                            }
                        } else {
                            $copy[$cid] = null;
                        }
                    }
                    $copies[] = $copy;
                }
                if (!empty($copies)) {
                    $data[$id] = ['_repeatable_group' => true, 'copies' => $copies];
                }
                continue;
            }

            // Pricing fields are handled below from the computed breakdown so the
            // stored value is the readable line-items, not a raw option index.
            if (!$id || in_array($type, ['heading', 'paragraph', 'divider', 'page_break', 'section', 'pricing'])) continue;

            // Children of repeatable sections were already collected above.
            $fParent = (string) ($field['parent'] ?? '');
            if ($fParent !== '' && isset($repeatableSecIds[$fParent])) continue;

            if ($type === 'file' && $request->hasFile($id)) {
                // Vault submissions under the form OWNER's quota — visitors are
                // anonymous and the owner is the one who keeps the file.
                try {
                    $userFile = UserFile::createFromUpload($request->file($id), $form->user, [
                        'enforce_allowlist' => false,
                    ]);
                    $files[$id] = $userFile->url;
                    $data[$id] = $request->file($id)->getClientOriginalName();
                } catch (\RuntimeException $e) {
                    // Submission attachment couldn't be stored (e.g. owner over
                    // quota). Drop the file but keep the rest of the submission
                    // so the visitor isn't blocked by something they can't fix.
                    $data[$id] = '[upload failed: ' . $e->getMessage() . ']';
                }
            } elseif ($type === 'signature') {
                $raw = (string) $request->input($id, '');
                if (str_starts_with($raw, 'data:image/png;base64,')) {
                    $bin = base64_decode(substr($raw, strlen('data:image/png;base64,')), true);
                    // Verify true PNG magic header (8 bytes) — defends against type-spoofing
                    // via a forged data-URL prefix wrapping arbitrary binary.
                    $pngMagic = "\x89PNG\r\n\x1a\n";
                    if ($bin !== false && strlen($bin) >= 24 && strlen($bin) <= 2_000_000
                        && substr($bin, 0, 8) === $pngMagic) {
                        try {
                            $userFile = UserFile::createFromBytes(
                                $bin,
                                'signature_' . bin2hex(random_bytes(4)) . '.png',
                                'image/png',
                                $form->user
                            );
                            $files[$id] = $userFile->url;
                            $data[$id]  = $userFile->url;
                        } catch (\RuntimeException $e) {
                            $data[$id] = '[signature not stored: ' . $e->getMessage() . ']';
                        }
                    }
                }
            } elseif ($type === 'consent') {
                $data[$id] = $request->boolean($id);
            } elseif ($type === 'checkbox') {
                $data[$id] = (array) $request->input($id, []);
            } elseif ($type === 'full_name') {
                $data[$id] = [
                    'first' => (string) $request->input($id . '_first', ''),
                    'last'  => (string) $request->input($id . '_last', ''),
                ];
            } elseif ($type === 'address') {
                $addr = (array) $request->input($id, []);
                $data[$id] = array_map(
                    fn ($v) => substr((string) $v, 0, 255),
                    array_intersect_key($addr, array_flip(['street', 'city', 'state', 'postal', 'country']))
                );
            } elseif ($type === 'time_range' || $type === 'date_range') {
                $range = (array) $request->input($id, []);
                $data[$id] = [
                    'start' => (string) ($range['start'] ?? ''),
                    'end'   => (string) ($range['end'] ?? ''),
                ];
            } elseif ($type === 'yes_no') {
                $val = (string) $request->input($id, '');
                $data[$id] = in_array($val, ['yes', 'no'], true) ? $val : '';
            } elseif ($type === 'ranking') {
                $val = (string) $request->input($id, '');
                $data[$id] = $val !== '' ? array_map('trim', explode(',', $val)) : [];
            } elseif ($type === 'hidden' && !empty($field['auto_collect'])) {
                $data[$id] = $this->resolveAutoCollect($field['auto_collect_param'] ?? 'ip', $request, $form);
            } else {
                $data[$id] = $request->input($id);
            }
        }

        // Pricing / Package selection — compute the per-field breakdown (chosen
        // option + ticked addons) and store it under each pricing field's id so
        // the owner sees a readable line-item summary. The grand total drives the
        // charge below (selectable-pricing path).
        $selection = $form->computeSelectionBreakdown($request);
        foreach ($selection['by_field'] as $fid => $breakdown) {
            $data[$fid] = $breakdown;
        }

        // Spam heuristics — honeypot, link count, blocked keywords, per-IP rate
        // limit. Matches are stored with is_spam=true so they're hidden from
        // the default inbox view but reviewable in the Spam tab.
        $scanText = collect($data)->reject(fn ($v) => is_array($v) || is_bool($v))
            ->map(fn ($v) => (string) $v)->implode(' ');
        // Sniff sender email/phone out of any free-form field so the trusted-
        // sender bypass works even for forms with custom (non-standard) field
        // IDs. Falls back to the well-known keys first for stability.
        $senderEmail = null;
        foreach (['email', 'Email', 'email_address', 'e_mail'] as $k) {
            if (!empty($data[$k]) && is_string($data[$k]) && filter_var($data[$k], FILTER_VALIDATE_EMAIL)) {
                $senderEmail = $data[$k]; break;
            }
        }
        if ($senderEmail === null) {
            foreach ($data as $v) {
                if (is_string($v) && filter_var($v, FILTER_VALIDATE_EMAIL)) { $senderEmail = $v; break; }
            }
        }
        $senderPhone = null;
        foreach (['phone', 'Phone', 'tel', 'mobile', 'phone_number'] as $k) {
            if (!empty($data[$k]) && is_string($data[$k])) { $senderPhone = $data[$k]; break; }
        }
        if ($senderPhone === null) {
            foreach ($data as $key => $v) {
                if (!is_string($v)) continue;
                if (preg_match('/(phone|tel|mobile|whatsapp)/i', (string) $key)
                    && preg_match('/[\d][\d\-\s().+]{6,}/', $v)) { $senderPhone = $v; break; }
            }
        }

        $spamCheck = app(SpamChecker::class)->check([
            'honeypot' => $request->input('_hp'),
            'ip'       => $request->ip(),
            'text'     => $scanText,
            'scope'    => 'form:' . $form->id,
            'user_id'  => $form->user_id,
            'email'    => $senderEmail,
            'phone'    => $senderPhone,
        ]);

        // Resolve the charge. Two pricing subsystems can each contribute to one
        // total, both recomputed server-side and never trusted from the client:
        //   - Fixed / per-field pricing (Task #2321): computeAmountCents($data)
        //     plus its itemised priceLineItems($data) breakdown. Only counts
        //     when the Payments toggle is enabled (its plan/source gate).
        //   - Pricing / Package fields (Task #2333): the selection breakdown
        //     ($selection) computed from the chosen option + ticked addons above.
        // A form only charges when it's flagged paid (plan allows), the owner has
        // a connected gateway to receive the funds, AND the combined total is
        // positive. If the gateway was removed after setup — or nothing priced
        // was selected — the submission degrades to a normal free submission
        // rather than stranding the customer at a dead checkout.
        $canCharge      = $form->isPaid() && (bool) $form->user?->defaultPaymentConnection();
        $paymentEnabled = !empty($form->paymentConfig()['enabled']);
        $perFieldCents  = ($canCharge && $paymentEnabled) ? $form->computeAmountCents($data) : 0;
        $lineItems      = ($canCharge && $paymentEnabled) ? $form->priceLineItems($data) : [];
        $chargeCents    = $canCharge ? ($perFieldCents + (int) $selection['amount_cents']) : 0;
        $chargeCurrency = $form->paymentCurrency();
        $isPaid         = $canCharge && $chargeCents > 0;

        $submission = $form->submissions()->create([
            'data' => $data,
            'files' => $files ?: null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'referrer' => substr((string) $request->headers->get('referer', ''), 0, 500),
            'country' => $this->resolveCountry($request->ip()),
            'is_spam' => $spamCheck['is_spam'],
            'spam_reason' => $spamCheck['is_spam'] ? $spamCheck['reason'] : null,
            // Paid forms start every attempt (spam included) as 'pending' — an
            // unpaid attempt that only becomes a real submission once its charge
            // clears. Free forms are immediately 'none' (complete).
            'payment_status' => $isPaid ? 'pending' : 'none',
            // Persist the computed breakdown so the owner sees exactly what was
            // (or will be) charged for this submission.
            'line_items' => $isPaid ? $lineItems : null,
        ]);

        // Fan the captured submission out to the owner's connected CRMs as a
        // lead (queued off the request cycle; no-op when no CRM is connected).
        // Obvious spam is never forwarded. Loop-safe: CRM pulls create Contacts
        // only, never form submissions.
        if (!$spamCheck['is_spam'] && $form->user_id && ($senderEmail || $senderPhone)) {
            $senderName = null;
            foreach (['name', 'Name', 'full_name', 'first_name', 'first_name '] as $k) {
                if (!empty($data[$k]) && is_string($data[$k])) { $senderName = $data[$k]; break; }
            }
            $nameParts = $senderName ? preg_split('/\s+/', trim($senderName), 2) : [];
            \App\Jobs\PushLeadToCrmJob::forUser((int) $form->user_id, [
                'email'        => $senderEmail,
                'phone'        => $senderPhone,
                'first_name'   => $nameParts[0] ?? null,
                'last_name'    => $nameParts[1] ?? null,
                'display_name' => $senderName,
                'company'      => null,
                'source'       => 'form:' . $form->id,
            ]);
        }

        // Paid form (Task #2319 / #2321): hold the submission pending and send
        // the customer to the OWNER's gateway for the computed total. Counting +
        // owner notifications happen only on a verified return
        // (confirmFormPayment). Obvious spam is never charged — it's stored for
        // review without a checkout.
        if ($isPaid && ! $spamCheck['is_spam']) {
            $checkout = app(\App\Services\Monetization\MonetizationCheckout::class)
                ->startFormPayment($form, $submission, $senderEmail, $chargeCents, $lineItems, $chargeCurrency);

            if ($request->wantsJson()) {
                return response()->json([
                    'ok' => true,
                    'payment_required' => true,
                    'checkout_url' => $checkout['url'],
                    'amount_cents' => $chargeCents,
                    'currency' => $chargeCurrency,
                ]);
            }
            return redirect()->away($checkout['url']);
        }

        // Reaching here means a free-form submission or a paid-form spam attempt
        // we store for review but never charge. Paid-form attempts (incl. spam)
        // must not touch the completed-submission counter or fire owner
        // notifications until — and unless — their charge clears.
        if (! $isPaid) {
            $form->increment('total_submissions');

            // Don't fire owner notifications / autoresponders / webhooks for spam —
            // the whole point of the Spam tab is to keep noise out of the inbox.
            if (! $spamCheck['is_spam']) {
                $this->fireNotifications($form, $submission);

                // Account-level forwarding rules — fan out to user's email/webhook
                // destinations whose source filter matches form_submission.
                try {
                    app(\App\Modules\User\Services\InboxForwarder::class)
                        ->dispatchForFormSubmission($form->user_id, $submission);
                } catch (\Throwable $e) {
                    logger()->warning('Inbox forwarder (form) failed: ' . $e->getMessage());
                }
            }
        }

        return $this->successResponse($request, $form, $spamCheck['is_spam'], $submission);
    }

    /**
     * Fire owner notifications + account forwarding for a paid-form
     * submission once its charge has cleared. Called by
     * MonetizationCheckout::confirmFormPayment (outside the request cycle).
     */
    public function finalizePaidSubmission(Form $form, FormSubmission $submission): void
    {
        $this->fireNotifications($form, $submission);
        try {
            app(\App\Modules\User\Services\InboxForwarder::class)
                ->dispatchForFormSubmission($form->user_id, $submission);
        } catch (\Throwable $e) {
            logger()->warning('Inbox forwarder (form) failed: ' . $e->getMessage());
        }
    }

    /**
     * Best-effort ISO country for a submission's IP, reused by the advanced
     * analytics geo breakdown. Returns null when no resolver is available
     * (the column simply stays empty — the breakdown degrades gracefully).
     */
    protected function resolveCountry(?string $ip): ?string
    {
        if (!$ip) return null;
        try {
            $c = app(\App\Modules\Common\Services\GeoIpService::class)->detectCountry($ip);
            return $c ? strtoupper(substr($c, 0, 2)) : null;
        } catch (\Throwable $e) {
            // Resolver unavailable — leave geo empty; breakdown degrades.
            return null;
        }
    }

    /**
     * Remember which repeatable copies carried an uploaded file so a 422
     * re-render can prompt the visitor to re-attach (uploads can't be flashed
     * to the session like old() text input). Structure:
     *   session('_rep_file_pending') = [sectionId => [copyIdx => [fieldId => filename]]]
     * Called BEFORE validation because a failed validate() redirects back.
     */
    protected function flashPendingRepeatableFiles(Request $request, Form $form): void
    {
        $repSecIds = [];
        foreach ($form->fields ?? [] as $f) {
            if (($f['type'] ?? null) === 'section' && !empty($f['repeatable']) && !empty($f['id'])) {
                $repSecIds[(string) $f['id']] = true;
            }
        }
        if (empty($repSecIds)) return;

        $pending = [];
        foreach ($request->allFiles() as $key => $copies) {
            if (!is_string($key) || !str_starts_with($key, 'rep_')) continue;
            $secId = substr($key, 4);
            if (!isset($repSecIds[$secId])) continue;
            foreach ((array) $copies as $ci => $childFiles) {
                foreach ((array) $childFiles as $cid => $file) {
                    if ($file instanceof \Illuminate\Http\UploadedFile && $file->isValid()) {
                        $pending[$secId][(int) $ci][(string) $cid] = $file->getClientOriginalName();
                    }
                }
            }
        }
        if (!empty($pending)) {
            session()->flash('_rep_file_pending', $pending);
        }
    }

    protected function buildSubmissionRules(Form $form): array
    {
        $rules = ['_hp' => ['nullable', 'string', 'max:200']];
        $messages = [];

        // Pre-identify repeatable sections so children can be routed to wildcard keys.
        $repeatableSectionIds = [];
        foreach ($form->fields ?? [] as $f) {
            if (($f['type'] ?? null) === 'section' && !empty($f['repeatable']) && !empty($f['id'])) {
                $repeatableSectionIds[(string) $f['id']] = $f;
            }
        }

        foreach ($form->fields ?? [] as $field) {
            $type = $field['type'] ?? 'text';
            $id = $field['id'] ?? null;
            if (!$id || in_array($type, ['heading', 'paragraph', 'divider', 'page_break'])) continue;

            // Repeatable sections: add array-count rules; non-repeatable sections skip.
            if ($type === 'section') {
                if (empty($field['repeatable'])) continue;
                $sMin = max(1, (int) ($field['repeat_min'] ?? 1));
                $sMax = isset($field['repeat_max']) ? (int) $field['repeat_max'] : null;
                $repKey = 'rep_' . $id;
                // One-question (oneq) layout degrades repeatable groups to a single fixed copy
                // (index 0). We cannot enforce min/max counts there because the UI never renders
                // multiple copies. Skip count rules for oneq; child-field rules still apply below.
                $isOneq = ($form->settings['layout'] ?? 'standard') === 'oneq';
                if ($isOneq) {
                    $rules[$repKey] = ['nullable', 'array'];
                } else {
                    // Use 'required' (not 'nullable') when min >= 1 so a client cannot simply
                    // omit the rep_* key and bypass the minimum copy enforcement.
                    $arrayRules = [$sMin >= 1 ? 'required' : 'nullable', 'array', "min:$sMin"];
                    if ($sMax) $arrayRules[] = "max:$sMax";
                    $rules[$repKey] = $arrayRules;
                }
                continue;
            }

            // Children of repeatable sections are validated under wildcard keys.
            $parent = $field['parent'] ?? null;
            if ($parent !== null && isset($repeatableSectionIds[(string) $parent])) {
                $repParent = 'rep_' . $parent;
                $stack = $this->buildFieldRuleStack($field);
                $rules["{$repParent}.*.$id"] = $stack;
                if (!empty($field['error_message'])) {
                    $messages["{$repParent}.*.$id.required"] = $field['error_message'];
                }
                continue;
            }

            // Non-section, non-repeatable-child — existing flat handling below.

            $req = !empty($field['required']);
            $base = $req ? 'required' : 'nullable';
            $minL = isset($field['min_length']) ? (int) $field['min_length'] : null;
            $maxL = isset($field['max_length']) ? (int) $field['max_length'] : null;
            $minN = isset($field['min']) && $field['min'] !== '' ? $field['min'] : null;
            $maxN = isset($field['max']) && $field['max'] !== '' ? $field['max'] : null;
            $pattern = $this->sanitizeRegex($field['pattern'] ?? null);

            $stack = [$base];
            switch ($type) {
                case 'email':
                    $stack[] = 'email';
                    $stack[] = 'max:' . ($maxL ?: 255);
                    if ($minL) $stack[] = "min:$minL";
                    break;
                case 'url':
                    $stack[] = 'url';
                    $stack[] = 'max:' . ($maxL ?: 500);
                    if ($minL) $stack[] = "min:$minL";
                    break;
                case 'phone':
                    $stack[] = 'string';
                    $stack[] = 'max:' . ($maxL ?: 40);
                    if ($minL) $stack[] = "min:$minL";
                    if ($pattern) $stack[] = 'regex:/' . str_replace('/', '\/', $pattern) . '/';
                    break;
                case 'number':
                    $stack[] = 'numeric';
                    if ($minN !== null) $stack[] = "min:$minN";
                    if ($maxN !== null) $stack[] = "max:$maxN";
                    break;
                case 'date':    $stack[] = 'date'; break;
                case 'time':    $stack[] = 'date_format:H:i'; break;
                case 'rating':  $stack = [$base, 'integer', 'min:0', 'max:' . (int) ($field['max'] ?? 5)]; break;
                case 'scale':   $stack = [$base, 'integer', 'min:' . (int) ($field['min'] ?? 0), 'max:' . (int) ($field['max'] ?? 10)]; break;
                case 'select':
                case 'radio':
                    $stack[] = 'string';
                    $stack[] = 'max:255';
                    if (!empty($field['options'])) $stack[] = 'in:' . implode(',', array_map(fn ($o) => str_replace(',', '\,', $o), $field['options']));
                    break;
                case 'checkbox':
                    $stack = $req ? ['required', 'array', 'min:1'] : ['nullable', 'array'];
                    break;
                case 'pricing':
                    // The chosen option is a 0-based index into price_options;
                    // ticked addons arrive under "{id}_addons" as index values.
                    $optCount   = count($field['price_options'] ?? []);
                    $addonCount = count($field['addons'] ?? []);
                    $stack = [$req ? 'required' : 'nullable', 'integer', 'min:0'];
                    if ($optCount > 0) $stack[] = 'max:' . ($optCount - 1);
                    $rules["{$id}_addons"] = ['nullable', 'array'];
                    if ($addonCount > 0) {
                        $rules["{$id}_addons.*"] = ['integer', 'min:0', 'max:' . ($addonCount - 1)];
                    }
                    if ($req) $messages["$id.required"] = $field['error_message'] ?? 'Please choose an option.';
                    break;
                case 'textarea':
                    $stack[] = 'string';
                    $stack[] = 'max:' . ($maxL ?: 10000);
                    if ($minL) $stack[] = "min:$minL";
                    break;
                case 'consent':
                    $stack = $req ? ['accepted'] : ['nullable'];
                    break;
                case 'file':
                    $maxKb = (int) ($field['file_max_kb'] ?? 10240);
                    if ($maxKb < 1) $maxKb = 10240;
                    $mimes = trim((string) ($field['file_types'] ?? ''));
                    $mimes = $mimes !== '' ? preg_replace('/[^a-zA-Z0-9,]/', '', $mimes) : 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,mp3,mp4,mov';
                    $stack = [$req ? 'required' : 'nullable', 'file', "max:$maxKb", "mimes:$mimes"];
                    break;
                case 'signature':
                    // Drawn signature is sent as a base64 PNG data URL string. We keep the
                    // regex string-rule simple (no user input → no DoS), then re-validate
                    // PNG magic bytes after decode in publicSubmit.
                    $stack = [$req ? 'required' : 'nullable', 'string', 'max:2000000', 'regex:/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/'];
                    if ($req) $messages["$id.required"] = $field['error_message'] ?? 'Please sign before submitting.';
                    break;
                case 'hidden':
                    $stack = ['nullable', 'string', 'max:1000'];
                    break;
                case 'full_name':
                    // Submitted as {id}_first / {id}_last sub-fields
                    $rules["{$id}_first"] = [$base, 'string', 'max:100'];
                    $rules["{$id}_last"]  = [$base, 'string', 'max:100'];
                    $stack = ['nullable']; // parent key not submitted
                    break;
                case 'address':
                    $rules["{$id}.street"]  = [$base, 'string', 'max:255'];
                    $rules["{$id}.city"]    = ['nullable', 'string', 'max:100'];
                    $rules["{$id}.state"]   = ['nullable', 'string', 'max:100'];
                    $rules["{$id}.postal"]  = ['nullable', 'string', 'max:20'];
                    $rules["{$id}.country"] = ['nullable', 'string', 'max:100'];
                    $stack = ['nullable', 'array'];
                    break;
                case 'country':
                    $stack[] = 'string';
                    $stack[] = 'max:100';
                    break;
                case 'currency':
                    $stack[] = 'numeric';
                    $stack[] = 'min:0';
                    break;
                case 'yes_no':
                    $stack[] = 'string';
                    $stack[] = 'in:yes,no';
                    break;
                case 'image_choice':
                    $stack[] = 'string';
                    $stack[] = 'max:255';
                    break;
                case 'ranking':
                    $stack = [$base, 'string', 'max:1000'];
                    break;
                case 'slider':
                    $stack[] = 'numeric';
                    if ($minN !== null) $stack[] = "min:$minN";
                    if ($maxN !== null) $stack[] = "max:$maxN";
                    break;
                case 'time_range':
                    $rules["{$id}.start"] = [$base, 'date_format:H:i'];
                    $rules["{$id}.end"]   = [$base, 'date_format:H:i', "after_or_equal:{$id}.start"];
                    $stack = ['nullable', 'array'];
                    break;
                case 'date_range':
                    $rules["{$id}.start"] = [$base, 'date'];
                    $rules["{$id}.end"]   = [$base, 'date', "after_or_equal:{$id}.start"];
                    if (!empty($field['min_date'])) {
                        $rules["{$id}.start"][] = 'after_or_equal:' . $field['min_date'];
                    }
                    if (!empty($field['max_date'])) {
                        $rules["{$id}.end"][] = 'before_or_equal:' . $field['max_date'];
                    }
                    $stack = ['nullable', 'array'];
                    break;
                default: // text and unknown
                    $stack[] = 'string';
                    $stack[] = 'max:' . ($maxL ?: 1000);
                    if ($minL) $stack[] = "min:$minL";
                    if ($pattern) $stack[] = 'regex:/' . str_replace('/', '\/', $pattern) . '/';
            }
            // Use array form (NOT pipe-delimited) so user-supplied regex containing `|`
            // is not split mid-rule by Laravel's string-rule parser.
            $rules[$id] = $stack;

            // Custom user-defined error message (used for any rule that fires)
            if (!empty($field['error_message'])) {
                foreach (['required', 'email', 'url', 'numeric', 'integer', 'date', 'date_format', 'min', 'max', 'in', 'array', 'accepted', 'file', 'mimes', 'string', 'regex'] as $rule) {
                    $messages["$id.$rule"] = $field['error_message'];
                }
            }
            if (!empty($field['pattern_message'])) {
                $messages["$id.regex"] = $field['pattern_message'];
            }
        }
        // Stash messages so the publicSubmit caller can use them
        $this->customMessages = $messages;
        return $rules;
    }

    /** @var array<string,string> */
    protected array $customMessages = [];

    /**
     * Build a Laravel validation rule stack for a single form field definition.
     * Shared between flat-field and repeatable-group-child validation.
     * Excluded types (file, signature, pricing, hidden) return ['nullable'].
     */
    protected function buildFieldRuleStack(array $field): array
    {
        $type  = $field['type'] ?? 'text';
        $req   = !empty($field['required']);
        $base  = $req ? 'required' : 'nullable';
        $minL  = isset($field['min_length']) ? (int) $field['min_length'] : null;
        $maxL  = isset($field['max_length']) ? (int) $field['max_length'] : null;
        $minN  = isset($field['min']) && $field['min'] !== '' ? $field['min'] : null;
        $maxN  = isset($field['max']) && $field['max'] !== '' ? $field['max'] : null;
        $pat   = $this->sanitizeRegex($field['pattern'] ?? null);
        $stack = [$base];

        switch ($type) {
            case 'email':
                $stack[] = 'email';
                $stack[] = 'max:' . ($maxL ?: 255);
                if ($minL) $stack[] = "min:$minL";
                break;
            case 'url':
                $stack[] = 'url';
                $stack[] = 'max:' . ($maxL ?: 500);
                if ($minL) $stack[] = "min:$minL";
                break;
            case 'phone':
                $stack[] = 'string';
                $stack[] = 'max:' . ($maxL ?: 40);
                if ($minL) $stack[] = "min:$minL";
                if ($pat) $stack[] = 'regex:/' . str_replace('/', '\/', $pat) . '/';
                break;
            case 'number':
                $stack[] = 'numeric';
                if ($minN !== null) $stack[] = "min:$minN";
                if ($maxN !== null) $stack[] = "max:$maxN";
                break;
            case 'date':
                $stack[] = 'date';
                break;
            case 'time':
                $stack[] = 'date_format:H:i';
                break;
            case 'rating':
                $stack = [$base, 'integer', 'min:0', 'max:' . (int) ($field['max'] ?? 5)];
                break;
            case 'scale':
                $stack = [$base, 'integer', 'min:' . (int) ($field['min'] ?? 0), 'max:' . (int) ($field['max'] ?? 10)];
                break;
            case 'select':
            case 'radio':
                $stack[] = 'string';
                $stack[] = 'max:255';
                if (!empty($field['options'])) {
                    $stack[] = 'in:' . implode(',', array_map(fn ($o) => str_replace(',', '\,', (string) $o), $field['options']));
                }
                break;
            case 'checkbox':
                $stack = $req ? ['required', 'array', 'min:1'] : ['nullable', 'array'];
                break;
            case 'textarea':
                $stack[] = 'string';
                $stack[] = 'max:' . ($maxL ?: 10000);
                if ($minL) $stack[] = "min:$minL";
                break;
            case 'consent':
                $stack = $req ? ['accepted'] : ['nullable'];
                break;
            case 'file':
                // Repeatable file children are collected via $request->file();
                // validate size/type here so an unauthenticated visitor can't
                // push an oversized or disallowed upload into the owner's vault.
                $maxKb = (int) ($field['file_max_kb'] ?? 10240);
                if ($maxKb < 1) $maxKb = 10240;
                $mimes = trim((string) ($field['file_types'] ?? ''));
                $mimes = $mimes !== '' ? preg_replace('/[^a-zA-Z0-9,]/', '', $mimes) : 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,mp3,mp4,mov';
                $stack = [$req ? 'required' : 'nullable', 'file', "max:$maxKb", "mimes:$mimes"];
                break;
            case 'signature':
            case 'pricing':
            case 'hidden':
                $stack = ['nullable'];
                break;
            default: // 'text' and unknown
                $stack[] = 'string';
                $stack[] = 'max:' . ($maxL ?: 1000);
                if ($minL) $stack[] = "min:$minL";
                if ($pat) $stack[] = 'regex:/' . str_replace('/', '\/', $pat) . '/';
                break;
        }
        return $stack;
    }

    /**
     * Verify the captcha token for the submitted form.
     * Returns null on success, or an error string to show the user.
     * Fails-open on network errors so temporary outages don't block real users.
     */
    protected function verifyCaptcha(Request $request, Form $form): ?string
    {
        $cap      = $form->captchaConfig();
        $provider = $cap['provider'] ?? 'honeypot';

        switch ($provider) {
            case 'math':
                $answer    = $request->session()->pull("_form_math_{$form->id}");
                $submitted = (int) $request->input('_math_answer');
                if ($answer === null || $submitted !== (int) $answer) {
                    return 'Incorrect answer to the math question. Please try again.';
                }
                break;

            case 'recaptcha_v2':
                $secret   = !empty($cap['secret_key']) ? decrypt($cap['secret_key']) : null;
                if (!$secret) break;
                $response = (string) $request->input('g-recaptcha-response', '');
                if ($response === '') return 'Please complete the CAPTCHA.';
                try {
                    $r = \Illuminate\Support\Facades\Http::asForm()
                        ->timeout(5)
                        ->post('https://www.google.com/recaptcha/api/siteverify', [
                            'secret'   => $secret,
                            'response' => $response,
                            'remoteip' => $request->ip(),
                        ]);
                    if (!($r->json('success'))) return 'CAPTCHA verification failed. Please try again.';
                } catch (\Exception) {
                    return 'CAPTCHA verification could not be completed. Please try again.';
                }
                break;

            case 'recaptcha_v3':
                $secret    = !empty($cap['secret_key']) ? decrypt($cap['secret_key']) : null;
                if (!$secret) break;
                $response  = (string) $request->input('_recaptcha_token', '');
                if ($response === '') return 'CAPTCHA token missing. Please refresh and try again.';
                try {
                    $r         = \Illuminate\Support\Facades\Http::asForm()
                        ->timeout(5)
                        ->post('https://www.google.com/recaptcha/api/siteverify', [
                            'secret'   => $secret,
                            'response' => $response,
                            'remoteip' => $request->ip(),
                        ]);
                    $threshold = (float) ($cap['score_threshold'] ?? 0.5);
                    if (!$r->json('success') || (float) $r->json('score', 1.0) < $threshold) {
                        return 'Our security check flagged this submission as likely automated. Please try again.';
                    }
                } catch (\Exception) {
                    return 'CAPTCHA verification could not be completed. Please try again.';
                }
                break;

            case 'hcaptcha':
                $secret   = !empty($cap['secret_key']) ? decrypt($cap['secret_key']) : null;
                if (!$secret) break;
                $response = (string) $request->input('h-captcha-response', '');
                if ($response === '') return 'Please complete the CAPTCHA.';
                try {
                    $r = \Illuminate\Support\Facades\Http::asForm()
                        ->timeout(5)
                        ->post('https://hcaptcha.com/siteverify', [
                            'secret'   => $secret,
                            'response' => $response,
                            'remoteip' => $request->ip(),
                        ]);
                    if (!$r->json('success')) return 'hCaptcha verification failed. Please try again.';
                } catch (\Exception) {
                    return 'CAPTCHA verification could not be completed. Please try again.';
                }
                break;

            case 'turnstile':
                $secret   = !empty($cap['secret_key']) ? decrypt($cap['secret_key']) : null;
                if (!$secret) break;
                $response = (string) $request->input('cf-turnstile-response', '');
                if ($response === '') return 'Please complete the Turnstile challenge.';
                try {
                    $r = \Illuminate\Support\Facades\Http::asForm()
                        ->timeout(5)
                        ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                            'secret'   => $secret,
                            'response' => $response,
                            'remoteip' => $request->ip(),
                        ]);
                    if (!$r->json('success')) return 'Turnstile verification failed. Please try again.';
                } catch (\Exception) {
                    return 'CAPTCHA verification could not be completed. Please try again.';
                }
                break;

            case 'honeypot':
            default:
                // Honeypot is validated via the SpamChecker using the _hp field
                break;
        }
        return null;
    }

    /**
     * Generate a math captcha question for forms that use it, storing the answer
     * in the session. Returns null when this form does not use math captcha.
     */
    protected function seedMathCaptcha(Form $form): ?array
    {
        if ($form->captchaConfig()['provider'] !== 'math') return null;
        $a      = random_int(1, 15);
        $b      = random_int(1, 15);
        $answer = $a + $b;
        session(["_form_math_{$form->id}" => $answer]);
        return ['question' => "What is {$a} + {$b}?", 'answer' => $answer];
    }

    /**
     * Resolve a value for auto-collected hidden fields from request context.
     * Only safe, non-sensitive metadata is collected — never cookies or credentials.
     *
     * Supports: ip, ua, referer, language, page_url, timestamp,
     *           utm_source, utm_medium, utm_campaign, utm_term, utm_content,
     *           browser, os, device, country (blank — no GeoIP service wired),
     *           and custom query keys via "query:{key}" prefix.
     */
    protected function resolveAutoCollect(string $param, Request $request, ?Form $form = null): string
    {
        // Custom query-string key: "query:my_param" → ?my_param=value
        if (str_starts_with($param, 'query:')) {
            $key = substr($param, 6);
            return substr((string) $request->query($key, ''), 0, 500);
        }

        $ua = (string) ($request->userAgent() ?? '');

        return match ($param) {
            'ip'           => (string) ($request->ip() ?? ''),
            'ua'           => substr($ua, 0, 500),
            'referer'      => substr((string) ($request->header('Referer', '')), 0, 500),
            'language'     => substr((string) ($request->header('Accept-Language', '')), 0, 100),
            'page_url'     => substr($request->fullUrl(), 0, 500),
            'landing_url'  => substr((string) ($form->getPublicUrl()), 0, 500),
            'timestamp'    => now()->toIso8601String(),
            'utm_source'   => substr((string) $request->query('utm_source', ''), 0, 200),
            'utm_medium'   => substr((string) $request->query('utm_medium', ''), 0, 200),
            'utm_campaign' => substr((string) $request->query('utm_campaign', ''), 0, 200),
            'utm_term'     => substr((string) $request->query('utm_term', ''), 0, 200),
            'utm_content'  => substr((string) $request->query('utm_content', ''), 0, 200),
            'browser'      => $this->parseUaBrowser($ua),
            'os'           => $this->parseUaOs($ua),
            'device'       => $this->parseUaDevice($ua),
            'biolink_alias' => $form->slug ?? '',
            'country'      => '', // GeoIP not wired — blank
            'city'         => '', // GeoIP not wired — blank
            default        => '',
        };
    }

    private function parseUaBrowser(string $ua): string
    {
        if (str_contains($ua, 'Edg/') || str_contains($ua, 'Edge/')) return 'Edge';
        if (str_contains($ua, 'OPR/') || str_contains($ua, 'Opera')) return 'Opera';
        if (str_contains($ua, 'Chrome/')) return 'Chrome';
        if (str_contains($ua, 'Firefox/')) return 'Firefox';
        if (str_contains($ua, 'Safari/') && str_contains($ua, 'Version/')) return 'Safari';
        return 'Other';
    }

    private function parseUaOs(string $ua): string
    {
        if (str_contains($ua, 'Windows')) return 'Windows';
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) return 'iOS';
        if (str_contains($ua, 'Mac OS X')) return 'macOS';
        if (str_contains($ua, 'Android')) return 'Android';
        if (str_contains($ua, 'Linux')) return 'Linux';
        return 'Other';
    }

    private function parseUaDevice(string $ua): string
    {
        if (str_contains($ua, 'Mobi') || str_contains($ua, 'iPhone') || str_contains($ua, 'Android')) return 'Mobile';
        if (str_contains($ua, 'iPad') || str_contains($ua, 'Tablet')) return 'Tablet';
        return 'Desktop';
    }

    protected function cleanPriceList($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $entry) {
            if (!is_array($entry)) continue;
            $label = trim((string) ($entry['label'] ?? ''));
            if ($label === '') continue;
            $label = mb_substr($label, 0, 120);
            $price = is_numeric($entry['price'] ?? null) ? (float) $entry['price'] : 0.0;
            $price = max(0.0, min(1000000.0, round($price, 2)));
            $out[] = ['label' => $label, 'price' => $price];
            if (count($out) >= 50) break;
        }
        return $out;
    }

    /**
     * Sanitize a user-supplied regex pattern.
     * Returns null if the pattern is unsafe, too long, or fails to compile.
     * Defends against catastrophic-backtracking by rejecting nested unbounded quantifiers.
     */
    protected function sanitizeRegex(?string $pattern): ?string
    {
        if ($pattern === null || $pattern === '') return null;
        if (strlen($pattern) > 200) return null;
        // Reject nested unbounded quantifiers — classic ReDoS shapes like (a+)+, (a*)*, (a+)*, (a*)+
        if (preg_match('/\([^)]*[+*][^)]*\)\s*[+*]/', $pattern)) return null;
        // Reject delimiter and modifier-injection attempts
        if (str_contains($pattern, "\0")) return null;
        // Compile-test with the same delimiter we use server-side. Suppress warnings.
        $delimited = '/' . str_replace('/', '\/', $pattern) . '/';
        set_error_handler(fn () => null);
        $ok = @preg_match($delimited, '') !== false;
        restore_error_handler();
        return $ok ? $pattern : null;
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
                    if (is_array($v) && !empty($v['_repeatable_group'])) {
                        $copyLines = [];
                        foreach (array_values($v['copies'] ?? []) as $rci => $rcopy) {
                            $copyLines[] = '  Copy ' . ($rci + 1) . ':';
                            foreach ((array) $rcopy as $rck => $rcv) {
                                $flat = is_array($rcv) ? implode(', ', $rcv) : ($rcv === null ? '' : (string) $rcv);
                                $copyLines[] = '    ' . ucfirst(str_replace('_', ' ', (string) $rck)) . ': ' . $flat;
                            }
                        }
                        $line = implode("\n", $copyLines);
                    } elseif (is_array($v) && !empty($v['_pricing'])) {
                        $cur   = $v['currency'] ?? 'USD';
                        $parts = [];
                        if (!empty($v['option']['label'])) $parts[] = $v['option']['label'];
                        foreach (($v['addons'] ?? []) as $ad) {
                            if (!empty($ad['label'])) $parts[] = $ad['label'];
                        }
                        $line = implode(' + ', $parts) . ' = '
                            . number_format(((int) ($v['total_cents'] ?? 0)) / 100, 2) . ' ' . $cur;
                    } else {
                        $line = is_array($v) ? implode(', ', $v) : (string) $v;
                    }
                    $body .= ucfirst(str_replace('_', ' ', $k)) . ': ' . $line . "\n";
                }
                $replyToRaw = $submission->data[$n['email']['reply_to_field']] ?? null;
                // Strip newlines to prevent header injection, then validate
                $replyTo = $replyToRaw ? preg_replace('/[\r\n\t]+/', '', (string) $replyToRaw) : null;
                $subject = preg_replace('/[\r\n]+/', ' ', str_replace('{form_title}', $form->title, $n['email']['subject']));
                $this->sendEmailViaConfig(
                    $form->user_id,
                    $n['email']['config_id'] ?? null,
                    array_filter(array_map('trim', explode(',', $n['email']['to']))),
                    $subject,
                    $body,
                    $replyTo && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : null,
                );
            }
        } catch (\Throwable $e) { logger()->warning('Form email failed: ' . $e->getMessage()); }

        // Auto-responder
        try {
            if (!empty($n['autoresponder']['enabled'])) {
                $to = $submission->data[$n['autoresponder']['email_field']] ?? null;
                if ($to && filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $this->sendEmailViaConfig(
                        $form->user_id,
                        $n['autoresponder']['config_id'] ?? null,
                        [$to],
                        $n['autoresponder']['subject'],
                        $n['autoresponder']['body'],
                        null,
                    );
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

        // SMS — dispatch via the user-selected SMS configuration (Twilio HTTP API
        // implemented; other providers stub-log the call against their saved creds
        // until per-provider transports are added).
        try {
            if (!empty($n['sms']['enabled']) && !empty($n['sms']['to']) && !empty($n['sms']['config_id'])) {
                $msg = str_replace(['{form_title}'], [$form->title], $n['sms']['message']);
                $this->sendSmsViaConfig($form->user_id, (int) $n['sms']['config_id'], $n['sms']['to'], $msg);
            }
        } catch (\Throwable $e) { logger()->warning('Form SMS failed: ' . $e->getMessage()); }

        // WhatsApp — best-effort, one-way ping to the form owner's verified
        // number. Fires for both free and paid submissions (the paid path also
        // routes here via finalizePaidSubmission once the charge clears).
        // WhatsAppAlerts never throws and degrades to preview-mode logging when
        // delivery credentials are absent.
        try {
            if (!empty($n['whatsapp']['enabled'])) {
                $title = $form->title ?: 'your form';
                $summary = $this->whatsappSubmissionSummary($submission);
                $message = "📝 New submission on \"{$title}\".";
                if ($summary !== '') {
                    $message .= "\n" . $summary;
                }
                $message .= "\nCheck your Sayzio dashboard to view the details.";
                \App\Services\WhatsApp\WhatsAppAlerts::send($form->user, $message);
            }
        } catch (\Throwable $e) { logger()->warning('Form WhatsApp alert failed: ' . $e->getMessage()); }
    }

    /**
     * Build a short, safe one-or-two-field preview of a form submission for the
     * WhatsApp alert body — just enough context for the creator to recognise the
     * lead without opening the dashboard. Skips honeypot/internal keys and the
     * structured pricing field, flattens array values, and truncates each value
     * so the message stays a short notification rather than a data dump.
     */
    protected function whatsappSubmissionSummary(FormSubmission $submission): string
    {
        $lines = [];
        foreach (($submission->data ?? []) as $key => $value) {
            if (count($lines) >= 2) break;
            $k = (string) $key;
            if ($k === '' || str_starts_with($k, '_')) continue; // honeypot / internal
            if (is_array($value) && (!empty($value['_pricing']) || !empty($value['_repeatable_group']))) continue; // structured values

            $flat = is_array($value)
                ? implode(', ', array_filter(array_map(fn ($x) => is_scalar($x) ? (string) $x : '', $value)))
                : (string) $value;
            $flat = trim(preg_replace('/\s+/', ' ', $flat));
            if ($flat === '') continue;

            $label = ucfirst(str_replace('_', ' ', $k));
            if (mb_strlen($flat) > 60) $flat = mb_substr($flat, 0, 57) . '…';
            $lines[] = $label . ': ' . $flat;
        }

        return implode("\n", $lines);
    }

    /**
     * Send an email through the user-selected mailer configuration. When no
     * config_id is provided, falls back to the application's default mailer.
     * Supports SMTP-shaped providers (smtp, sendgrid) end-to-end; other
     * providers (mailgun, postmark, ses) require their respective transport
     * packages and will log a warning until those are wired in.
     *
     * @param  array<int, string> $to
     */
    protected function sendEmailViaConfig(int $userId, ?int $configId, array $to, string $subject, string $body, ?string $replyTo): void
    {
        $to = array_values(array_filter($to, fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL)));
        if (empty($to)) return;

        $build = function ($m) use ($to, $subject, $replyTo) {
            $m->to($to)->subject($subject);
            if ($replyTo) $m->replyTo($replyTo);
        };

        if (! $configId) {
            // No config selected → use application default mailer.
            \Illuminate\Support\Facades\Mail::raw($body, $build);
            return;
        }

        $config = \App\Modules\User\Models\IntegrationConfig::where('user_id', $userId)
            ->where('id', $configId)->kind('email')->active()->first();
        if (! $config) {
            logger()->warning("Form email skipped: integration config #{$configId} not found / inactive.");
            return;
        }

        $cred = (array) $config->credentials;
        $meta = (array) $config->meta;
        $mailerKey = 'integ_' . $config->id;

        // Only smtp + sendgrid are wired today (both go through SMTP transport).
        $smtpConfig = match ($config->provider) {
            'smtp' => [
                'transport'  => 'smtp',
                'host'       => $meta['host'] ?? null,
                'port'       => (int) ($meta['port'] ?? 587),
                'encryption' => $meta['encryption'] ?? null,
                'username'   => $meta['username'] ?? null,
                'password'   => $cred['password'] ?? null,
                'timeout'    => 10,
            ],
            'sendgrid' => [
                'transport'  => 'smtp',
                'host'       => 'smtp.sendgrid.net',
                'port'       => 587,
                'encryption' => 'tls',
                'username'   => 'apikey',
                'password'   => $cred['api_key'] ?? null,
                'timeout'    => 10,
            ],
            default => null,
        };

        if (! $smtpConfig) {
            logger()->warning("Form email skipped: provider '{$config->provider}' transport not yet wired.");
            return;
        }

        config(['mail.mailers.' . $mailerKey => $smtpConfig]);
        $fromEmail = $meta['from_email'] ?? config('mail.from.address');
        $fromName  = $meta['from_name']  ?? config('mail.from.name');

        try {
            \Illuminate\Support\Facades\Mail::mailer($mailerKey)->raw($body, function ($m) use ($build, $fromEmail, $fromName) {
                if ($fromEmail) $m->from($fromEmail, $fromName ?? '');
                $build($m);
            });
        } finally {
            // Purge the runtime mailer config so the next request / queue job in
            // a long-running worker does not see leaked credentials. Also forget
            // the resolved mailer instance from the MailManager cache.
            $mailers = (array) config('mail.mailers');
            unset($mailers[$mailerKey]);
            config(['mail.mailers' => $mailers]);
            try { app('mail.manager')->forgetMailers(); } catch (\Throwable $e) { /* older Laravel */ }
        }
    }

    /**
     * Dispatch an SMS through the user-selected SMS configuration. Twilio is
     * implemented end-to-end via its HTTPS Messages API. Other providers are
     * recognised and audit-logged with the proper credential trail until their
     * HTTP transports are added.
     */
    protected function sendSmsViaConfig(int $userId, int $configId, string $to, string $message): void
    {
        $config = \App\Modules\User\Models\IntegrationConfig::where('user_id', $userId)
            ->where('id', $configId)->kind('sms')->active()->first();
        if (! $config) {
            logger()->warning("Form SMS skipped: integration config #{$configId} not found / inactive.");
            return;
        }

        $cred = (array) $config->credentials;
        $meta = (array) $config->meta;

        // Sanitise destination — keep only digits and a leading '+'.
        $toClean = preg_replace('/[^\d+]/', '', $to);
        if ($toClean === '' || strlen($toClean) > 20) {
            logger()->warning("Form SMS skipped: invalid destination number.");
            return;
        }

        switch ($config->provider) {
            case 'twilio':
                $sid   = $meta['account_sid'] ?? null;
                $token = $cred['auth_token']  ?? null;
                $from  = $meta['from_number'] ?? null;
                if (! $sid || ! $token || ! $from) {
                    logger()->warning('Twilio SMS skipped: missing credentials.');
                    return;
                }
                \Illuminate\Support\Facades\Http::withBasicAuth($sid, $token)
                    ->asForm()->timeout(10)
                    ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                        'From' => $from, 'To' => $toClean, 'Body' => $message,
                    ])->throw();
                break;

            default:
                // Provider recognised but transport not yet wired — leave a
                // structured audit entry so the user can see the dispatch
                // attempt against the correct configuration.
                logger()->info('SMS dispatch (' . $config->provider . ') via config #' . $config->id
                    . " to {$toClean}: " . substr($message, 0, 160));
                break;
        }
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
