<?php

namespace App\Modules\Common\Services;

/**
 * Canonical catalogue of every templated/transactional email the platform
 * sends. The central pipeline (Emailer) resolves each send by its key here:
 * an admin override (EmailTemplateSettings) wins, otherwise the built-in
 * default below is used — so with no override every email sends identical
 * content to before.
 *
 * Each entry:
 *  - category    grouping for the admin Email Templates screen
 *  - label       human title
 *  - description what triggers it
 *  - format      'html' | 'text' (default content type)
 *  - body_type   'view'     -> default body is the Blade view (rich layout)
 *                'inline'   -> default body is the token template below
 *                'mailable' -> default body is the Mailable's rendered Blade
 *                'dynamic'  -> body is computed at the call site and passed in
 *                              (registry body is only documentation/starting point)
 *  - view        Blade view name (view/mailable types)
 *  - subject     default subject (a {{token}} template)
 *  - body        default body (a {{token}} template; inline/dynamic types)
 *  - pref_type   NotificationService catalog type this maps to (informational;
 *                the call-site prefersChannel gate is left intact)
 *  - variables   token => ['label' => ..., 'sample' => ...] for the docs panel,
 *                subject/override substitution, and the live preview
 *  - sample_view optional Blade data used to render a preview of a view/mailable
 *                default (best-effort)
 */
class EmailTemplateRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        return [
            // ----------------------------------------------------------------
            // Authentication
            // ----------------------------------------------------------------
            'auth.otp_code' => [
                'category' => 'auth',
                'label' => 'Login / verification code (OTP)',
                'description' => 'The 6-digit one-time code emailed for login and email verification.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your Sayzio Verification Code',
                'body' => "Your Sayzio verification code is: {{code}}\n\nThis code expires in {{ttl_minutes}} minutes.\n\nIf you didn't request this code, you can safely ignore this email.",
                'variables' => [
                    'code' => ['label' => 'The one-time code', 'sample' => '123456'],
                    'ttl_minutes' => ['label' => 'Minutes until the code expires', 'sample' => '10'],
                ],
            ],
            'auth.verify_email' => [
                'category' => 'auth',
                'label' => 'Verify your email (link)',
                'description' => 'Sent when a user requests a link to verify their email address.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.verify-email',
                'subject' => 'Verify Your Email - Sayzio',
                'variables' => [
                    'name' => ['label' => 'Recipient name', 'sample' => 'Alex Rivera'],
                    'verification_url' => ['label' => 'Verification link', 'sample' => 'https://sayzio.app/verify/123/abc'],
                ],
                'sample_view' => [
                    'user' => ['name' => 'Alex Rivera'],
                    'verificationUrl' => 'https://sayzio.app/verify/123/abc',
                ],
            ],
            'auth.verify_email_reminder' => [
                'category' => 'auth',
                'label' => 'Verify your email (reminder)',
                'description' => 'Periodic reminder nudging users who have not yet verified their email.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.verify-email-reminder',
                'subject' => 'Reminder: verify your Sayzio email',
                'pref_type' => 'email_verification_reminder',
                'variables' => [
                    'name' => ['label' => 'Recipient name', 'sample' => 'Alex Rivera'],
                    'verification_url' => ['label' => 'Verification link', 'sample' => 'https://sayzio.app/verify/123/abc'],
                ],
            ],
            'admin.password_reset' => [
                'category' => 'auth',
                'label' => 'Admin password reset',
                'description' => 'Password reset link sent to a back-office admin.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.admin-password-reset',
                'subject' => 'Reset Your Admin Password - Sayzio',
                'variables' => [
                    'reset_url' => ['label' => 'Reset link', 'sample' => 'https://sayzio.app/admin/password/reset/abc'],
                ],
                'sample_view' => [
                    'admin' => ['name' => 'Admin'],
                    'resetUrl' => 'https://sayzio.app/admin/password/reset/abc',
                ],
            ],

            // ----------------------------------------------------------------
            // Billing
            // ----------------------------------------------------------------
            'billing.receipt' => [
                'category' => 'billing',
                'label' => 'Payment receipt',
                'description' => 'Sent after a successful payment, with the tax invoice PDF attached.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Receipt {{invoice_number}}',
                'body' => "Thanks for your payment.\n\nInvoice: {{invoice_number}}\nAmount: {{amount}} {{currency}}\n\nYour tax invoice is attached (and also available in your billing history).",
                'variables' => [
                    'invoice_number' => ['label' => 'Invoice number', 'sample' => 'INV-1042'],
                    'amount' => ['label' => 'Amount paid', 'sample' => '19.00'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                ],
            ],
            'billing.client_invoice' => [
                'category' => 'billing',
                'label' => 'Client invoice',
                'description' => 'A creator-issued invoice emailed to their client with a pay link.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.client-invoice',
                'subject' => 'Invoice {{invoice_number}}',
                'variables' => [
                    'invoice_number' => ['label' => 'Invoice number', 'sample' => 'INV-1042'],
                    'pay_url' => ['label' => 'Pay link', 'sample' => 'https://sayzio.app/invoice/1/pay'],
                ],
            ],
            'billing.payment_reminder' => [
                'category' => 'billing',
                'label' => 'Invoice payment reminder',
                'description' => 'A creator-issued reminder nudging a client about an unpaid or overdue invoice, with the pay link.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Reminder: invoice {{invoice_number}} is awaiting payment',
                'body' => "This is a friendly reminder that invoice {{invoice_number}} for {{amount}} {{currency}} is still awaiting payment.\n\nDue date: {{due_date}}\n\nYou can view and pay the invoice here:\n{{pay_url}}\n\nIf you've already paid, please disregard this message.",
                'variables' => [
                    'invoice_number' => ['label' => 'Invoice number', 'sample' => 'INV-1042'],
                    'amount' => ['label' => 'Amount due', 'sample' => '19.00'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                    'due_date' => ['label' => 'Due date', 'sample' => 'Jul 30, 2026'],
                    'pay_url' => ['label' => 'Pay link', 'sample' => 'https://sayzio.app/invoice/1/pay'],
                ],
            ],
            'billing.creator_sub_renewal_reminder' => [
                'category' => 'billing',
                'label' => 'Creator subscription renewal reminder',
                'description' => 'A friendly heads-up sent to a fan a few days before their subscription to a creator auto-renews, naming the creator, amount, billing cycle, exact renewal date and a manage/cancel link.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your subscription to {{creator_name}} renews {{renews_in}}',
                'body' => "Just a friendly heads-up: your {{cycle}} subscription to {{creator_name}} will automatically renew {{renews_in}}, on {{renewal_date}}.\n\nYou'll be charged {{amount}} {{currency}}.\n\nNothing to do if you'd like to keep your subscription — it renews automatically. If you'd prefer to make changes or cancel before then, you can manage your subscription here:\n{{manage_url}}",
                'variables' => [
                    'creator_name' => ['label' => 'Creator name', 'sample' => 'Alex Rivera'],
                    'amount' => ['label' => 'Renewal amount', 'sample' => '9.00'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                    'cycle' => ['label' => 'Billing cycle', 'sample' => 'monthly'],
                    'renews_in' => ['label' => 'Relative renewal time', 'sample' => 'in 3 days'],
                    'renewal_date' => ['label' => 'Exact renewal date', 'sample' => 'July 3, 2026'],
                    'manage_url' => ['label' => 'Manage / cancel link', 'sample' => 'https://sayzio.app/@alex/manage-subscription'],
                ],
            ],
            'billing.refund_issued' => [
                'category' => 'billing',
                'label' => 'Refund issued',
                'description' => 'Confirms a refund has been issued for an invoice.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Refund issued for invoice {{invoice_number}}',
                'body' => "A refund of {{amount}} {{currency}} has been issued for invoice {{invoice_number}}.\nA credit note is available in your billing history.",
                'variables' => [
                    'invoice_number' => ['label' => 'Invoice number', 'sample' => 'INV-1042'],
                    'amount' => ['label' => 'Refund amount', 'sample' => '19.00'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                ],
            ],
            'billing.refund_acknowledged' => [
                'category' => 'billing',
                'label' => 'Refund request received',
                'description' => 'Acknowledges an offline refund request that will be processed manually.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Refund request received for invoice {{invoice_number}}',
                'body' => "We've received your refund request of {{amount}} {{currency}} for invoice {{invoice_number}}.\nOffline refunds are processed manually; you'll get a second email with the credit note once the payout is confirmed.",
                'variables' => [
                    'invoice_number' => ['label' => 'Invoice number', 'sample' => 'INV-1042'],
                    'amount' => ['label' => 'Refund amount', 'sample' => '19.00'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                ],
            ],
            'billing.wallet_low' => [
                'category' => 'billing',
                'label' => 'Low coin balance',
                'description' => 'Warns a user their coin wallet balance has dropped below the alert threshold.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'Your coin balance is low',
                'body' => 'Your coin balance is low. Top up to keep using coin-priced add-ons.',
                'variables' => [
                    'balance' => ['label' => 'Current balance', 'sample' => '40'],
                    'threshold' => ['label' => 'Alert threshold', 'sample' => '50'],
                ],
            ],
            'billing.subscription_renewal_failed' => [
                'category' => 'billing',
                'label' => 'Subscription renewal failed',
                'description' => 'Sent when an automatic renewal charge fails.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => "Renewal failed — we couldn't charge your payment method",
                'body' => "We couldn't process your renewal for {{plan_name}}.\nYour plan features remain active until {{grace_until}}. Please update your payment method before then: {{update_url}}\nWe'll automatically retry the charge while your grace period is open, so once your card is fixed your plan keeps running.",
                'variables' => [
                    'plan_name' => ['label' => 'Plan name', 'sample' => 'Pro'],
                    'grace_until' => ['label' => 'Grace period end', 'sample' => 'July 30, 2026'],
                    'update_url' => ['label' => 'Update payment method link', 'sample' => 'https://app.example.com/user/billing'],
                ],
            ],
            'billing.subscription_grace_ending' => [
                'category' => 'billing',
                'label' => 'Subscription grace period ending',
                'description' => 'Warns the plan will downgrade within 24 hours unless renewed.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your plan is about to downgrade',
                'body' => 'Your {{plan_name}} plan will downgrade in less than 24 hours unless you renew.',
                'variables' => [
                    'plan_name' => ['label' => 'Plan name', 'sample' => 'Pro'],
                ],
            ],
            'billing.subscription_downgraded' => [
                'category' => 'billing',
                'label' => 'Subscription downgraded',
                'description' => 'Confirms a plan was downgraded to Free after the grace period ended.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your plan has been downgraded',
                'body' => 'Your {{plan_name}} plan has been downgraded to Free because the grace period ended.',
                'variables' => [
                    'plan_name' => ['label' => 'Plan name', 'sample' => 'Pro'],
                ],
            ],
            'billing.subscription_downgrade_scheduled' => [
                'category' => 'billing',
                'label' => 'Subscription downgrade scheduled',
                'description' => 'Confirms a downgrade to a lower plan has been scheduled for the end of the current cycle.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your plan change to {{target_plan}} is scheduled',
                'body' => "You scheduled a change from {{plan_name}} to {{target_plan}}.\nYour current plan stays active until {{effective}}, when {{target_plan}} takes over.\nYou can cancel this change any time before then from your billing settings.",
                'variables' => [
                    'plan_name' => ['label' => 'Current plan name', 'sample' => 'Pro'],
                    'target_plan' => ['label' => 'New (lower) plan name', 'sample' => 'Starter'],
                    'effective' => ['label' => 'Effective date', 'sample' => 'July 30, 2026'],
                ],
            ],
            'billing.subscription_downgrade_applied' => [
                'category' => 'billing',
                'label' => 'Subscription downgrade applied',
                'description' => 'Confirms a scheduled downgrade has taken effect at cycle end, including any add-ons that were dropped.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your plan is now {{target_plan}}',
                'body' => "Your scheduled plan change has taken effect — you're now on {{target_plan}}.\n{{dropped_addons}}\nYour next invoice will reflect the {{target_plan}} price.",
                'variables' => [
                    'target_plan' => ['label' => 'New (lower) plan name', 'sample' => 'Starter'],
                    'dropped_addons' => ['label' => 'Dropped add-ons summary', 'sample' => 'These add-ons are no longer included: Extra Storage, Priority Support.'],
                ],
            ],
            'billing.subscription_downgrade_cancelled' => [
                'category' => 'billing',
                'label' => 'Subscription downgrade cancelled',
                'description' => 'Confirms a pending scheduled downgrade was cancelled and the user stays on their current plan.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your scheduled plan change to {{target_plan}} was cancelled',
                'body' => "Your scheduled change to {{target_plan}} has been cancelled.\nYou'll stay on your {{plan_name}} plan, and it will renew as usual.",
                'variables' => [
                    'plan_name' => ['label' => 'Current plan name', 'sample' => 'Pro'],
                    'target_plan' => ['label' => 'Cancelled (lower) plan name', 'sample' => 'Starter'],
                ],
            ],
            'billing.subscription_update' => [
                'category' => 'billing',
                'label' => 'Subscription status update',
                'description' => 'Generic subscription status change notice.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Subscription update',
                'body' => 'Your subscription status has changed.',
                'variables' => [],
            ],
            'billing.offline_renewal' => [
                'category' => 'billing',
                'label' => 'Offline renewal due',
                'description' => 'Manual bank transfer / UPI renewal instructions for the offline gateway.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'Action required: pay your {{plan_name}} renewal ({{invoice_number}})',
                'body' => "Your {{plan_name}} renewal invoice {{invoice_number}} is ready.\nAmount due: {{amount}}\nPlease pay by: {{due_date}}\n\nView invoice & payment instructions: {{invoice_url}}",
                'variables' => [
                    'plan_name' => ['label' => 'Plan name', 'sample' => 'Pro'],
                    'invoice_number' => ['label' => 'Invoice number', 'sample' => 'INV-1042'],
                    'amount' => ['label' => 'Amount due', 'sample' => '19.00 USD'],
                    'due_date' => ['label' => 'Due date', 'sample' => 'Jul 30, 2026'],
                    'invoice_url' => ['label' => 'Invoice URL', 'sample' => 'https://sayzio.app/user/billing'],
                ],
            ],

            // ----------------------------------------------------------------
            // Developer API
            // ----------------------------------------------------------------
            'api.usage_nearing' => [
                'category' => 'api',
                'label' => 'API usage nearing limit',
                'description' => "Warns a developer they're approaching their monthly API call allowance.",
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => "You're nearing your monthly API limit",
                'body' => "You're nearing your monthly API call allowance.",
                'pref_type' => 'api.usage_warning',
                'variables' => [],
            ],
            'api.usage_full' => [
                'category' => 'api',
                'label' => 'API allowance used up',
                'description' => "Notifies a developer they've used their full monthly API allowance.",
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => "You've used your full monthly API allowance",
                'body' => "You've used your full monthly API allowance.",
                'pref_type' => 'api.usage_warning',
                'variables' => [],
            ],
            'api.usage_rejected' => [
                'category' => 'api',
                'label' => 'API calls being rejected',
                'description' => 'Alerts a developer their API calls are now being rejected (overage unavailable).',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'Your API calls are being rejected',
                'body' => 'Your API calls are being rejected because your allowance is exhausted and overage is unavailable.',
                'pref_type' => 'api.usage_warning',
                'variables' => [],
            ],

            // ----------------------------------------------------------------
            // Workspaces / team
            // ----------------------------------------------------------------
            'workspace.invite' => [
                'category' => 'workspace',
                'label' => 'Workspace invitation',
                'description' => 'Branded invite sent when a workspace owner invites a teammate.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.workspace-invite',
                'subject' => "You've been invited to {{workspace_name}}",
                'variables' => [
                    'workspace_name' => ['label' => 'Workspace name', 'sample' => 'Acme Studio'],
                    'inviter_name' => ['label' => 'Inviter name', 'sample' => 'Jordan Lee'],
                    'role_label' => ['label' => 'Role', 'sample' => 'Editor'],
                    'accept_url' => ['label' => 'Accept link', 'sample' => 'https://sayzio.app/workspaces/invite/abc'],
                ],
            ],
            'workspace.invite_accepted' => [
                'category' => 'workspace',
                'label' => 'Invite accepted',
                'description' => 'Notifies the inviter that their teammate accepted the workspace invite.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Invite accepted: {{workspace_name}}',
                'body' => "{{user_name}} ({{user_email}}) accepted your invite to '{{workspace_name}}'.",
                'variables' => [
                    'user_name' => ['label' => 'Member name', 'sample' => 'Sam Carter'],
                    'user_email' => ['label' => 'Member email', 'sample' => 'sam@example.com'],
                    'workspace_name' => ['label' => 'Workspace name', 'sample' => 'Acme Studio'],
                ],
            ],
            'workspace.member_removed' => [
                'category' => 'workspace',
                'label' => 'Removed from workspace',
                'description' => 'Notifies a member they were removed from a workspace.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Removed from workspace: {{workspace_name}}',
                'body' => "You've been removed from the '{{workspace_name}}' workspace on {{app_name}}.",
                'variables' => [
                    'workspace_name' => ['label' => 'Workspace name', 'sample' => 'Acme Studio'],
                    'app_name' => ['label' => 'App name', 'sample' => 'Sayzio'],
                ],
            ],
            'workspace.two_factor_policy' => [
                'category' => 'workspace',
                'label' => 'Workspace 2FA policy',
                'description' => 'Tells a member two-factor authentication is now required for a workspace.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.workspace-2fa-policy',
                'subject' => 'Two-factor authentication is required',
                'variables' => [
                    'workspace_name' => ['label' => 'Workspace name', 'sample' => 'Acme Studio'],
                ],
            ],
            'workspace.sensitive_action' => [
                'category' => 'workspace',
                'label' => 'Sensitive workspace action',
                'description' => 'Alerts the workspace owner about a sensitive action performed in their workspace.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.sensitive-action-alert',
                'subject' => 'Sensitive action on {{workspace_name}}',
                'variables' => [
                    'workspace_name' => ['label' => 'Workspace name', 'sample' => 'Acme Studio'],
                    'action' => ['label' => 'Action', 'sample' => 'API key created'],
                ],
            ],

            // ----------------------------------------------------------------
            // Security
            // ----------------------------------------------------------------
            'security.suspicious_login' => [
                'category' => 'security',
                'label' => 'New / suspicious sign-in',
                'description' => 'Alerts the account owner about a sign-in from a new location, OS, or browser.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.suspicious-login',
                'subject' => 'New sign-in to your Sayzio account ({{location}})',
                'variables' => [
                    'location' => ['label' => 'Sign-in location', 'sample' => 'US'],
                ],
            ],
            'security.platform_role_attached' => [
                'category' => 'security',
                'label' => 'Platform role attached',
                'description' => 'Security alert sent when a platform-level role is attached to an account.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.platform-role-attached-alert',
                'subject' => 'Security alert: a platform role was attached',
                'variables' => [],
            ],

            // ----------------------------------------------------------------
            // Digests
            // ----------------------------------------------------------------
            'digests.follower' => [
                'category' => 'digests',
                'label' => 'Follower daily digest',
                'description' => 'Daily digest of new updates from creators a user follows.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.follower-digest',
                'subject' => 'Your daily digest',
                'pref_type' => 'follower_digest',
                'variables' => [
                    'count' => ['label' => 'Number of updates', 'sample' => '5'],
                ],
            ],
            'digests.backlink' => [
                'category' => 'digests',
                'label' => 'Backlink weekly digest',
                'description' => 'Weekly digest of new backlinks/mentions discovered for a user.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.backlink-digest',
                'subject' => 'Your weekly backlink digest',
                'pref_type' => 'backlink_digest',
                'variables' => [
                    'total_backlinks' => ['label' => 'New mentions', 'sample' => '3'],
                    'property_count' => ['label' => 'Properties', 'sample' => '2'],
                ],
            ],
            'digests.creator' => [
                'category' => 'digests',
                'label' => 'Creator weekly digest',
                'description' => 'Weekly creator analytics digest (followers, posts, etc.).',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.creator-digest',
                'subject' => 'Your week on Sayzio',
                'pref_type' => 'creator_digest',
                'variables' => [
                    'new_followers' => ['label' => 'New followers', 'sample' => '12'],
                ],
            ],
            'insider.new_post' => [
                'category' => 'digests',
                'label' => 'Insider post published',
                'description' => 'Notifies insider members that a creator posted a new update.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.insider-post-text',
                'subject' => '{{creator_name}} posted: {{title}}',
                'pref_type' => 'insider_post',
                'variables' => [
                    'creator_name' => ['label' => 'Creator name', 'sample' => 'Jamie Fox'],
                    'title' => ['label' => 'Post title', 'sample' => 'Behind the scenes'],
                ],
            ],
            'roadmap.shipped' => [
                'category' => 'digests',
                'label' => 'Roadmap item shipped',
                'description' => 'Tells subscribers a roadmap feature has shipped.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.roadmap-shipped',
                'subject' => '[{{app_name}}] Shipped: {{title}}',
                'pref_type' => 'roadmap_shipped',
                'variables' => [
                    'app_name' => ['label' => 'App name', 'sample' => 'Sayzio'],
                    'title' => ['label' => 'Feature title', 'sample' => 'Dark mode'],
                ],
            ],

            // ----------------------------------------------------------------
            // Connections / health
            // ----------------------------------------------------------------
            'connections.social_broken' => [
                'category' => 'connections',
                'label' => 'Social connection lost',
                'description' => 'Tells a user a connected social account needs to be reconnected.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.social-connection-broken',
                'subject' => 'Your social connection needs attention',
                'variables' => [
                    'provider' => ['label' => 'Provider', 'sample' => 'Instagram'],
                ],
            ],
            'connections.cloud_broken' => [
                'category' => 'connections',
                'label' => 'Cloud connection lost',
                'description' => 'Tells a user a connected cloud storage account needs to be reconnected.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.cloud-connection-broken',
                'subject' => 'Your cloud connection needs attention',
                'variables' => [
                    'provider' => ['label' => 'Provider', 'sample' => 'Google Drive'],
                ],
            ],
            'connections.inbox_forward_broken' => [
                'category' => 'connections',
                'label' => 'Inbox forwarding failing',
                'description' => 'Warns a user that an inbox forwarding rule keeps failing.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.inbox-forward-broken',
                'subject' => 'Heads up: your "{{label}}" forwarding rule on Sayzio keeps failing',
                'variables' => [
                    'label' => ['label' => 'Forwarding rule label', 'sample' => 'Work inbox'],
                ],
            ],
            'domain.health_alert' => [
                'category' => 'connections',
                'label' => 'Domain health alert',
                'description' => 'Alerts a user about a custom domain health/verification issue.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.domain-health-alert',
                'subject' => 'Domain health alert',
                'variables' => [
                    'domain' => ['label' => 'Domain', 'sample' => 'links.acme.com'],
                ],
            ],
            'link.insurance_alert' => [
                'category' => 'connections',
                'label' => 'Link health alert',
                'description' => 'Alerts a user when a monitored link breaks or is restored.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.link-insurance-alert',
                'subject' => 'Link health alert',
                'variables' => [
                    'url' => ['label' => 'Monitored URL', 'sample' => 'https://example.com'],
                ],
            ],

            // ----------------------------------------------------------------
            // Events (RSVP)
            // ----------------------------------------------------------------
            'events.rsvp_confirmation' => [
                'category' => 'events',
                'label' => 'RSVP confirmation',
                'description' => 'Confirms an attendee RSVP to an event.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.event-rsvp-confirmation-text',
                'subject' => 'RSVP {{verb}}: {{title}}',
                'variables' => [
                    'verb' => ['label' => 'RSVP verb', 'sample' => 'confirmed'],
                    'title' => ['label' => 'Event title', 'sample' => 'Launch Party'],
                ],
            ],
            'events.rsvp_notify_owner' => [
                'category' => 'events',
                'label' => 'RSVP — owner notification',
                'description' => 'Notifies the event owner of a new RSVP.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.event-rsvp-notify-text',
                'subject' => 'New RSVP — {{name}} · {{response}}',
                'variables' => [
                    'name' => ['label' => 'Attendee name', 'sample' => 'Pat Doe'],
                    'response' => ['label' => 'Response', 'sample' => 'Going'],
                ],
            ],
            'events.rsvp_reminder' => [
                'category' => 'events',
                'label' => 'RSVP reminder',
                'description' => 'Reminds an attendee an event is starting soon.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.event-rsvp-reminder-text',
                'subject' => 'Reminder: {{title}}',
                'variables' => [
                    'title' => ['label' => 'Event title', 'sample' => 'Launch Party'],
                ],
            ],

            // ----------------------------------------------------------------
            // Reviews / privacy
            // ----------------------------------------------------------------
            'reviews.verification' => [
                'category' => 'reviews',
                'label' => 'Review verification',
                'description' => 'Asks a review author to confirm their review.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.review-verification-text',
                'subject' => 'Confirm your review for {{title}}',
                'variables' => [
                    'title' => ['label' => 'Reviewed page/title', 'sample' => 'Acme Cafe'],
                ],
            ],
            'privacy.request' => [
                'category' => 'privacy',
                'label' => 'Privacy request update',
                'description' => 'Updates a user (or admin) about a privacy export/deletion request.',
                'format' => 'html',
                'body_type' => 'mailable',
                'view' => 'emails.privacy-request-text',
                'subject' => 'Update on your request',
                'variables' => [
                    'stage' => ['label' => 'Request stage', 'sample' => 'received'],
                ],
            ],

            // ----------------------------------------------------------------
            // Account
            // ----------------------------------------------------------------
            'account.handle_banned' => [
                'category' => 'account',
                'label' => 'Handle changed (banned)',
                'description' => "Notifies a user their handle was changed because it matched a banned name.",
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.handle-banned',
                'subject' => 'Your Sayzio handle was changed',
                'variables' => [
                    'user_name' => ['label' => 'User name', 'sample' => 'Alex Rivera'],
                    'handle' => ['label' => 'New handle', 'sample' => 'alex-r'],
                    'profile_url' => ['label' => 'Profile URL', 'sample' => 'https://sayzio.app/@alex-r'],
                ],
            ],
            'account.notice' => [
                'category' => 'account',
                'label' => 'Account notice (admin action)',
                'description' => 'Generic account notice sent when an admin acts on an account (suspend, restore, etc.).',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'An update about your Sayzio account',
                'body' => 'There has been an update to your Sayzio account.',
                'variables' => [],
            ],
            'starter.free_window_reminder' => [
                'category' => 'account',
                'label' => 'Starter free window reminder',
                'description' => 'Yearly nudge to re-confirm the free Starter plan window.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.starter-free-window-reminder',
                'subject' => 'Keep your free Sayzio Starter plan — renew for another year',
                'variables' => [
                    'name' => ['label' => 'User name', 'sample' => 'Alex Rivera'],
                    'renew_url' => ['label' => 'Renew link', 'sample' => 'https://sayzio.app/starter/renew/abc'],
                ],
            ],

            // ----------------------------------------------------------------
            // Posts (creator review workflow)
            // ----------------------------------------------------------------
            'posts.review_request' => [
                'category' => 'account',
                'label' => 'Post awaiting review',
                'description' => 'Asks a reviewer to review a post that is waiting.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'A post is waiting for your review',
                'body' => "{{message}}\n\nReview it: {{url}}",
                'variables' => [
                    'message' => ['label' => 'Message', 'sample' => 'A new draft needs your review.'],
                    'url' => ['label' => 'Posts URL', 'sample' => 'https://sayzio.app/user/posts'],
                ],
            ],
            'posts.review_decision' => [
                'category' => 'account',
                'label' => 'Post review decision',
                'description' => "Notifies the post author of a reviewer's decision.",
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Update on your post review',
                'body' => "{{message}}\n\nOpen your posts: {{url}}",
                'variables' => [
                    'message' => ['label' => 'Message', 'sample' => 'Jordan approved your post.'],
                    'url' => ['label' => 'Posts URL', 'sample' => 'https://sayzio.app/user/posts'],
                ],
            ],

            // ----------------------------------------------------------------
            // Messaging / engagement
            // ----------------------------------------------------------------
            'messaging.dm_new' => [
                'category' => 'messaging',
                'label' => 'New direct message',
                'description' => 'Notifies a user they received a new DM.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New Sayzio DM from {{sender_name}}',
                'body' => '{{sender_name}}: {{preview}}',
                'pref_type' => 'dm.new',
                'variables' => [
                    'sender_name' => ['label' => 'Sender name', 'sample' => 'Jamie Fox'],
                    'preview' => ['label' => 'Message preview', 'sample' => 'Hey, loved your latest post!'],
                ],
            ],
            'follow.new_follower' => [
                'category' => 'messaging',
                'label' => 'New follower',
                'description' => 'Notifies a creator they gained a new follower.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New follower on Sayzio',
                'body' => '{{follower_name}} just followed you on Sayzio.',
                'variables' => [
                    'follower_name' => ['label' => 'Follower name', 'sample' => 'Sam Carter'],
                ],
            ],
            'restaurant.new_order' => [
                'category' => 'messaging',
                'label' => 'New restaurant order',
                'description' => 'Alerts a menu owner about a new order.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New order · {{where}}',
                'body' => "{{where}} · {{count}} item(s) · {{currency}} {{subtotal}} on \"{{link_title}}\".\n\nManage orders: {{orders_url}}",
                'pref_type' => 'restaurant.new_order',
                'variables' => [
                    'where' => ['label' => 'Table / walk-in', 'sample' => 'Table 4'],
                    'count' => ['label' => 'Item count', 'sample' => '3'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                    'subtotal' => ['label' => 'Order subtotal', 'sample' => '24.00'],
                    'link_title' => ['label' => 'Menu title', 'sample' => "Joe's Diner"],
                    'orders_url' => ['label' => 'Orders dashboard URL', 'sample' => 'https://sayzio.app/user/links/1/restaurant/orders'],
                ],
            ],
            'monetization.creator_notice' => [
                'category' => 'messaging',
                'label' => 'Creator monetization notice',
                'description' => 'Notifies a creator about a sale or subscription event.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'Update from your Sayzio storefront',
                'body' => 'You have a new update from your Sayzio storefront.',
                'variables' => [],
            ],

            // ----------------------------------------------------------------
            // Newsletter
            // ----------------------------------------------------------------
            'newsletter.welcome' => [
                'category' => 'newsletter',
                'label' => 'Newsletter welcome',
                'description' => 'Welcomes a new newsletter subscriber.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.newsletter-welcome',
                'subject' => 'Welcome to the {{app_name}} newsletter',
                'variables' => [
                    'app_name' => ['label' => 'App name', 'sample' => 'Sayzio'],
                ],
            ],
            'newsletter.unsubscribe_link' => [
                'category' => 'newsletter',
                'label' => 'Newsletter manage / unsubscribe link',
                'description' => 'Sends a subscriber a link to manage or unsubscribe from the newsletter.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.newsletter-unsubscribe-link',
                'subject' => 'Manage your {{app_name}} subscription',
                'variables' => [
                    'app_name' => ['label' => 'App name', 'sample' => 'Sayzio'],
                ],
            ],
            'newsletter.issue' => [
                'category' => 'newsletter',
                'label' => 'Newsletter issue',
                'description' => 'A broadcast newsletter issue sent to subscribers (body composed in the issue editor).',
                'format' => 'html',
                'body_type' => 'dynamic',
                'subject' => '{{subject}}',
                'body' => '{{body}}',
                'variables' => [
                    'subject' => ['label' => 'Issue subject', 'sample' => 'This month at Sayzio'],
                ],
            ],

            // ----------------------------------------------------------------
            // System / operational alerts (sent to the operator team)
            // ----------------------------------------------------------------
            'system.health_alert' => [
                'category' => 'system',
                'label' => 'System health alert',
                'description' => 'Operational alerts to the team (schema drift, template health, cut-offs, etc.). Body is generated by the check that raised it.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'Sayzio system alert',
                'body' => 'A system health check raised an alert.',
                'variables' => [],
            ],
            'support.contact_request' => [
                'category' => 'system',
                'label' => 'New contact request',
                'description' => 'Sent to the support inbox when a visitor submits a quick-contact request (call back / WhatsApp / email) from the assistant or the standalone widget. Body is generated at the call site.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'New contact request',
                'body' => 'A new contact request was submitted.',
                'variables' => [],
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    /**
     * Templates grouped by category, preserving definition order, for the
     * admin Email Templates screen.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function byCategory(): array
    {
        $grouped = [];
        foreach (self::all() as $key => $entry) {
            $grouped[$entry['category']][$key] = $entry;
        }
        return $grouped;
    }

    /**
     * Resolve the category for a key (defaults to "uncategorized").
     */
    public static function categoryFor(string $key): string
    {
        return self::all()[$key]['category'] ?? 'uncategorized';
    }

    /** Human labels for the category groupings shown in the admin UI. */
    public const CATEGORY_LABELS = [
        'auth'         => 'Authentication',
        'billing'      => 'Billing',
        'api'          => 'Developer API',
        'workspace'    => 'Workspaces & team',
        'security'     => 'Security',
        'digests'      => 'Digests',
        'connections'  => 'Connections & health',
        'events'       => 'Events (RSVP)',
        'reviews'      => 'Reviews',
        'privacy'      => 'Privacy',
        'account'      => 'Account',
        'messaging'    => 'Messaging & engagement',
        'newsletter'   => 'Newsletter',
        'system'       => 'System alerts',
    ];

    public static function categoryLabel(string $category): string
    {
        return self::CATEGORY_LABELS[$category] ?? ucfirst($category);
    }

    /**
     * token => sample-value map for a key, used to render previews and to
     * document the available variables in the editor.
     *
     * @return array<string, string>
     */
    public static function sampleTokens(string $key): array
    {
        $vars   = self::all()[$key]['variables'] ?? [];
        $tokens = [];
        foreach ($vars as $name => $meta) {
            $tokens[$name] = (string) ($meta['sample'] ?? '');
        }
        return $tokens;
    }
}
