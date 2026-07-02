<?php

namespace App\Modules\User\Support;

/**
 * Single source of truth for the third-party provider catalogue used by
 * IntegrationConfig. Each provider declares the fields its credential set
 * needs, classified by kind (payment / sms / email).
 *
 * Field schema:
 *   ['key', 'label', 'type' (text|password|email|url|textarea|select),
 *    'required' (bool), 'group' ('credentials'|'meta'), 'options' (for select),
 *    'help' (string), 'placeholder' (string)]
 */
class IntegrationConfigRegistry
{
    public static function kinds(): array
    {
        return [
            'payment' => ['label' => 'Payments', 'icon' => 'fa-credit-card',  'color' => '#10b981',
                          'subtitle' => 'Configure gateways used for paid links, paid forms, and donations.'],
            'sms'     => ['label' => 'SMS',      'icon' => 'fa-sms',           'color' => '#f59e0b',
                          'subtitle' => 'Send notification SMS to your team or transactional SMS to leads.'],
            'email'   => ['label' => 'Email',    'icon' => 'fa-envelope',      'color' => '#6366f1',
                          'subtitle' => 'Outbound mailers used for form notifications, autoresponders & broadcasts.'],
        ];
    }

    public static function providers(string $kind): array
    {
        return self::all()[$kind] ?? [];
    }

    public static function provider(string $kind, string $provider): ?array
    {
        return self::providers($kind)[$provider] ?? null;
    }

