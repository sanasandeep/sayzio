<?php

namespace App\Modules\User\Support;

/**
 * Single, organized source of truth for every "How to get this" inline
 * help guide shown next to a field where a creator must paste in a value
 * obtained from an external service (a tracking pixel ID, a DNS record,
 * an OAuth connect flow, a payment/SMS/email provider credential, a
 * webhook URL, a payout processor account, or a developer API key).
 *
 * This class is display-only: it feeds the <x-how-to-get-this> component
 * and never touches validation, persistence, or field names. Add a new
 * guide by adding one entry below — nothing else needs to change.
 */
class ExternalValueGuides
{
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function all(): array
    {
        return array_merge(
            self::pixelGuides(),
            self::domainGuides(),
            self::connectedAppGuides(),
            self::integrationGuides(),
            self::formGuides(),
            self::payoutGuides(),
            self::apiKeyGuides(),
        );
    }

    // ============================ PIXELS ============================

    protected static function pixelGuides(): array
    {
        return [
            'pixel.facebook' => [
                'title' => 'Where do I find my Facebook Pixel ID?',
                'steps' => [
                    'Open <strong>Events Manager</strong> in Meta Business Suite.',
                    'Select your pixel from the left-hand list (or create one under <em>Connect Data Sources → Web</em>).',
                    'Click the pixel name, then open the <strong>Settings</strong> tab.',
                    'Copy the number under <strong>Pixel ID</strong> (a plain numeric ID, e.g. <code>1234567890123456</code>).',
                ],
                'docs_url' => 'https://www.facebook.com/business/help/952192354843755',
                'docs_label' => 'Meta Pixel documentation',
            ],
            'pixel.google_analytics' => [
                'title' => 'Where do I find my Google Analytics Measurement ID?',
                'steps' => [
                    'Sign in to <strong>Google Analytics</strong> and open the property you want to track.',
                    'Click <strong>Admin</strong> (gear icon) → under the property column, choose <strong>Data Streams</strong>.',
                    'Select your web stream (create one if none exists).',
                    'Copy the <strong>Measurement ID</strong> shown at the top right — it looks like <code>G-XXXXXXXXXX</code>.',
                ],
                'docs_url' => 'https://support.google.com/analytics/answer/9539598',
                'docs_label' => 'Google Analytics documentation',
            ],
            'pixel.google_tag_manager' => [
                'title' => 'Where do I find my Google Tag Manager container ID?',
                'steps' => [
                    'Sign in to <strong>tagmanager.google.com</strong>.',
                    'Select (or create) the container for your website.',
                    'The <strong>Container ID</strong> is shown next to the container name and in the top bar — it looks like <code>GTM-XXXXXXX</code>.',
                ],
                'docs_url' => 'https://support.google.com/tagmanager/answer/6103696',
                'docs_label' => 'Tag Manager documentation',
            ],
            'pixel.linkedin' => [
                'title' => 'Where do I find my LinkedIn Insight Tag ID?',
                'steps' => [
                    'Go to <strong>LinkedIn Campaign Manager</strong> → <em>Account Assets → Insight Tag</em>.',
                    'If you don\'t have one yet, click <strong>Create Insight Tag</strong>.',
                    'Open the tag\'s details — the numeric <strong>Partner ID</strong> (also called Insight Tag ID) is shown in the install instructions.',
                ],
                'docs_url' => 'https://www.linkedin.com/help/lms/answer/a427660',
                'docs_label' => 'LinkedIn Insight Tag documentation',
            ],
            'pixel.twitter' => [
                'title' => 'Where do I find my X (Twitter) Pixel ID?',
                'steps' => [
                    'Sign in to <strong>ads.x.com</strong> (Twitter/X Ads).',
                    'Open <em>Tools → Events Manager</em> (previously "Conversion Tracking").',
                    'Select your website event/pixel, or create a new one.',
                    'Copy the <strong>Pixel ID</strong> shown in the tag setup panel.',
                ],
                'docs_url' => 'https://business.x.com/en/help/campaign-measurement-and-analytics/conversion-tracking-for-websites.html',
                'docs_label' => 'X Ads pixel documentation',
            ],
            'pixel.pinterest' => [
                'title' => 'Where do I find my Pinterest Tag ID?',
                'steps' => [
                    'Sign in to <strong>Pinterest Ads Manager</strong> → <em>Ads → Conversions</em>.',
                    'Open the <strong>Pinterest tag</strong> page (create one if you haven\'t already).',
                    'Copy the numeric <strong>Tag ID</strong> shown under the tag name.',
                ],
                'docs_url' => 'https://help.pinterest.com/en/business/article/install-the-pinterest-tag',
                'docs_label' => 'Pinterest tag documentation',
            ],
            'pixel.tiktok' => [
                'title' => 'Where do I find my TikTok Pixel ID?',
                'steps' => [
                    'Sign in to <strong>TikTok Ads Manager</strong> → <em>Assets → Events</em>.',
                    'Select <strong>Web Events</strong>, then choose or create a pixel.',
                    'Copy the <strong>Pixel ID</strong> shown at the top of the pixel\'s setup page.',
                ],
                'docs_url' => 'https://ads.tiktok.com/help/article/get-started-pixel',
                'docs_label' => 'TikTok Pixel documentation',
            ],
            'pixel.snapchat' => [
                'title' => 'Where do I find my Snap Pixel ID?',
                'steps' => [
                    'Sign in to <strong>Snapchat Ads Manager</strong> → <em>Events Manager</em>.',
                    'Select your Snap Pixel, or create a new one.',
                    'Copy the <strong>Pixel ID</strong> shown in the pixel setup instructions.',
                ],
                'docs_url' => 'https://businesshelp.snapchat.com/s/article/snap-pixel-website-install',
                'docs_label' => 'Snap Pixel documentation',
            ],
            'pixel.quora' => [
                'title' => 'Where do I find my Quora Pixel ID?',
                'steps' => [
                    'Sign in to <strong>Quora Ads Manager</strong> → <em>Tools → Pixel</em>.',
                    'Create a pixel if you don\'t already have one.',
                    'Copy the <strong>Pixel ID</strong> shown in the installation code snippet.',
                ],
                'docs_url' => 'https://www.quora.com/business/help',
                'docs_label' => 'Quora Ads help',
            ],
            'pixel.custom' => [
                'title' => 'What goes in a custom tracking script?',
                'steps' => [
                    'Get the full tracking snippet from your analytics or ad platform\'s "install pixel" / "add tracking code" page.',
                    'Paste the entire <code>&lt;script&gt;</code> snippet exactly as provided — do not edit it.',
                    'Only use scripts from services you trust: this runs on every visit to your link or page.',
                ],
            ],
        ];
    }

