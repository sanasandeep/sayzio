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
     * Fifty notification templates. Grouped (in UI) by purpose.
     * The original 22 type keys are unchanged (existing campaigns keep
     * working); several labels were remapped to the canonical template
     * catalog names (task #6179), and 28 new types were added.
     */
    public const TYPES = [
        // Informational / social proof
        'recent_activity'      => 'Recent Activity',
        'visitor_count'        => 'Live Counter',
        'conversion_count'     => 'Conversions Counter',
        'trust_badge'          => 'Badge',
        'review'               => 'Reviews',
        'testimonial_quote'    => 'Testimonial Carousel',
        'informational_mini'   => 'Informational Mini',
        'inline_informational' => 'Inline Informational',
        'inline_conversions'   => 'Inline Conversions Counter',
        'new_feature'          => 'New Feature Announcement',
        // Bars & banners
        'announcement_bar'     => 'Informational Bar',
        'informational_bar_mini' => 'Informational Bar Mini',
        'sticky_cta'           => 'Button Bar',
        'cookie_consent'       => 'Cookie Notification',
        'collector_bar'        => 'Collector Bar',
        'coupon_bar'           => 'Coupon Bar',
        'free_shipping_bar'    => 'Free Shipping Bar',
        // Modals & popups
        'exit_offer'           => 'Exit Intent Popup',
        'collector_modal'      => 'Collector Modal',
        'two_step_modal'       => 'Two-Step Collector Modal',
        'button_modal'         => 'Button Modal',
        // Collectors
        'email_signup'         => 'Email Collector',
        'newsletter_signup'    => 'Newsletter Signup',
        'request_collector'    => 'Request Collector',
        'sms_collector'        => 'SMS Collector',
        'webinar_signup'       => 'Webinar Signup',
        'push_opt_in'          => 'Push Opt-in',
        // Feedback
        'feedback_thumbs'      => 'Thumbs Feedback',
        'emoji_feedback'       => 'Emoji Feedback',
        'score_feedback'       => 'Score Feedback (NPS)',
        'text_feedback'        => 'Text Feedback',
        'star_rating'          => 'Star Rating',
        'survey_popup'         => 'Survey Popup',
        // Urgency & ecommerce
        'countdown'            => 'Countdown Collector',
        'flash_sale'           => 'Flash Sale Banner',
        'low_stock'            => 'Urgency / Low Stock Alert',
        'coupon'               => 'Coupon',
        'abandoned_cart'       => 'Abandoned Cart Reminder',
        'loyalty_points'       => 'Loyalty Points',
        'holiday_seasonal'     => 'Holiday / Seasonal',
        // Media
        'video_popup'          => 'Video',
        'audio_widget'         => 'Audio',
        'image_widget'         => 'Image',
        // Engagement
        'share_buttons'        => 'Social Share',
        'engagement_links'     => 'Engagement Links',
        'referral_program'     => 'Referral Program',
        'app_download'         => 'App Download Banner',
        // Contact
        'whatsapp_chat'        => 'WhatsApp Chat',
        'contact_us'           => 'Contact Us',
        // Free-form
        'custom_html'          => 'Custom HTML',
    ];

    /**
     * Legacy template keys that are no longer part of the canonical
     * 50-template catalog but must keep working for existing campaigns.
     * Hidden from pickers; still normalized, rendered and editable.
     */
    public const LEGACY_TYPES = [
        'social_followers' => 'Social Followers',
        'click_to_call'    => 'Click-to-Call',
        'price_drop'       => 'Price Drop',
    ];

    public const TYPE_DESCRIPTIONS = [
        'recent_activity'      => 'Show real-time signups, purchases or downloads',
        'visitor_count'        => 'Live viewer count to build social proof',
        'conversion_count'     => 'Total conversions in the last X days',
        'trust_badge'          => 'Star rating + review count badge',
        'informational_mini'   => 'Tiny informational note card',
        'review'               => 'Rotating customer review with stars',
        'testimonial_quote'    => 'Rotating quotes from happy customers',
        'inline_informational' => 'Short informational note shown in-page',
        'inline_conversions'   => 'Compact conversions counter shown in-page',
        'new_feature'          => 'Announce a new feature with a NEW badge',
        'announcement_bar'     => 'Slim full-width informational bar',
        'informational_bar_mini' => 'Extra-slim compact informational bar',
        'sticky_cta'           => 'Persistent full-width bar with a CTA button',
        'cookie_consent'       => 'GDPR-style cookie notification bar',
        'collector_bar'        => 'Full-width bar with an email input',
        'coupon_bar'           => 'Full-width bar with a copyable coupon code',
        'free_shipping_bar'    => 'Free-shipping threshold bar',
        'exit_offer'           => 'Stop visitors from leaving with an offer',
        'collector_modal'      => 'Centered modal with an email form',
        'two_step_modal'       => 'Teaser button first, email form second',
        'button_modal'         => 'Centered modal with a big CTA button',
        'email_signup'         => 'Corner card email capture',
        'newsletter_signup'    => 'Newsletter subscribe card with consent note',
        'request_collector'    => 'Collect name, email and a request message',
        'sms_collector'        => 'Collect phone numbers for SMS updates',
        'webinar_signup'       => 'Event/webinar registration form',
        'push_opt_in'          => 'Collect notification opt-in consent leads',
        'feedback_thumbs'      => 'Quick thumbs up / down feedback',
        'emoji_feedback'       => 'One-tap emoji reaction feedback',
        'score_feedback'       => '0–10 NPS style score feedback',
        'text_feedback'        => 'Open text box for visitor feedback',
        'star_rating'          => '1–5 star rating collector',
        'survey_popup'         => 'One-question multiple choice survey',
        'countdown'            => 'Ticking countdown to a date',
        'flash_sale'           => 'Bold sale banner with discount %',
        'low_stock'            => 'Only N left in stock — urgency nudge',
        'coupon'               => 'Card with a copyable discount code',
        'abandoned_cart'       => 'Nudge visitors back to their cart',
        'loyalty_points'       => 'Promote your rewards / points program',
        'holiday_seasonal'     => 'Festive seasonal promo card',
        'video_popup'          => 'Click to open a video in lightbox',
        'audio_widget'         => 'Inline audio player card',
        'image_widget'         => 'Clickable image card',
        'share_buttons'        => 'Row of social share buttons',
        'engagement_links'     => 'Small list of links to key pages',
        'referral_program'     => 'Share-your-link referral prompt',
        'app_download'         => 'App Store / Google Play banner',
        'whatsapp_chat'        => 'Floating WhatsApp chat bubble',
        'contact_us'           => 'Mini contact form (name, email, message)',
        'custom_html'          => 'Paste your own HTML / embed code',
    ];

    /** Font-Awesome icon per template, used by pickers (web + mobile). */
    public const TYPE_ICONS = [
        'recent_activity'      => 'fa-bolt',
        'visitor_count'        => 'fa-eye',
        'conversion_count'     => 'fa-chart-line',
        'trust_badge'          => 'fa-shield-halved',
        'informational_mini'   => 'fa-circle-dot',
        'review'               => 'fa-star-half-stroke',
        'testimonial_quote'    => 'fa-quote-left',
        'inline_informational' => 'fa-circle-info',
        'inline_conversions'   => 'fa-arrow-trend-up',
        'new_feature'          => 'fa-wand-magic-sparkles',
        'announcement_bar'     => 'fa-bullhorn',
        'informational_bar_mini' => 'fa-minus',
        'sticky_cta'           => 'fa-hand-pointer',
        'cookie_consent'       => 'fa-cookie-bite',
        'collector_bar'        => 'fa-envelope-open-text',
        'coupon_bar'           => 'fa-ticket',
        'free_shipping_bar'    => 'fa-truck-fast',
        'exit_offer'           => 'fa-door-open',
        'collector_modal'      => 'fa-window-restore',
        'two_step_modal'       => 'fa-forward-step',
        'button_modal'         => 'fa-square-arrow-up-right',
        'email_signup'         => 'fa-envelope',
        'newsletter_signup'    => 'fa-newspaper',
        'request_collector'    => 'fa-inbox',
        'sms_collector'        => 'fa-comment-sms',
        'webinar_signup'       => 'fa-chalkboard-user',
        'push_opt_in'          => 'fa-bell',
        'feedback_thumbs'      => 'fa-thumbs-up',
        'emoji_feedback'       => 'fa-face-smile',
        'score_feedback'       => 'fa-gauge-high',
        'text_feedback'        => 'fa-pen-to-square',
        'star_rating'          => 'fa-star',
        'survey_popup'         => 'fa-square-poll-vertical',
        'countdown'            => 'fa-hourglass-half',
        'flash_sale'           => 'fa-fire',
        'low_stock'            => 'fa-box-open',
        'coupon'               => 'fa-percent',
        'abandoned_cart'       => 'fa-cart-shopping',
        'loyalty_points'       => 'fa-gem',
        'holiday_seasonal'     => 'fa-gifts',
        'video_popup'          => 'fa-circle-play',
        'audio_widget'         => 'fa-volume-high',
        'image_widget'         => 'fa-image',
        'share_buttons'        => 'fa-share-nodes',
        'engagement_links'     => 'fa-link',
        'referral_program'     => 'fa-user-plus',
        'app_download'         => 'fa-mobile-screen',
        'whatsapp_chat'        => 'fa-comments',
        'contact_us'           => 'fa-address-card',
        'custom_html'          => 'fa-code',
    ];

    public const TYPE_GROUPS = [
        'Informational'       => ['recent_activity', 'visitor_count', 'conversion_count', 'trust_badge', 'review', 'testimonial_quote', 'informational_mini', 'inline_informational', 'inline_conversions', 'new_feature'],
        'Bars'                => ['announcement_bar', 'informational_bar_mini', 'sticky_cta', 'cookie_consent', 'collector_bar', 'coupon_bar', 'free_shipping_bar'],
        'Modals'              => ['exit_offer', 'collector_modal', 'two_step_modal', 'button_modal'],
        'Collectors'          => ['email_signup', 'newsletter_signup', 'request_collector', 'sms_collector', 'webinar_signup', 'push_opt_in'],
        'Feedback'            => ['feedback_thumbs', 'emoji_feedback', 'score_feedback', 'text_feedback', 'star_rating', 'survey_popup'],
        'Urgency & Ecommerce' => ['countdown', 'flash_sale', 'low_stock', 'coupon', 'abandoned_cart', 'loyalty_points', 'holiday_seasonal'],
        'Media'               => ['video_popup', 'audio_widget', 'image_widget'],
        'Engagement'          => ['share_buttons', 'engagement_links', 'referral_program', 'app_download'],
        'Contact'             => ['whatsapp_chat', 'contact_us'],
        'Custom'              => ['custom_html'],
    ];

    /**
     * Types whose embed collects visitor data through POST /sp/{uuid}/submit.
     * These land in the shared social_proof_submissions store.
     */
    public const SUBMISSION_TYPES = [
        'collector_bar', 'collector_modal', 'two_step_modal',
        'request_collector', 'sms_collector', 'webinar_signup', 'push_opt_in',
        'emoji_feedback', 'score_feedback', 'text_feedback', 'star_rating',
        'survey_popup', 'feedback_thumbs', 'contact_us',
        'email_signup', 'newsletter_signup', 'exit_offer',
    ];

    /**
     * Submission types that carry an email we should also mirror into the
     * owner's Subscribers list (same as the legacy subscribe endpoint).
     */
    public const EMAIL_CAPTURE_TYPES = [
        'email_signup', 'newsletter_signup', 'exit_offer', 'collector_bar',
        'collector_modal', 'two_step_modal', 'webinar_signup',
        'request_collector', 'contact_us',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $sp) {
            if (empty($sp->uuid)) $sp->uuid = (string) Str::uuid();
        });

        // Bust the biolink-editor "Buzz" dropdown cache on any write so a
        // newly created / edited / deleted notification shows up immediately
        // in the editor. See BiolinkBlockController::editor().
        static::saved(fn (self $sp) => static::forgetEditorBuzzCache($sp));
        static::deleted(fn (self $sp) => static::forgetEditorBuzzCache($sp));
    }

    protected static function forgetEditorBuzzCache(self $sp): void
    {
        $uid = $sp->user_id;
        if (!$uid) return;
        $ws = $sp->workspace_id ?? 'none';
        \Illuminate\Support\Facades\Cache::forget("editor:buzz:{$uid}:{$ws}");
        \Illuminate\Support\Facades\Cache::forget("editor:buzz:{$uid}:none");
    }

    public function user()  { return $this->belongsTo(User::class); }
    public function items() { return $this->hasMany(SocialProofItem::class)->orderBy('sort_order'); } // legacy
    public function events(){ return $this->hasMany(SocialProofEvent::class); }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]
            ?? self::LEGACY_TYPES[$this->type]
            ?? ucfirst(str_replace('_', ' ', $this->type ?? ''));
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
            // --- 50-template catalog additions (task #6179) ---
            'inline_informational' => ['text' => 'Did you know? We plant a tree for every order.', 'selector' => '', 'icon' => '💡'],
            'inline_conversions'   => ['text' => '{count} people signed up this week', 'count' => 132, 'selector' => ''],
            'new_feature'          => ['badge' => 'NEW', 'title' => 'Dark mode is here!', 'body' => 'Flip the switch in your settings.', 'cta' => 'Try it now', 'cta_url' => '#'],
            'collector_bar'        => ['text' => 'Get 10% off — join our list', 'placeholder' => 'Your email', 'cta' => 'Subscribe', 'success_text' => 'Thanks! Check your inbox.', 'placement' => 'top'],
            'coupon_bar'           => ['text' => 'Spring sale — use code', 'code' => 'SPRING20', 'placement' => 'top'],
            'free_shipping_bar'    => ['text' => 'Free shipping on orders over {threshold}!', 'threshold' => '$50', 'cta_label' => 'Shop now', 'cta_url' => '', 'placement' => 'top'],
            'collector_modal'      => ['title' => 'Join 10,000+ subscribers', 'body' => 'Get our best tips, once a week.', 'placeholder' => 'Your email', 'cta' => 'Subscribe', 'success_text' => 'You\'re in — welcome!'],
            'two_step_modal'       => ['teaser' => 'Want 10% off your first order?', 'teaser_cta' => 'Yes, show me', 'title' => 'Unlock your discount', 'body' => 'Enter your email and we\'ll send the code.', 'placeholder' => 'Your email', 'cta' => 'Get my code', 'success_text' => 'Code sent — check your inbox!'],
            'button_modal'         => ['title' => 'Ready to level up?', 'body' => 'Start your free 14-day trial today.', 'cta' => 'Start free trial', 'cta_url' => '#'],
            'request_collector'    => ['title' => 'Request a callback', 'body' => 'Tell us what you need and we\'ll get back to you.', 'cta' => 'Send request', 'success_text' => 'Request received — thank you!'],
            'sms_collector'        => ['title' => 'Get SMS updates', 'body' => 'Be first to hear about drops and deals.', 'placeholder' => '+1 555 000 1234', 'cta' => 'Sign me up', 'success_text' => 'You\'re on the list!'],
            'webinar_signup'       => ['title' => 'Free live webinar', 'body' => 'How to grow your audience in 30 days.', 'event_at' => now()->addDays(7)->toIso8601String(), 'cta' => 'Save my seat', 'success_text' => 'Seat saved — see you there!'],
            'push_opt_in'          => ['title' => 'Never miss an update', 'body' => 'Enable notifications to hear it first.', 'allow_label' => 'Enable', 'deny_label' => 'Not now', 'success_text' => 'You\'re subscribed to updates!'],
            'emoji_feedback'       => ['question' => 'How was your experience?', 'emojis' => ['😠', '🙁', '😐', '🙂', '😍'], 'success_text' => 'Thanks for the feedback!'],
            'score_feedback'       => ['question' => 'How likely are you to recommend us?', 'low_label' => 'Not likely', 'high_label' => 'Very likely', 'success_text' => 'Thanks for the feedback!'],
            'text_feedback'        => ['question' => 'What can we do better?', 'placeholder' => 'Type your feedback…', 'cta' => 'Send', 'success_text' => 'Thanks — we read every note!'],
            'star_rating'          => ['question' => 'Rate your experience', 'success_text' => 'Thanks for rating us!'],
            'survey_popup'         => ['question' => 'What brought you here today?', 'options' => ['Just browsing', 'Comparing options', 'Ready to buy'], 'success_text' => 'Thanks for sharing!'],
            'coupon'               => ['title' => 'Here\'s a treat 🎁', 'body' => 'Use this code at checkout.', 'code' => 'WELCOME10', 'cta_label' => 'Shop now', 'cta_url' => ''],
            'abandoned_cart'       => ['title' => 'You left something behind', 'body' => 'Your cart is saved — complete your order now.', 'cta' => 'Return to cart', 'cta_url' => '#'],
            'loyalty_points'       => ['title' => 'Earn points on every order', 'body' => 'Join our rewards program and save on your next purchase.', 'cta' => 'Join rewards', 'cta_url' => '#'],
            'holiday_seasonal'     => ['title' => 'Holiday Sale 🎄', 'body' => 'Up to 40% off — this week only.', 'cta' => 'Shop the sale', 'cta_url' => '#', 'emoji' => '🎄'],
            'audio_widget'         => ['title' => 'Listen to our latest episode', 'audio_url' => ''],
            'image_widget'         => ['image_url' => '', 'caption' => 'Our new collection', 'link_url' => ''],
            'engagement_links'     => ['title' => 'Popular right now', 'links' => [['label' => 'Pricing', 'url' => '#'], ['label' => 'Blog', 'url' => '#']]],
            'referral_program'     => ['title' => 'Give $10, get $10', 'body' => 'Share your link with friends.', 'referral_url' => '', 'cta' => 'Copy my link'],
            'app_download'         => ['title' => 'Get our app', 'body' => 'Faster checkout, exclusive deals.', 'appstore_url' => '', 'play_url' => ''],
            'contact_us'           => ['title' => 'Contact us', 'body' => 'We usually reply within a day.', 'cta' => 'Send message', 'success_text' => 'Message sent — thank you!'],
            'newsletter_signup'    => ['title' => 'Subscribe to our newsletter', 'body' => 'One useful email a week. No spam, ever.', 'placeholder' => 'Your email', 'cta' => 'Subscribe', 'consent_note' => 'You can unsubscribe at any time.', 'success_text' => 'Welcome aboard — check your inbox!'],
            'informational_mini'   => ['text' => 'We ship worldwide 🌍', 'icon' => 'ℹ️'],
            'informational_bar_mini' => ['text' => '30-day money-back guarantee', 'placement' => 'top'],
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
        if (!isset(self::TYPES[$type]) && !isset(self::LEGACY_TYPES[$type])) $type = 'recent_activity';
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
            'name'            => trim((string)($n['name'] ?? self::TYPES[$type] ?? self::LEGACY_TYPES[$type])) ?: (self::TYPES[$type] ?? self::LEGACY_TYPES[$type]),
            'settings'        => $settings,
            'design_override' => $designOverride,
            'triggers'        => $triggers,
            'triggers_logic'  => in_array($n['triggers_logic'] ?? 'or', ['or', 'and'], true) ? ($n['triggers_logic'] ?? 'or') : 'or',
            'is_active'       => (bool)($n['is_active'] ?? true),
            'sort_order'      => (int)($n['sort_order'] ?? 0),
        ];
    }
}