    public static function all(): array
    {
        return [
            // ============================ PAYMENT ============================
            'payment' => [
                'stripe' => [
                    'label' => 'Stripe', 'icon' => 'fa-stripe-s', 'color' => '#635bff',
                    'fields' => [
                        ['key' => 'publishable_key', 'label' => 'Publishable key', 'type' => 'text',     'required' => true,  'group' => 'meta',        'placeholder' => 'pk_live_…'],
                        ['key' => 'secret_key',      'label' => 'Secret key',      'type' => 'password', 'required' => true,  'group' => 'credentials', 'placeholder' => 'sk_live_…'],
                        ['key' => 'webhook_secret',  'label' => 'Webhook signing secret', 'type' => 'password', 'required' => false, 'group' => 'credentials', 'placeholder' => 'whsec_…', 'help' => 'Used to verify webhook events.'],
                        ['key' => 'mode',            'label' => 'Mode',            'type' => 'select',   'required' => true,  'group' => 'meta',        'options' => ['live' => 'Live', 'test' => 'Test']],
                    ],
                ],
                'paypal' => [
                    'label' => 'PayPal', 'icon' => 'fa-paypal', 'color' => '#003087',
                    'fields' => [
                        ['key' => 'client_id',     'label' => 'Client ID',     'type' => 'text',     'required' => true, 'group' => 'meta'],
                        ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'mode',          'label' => 'Mode',          'type' => 'select',   'required' => true, 'group' => 'meta', 'options' => ['live' => 'Live', 'sandbox' => 'Sandbox']],
                    ],
                ],
                'razorpay' => [
                    'label' => 'Razorpay', 'icon' => 'fa-rupee-sign', 'color' => '#0c2451',
                    'fields' => [
                        ['key' => 'key_id',         'label' => 'Key ID',         'type' => 'text',     'required' => true, 'group' => 'meta',        'placeholder' => 'rzp_live_…'],
                        ['key' => 'key_secret',     'label' => 'Key secret',     'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'webhook_secret', 'label' => 'Webhook secret', 'type' => 'password', 'required' => false, 'group' => 'credentials'],
                    ],
                ],
                'cashfree' => [
                    'label' => 'Cashfree', 'icon' => 'fa-money-bill-wave', 'color' => '#00d09c',
                    'fields' => [
                        ['key' => 'app_id',     'label' => 'App ID',     'type' => 'text',     'required' => true, 'group' => 'meta'],
                        ['key' => 'secret_key', 'label' => 'Secret key', 'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'mode',       'label' => 'Mode',       'type' => 'select',   'required' => true, 'group' => 'meta', 'options' => ['production' => 'Production', 'sandbox' => 'Sandbox']],
                    ],
                ],
            ],

            // ============================ SMS ============================
            'sms' => [
                'twilio' => [
                    'label' => 'Twilio', 'icon' => 'fa-phone', 'color' => '#f22f46',
                    'fields' => [
                        ['key' => 'account_sid',  'label' => 'Account SID', 'type' => 'text',     'required' => true, 'group' => 'meta',        'placeholder' => 'AC…'],
                        ['key' => 'auth_token',   'label' => 'Auth Token',  'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'from_number',  'label' => 'From number', 'type' => 'text',     'required' => true, 'group' => 'meta',        'placeholder' => '+15551234567'],
                    ],
                ],
                'msg91' => [
                    'label' => 'MSG91', 'icon' => 'fa-mobile-alt', 'color' => '#3b82f6',
                    'fields' => [
                        ['key' => 'auth_key',  'label' => 'Auth key', 'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'sender_id', 'label' => 'Sender ID', 'type' => 'text',    'required' => true, 'group' => 'meta', 'help' => '6-character alpha sender ID.'],
                        ['key' => 'route',     'label' => 'Route',    'type' => 'select',   'required' => false, 'group' => 'meta', 'options' => ['1' => 'Promotional', '4' => 'Transactional']],
                    ],
                ],
                'plivo' => [
                    'label' => 'Plivo', 'icon' => 'fa-comment-dots', 'color' => '#0085ff',
                    'fields' => [
                        ['key' => 'auth_id',     'label' => 'Auth ID',     'type' => 'text',     'required' => true, 'group' => 'meta'],
                        ['key' => 'auth_token',  'label' => 'Auth token',  'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'from_number', 'label' => 'From number', 'type' => 'text',     'required' => true, 'group' => 'meta'],
                    ],
                ],
                'vonage' => [
                    'label' => 'Vonage', 'icon' => 'fa-paper-plane', 'color' => '#871eff',
                    'fields' => [
                        ['key' => 'api_key',     'label' => 'API key',     'type' => 'text',     'required' => true, 'group' => 'meta'],
                        ['key' => 'api_secret',  'label' => 'API secret',  'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'from_name',   'label' => 'From name/number', 'type' => 'text', 'required' => true, 'group' => 'meta'],
                    ],
                ],
            ],

            // ============================ EMAIL ============================
            'email' => [
                'smtp' => [
                    'label' => 'SMTP', 'icon' => 'fa-server', 'color' => '#6b7280',
                    'fields' => [
                        ['key' => 'host',       'label' => 'SMTP host', 'type' => 'text',     'required' => true,  'group' => 'meta', 'placeholder' => 'smtp.example.com'],
                        ['key' => 'port',       'label' => 'Port',      'type' => 'text',     'required' => true,  'group' => 'meta', 'placeholder' => '587'],
                        ['key' => 'encryption', 'label' => 'Encryption','type' => 'select',   'required' => false, 'group' => 'meta', 'options' => ['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', '' => 'None']],
                        ['key' => 'username',   'label' => 'Username',  'type' => 'text',     'required' => true,  'group' => 'meta'],
                        ['key' => 'password',   'label' => 'Password',  'type' => 'password', 'required' => true,  'group' => 'credentials'],
                        ['key' => 'from_email', 'label' => 'From email','type' => 'email',    'required' => true,  'group' => 'meta'],
                        ['key' => 'from_name',  'label' => 'From name', 'type' => 'text',     'required' => false, 'group' => 'meta'],
                    ],
                ],
                'sendgrid' => [
                    'label' => 'SendGrid', 'icon' => 'fa-envelope-open-text', 'color' => '#1a82e2',
                    'fields' => [
                        ['key' => 'api_key',    'label' => 'API key',   'type' => 'password', 'required' => true, 'group' => 'credentials', 'placeholder' => 'SG.…'],
                        ['key' => 'from_email', 'label' => 'From email','type' => 'email',    'required' => true, 'group' => 'meta'],
                        ['key' => 'from_name',  'label' => 'From name', 'type' => 'text',     'required' => false, 'group' => 'meta'],
                    ],
                ],
                'mailgun' => [
                    'label' => 'Mailgun', 'icon' => 'fa-envelope', 'color' => '#f06b66',
                    'fields' => [
                        ['key' => 'api_key',    'label' => 'API key',    'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'domain',     'label' => 'Domain',     'type' => 'text',     'required' => true, 'group' => 'meta', 'placeholder' => 'mg.example.com'],
                        ['key' => 'region',     'label' => 'Region',     'type' => 'select',   'required' => false, 'group' => 'meta', 'options' => ['us' => 'US', 'eu' => 'EU']],
                        ['key' => 'from_email', 'label' => 'From email', 'type' => 'email',    'required' => true, 'group' => 'meta'],
                        ['key' => 'from_name',  'label' => 'From name',  'type' => 'text',     'required' => false, 'group' => 'meta'],
                    ],
                ],
                'postmark' => [
                    'label' => 'Postmark', 'icon' => 'fa-paper-plane', 'color' => '#ffd200',
                    'fields' => [
                        ['key' => 'server_token', 'label' => 'Server token', 'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'from_email',   'label' => 'From email',   'type' => 'email',    'required' => true, 'group' => 'meta'],
                        ['key' => 'from_name',    'label' => 'From name',    'type' => 'text',     'required' => false, 'group' => 'meta'],
                    ],
                ],
                'ses' => [
                    'label' => 'Amazon SES', 'icon' => 'fa-aws', 'color' => '#ff9900',
                    'fields' => [
                        ['key' => 'access_key_id',     'label' => 'Access key ID',     'type' => 'text',     'required' => true, 'group' => 'meta'],
                        ['key' => 'secret_access_key', 'label' => 'Secret access key', 'type' => 'password', 'required' => true, 'group' => 'credentials'],
                        ['key' => 'region',            'label' => 'AWS region',        'type' => 'text',     'required' => true, 'group' => 'meta', 'placeholder' => 'us-east-1'],
                        ['key' => 'from_email',        'label' => 'From email',        'type' => 'email',    'required' => true, 'group' => 'meta'],
                        ['key' => 'from_name',         'label' => 'From name',         'type' => 'text',     'required' => false, 'group' => 'meta'],
                    ],
                ],
            ],
        ];
    }
}