    // ============================ DOMAINS ============================

    protected static function domainGuides(): array
    {
        return [
            'domain.cname' => [
                'title' => 'How do I point my domain here?',
                'steps' => [
                    'Add the domain below first — Sayzio will show you the exact DNS record to create once it\'s added.',
                    'Log in to the account where you registered the domain (e.g. GoDaddy, Namecheap, Cloudflare, Google Domains) or your DNS host if different.',
                    'Open the <strong>DNS management</strong> / <strong>DNS records</strong> page for the domain.',
                    'Add a new <strong>CNAME record</strong>: set the <em>Host/Name</em> to your subdomain (e.g. <code>links</code>) and the <em>Value/Target</em> to the host Sayzio shows you.',
                    'Save the record and wait for DNS to propagate (usually a few minutes, sometimes up to 24-48 hours), then click <strong>Verify</strong> on this page.',
                ],
            ],
        ];
    }

    // ============================ CONNECTED APPS ============================

    protected static function connectedAppGuides(): array
    {
        return [
            'connected_apps.crm.salesforce' => [
                'title' => 'What happens when I connect Salesforce?',
                'steps' => [
                    'Click <strong>Connect</strong> below — you\'ll be redirected to Salesforce\'s login page.',
                    'Log in with the Salesforce account that has access to the org you want to sync.',
                    'Review the requested permissions (API access, read/write contacts) and click <strong>Allow</strong>.',
                    'You\'ll be redirected back here automatically once Salesforce confirms the connection — no ID or secret to copy yourself.',
                ],
                'docs_url' => 'https://help.salesforce.com/s/articleView?id=sf.remoteaccess_authenticate.htm',
                'docs_label' => 'Salesforce OAuth documentation',
            ],
            'connected_apps.crm.hubspot' => [
                'title' => 'What happens when I connect HubSpot?',
                'steps' => [
                    'Click <strong>Connect</strong> below — you\'ll be redirected to HubSpot\'s account chooser.',
                    'Pick the HubSpot account (portal) you want to sync contacts with.',
                    'Review the requested scopes and click <strong>Connect app</strong>.',
                    'You\'ll be redirected back here automatically — no ID or secret to copy yourself.',
                ],
                'docs_url' => 'https://developers.hubspot.com/docs/api/oauth-quickstart-guide',
                'docs_label' => 'HubSpot OAuth documentation',
            ],
            'connected_apps.crm.zoho' => [
                'title' => 'What happens when I connect Zoho CRM?',
                'steps' => [
                    'Click <strong>Connect</strong> below — you\'ll be redirected to Zoho\'s login page.',
                    'Log in with the Zoho account tied to the CRM organization you want to sync.',
                    'Review the requested permissions and click <strong>Accept</strong>.',
                    'You\'ll be redirected back here automatically — no ID or secret to copy yourself.',
                ],
                'docs_url' => 'https://www.zoho.com/crm/developer/docs/api/v2/oauth-overview.html',
                'docs_label' => 'Zoho CRM OAuth documentation',
            ],
            'connected_apps.analytics.google_analytics' => [
                'title' => 'Where do I find my Measurement ID and API Secret?',
                'steps' => [
                    'Sign in to <strong>Google Analytics</strong> and open your GA4 property.',
                    'Click <strong>Admin</strong> (gear icon) → <em>Data Streams</em> → select your web stream.',
                    'Copy the <strong>Measurement ID</strong> shown at the top (e.g. <code>G-XXXXXXXXXX</code>).',
                    'On the same stream page, open <strong>Measurement Protocol API secrets</strong> under "Additional Settings".',
                    'Click <strong>Create</strong>, name it (e.g. "Sayzio"), and copy the generated <strong>API Secret</strong> — it\'s only shown once.',
                ],
                'docs_url' => 'https://developers.google.com/analytics/devguides/collection/protocol/ga4#recommended_parameters_for_reports',
                'docs_label' => 'GA4 Measurement Protocol documentation',
            ],
        ];
    }

