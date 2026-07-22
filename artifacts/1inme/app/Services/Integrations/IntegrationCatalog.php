<?php

namespace App\Services\Integrations;

use App\Services\AI\AiEngineSettings;
use App\Services\Billing\GatewayManager;
use App\Modules\User\Services\SocialFollowers\SocialOAuthService;
use Illuminate\Support\Str;

/**
 * Single source of truth describing every third-party integration the
 * platform talks to, grouped by category, for the admin Integrations hub.
 *
 * Each integration entry exposes a human label, description, icon, a
 * {key,label,tone} status descriptor (configured / env fallback / not
 * configured / preview) and a route to its editor. Some editors are
 * dedicated pages that already existed (AI Engine, Payment Gateways,
 * Email/SMTP, Social OAuth, WhatsApp & alerts) and some are the new
 * env-only editors rendered inside this hub (reviews keys, Google
 * Contacts OAuth, S3 storage).
 *
 * This catalog only *reads* status — it never mutates settings — so it is
 * safe to call on every hub render.
 */
class IntegrationCatalog
{
    /**
     * @return array<int,array{
     *   key:string,label:string,icon:string,
     *   items:array<int,array{key:string,label:string,desc:string,icon:string,status:array,route:?string,external:bool}>
     * }>
     */
    public static function categories(): array
    {
        return [
            [
                'key'   => 'ai',
                'label' => 'AI & Voice',
                'icon'  => 'fas fa-brain',
                'items' => [
                    [
                        'key'      => 'ai-engine',
                        'label'    => 'AI Engine (OpenAI)',
                        'desc'     => 'OpenAI key powering chat, embeddings and every coin-charged AI feature, plus Whisper (STT) and ElevenLabs (TTS) for the voice assistant.',
                        'icon'     => 'fas fa-brain',
                        'status'   => self::aiEngineStatus(),
                        'route'    => route('admin.ai-engine.edit'),
                        'external' => true,
                    ],
                ],
            ],
            [
                'key'   => 'messaging',
                'label' => 'Messaging & Alerts',
                'icon'  => 'fas fa-comment-dots',
                'items' => [
                    [
                        'key'      => 'whatsapp',
                        'label'    => 'WhatsApp Cloud API',
                        'desc'     => 'Delivers login & verification OTPs over WhatsApp. Preview mode (logged, not sent) when unset.',
                        'icon'     => 'fab fa-whatsapp',
                        'status'   => IntegrationKeySettings::whatsappStatus(),
                        'route'    => route('admin.api-keys.index'),
                        'external' => true,
                    ],
                    [
                        'key'      => 'alerts',
                        'label'    => 'Internal alerts (Slack / Discord)',
                        'desc'     => 'Posts system & team alerts (downtime, broadcasts, payment failures) to Slack and/or Discord webhooks.',
                        'icon'     => 'fas fa-bell',
                        'status'   => IntegrationKeySettings::alertsStatus(),
                        'route'    => route('admin.api-keys.index'),
                        'external' => true,
                    ],
                    [
                        'key'      => 'mail',
                        'label'    => 'Email / SMTP',
                        'desc'     => 'Outbound mail transport for notifications, newsletters and email OTPs.',
                        'icon'     => 'fas fa-envelope',
                        'status'   => MailSettings::status(),
                        'route'    => route('admin.mail-settings.index'),
                        'external' => true,
                    ],
                ],
            ],
            [
                'key'   => 'payments',
                'label' => 'Payments',
                'icon'  => 'fas fa-credit-card',
                'items' => [
                    [
                        'key'      => 'gateways',
                        'label'    => 'Payment gateways',
                        'desc'     => 'Razorpay, Stripe, PayPal, Cashfree and offline — credentials, mode and enablement.',
                        'icon'     => 'fas fa-credit-card',
                        'status'   => self::gatewaysStatus(),
                        'route'    => route('admin.payment-gateways.index'),
                        'external' => true,
                    ],
                ],
            ],
            [
                'key'   => 'social',
                'label' => 'Social OAuth',
                'icon'  => 'fas fa-share-nodes',
                'items' => [
                    [
                        'key'      => 'social-oauth',
                        'label'    => 'Social login & follow OAuth',
                        'desc'     => 'Facebook, Instagram, LinkedIn, X, Pinterest and TikTok one-click connect for creators.',
                        'icon'     => 'fas fa-share-nodes',
                        'status'   => self::socialStatus(),
                        'route'    => route('admin.social-oauth.index'),
                        'external' => true,
                    ],
                ],
            ],
            [
                'key'   => 'reviews',
                'label' => 'Reviews',
                'icon'  => 'fas fa-star',
                'items' => [
                    [
                        'key'      => 'google-places',
                        'label'    => 'Google Places (reviews)',
                        'desc'     => 'Imports Google Business Profile reviews. Absent key ⇒ preview mode.',
                        'icon'     => 'fab fa-google',
                        'status'   => PlatformServiceSettings::googlePlacesStatus(),
                        'route'    => route('admin.integrations.google-places.edit'),
                        'external' => false,
                    ],
                    [
                        'key'      => 'trustpilot',
                        'label'    => 'Trustpilot (reviews)',
                        'desc'     => 'Imports Trustpilot Business Unit reviews. Absent key ⇒ preview mode.',
                        'icon'     => 'fas fa-star',
                        'status'   => PlatformServiceSettings::trustpilotStatus(),
                        'route'    => route('admin.integrations.trustpilot.edit'),
                        'external' => false,
                    ],
                ],
            ],
            [
                'key'   => 'contacts',
                'label' => 'Contacts',
                'icon'  => 'fas fa-address-book',
                'items' => [
                    [
                        'key'      => 'google-contacts',
                        'label'    => 'Google Contacts OAuth',
                        'desc'     => 'OAuth client powering two-way Google Contacts sync (People API).',
                        'icon'     => 'fab fa-google',
                        'status'   => PlatformServiceSettings::googleContactsStatus(),
                        'route'    => route('admin.integrations.google-contacts.edit'),
                        'external' => false,
                    ],
                ],
            ],
            [
                'key'   => 'crm-analytics',
                'label' => 'CRM & Analytics',
                'icon'  => 'fas fa-plug-circle-bolt',
                'items' => self::connectedAppItems(),
            ],
            [
                'key'   => 'storage',
                'label' => 'Storage',
                'icon'  => 'fas fa-database',
                'items' => [
                    [
                        'key'      => 's3',
                        'label'    => 'S3 / CloudFront storage',
                        'desc'     => 'Durable user-content storage for uploads and public assets. Always S3-backed — cannot be switched to local disk.',
                        'icon'     => 'fab fa-aws',
                        'status'   => PlatformServiceSettings::s3Status(),
                        'route'    => route('admin.integrations.storage.edit'),
                        'external' => false,
                    ],
                ],
            ],
            [
                'key'   => 'biolink-defaults',
                'label' => 'Biolink Block Defaults',
                'icon'  => 'fas fa-layer-group',
                'items' => [
                    [
                        'key'      => 'block-defaults',
                        'label'    => 'Block First-Paint Defaults',
                        'desc'     => 'Configure sample text, placeholder images/media URLs, and default styling for each biolink block type. Only affects newly-created blocks.',
                        'icon'     => 'fas fa-layer-group',
                        'status'   => self::blockDefaultsStatus(),
                        'route'    => route('admin.block-defaults.index'),
                        'external' => true,
                    ],
                ],
            ],
            [
                'key'   => 'system',
                'label' => 'System',
                'icon'  => 'fas fa-server',
                'items' => [
                    [
                        'key'      => 'github-token',
                        'label'    => 'GitHub Token',
                        'desc'     => 'Personal access token shared by the post-publish repo push sync and the Zio Browser release refresh (raises the GitHub API rate limit to 5,000 req/hr).',
                        'icon'     => 'fab fa-github',
                        'status'   => PlatformServiceSettings::githubStatus(),
                        'route'    => route('admin.integrations.github.edit'),
                        'external' => false,
                    ],
                    [
                        'key'      => 'system-update',
                        'label'    => 'System Update (GitHub → EC2)',
                        'desc'     => 'Check for new commits and trigger the GitHub Actions deploy to EC2 with one click. Not applicable on Replit (managed by the platform there).',
                        'icon'     => 'fas fa-circle-up',
                        'status'   => self::systemUpdateStatus(),
                        'route'    => route('admin.system-update.show'),
                        'external' => false,
                    ],
                ],
            ],
        ];
    }

