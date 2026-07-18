<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Form extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'project_id', 'domain_id', 'slug', 'title', 'description',
        'fields', 'design', 'settings', 'notifications',
        'is_active', 'is_multi_step',
    ];

    protected function casts(): array
    {
        return [
            'fields' => 'array',
            'design' => 'array',
            'settings' => 'array',
            'notifications' => 'array',
            'is_active' => 'boolean',
            'is_multi_step' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class);
    }

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    /**
     * Base URL for this form's public/embed links. Uses the associated
     * global/custom domain's host when one is set, still verified+active, and
     * still available to the form's owner under their current plan; otherwise
     * falls back to the platform APP_URL. This means a creator who downgrades
     * (losing the custom_domains feature) gracefully reverts to platform links
     * at render time without us having to mutate the stored domain_id. The
     * /f/{slug} routes are host-agnostic, so a form resolves on whichever host
     * this returns.
     */
    public function baseUrl(): string
    {
        $domain = $this->domain;
        if (
            $domain
            && $domain->is_active
            && $domain->is_verified
            && $this->user
            && Domain::availableTo($this->user)->where('id', $domain->id)->exists()
        ) {
            return 'https://' . $domain->domain;
        }
        return rtrim(config('app.url'), '/');
    }

    public function getPublicUrl(): string
    {
        return $this->baseUrl() . '/f/' . $this->slug;
    }

    public function getEmbedScript(): string
    {
        return '<script src="' . $this->baseUrl() . '/f/' . $this->slug . '/embed.js" async></script>'
             . '<div data-1inme-form="' . $this->slug . '"></div>';
    }

    public function getIframeEmbed(int $h = 600): string
    {
        $src = $this->baseUrl() . '/f/' . $this->slug . '/iframe';
        return '<iframe src="' . $src . '" style="width:100%;height:' . $h . 'px;border:0;" loading="lazy"></iframe>';
    }

    /** Default field schema used when nothing has been built yet. */
    public static function defaultFields(): array
    {
        return [
            ['id' => 'name',    'type' => 'text',  'label' => 'Your Name', 'placeholder' => 'Jane Doe', 'required' => true],
            ['id' => 'email',   'type' => 'email', 'label' => 'Email Address', 'placeholder' => 'you@example.com', 'required' => true],
            ['id' => 'message', 'type' => 'textarea', 'label' => 'Message', 'placeholder' => 'How can we help?', 'required' => true, 'rows' => 4],
        ];
    }

    public static function defaultDesign(): array
    {
        return [
            'theme' => 'light',
            'accent' => '#3d6bff', // brand accent default (blue) — retired purple must not creep back
            'background' => '#f5f5f8',     // page background
            'card_color' => '#ffffff',     // form card surface (separate from page bg)
            'card_image' => null,          // optional background image for the form card
            'card_image_mode' => 'cover',  // cover | contain | tile
            'card_image_opacity' => 100,   // 0-100 (rendered as overlay alpha)
            'text' => '#0f172a',
            'border_radius' => 12,
            'button_label' => 'Submit',
            'button_style' => 'gradient', // gradient | solid | outline
            'layout' => 'stacked',        // stacked | inline | oneq
            'font' => 'Plus Jakarta Sans',
            'show_branding' => true,
        ];
    }

    public static function defaultSettings(): array
    {
        return [
            'success_message' => 'Thanks! Your submission has been received.',
            'success_action' => 'message', // message | redirect
            'success_redirect' => null,
            'captcha' => false,
            'allow_multiple' => true,
            'require_login' => false,
            'close_after' => null, // ISO date or null
            'submission_limit' => null,
            // Paid forms (Task #2319). Opt-in charge to submit, collected
            // through the OWNER's connected payment gateway (0% platform fee).
            // Stored as a structured map so per-field / variable pricing can
            // layer on later (mode='per_field' with a fields[] breakdown)
            // without a schema change — fixed mode just uses amount_cents.
            'payment' => [
                'enabled'      => false,
                'mode'         => 'fixed',  // fixed | per_field (future)
                'amount_cents' => 0,
                'currency'     => 'USD',
                'label'        => null,     // optional override for the pay button
            ],
        ];
    }

    /** Payment settings merged over the defaults. */
    public function paymentConfig(): array
    {
        $defaults = static::defaultSettings()['payment'];
        $settings = $this->settings ?? [];
        return array_merge($defaults, (array) ($settings['payment'] ?? []));
    }

    /**
     * Field types that can carry a price (Task #2321): add-ons (consent),
     * tiers (select/radio/checkbox per-option) and quantity (number ×
     * per-unit). Anything else is informational and never priced.
     */
    public const PRICED_FIELD_TYPES = ['number', 'select', 'radio', 'checkbox', 'consent'];

    /**
     * Whether this form is *capable* of charging to submit, gated by the
     * owner's plan (a downgrade silently reverts the form to free at submit
     * time without us mutating stored settings). A form can charge when:
     *   - Fixed mode (Task #2319): the Payments toggle is on with a positive
     *     amount.
     *   - Per-field mode (Task #2321): the Payments toggle is on and a priced
     *     field carries a price (or there's a positive base fee).
     *   - Pricing / Package fields (Task #2333): the form has one or more
     *     pricing fields, whose charge is computed per submission from the
     *     submitter's selection.
     * In every variable case the actual charge is only known per submission
     * (a zero computed total ⇒ free submit), so isPaid() just reports the form
     * is capable of charging.
     */
    public function isPaid(): bool
    {
        if (! (bool) ($this->user?->getPlanFeature('paid_forms', false))) {
            return false;
        }
        if (!empty($this->paymentConfig()['enabled'])) {
            $hasSource = $this->paymentMode() === 'per_field'
                ? ($this->paymentAmountCents() > 0 || $this->hasPricedFields())
                : $this->paymentAmountCents() > 0;
            if ($hasSource) {
                return true;
            }
        }
        return $this->hasPricingFields();
    }

    /** Pricing mode: 'fixed' (one price) or 'per_field' (priced fields). */
    public function paymentMode(): string
    {
        $mode = (string) ($this->paymentConfig()['mode'] ?? 'fixed');
        return $mode === 'per_field' ? 'per_field' : 'fixed';
    }

    /**
     * Base charge in cents. In fixed mode this is the whole price; in
     * per_field mode it's an optional base fee added on top of the selected
     * priced fields. Use computeAmountCents() for the per-submission total.
     */
    public function paymentAmountCents(): int
    {
        return max(0, (int) ($this->paymentConfig()['amount_cents'] ?? 0));
    }

    /** Pricing / Package fields defined on this form (selectable pricing). */
    public function pricingFields(): array
    {
        return array_values(array_filter(
            $this->fields ?? [],
            fn ($f) => is_array($f) && ($f['type'] ?? null) === 'pricing'
        ));
    }

    public function hasPricingFields(): bool
    {
        return count($this->pricingFields()) > 0;
    }

    /** Convert a dollar (or numeric string) price into whole cents. */
    public static function priceToCents($price): int
    {
        return max(0, (int) round(((float) $price) * 100));
    }

    /**
     * Compute the charge amount + a per-field line-item breakdown from a
     * submitter's selection. Returns:
     *   ['amount_cents' => int, 'currency' => string, 'by_field' => [id => [...]]].
     * Each by_field entry carries the chosen option + ticked addons as
     * structured data so the owner can see exactly what produced the charge.
     * Unknown / out-of-range indices are ignored (defensive — never trust
     * the submitter's raw indices).
     */
    public function computeSelectionBreakdown(\Illuminate\Http\Request $request): array
    {
        $currency = $this->paymentCurrency();
        $byField  = [];
        $total    = 0;

        foreach ($this->pricingFields() as $field) {
            $id = $field['id'] ?? null;
            if (!$id) continue;

            $opts   = array_values($field['price_options'] ?? []);
            $addons = array_values($field['addons'] ?? []);

            $option = null;
            $chosen = $request->input($id);
            if ($chosen !== null && $chosen !== '' && ctype_digit((string) $chosen) && isset($opts[(int) $chosen])) {
                $o = $opts[(int) $chosen];
                $option = [
                    'label'       => (string) ($o['label'] ?? ''),
                    'price_cents' => self::priceToCents($o['price'] ?? 0),
                ];
            }

            $selectedAddons = [];
            foreach ((array) $request->input($id . '_addons', []) as $ai) {
                if (ctype_digit((string) $ai) && isset($addons[(int) $ai])) {
                    $a = $addons[(int) $ai];
                    $selectedAddons[] = [
                        'label'       => (string) ($a['label'] ?? ''),
                        'price_cents' => self::priceToCents($a['price'] ?? 0),
                    ];
                }
            }

            if ($option === null && empty($selectedAddons)) {
                continue; // nothing chosen for this pricing field
            }

            $fieldTotal = ($option['price_cents'] ?? 0)
                + array_sum(array_column($selectedAddons, 'price_cents'));
            $total += $fieldTotal;

            $byField[$id] = [
                '_pricing'    => true,
                'label'       => (string) ($field['label'] ?? 'Pricing'),
                'option'      => $option,
                'addons'      => $selectedAddons,
                'currency'    => $currency,
                'total_cents' => $fieldTotal,
            ];
        }

        return ['amount_cents' => $total, 'currency' => $currency, 'by_field' => $byField];
    }

    public function paymentCurrency(): string
    {
        $cur = strtoupper((string) ($this->paymentConfig()['currency'] ?? 'USD'));
        return $cur !== '' ? $cur : 'USD';
    }

    /** True when at least one field carries a price (per_field mode). */
    public function hasPricedFields(): bool
    {
        foreach ($this->fields ?? [] as $field) {
            if (!in_array($field['type'] ?? '', self::PRICED_FIELD_TYPES, true)) continue;
            if ((int) ($field['price_cents'] ?? 0) > 0) return true;
            foreach ((array) ($field['option_prices'] ?? []) as $cents) {
                if ((int) $cents > 0) return true;
            }
        }
        return false;
    }

    /**
     * The per-submission total in cents. Fixed mode returns the flat price;
     * per_field mode sums the selected priced fields on top of the optional
     * base fee. Always recompute server-side from the submitted $data — never
     * trust a client-sent total.
     */
    public function computeAmountCents(array $data): int
    {
        if ($this->paymentMode() !== 'per_field') {
            return $this->paymentAmountCents();
        }

        $total = $this->paymentAmountCents(); // optional base fee
        foreach ($this->priceLineItems($data) as $item) {
            // priceLineItems already includes the base fee as its first row
            // when present, so skip it here to avoid double-counting.
            if (($item['field'] ?? null) === '__base__') continue;
            $total += (int) ($item['amount_cents'] ?? 0);
        }
        return max(0, $total);
    }

    /**
     * Itemised price breakdown for a submission: [{field, label, detail,
     * amount_cents}]. Returns the flat-price row for fixed mode, or the base
     * fee + each selected priced field for per_field mode. Stored alongside
     * the submission's amount_cents so the owner sees what was charged.
     */
    public function priceLineItems(array $data): array
    {
        if ($this->paymentMode() !== 'per_field') {
            $amount = $this->paymentAmountCents();
            if ($amount <= 0) return [];
            $label = (string) ($this->paymentConfig()['label'] ?? '') ?: 'Form payment';
            return [[
                'field'        => '__base__',
                'label'        => $label,
                'detail'       => null,
                'amount_cents' => $amount,
            ]];
        }

        $items = [];

        $base = $this->paymentAmountCents();
        if ($base > 0) {
            $items[] = [
                'field'        => '__base__',
                'label'        => (string) ($this->paymentConfig()['label'] ?? '') ?: 'Base fee',
                'detail'       => null,
                'amount_cents' => $base,
            ];
        }

        foreach ($this->fields ?? [] as $field) {
            $type = $field['type'] ?? '';
            $id   = (string) ($field['id'] ?? '');
            if ($id === '' || !in_array($type, self::PRICED_FIELD_TYPES, true)) continue;

            $label   = (string) ($field['label'] ?? $id);
            $value   = $data[$id] ?? null;
            $unit    = max(0, (int) ($field['price_cents'] ?? 0));
            $options = (array) ($field['option_prices'] ?? []);

            if ($type === 'number') {
                $qty = (int) round((float) $value);
                if ($unit > 0 && $qty > 0) {
                    $items[] = [
                        'field'        => $id,
                        'label'        => $label,
                        'detail'       => $qty . ' × ' . number_format($unit / 100, 2),
                        'amount_cents' => $unit * $qty,
                    ];
                }
            } elseif ($type === 'consent') {
                if ($unit > 0 && filter_var($value, FILTER_VALIDATE_BOOL)) {
                    $items[] = [
                        'field'        => $id,
                        'label'        => $label,
                        'detail'       => null,
                        'amount_cents' => $unit,
                    ];
                }
            } elseif ($type === 'checkbox') {
                foreach ((array) $value as $opt) {
                    $opt   = (string) $opt;
                    $price = (int) ($options[$opt] ?? 0);
                    if ($price > 0) {
                        $items[] = [
                            'field'        => $id,
                            'label'        => $label,
                            'detail'       => $opt,
                            'amount_cents' => $price,
                        ];
                    }
                }
            } else { // select | radio
                $opt   = (string) $value;
                $price = (int) ($options[$opt] ?? 0);
                if ($opt !== '' && $price > 0) {
                    $items[] = [
                        'field'        => $id,
                        'label'        => $label,
                        'detail'       => $opt,
                        'amount_cents' => $price,
                    ];
                }
            }
        }

        return $items;
    }

    public static function defaultNotifications(): array
    {
        return [
            'email' => ['enabled' => false, 'to' => '', 'subject' => 'New form submission', 'reply_to_field' => 'email', 'config_id' => null],
            'autoresponder' => ['enabled' => false, 'subject' => 'Thanks for your submission', 'body' => 'We received your submission and will get back to you soon.', 'email_field' => 'email', 'config_id' => null],
            'sms' => ['enabled' => false, 'to' => '', 'message' => 'New form submission on {form_title}', 'config_id' => null],
            'whatsapp' => ['enabled' => false], // one-way alert to the form owner's verified WhatsApp number
            'webhooks' => [], // [{url, method, headers, enabled}]
        ];
    }

    public static function fieldTypes(): array
    {
        return [
            'text'         => ['label' => 'Short Text',          'icon' => 'fa-font',            'group' => 'basic'],
            'textarea'     => ['label' => 'Long Text',           'icon' => 'fa-align-left',      'group' => 'basic'],
            'email'        => ['label' => 'Email',               'icon' => 'fa-envelope',        'group' => 'basic'],
            'phone'        => ['label' => 'Phone',               'icon' => 'fa-phone',           'group' => 'basic'],
            'number'       => ['label' => 'Number',              'icon' => 'fa-hashtag',         'group' => 'basic'],
            'url'          => ['label' => 'Website URL',         'icon' => 'fa-link',            'group' => 'basic'],
            'date'         => ['label' => 'Date',                'icon' => 'fa-calendar',        'group' => 'basic'],
            'time'         => ['label' => 'Time',                'icon' => 'fa-clock',           'group' => 'basic'],
            'select'       => ['label' => 'Dropdown',            'icon' => 'fa-caret-square-down','group'=> 'choice'],
            'radio'        => ['label' => 'Multiple Choice',     'icon' => 'fa-dot-circle',      'group' => 'choice'],
            'checkbox'     => ['label' => 'Checkboxes',          'icon' => 'fa-check-square',    'group' => 'choice'],
            'rating'       => ['label' => 'Star Rating',         'icon' => 'fa-star',            'group' => 'choice'],
            'scale'        => ['label' => 'Linear Scale',        'icon' => 'fa-sliders-h',       'group' => 'choice'],
            'pricing'      => ['label' => 'Pricing / Package',   'icon' => 'fa-tags',            'group' => 'advanced'],
            'file'         => ['label' => 'File Upload',         'icon' => 'fa-paperclip',       'group' => 'advanced'],
            'signature'    => ['label' => 'Signature',           'icon' => 'fa-signature',       'group' => 'advanced'],
            'consent'      => ['label' => 'Consent / Terms',     'icon' => 'fa-shield-alt',      'group' => 'advanced'],
            'hidden'       => ['label' => 'Hidden Field',        'icon' => 'fa-eye-slash',       'group' => 'advanced'],
            'full_name'    => ['label' => 'Full Name',           'icon' => 'fa-id-card',         'group' => 'personal'],
            'address'      => ['label' => 'Address',             'icon' => 'fa-map-marker-alt',  'group' => 'personal'],
            'country'      => ['label' => 'Country / Region',   'icon' => 'fa-globe',           'group' => 'personal'],
            'currency'     => ['label' => 'Currency / Money',   'icon' => 'fa-dollar-sign',     'group' => 'personal'],
            'yes_no'       => ['label' => 'Yes / No',            'icon' => 'fa-toggle-on',       'group' => 'choice'],
            'image_choice' => ['label' => 'Image Choice',        'icon' => 'fa-images',          'group' => 'choice'],
            'ranking'      => ['label' => 'Ranking',             'icon' => 'fa-list-ol',         'group' => 'choice'],
            'slider'       => ['label' => 'Range Slider',        'icon' => 'fa-grip-lines-vertical','group'=> 'choice'],
            'time_range'   => ['label' => 'Time Range',          'icon' => 'fa-business-time',   'group' => 'advanced'],
            'date_range'   => ['label' => 'Date Range',          'icon' => 'fa-calendar-week',   'group' => 'advanced'],
            'heading'      => ['label' => 'Section Heading',     'icon' => 'fa-heading',         'group' => 'layout'],
            'paragraph'    => ['label' => 'Paragraph Text',      'icon' => 'fa-paragraph',       'group' => 'layout'],
            'divider'      => ['label' => 'Divider',             'icon' => 'fa-minus',           'group' => 'layout'],
            'page_break'   => ['label' => 'Page Break (Multi-step)', 'icon' => 'fa-file-export', 'group' => 'layout'],
            'section'      => ['label' => 'Section / Group',     'icon' => 'fa-layer-group',     'group' => 'layout'],
        ];
    }

    /**
     * Returns the normalized captcha configuration for this form.
     * Handles backward-compat where settings['captcha'] was stored as `false`.
     */
    public function captchaConfig(): array
    {
        $defaults = [
            'provider'        => 'honeypot',
            'site_key'        => null,
            'secret_key'      => null,
            'score_threshold' => 0.5,
        ];
        $cap = ($this->settings ?? [])['captcha'] ?? null;
        if (!is_array($cap)) return $defaults;
        return array_merge($defaults, $cap);
    }

    protected static function booted(): void
    {
        static::creating(function (self $form) {
            if (empty($form->slug)) {
                $form->slug = static::uniqueSlug($form->title ?: 'form');
            }
        });

        // Bust the biolink-editor forms dropdown cache so a newly created /
        // edited / deleted form shows up immediately when the owner opens
        // the editor. See BiolinkBlockController::editor().
        static::saved(fn (self $form) => static::forgetEditorFormsCache($form));
        static::deleted(fn (self $form) => static::forgetEditorFormsCache($form));
    }

    protected static function forgetEditorFormsCache(self $form): void
    {
        $uid = $form->user_id;
        if (!$uid) return;
        $ws = $form->workspace_id ?? 'none';
        \Illuminate\Support\Facades\Cache::forget("editor:forms:{$uid}:{$ws}");
        \Illuminate\Support\Facades\Cache::forget("editor:forms:{$uid}:none");
    }

    public static function uniqueSlug(string $base): string
    {
        $base = Str::slug(Str::limit($base, 50, '')) ?: Str::random(8);
        return \App\Support\UniqueSuffix::resolve(static::query(), $base);
    }
}