    // ============================ INTEGRATION HUB ============================

    protected static function integrationGuides(): array
    {
        return [
            'integrations.payment.stripe' => [
                'title' => 'Where do I find my Stripe keys?',
                'steps' => [
                    'Sign in to the <strong>Stripe Dashboard</strong> at dashboard.stripe.com.',
                    'Open <em>Developers → API keys</em>.',
                    'Copy the <strong>Publishable key</strong> (starts with <code>pk_</code>) and the <strong>Secret key</strong> (starts with <code>sk_</code>) — click "Reveal" to see the secret key.',
                    'Use the <strong>Test</strong> keys while trying things out, and switch to <strong>Live</strong> keys (toggle top-right) when you\'re ready to accept real payments.',
                    'To get the <strong>Webhook signing secret</strong>: go to <em>Developers → Webhooks</em>, add (or open) an endpoint, and copy the "Signing secret" (starts with <code>whsec_</code>).',
                ],
                'docs_url' => 'https://stripe.com/docs/keys',
                'docs_label' => 'Stripe API keys documentation',
            ],
            'integrations.payment.paypal' => [
                'title' => 'Where do I find my PayPal Client ID and Secret?',
                'steps' => [
                    'Sign in to the <strong>PayPal Developer Dashboard</strong> at developer.paypal.com.',
                    'Open <em>Apps & Credentials</em>.',
                    'Choose <strong>Live</strong> or <strong>Sandbox</strong> mode (top toggle) to match the mode you\'re configuring.',
                    'Select an existing app or click <strong>Create App</strong>.',
                    'Copy the <strong>Client ID</strong> and click "Show" to reveal the <strong>Secret</strong>.',
                ],
                'docs_url' => 'https://developer.paypal.com/api/rest/#link-getcredentials',
                'docs_label' => 'PayPal REST API credentials documentation',
            ],
            'integrations.payment.razorpay' => [
                'title' => 'Where do I find my Razorpay keys?',
                'steps' => [
                    'Sign in to the <strong>Razorpay Dashboard</strong>.',
                    'Open <em>Settings → API Keys</em>.',
                    'Click <strong>Generate Key</strong> (or view an existing key pair) — copy the <strong>Key ID</strong> (starts with <code>rzp_</code>) and <strong>Key Secret</strong> (shown only once, download and store it).',
                    'For the <strong>Webhook secret</strong>: go to <em>Settings → Webhooks</em>, add an endpoint, and set your own secret string there — enter the same string here.',
                ],
                'docs_url' => 'https://razorpay.com/docs/payments/dashboard/account-settings/api-keys/',
                'docs_label' => 'Razorpay API keys documentation',
            ],
            'integrations.payment.cashfree' => [
                'title' => 'Where do I find my Cashfree App ID and Secret key?',
                'steps' => [
                    'Sign in to the <strong>Cashfree Merchant Dashboard</strong>.',
                    'Open <em>Developers → API Keys</em>.',
                    'Switch between <strong>Test</strong> and <strong>Production</strong> mode to match what you\'re configuring.',
                    'Copy the <strong>App ID</strong> and <strong>Secret key</strong> shown there.',
                ],
                'docs_url' => 'https://docs.cashfree.com/docs/api-keys',
                'docs_label' => 'Cashfree API keys documentation',
            ],
            'integrations.sms.twilio' => [
                'title' => 'Where do I find my Twilio credentials?',
                'steps' => [
                    'Sign in to the <strong>Twilio Console</strong>.',
                    'On the account dashboard, copy the <strong>Account SID</strong> and <strong>Auth Token</strong> (click "Show" to reveal it).',
                    'Open <em>Phone Numbers → Manage → Active numbers</em> and copy the number you want to send from as the <strong>From number</strong> (in E.164 format, e.g. <code>+15551234567</code>).',
                ],
                'docs_url' => 'https://www.twilio.com/docs/iam/api',
                'docs_label' => 'Twilio Account API documentation',
            ],
            'integrations.sms.plivo' => [
                'title' => 'Where do I find my Plivo credentials?',
                'steps' => [
                    'Sign in to the <strong>Plivo Console</strong>.',
                    'On the dashboard, copy your <strong>Auth ID</strong> and <strong>Auth Token</strong> (click "Show" to reveal it).',
                    'Open <em>Phone Numbers</em> and copy a purchased number as the <strong>From number</strong> (in E.164 format, e.g. <code>+15551234567</code>).',
                ],
                'docs_url' => 'https://www.plivo.com/docs/sms/api/authentication',
                'docs_label' => 'Plivo authentication documentation',
            ],
            'integrations.sms.vonage' => [
                'title' => 'Where do I find my Vonage credentials?',
                'steps' => [
                    'Sign in to the <strong>Vonage API Dashboard</strong>.',
                    'On the dashboard home, copy your <strong>API key</strong> and <strong>API secret</strong>.',
                    'Enter the sender you want recipients to see as <strong>From name/number</strong> (an approved alphanumeric sender ID or a Vonage number).',
                ],
                'docs_url' => 'https://developer.vonage.com/en/getting-started/overview',
                'docs_label' => 'Vonage API documentation',
            ],
            'integrations.email.smtp' => [
                'title' => 'Where do I find my SMTP details?',
                'steps' => [
                    'These come from whichever mailbox or transactional email provider you send from (e.g. Gmail, Outlook, Zoho Mail, your hosting provider).',
                    'Look for "SMTP settings" in that provider\'s account/mail settings — it lists the <strong>host</strong> (e.g. <code>smtp.gmail.com</code>) and <strong>port</strong> (587 for TLS, 465 for SSL).',
                    'The <strong>username</strong> is usually your full email address; the <strong>password</strong> is often an app-specific password rather than your normal login password (required by Gmail, Outlook, etc. when 2FA is on).',
                    'Set <strong>From email</strong> to an address you\'re authorized to send from with these credentials.',
                ],
            ],
            'integrations.email.sendgrid' => [
                'title' => 'Where do I find my SendGrid API key?',
                'steps' => [
                    'Sign in to <strong>SendGrid</strong> → <em>Settings → API Keys</em>.',
                    'Click <strong>Create API Key</strong>, give it "Mail Send" permission, and copy the key (starts with <code>SG.</code>) — it\'s only shown once.',
                    'Set <strong>From email</strong> to a sender you\'ve verified under <em>Settings → Sender Authentication</em>.',
                ],
                'docs_url' => 'https://www.twilio.com/docs/sendgrid/ui/account-and-settings/api-keys',
                'docs_label' => 'SendGrid API keys documentation',
            ],
            'integrations.email.mailgun' => [
                'title' => 'Where do I find my Mailgun API key and domain?',
                'steps' => [
                    'Sign in to <strong>Mailgun</strong> → <em>Sending → Domains</em> and copy the <strong>domain</strong> you\'ve verified (e.g. <code>mg.example.com</code>).',
                    'Open <em>Settings → API Keys</em> and copy your <strong>Private API key</strong>.',
                    'Set <strong>Region</strong> to match where your domain was created (US or EU — shown on the domain\'s settings page).',
                ],
                'docs_url' => 'https://documentation.mailgun.com/en/latest/api-intro.html#authentication-1',
                'docs_label' => 'Mailgun API documentation',
            ],
            'integrations.email.postmark' => [
                'title' => 'Where do I find my Postmark server token?',
                'steps' => [
                    'Sign in to <strong>Postmark</strong> and open the server you want to send from.',
                    'Go to the <em>API Tokens</em> tab and copy the <strong>Server API token</strong>.',
                    'Set <strong>From email</strong> to a Sender Signature (or verified domain address) approved under <em>Sender Signatures</em>.',
                ],
                'docs_url' => 'https://postmarkapp.com/support/article/1008-what-are-the-different-types-of-tokens-and-api-keys',
                'docs_label' => 'Postmark API tokens documentation',
            ],
            'integrations.email.ses' => [
                'title' => 'Where do I find my Amazon SES credentials?',
                'steps' => [
                    'Sign in to the <strong>AWS Console</strong> → <em>IAM → Users</em>, and create (or open) a user with SES sending permission.',
                    'Under <em>Security credentials</em>, create an <strong>Access key</strong> — copy the <strong>Access key ID</strong> and <strong>Secret access key</strong> (shown only once).',
                    'Set <strong>AWS region</strong> to the region where your SES identity is verified (e.g. <code>us-east-1</code>).',
                    'Set <strong>From email</strong> to an address or domain verified in <em>SES → Verified identities</em>.',
                ],
                'docs_url' => 'https://docs.aws.amazon.com/ses/latest/dg/send-email-smtp.html',
                'docs_label' => 'Amazon SES documentation',
            ],
        ];
    }