    /** Flat count of integrations whose status tone is green. */
    public static function summary(): array
    {
        $total = 0;
        $configured = 0;
        $attention = 0;
        foreach (self::categories() as $cat) {
            foreach ($cat['items'] as $item) {
                $total++;
                $tone = $item['status']['tone'] ?? 'slate';
                if ($tone === 'green') $configured++;
                elseif ($tone === 'amber') $attention++;
            }
        }
        return ['total' => $total, 'configured' => $configured, 'attention' => $attention];
    }

    // ─────────────────────────────────────────────────────────────
    // Per-integration status helpers for systems without their own
    // status() descriptor.
    // ─────────────────────────────────────────────────────────────

    /**
     * Connected Apps hub items, derived from the data-driven registry so a
     * new provider auto-appears here. CRMs route to the generic OAuth editor;
     * Google Analytics routes to its own enable switch.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function connectedAppItems(): array
    {
        $items = [];
        foreach (\App\Modules\User\Support\ConnectedApps\ConnectedAppRegistry::all() as $key => $meta) {
            if ($key === 'google_analytics') {
                $items[] = [
                    'key'      => 'google-analytics',
                    'label'    => $meta['label'],
                    'desc'     => 'Server-side GA4 Measurement Protocol forwarding of click events. Creators bring their own property; toggle availability here.',
                    'icon'     => $meta['icon'],
                    'status'   => PlatformServiceSettings::googleAnalyticsStatus(),
                    'route'    => route('admin.integrations.google-analytics.edit'),
                    'external' => false,
                ];
                continue;
            }
            $items[] = [
                'key'      => $key,
                'label'    => $meta['label'],
                'desc'     => 'OAuth client credentials for two-way ' . $meta['label'] . ' sync. Absent ⇒ creators see "coming soon".',
                'icon'     => $meta['icon'],
                'status'   => PlatformServiceSettings::connectedAppStatus($key),
                'route'    => route('admin.integrations.connected-app.edit', $key),
                'external' => false,
            ];
        }
        return $items;
    }

    private static function aiEngineStatus(): array
    {
        $hasKey  = AiEngineSettings::openAiKey() !== null;
        $enabled = AiEngineSettings::isEnabled();
        if (!$hasKey) {
            return ['key' => 'preview', 'label' => 'No key (preview)', 'tone' => 'slate'];
        }
        if (!$enabled) {
            return ['key' => 'disabled', 'label' => 'Key set, engine off', 'tone' => 'amber'];
        }
        return ['key' => 'configured', 'label' => 'Configured', 'tone' => 'green'];
    }

    private static function gatewaysStatus(): array
    {
        try {
            $enabled = app(GatewayManager::class)->enabledAdapters();
            $count = count($enabled);
        } catch (\Throwable $e) {
            $count = 0;
        }
        if ($count <= 0) {
            return ['key' => 'preview', 'label' => 'None enabled', 'tone' => 'slate'];
        }
        return ['key' => 'configured', 'label' => $count . ' enabled', 'tone' => 'green'];
    }

    private static function socialStatus(): array
    {
        try {
            $oauth = app(SocialOAuthService::class);
            $total = 0;
            $configured = 0;
            foreach (array_keys(SocialOAuthService::PROVIDERS) as $key) {
                $total++;
                if ($oauth->isConfigured($key)) $configured++;
            }
        } catch (\Throwable $e) {
            return ['key' => 'preview', 'label' => 'Not configured', 'tone' => 'slate'];
        }
        if ($configured <= 0) {
            return ['key' => 'preview', 'label' => 'None configured', 'tone' => 'slate'];
        }
        return ['key' => 'configured', 'label' => $configured . '/' . $total . ' configured', 'tone' => 'green'];
    }

    private static function blockDefaultsStatus(): array
    {
        try {
            $overrides = \App\Modules\User\Support\BlockDefaults::getAdminOverrides();
            $count = count($overrides);
        } catch (\Throwable $e) {
            $count = 0;
        }
        if ($count === 0) {
            return ['key' => 'default', 'label' => 'System defaults', 'tone' => 'slate'];
        }
        return ['key' => 'customised', 'label' => $count . ' ' . Str::plural('type', $count) . ' customised', 'tone' => 'green'];
    }

    private static function systemUpdateStatus(): array
    {
        if (\App\Services\Integrations\SystemUpdateService::isReplit()) {
            return ['key' => 'managed', 'label' => 'Managed by Replit', 'tone' => 'slate'];
        }
        if (!\App\Services\Integrations\SystemUpdateService::isConfigured()) {
            return ['key' => 'not_configured', 'label' => 'Not configured', 'tone' => 'amber'];
        }
        try {
            $status = \App\Services\Integrations\SystemUpdateService::cachedStatus();
            if (!empty($status['available'])) {
                $behind = $status['commits_behind'] ? $status['commits_behind'] . ' commits behind' : 'Update available';
                return ['key' => 'update_available', 'label' => $behind, 'tone' => 'amber'];
            }
            return ['key' => 'up_to_date', 'label' => 'Up to date', 'tone' => 'green'];
        } catch (\Throwable $e) {
            return ['key' => 'unknown', 'label' => 'Unknown', 'tone' => 'slate'];
        }
    }
}
