<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SocialProof extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'uuid', 'name', 'type', 'is_active',
        'settings', 'design', 'targeting', 'schedule', 'notifications',
        'directory_badge_notification_id',
        'impressions', 'clicks', 'conversions',
    ];

    /**
     * Notification types eligible to render as a creator's directory badge.
     * Kept in the model so both the editor UI and the directory query agree.
     */
    public const DIRECTORY_BADGE_TYPES = [
        'recent_activity', 'visitor_count', 'conversion_count', 'social_followers', 'trust_badge',
    ];

    protected $casts = [
        'is_active'     => 'boolean',
        'settings'      => 'array',
        'design'        => 'array',
        'targeting'     => 'array',
        'schedule'      => 'array',
        'notifications' => 'array',
        'impressions'   => 'integer',
        'clicks'        => 'integer',
        'conversions'   => 'integer',
    ];

    /**
     * Twenty-two notification types. Grouped (in UI) by purpose.
     */
    public const TYPES = [
        // Social proof
        'recent_activity'    => 'Recent Activity',
        'visitor_count'      => 'Live Visitor Counter',
        'conversion_count'   => 'Conversion Counter',
        'social_followers'   => 'Social Followers',
        'trust_badge'        => 'Trust Badge / Rating',
        'review'             => 'Customer Review',
        'testimonial_quote'  => 'Big Testimonial Quote',
        // Capture / conversion
        'email_signup'       => 'Email Signup Prompt',
        'exit_offer'         => 'Exit-Intent Offer',
        'feedback_thumbs'    => 'Feedback Thumbs Up/Down',
        // Urgency
        'countdown'          => 'Countdown Timer',
        'flash_sale'         => 'Flash Sale Banner',
        'low_stock'          => 'Low-Stock Warning',
        'price_drop'         => 'Price Drop',
        // Bars & banners
        'announcement_bar'   => 'Announcement Bar',
        'sticky_cta'         => 'Sticky CTA Bar',
        'cookie_consent'     => 'Cookie Consent',
        // Contact
        'whatsapp_chat'      => 'WhatsApp Chat Bubble',
        'click_to_call'      => 'Click-to-Call Bubble',
        // Engagement
        'video_popup'        => 'Video Popup',
        'share_buttons'      => 'Social Share Buttons',
        // Free-form
        'custom_html'        => 'Custom HTML',
    ];

    public const TYPE_DESCRIPTIONS = [
        'recent_activity'   => 'Show real-time signups, purchases or downloads',
        'visitor_count'     => 'Live viewer count to build social proof',
        'conversion_count'  => 'Total conversions in the last X days',
        'social_followers'  => 'Followers / subscribers count badge',
        'trust_badge'       => 'Star rating + review count badge',
        'review'            => 'Rotating customer review with stars',
        'testimonial_quote' => 'Big quote from a happy customer',
        'email_signup'      => 'Inline email capture popup',
        'exit_offer'        => 'Stop visitors from leaving with an offer',
        'feedback_thumbs'   => 'Quick thumbs up / down feedback',
        'countdown'         => 'Ticking countdown to a date',
        'flash_sale'        => 'Bold sale banner with discount %',
        'low_stock'         => 'Only N left in stock — urgency nudge',
        'price_drop'        => 'Strikethrough old price + new price',
        'announcement_bar'  => 'Slim full-width announcement at top/bottom',
        'sticky_cta'        => 'Persistent call-to-action button',
        'cookie_consent'    => 'GDPR-style cookie consent bar',
        'whatsapp_chat'     => 'Floating WhatsApp chat bubble',
        'click_to_call'     => 'Floating click-to-call phone bubble',
        'video_popup'       => 'Click to open a video in lightbox',
        'share_buttons'     => 'Row of social share buttons',
        'custom_html'       => 'Paste your own HTML / embed code',
    ];

    public const TYPE_GROUPS = [
        'Social proof' => ['recent_activity', 'visitor_count', 'conversion_count', 'social_followers', 'trust_badge', 'review', 'testimonial_quote'],
        'Capture'      => ['email_signup', 'exit_offer', 'feedback_thumbs'],
        'Urgency'      => ['countdown', 'flash_sale', 'low_stock', 'price_drop'],
        'Bars'         => ['announcement_bar', 'sticky_cta', 'cookie_consent'],
        'Contact'      => ['whatsapp_chat', 'click_to_call'],
        'Engagement'   => ['video_popup', 'share_buttons'],
        'Custom'       => ['custom_html'],
    ];

    protected static function booted(): void
    {
        static::creating(function (self $sp) {
            if (empty($sp->uuid)) $sp->uuid = (string) Str::uuid();
        });
    }

    public function user()  { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(SocialProofItem::class)->orderBy('sort_order'); } // legacy
    public function events(){ return $this->hasMany(SocialProofEvent::class); }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst(str_replace('_', ' ', $this->type ?? ''));
    }

    public function ctr(): float
    {
        return $this->impressions > 0 ? round(($this->clicks / $this->impressions) * 100, 2) : 0.0;
    }

    public function notificationCount(): int
    {
        return is_array($this->notifications) ? count($this->notifications) : 0;
    }

    public static function defaultDesign(): array
    {
        return [
            'position'   => 'bottom-left',
            'theme'      => 'light',
            'accent'     => '#7c3aed',
            'rounded'    => 'lg',
            'shadow'     => true,
            'animation'  => 'slide-up',
            'show_close' => true,
        ];
    }

    public static function defaultTargeting(): array
    {
        return [
            'pages_include'   => [],
            'pages_exclude'   => [],
            'devices'         => ['desktop', 'tablet', 'mobile'],
            'delay'           => 3,
            'interval'        => 8,
            'duration'        => 5,
            'max_per_session' => 0,
        ];
    }

    public static function defaultTriggers(): array
    {
        return [['kind' => 'on_load', 'params' => (object)[]]];
    }

    /**
     * Per-type starter settings used when adding a new notification.
     */
    public static function defaultSettingsFor(string $type): array
    {
        return match ($type) {
            'recent_activity'   => ['title_template' => '{name} from {location}', 'body_template' => '{action}', 'pool' => []],
            'visitor_count'     => ['text' => '{count} people are viewing this page', 'min' => 12, 'max' => 48],
            'conversion_count'  => ['text' => '{count} people purchased in the last 24 hours', 'count' => 47],
            'social_followers'  => ['network' => 'instagram', 'handle' => '@yourbrand', 'count' => 1234, 'url' => ''],
            'trust_badge'       => ['rating' => 4.9, 'reviews' => 2345, 'label' => 'on Trustpilot'],
            'review'            => ['rotate' => true, 'items' => [['author' => 'Sarah K.', 'text' => 'Absolutely love this product!', 'rating' => 5]]],
            'testimonial_quote' => ['quote' => 'This is the best tool I have ever used. Highly recommended!', 'author' => 'Jane Doe', 'role' => 'CEO at Acme'],
            'email_signup'      => ['title' => 'Join our newsletter', 'body' => 'Weekly tips delivered to your inbox.', 'cta' => 'Subscribe'],
            'exit_offer'        => ['title' => 'Wait! Don\'t leave yet', 'body' => 'Get 10% off your first order.', 'cta' => 'Claim 10% off', 'cta_url' => '#'],
            'feedback_thumbs'   => ['question' => 'Was this page helpful?'],
            'countdown'         => ['title' => 'Limited offer ends in', 'ends_at' => now()->addDays(3)->toIso8601String(), 'expired_text' => 'Offer expired'],
            'flash_sale'        => ['title' => 'Flash sale!', 'discount' => '20% OFF', 'ends_at' => now()->addHours(6)->toIso8601String(), 'cta' => 'Shop now', 'cta_url' => '#'],
            'low_stock'         => ['text' => 'Only {count} left in stock — order soon!', 'count' => 3],
            'price_drop'        => ['text' => 'Price dropped from {old} to {new}!', 'old_price' => '$99', 'new_price' => '$49'],
            'announcement_bar'  => ['text' => 'Free shipping on orders over $50!', 'cta_label' => 'Shop now', 'cta_url' => '#', 'placement' => 'top'],
            'sticky_cta'        => ['text' => 'Ready to get started?', 'cta_label' => 'Sign up free', 'cta_url' => '#'],
            'cookie_consent'    => ['title' => 'We use cookies', 'body' => 'To improve your experience on our site.', 'accept_label' => 'Accept all', 'reject_label' => 'Reject', 'policy_url' => ''],
            'whatsapp_chat'     => ['phone' => '+1234567890', 'message' => 'Hi! I have a question.', 'label' => 'Chat with us'],
            'click_to_call'     => ['phone' => '+1234567890', 'label' => 'Call us'],
            'video_popup'       => ['video_url' => 'https://www.youtube.com/embed/dQw4w9WgXcQ', 'thumbnail_text' => 'Watch our 60s demo'],
            'share_buttons'     => ['networks' => ['twitter', 'facebook', 'linkedin', 'whatsapp'], 'url' => '', 'text' => 'Check this out:'],
            'custom_html'       => ['html' => '<div style="padding:12px;font:14px sans-serif;">Hello world!</div>'],
            default             => [],
        };
    }

    /**
     * Build a fresh notification skeleton with id + defaults.
     */
    public static function newNotification(string $type, ?string $name = null): array
    {
        return [
            'id'              => (string) Str::uuid(),
            'type'            => $type,
            'name'            => $name ?: (self::TYPES[$type] ?? 'Notification'),
            'settings'        => self::defaultSettingsFor($type),
            'design_override' => (object)[],
            'triggers'        => self::defaultTriggers(),
            'triggers_logic'  => 'or',
            'is_active'       => true,
            'sort_order'      => 0,
        ];
    }

    /**
     * Normalize one notification array — fills missing defaults & coerces types.
     */
    public static function normalizeNotification(array $n): array
    {
        $type = $n['type'] ?? 'recent_activity';
        if (!isset(self::TYPES[$type])) $type = 'recent_activity';
        $settings = is_array($n['settings'] ?? null) ? $n['settings'] : [];
        $settings = array_merge(self::defaultSettingsFor($type), $settings);

        $triggers = $n['triggers'] ?? self::defaultTriggers();
        if (!is_array($triggers)) $triggers = self::defaultTriggers();
        $triggers = array_values(array_filter(array_map(function ($t) {
            if (!is_array($t)) return null;
            $kind = $t['kind'] ?? '';
            if (!in_array($kind, ['on_load', 'after_delay', 'on_scroll', 'on_exit_intent', 'on_click', 'after_idle', 'url_contains'], true)) return null;
            $params = is_array($t['params'] ?? null) ? $t['params'] : [];
            return ['kind' => $kind, 'params' => $params];
        }, $triggers)));
        if (empty($triggers)) $triggers = self::defaultTriggers();

        $designOverride = $n['design_override'] ?? [];
        if (!is_array($designOverride)) $designOverride = [];

        return [
            'id'              => (string)($n['id'] ?? Str::uuid()),
            'type'            => $type,
            'name'            => trim((string)($n['name'] ?? self::TYPES[$type])) ?: self::TYPES[$type],
            'settings'        => $settings,
            'design_override' => $designOverride,
            'triggers'        => $triggers,
            'triggers_logic'  => in_array($n['triggers_logic'] ?? 'or', ['or', 'and'], true) ? $n['triggers_logic'] : 'or',
            'is_active'       => (bool)($n['is_active'] ?? true),
            'sort_order'      => (int)($n['sort_order'] ?? 0),
        ];
    }
}