    // ============================ FORMS ============================

    protected static function formGuides(): array
    {
        return [
            'forms.webhook' => [
                'title' => 'Where do I get a webhook URL?',
                'steps' => [
                    'A webhook URL is an endpoint that receives a copy of each submission the moment it happens — it can come from an automation tool or your own server.',
                    'In <strong>Zapier</strong>: create a Zap with trigger "Webhooks by Zapier → Catch Hook", then copy the generated URL.',
                    'In <strong>Make (Integromat)</strong>: add a "Webhooks → Custom webhook" module, click "Add", and copy the URL it generates.',
                    'In <strong>n8n</strong>: add a Webhook trigger node, copy the "Production URL" shown.',
                    'If you have your own backend, use any endpoint you control that accepts an HTTP <code>POST</code> with a JSON body.',
                ],
            ],
        ];
    }

    // ============================ PAYOUTS ============================

    protected static function payoutGuides(): array
    {
        return [
            'payouts.stripe' => [
                'title' => 'What happens when I connect Stripe?',
                'steps' => [
                    'Click <strong>Connect</strong> — you\'ll be taken to Stripe\'s hosted onboarding.',
                    'Sign in or create a Stripe account, then fill in your business/individual details, bank account, and identity verification as Stripe asks.',
                    'Once Stripe confirms your account, you\'ll be redirected back here automatically — no keys or IDs to copy yourself.',
                ],
                'docs_url' => 'https://stripe.com/connect',
                'docs_label' => 'Stripe Connect documentation',
            ],
            'payouts.paypal' => [
                'title' => 'What happens when I connect PayPal?',
                'steps' => [
                    'Click <strong>Connect</strong> — you\'ll be taken to PayPal\'s hosted onboarding.',
                    'Sign in with (or create) a PayPal business account and grant the requested permissions.',
                    'Once PayPal confirms the connection, you\'ll be redirected back here automatically — no keys or IDs to copy yourself.',
                ],
                'docs_url' => 'https://developer.paypal.com/docs/multiparty/',
                'docs_label' => 'PayPal Commerce Platform documentation',
            ],
            'payouts.razorpay' => [
                'title' => 'What happens when I connect Razorpay?',
                'steps' => [
                    'Click <strong>Connect</strong> — you\'ll be taken to Razorpay\'s hosted onboarding for Route (linked accounts).',
                    'Sign in with (or create) a Razorpay account and complete the KYC/bank details Razorpay asks for.',
                    'Once Razorpay confirms your linked account, you\'ll be redirected back here automatically — no keys or IDs to copy yourself.',
                ],
                'docs_url' => 'https://razorpay.com/route/',
                'docs_label' => 'Razorpay Route documentation',
            ],
            'payouts.phonepe' => [
                'title' => 'What happens when I connect PhonePe?',
                'steps' => [
                    'Click <strong>Connect</strong> — you\'ll be taken to PhonePe\'s hosted merchant onboarding.',
                    'Sign in with (or create) a PhonePe Business account and complete the KYC/bank details PhonePe asks for.',
                    'Once PhonePe confirms your merchant account, you\'ll be redirected back here automatically — no keys or IDs to copy yourself.',
                ],
                'docs_url' => 'https://developer.phonepe.com/payment-gateway',
                'docs_label' => 'PhonePe Payment Gateway documentation',
            ],
            'payouts.ccavenue' => [
                'title' => 'What happens when I connect CCAvenue?',
                'steps' => [
                    'Click <strong>Connect</strong> — you\'ll be taken to CCAvenue\'s hosted merchant onboarding.',
                    'Sign in with (or create) a CCAvenue merchant account and complete the registration and bank details CCAvenue asks for.',
                    'Once CCAvenue activates your merchant account, you\'ll be redirected back here automatically — no keys or IDs to copy yourself.',
                ],
                'docs_url' => 'https://www.ccavenue.com/',
                'docs_label' => 'CCAvenue merchant documentation',
            ],
            'payouts.paytm' => [
                'title' => 'What happens when I connect Paytm?',
                'steps' => [
                    'Click <strong>Connect</strong> — you\'ll be taken to Paytm\'s hosted merchant onboarding.',
                    'Sign in with (or create) a Paytm for Business account and complete the KYC/bank details Paytm asks for.',
                    'Once Paytm confirms your merchant account, you\'ll be redirected back here automatically — no keys or IDs to copy yourself.',
                ],
                'docs_url' => 'https://business.paytm.com/payment-gateway',
                'docs_label' => 'Paytm Payment Gateway documentation',
            ],
            'payouts.ccbill' => [
                'title' => 'What happens when I connect CCBill?',
                'steps' => [
                    'Click <strong>Connect</strong> — you\'ll be taken to CCBill\'s hosted merchant onboarding.',
                    'If you don\'t already have a CCBill account, you\'ll be guided through their high-risk merchant application.',
                    'CCBill\'s onboarding collects the account details it needs (including your account/sub-account and flex form identifiers) directly — you don\'t need to enter them here.',
                    'Once CCBill confirms your account, you\'ll be redirected back here automatically.',
                ],
                'docs_url' => 'https://ccbill.com/',
                'docs_label' => 'CCBill merchant documentation',
            ],
            'payouts.segpay' => [
                'title' => 'What happens when I connect Segpay?',
                'steps' => [
                    'Click <strong>Connect</strong> — you\'ll be taken to Segpay\'s hosted merchant onboarding.',
                    'If you don\'t already have a Segpay account, you\'ll be guided through their merchant application process.',
                    'Segpay\'s onboarding collects the package and API details it needs directly — you don\'t need to enter them here.',
                    'Once Segpay confirms your account, you\'ll be redirected back here automatically.',
                ],
                'docs_url' => 'https://www.segpay.com/',
                'docs_label' => 'Segpay merchant documentation',
            ],
        ];
    }

    // ============================ DEVELOPER API ============================

    protected static function apiKeyGuides(): array
    {
        return [
            'api_keys.developer' => [
                'title' => 'What is this key used for, and how do I use it?',
                'steps' => [
                    'This key is generated by Sayzio, not an external service — click <strong>Generate key</strong> below to create one.',
                    'Copy it immediately after creating it — for your security, the full key is only shown once and can\'t be viewed again later.',
                    'Use it as a Bearer token: send it in the <code>Authorization: Bearer &lt;your key&gt;</code> header of your API requests.',
                    'Store it somewhere safe (a password manager or your server\'s secret store) — treat it like a password, never commit it to code or share it publicly.',
                    'If a key is ever exposed, revoke it here immediately and generate a new one.',
                ],
                'docs_url' => '/docs/api',
                'docs_label' => 'API reference',
            ],
        ];
    }
}
