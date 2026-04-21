<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Form extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'project_id', 'slug', 'title', 'description',
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

    public function submissions()
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function getPublicUrl(): string
    {
        return rtrim(config('app.url'), '/') . '/f/' . $this->slug;
    }

    public function getEmbedScript(): string
    {
        return '<script src="' . rtrim(config('app.url'), '/') . '/f/' . $this->slug . '/embed.js" async></script>'
             . '<div data-1inme-form="' . $this->slug . '"></div>';
    }

    public function getIframeEmbed(int $h = 600): string
    {
        $src = rtrim(config('app.url'), '/') . '/f/' . $this->slug . '/iframe';
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
            'accent' => '#8b5cf6',
            'background' => '#f5f5f8',     // page background
            'card_color' => '#ffffff',     // form card surface (separate from page bg)
            'card_image' => null,          // optional background image for the form card
            'card_image_mode' => 'cover',  // cover | contain | tile
            'card_image_opacity' => 100,   // 0-100 (rendered as overlay alpha)
            'text' => '#0f172a',
            'border_radius' => 12,
            'button_label' => 'Submit',
            'button_style' => 'gradient', // gradient | solid | outline
            'layout' => 'stacked',        // stacked | inline
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
        ];
    }

    public static function defaultNotifications(): array
    {
        return [
            'email' => ['enabled' => false, 'to' => '', 'subject' => 'New form submission', 'reply_to_field' => 'email', 'config_id' => null],
            'autoresponder' => ['enabled' => false, 'subject' => 'Thanks for your submission', 'body' => 'We received your submission and will get back to you soon.', 'email_field' => 'email', 'config_id' => null],
            'sms' => ['enabled' => false, 'to' => '', 'message' => 'New form submission on {form_title}', 'config_id' => null],
            'webhooks' => [], // [{url, method, headers, enabled}]
        ];
    }

    public static function fieldTypes(): array
    {
        return [
            'text'      => ['label' => 'Short Text',  'icon' => 'fa-font'],
            'textarea'  => ['label' => 'Long Text',   'icon' => 'fa-align-left'],
            'email'     => ['label' => 'Email',       'icon' => 'fa-envelope'],
            'phone'     => ['label' => 'Phone',       'icon' => 'fa-phone'],
            'number'    => ['label' => 'Number',      'icon' => 'fa-hashtag'],
            'url'       => ['label' => 'Website URL', 'icon' => 'fa-link'],
            'date'      => ['label' => 'Date',        'icon' => 'fa-calendar'],
            'time'      => ['label' => 'Time',        'icon' => 'fa-clock'],
            'select'    => ['label' => 'Dropdown',    'icon' => 'fa-caret-square-down'],
            'radio'     => ['label' => 'Multiple Choice', 'icon' => 'fa-dot-circle'],
            'checkbox'  => ['label' => 'Checkboxes',  'icon' => 'fa-check-square'],
            'rating'    => ['label' => 'Star Rating', 'icon' => 'fa-star'],
            'scale'     => ['label' => 'Linear Scale','icon' => 'fa-sliders-h'],
            'file'      => ['label' => 'File Upload', 'icon' => 'fa-paperclip'],
            'signature' => ['label' => 'Signature',   'icon' => 'fa-signature'],
            'consent'   => ['label' => 'Consent / Terms', 'icon' => 'fa-shield-alt'],
            'hidden'    => ['label' => 'Hidden Field','icon' => 'fa-eye-slash'],
            'heading'   => ['label' => 'Section Heading', 'icon' => 'fa-heading'],
            'paragraph' => ['label' => 'Paragraph Text','icon' => 'fa-paragraph'],
            'divider'   => ['label' => 'Divider',     'icon' => 'fa-minus'],
            'page_break'=> ['label' => 'Page Break (Multi-step)', 'icon' => 'fa-file-export'],
            'section'   => ['label' => 'Section / Group',  'icon' => 'fa-layer-group'],
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $form) {
            if (empty($form->slug)) {
                $form->slug = static::uniqueSlug($form->title ?: 'form');
            }
        });
    }

    public static function uniqueSlug(string $base): string
    {
        $base = Str::slug(Str::limit($base, 50, '')) ?: Str::random(8);
        $slug = $base;
        $i = 1;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }
        return $slug;
    }
}
