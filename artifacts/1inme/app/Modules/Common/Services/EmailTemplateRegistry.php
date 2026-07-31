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
            'billing.custom_plan_approved' => [
                'category' => 'billing',
                'label' => 'Custom plan offer approved',
                'description' => 'Sent to the prospect when an admin approves their custom plan request and provisions a bespoke plan for them.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'Your custom plan "{{plan_name}}" is ready — {{price}}/{{cycle}}',
                'body' => "Hi {{name}},\n\nGreat news! Our team has reviewed your custom plan request and set up a bespoke plan just for you:\n\nPlan: {{plan_name}}\nPrice: {{price}}/{{cycle}}\n\nSign in to your dashboard to review and activate it:\n{{dashboard}}\n\nIf you have any questions, just reply to this email.",
                'variables' => [
                    'name'      => ['label' => 'Requester name',  'sample' => 'Jane Smith'],
                    'plan_name' => ['label' => 'Custom plan name', 'sample' => 'Enterprise Custom'],
                    'price'     => ['label' => 'Formatted price',  'sample' => '$99.00'],
                    'cycle'     => ['label' => 'Billing cycle',    'sample' => 'monthly'],
                    'dashboard' => ['label' => 'Dashboard URL',    'sample' => 'https://sayzio.app/user/dashboard'],
                ],
            ],
            'billing.custom_plan_declined' => [
                'category' => 'billing',
                'label' => 'Custom plan request declined',
                'description' => 'Sent to the prospect when an admin declines their custom plan request.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'Update on your custom plan request',
                'body' => "Hi {{name}},\n\nThank you for reaching out about a custom plan. After reviewing your request, we're unable to put together an offer at this time.{{decline_reason}}\n\nPlease don't hesitate to reach out again in the future — our standard plans at sayzio.app/pricing may also suit your needs.",
                'variables' => [
                    'name'           => ['label' => 'Requester name',  'sample' => 'Jane Smith'],
                    'decline_reason' => ['label' => 'Decline reason (may be blank)', 'sample' => "\n\nReason: Budget constraints at this time."],
                ],
            ],

            // ----------------------------------------------------------------
            // Events & ticketing
            // ----------------------------------------------------------------
            'ticketing.confirmation' => [
                'category' => 'events',
                'label' => 'Ticket purchase confirmation',
                'description' => 'Sent after a successful event ticket purchase, with a link to the QR ticket.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => "Your ticket for {{event_name}}",
                'body' => "You're all set for {{event_name}}!\n\nTicket: {{tier_name}} × {{quantity}}\n\nShow this QR code at the door:\n{{ticket_url}}",
                'variables' => [
                    'event_name' => ['label' => 'Event name', 'sample' => 'Summer Music Fest'],
                    'tier_name' => ['label' => 'Ticket tier', 'sample' => 'General Admission'],
                    'quantity' => ['label' => 'Quantity purchased', 'sample' => '2'],
                    'ticket_url' => ['label' => 'QR ticket link', 'sample' => 'https://sayzio.app/summerfest/tickets/ABC123'],
                ],
            ],
            'ticketing.refunded' => [
                'category' => 'events',
                'label' => 'Ticket refund confirmation',
                'description' => 'Sent to the attendee when the organizer refunds their event ticket.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => "Your ticket for {{event_name}} was refunded",
                'body' => "Your ticket for {{event_name}} has been refunded.\n\nTicket: {{tier_name}} × {{quantity}}\nRefunded: {{amount}}\nReason: {{reason}}\n\nThe refund has been sent back to your original payment method. This ticket is no longer valid for entry.",
                'variables' => [
                    'event_name' => ['label' => 'Event name', 'sample' => 'Summer Music Fest'],
                    'tier_name' => ['label' => 'Ticket tier', 'sample' => 'General Admission'],
                    'quantity' => ['label' => 'Quantity refunded', 'sample' => '2'],
                    'amount' => ['label' => 'Refunded amount', 'sample' => 'USD 50.00'],
                    'reason' => ['label' => 'Refund reason', 'sample' => 'Event cancelled'],
                ],
            ],

            'ticketing.tier_capacity' => [
                'category' => 'events',
                'label' => 'Ticket tier capacity alert',
                'description' => 'Sent to the event owner the moment a paid ticket tier crosses "90%+ full" or "sold out", so they can add capacity before missing sales. Counts-only, no attendee details.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => '"{{tier_name}}" for {{event_name}} is {{status_label}}',
                'body' => "Heads up — your \"{{tier_name}}\" ticket tier for {{event_name}} is {{status_label}}.\n\nSold: {{sold}} of {{capacity}} ({{remaining}} left)\n\nWant to sell more? Add capacity to this tier before you miss sales:\n{{manage_url}}",
                'variables' => [
                    'event_name' => ['label' => 'Event name', 'sample' => 'Summer Music Fest'],
                    'tier_name' => ['label' => 'Ticket tier', 'sample' => 'VIP'],
                    'status_label' => ['label' => 'Threshold crossed', 'sample' => 'sold out'],
                    'sold' => ['label' => 'Tickets sold', 'sample' => '100'],
                    'capacity' => ['label' => 'Tier capacity', 'sample' => '100'],
                    'remaining' => ['label' => 'Tickets remaining', 'sample' => '0'],
                    'manage_url' => ['label' => 'Manage tiers link', 'sample' => 'https://sayzio.app/user/links/1/tickets'],
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

            'transfer.sent' => [
                'category' => 'account',
                'label' => 'Asset transfer sent',
                'description' => 'Confirms to the sender that their link or workspace was transferred to another account.',
                'format' => 'text',
                'body_type' => 'inline',
                'pref_type' => 'asset_transfer',
                'subject' => 'Transfer complete: {{asset_label}}',
                'body' => "Your {{asset_kind}} \"{{asset_label}}\" was transferred to {{recipient_name}} ({{recipient_email}}). This transfer is instant and cannot be undone.",
                'variables' => [
                    'asset_kind' => ['label' => 'Asset kind', 'sample' => 'link'],
                    'asset_label' => ['label' => 'Asset name', 'sample' => 'My Bio Page'],
                    'recipient_name' => ['label' => 'Recipient name', 'sample' => 'Sam Carter'],
                    'recipient_email' => ['label' => 'Recipient email', 'sample' => 'sam@example.com'],
                ],
            ],
            'transfer.received' => [
                'category' => 'account',
                'label' => 'Asset transfer received',
                'description' => 'Notifies the recipient that a link or workspace was transferred into their account.',
                'format' => 'text',
                'body_type' => 'inline',
                'pref_type' => 'asset_transfer',
                'subject' => 'You received a {{asset_kind}}: {{asset_label}}',
                'body' => "{{sender_name}} ({{sender_email}}) transferred the {{asset_kind}} \"{{asset_label}}\" to your account. It's available in your dashboard now. If it exceeds your plan limits it will keep working, but editing may require an upgrade.",
                'variables' => [
                    'asset_kind' => ['label' => 'Asset kind', 'sample' => 'link'],
                    'asset_label' => ['label' => 'Asset name', 'sample' => 'My Bio Page'],
                    'sender_name' => ['label' => 'Sender name', 'sample' => 'Jordan Lee'],
                    'sender_email' => ['label' => 'Sender email', 'sample' => 'jordan@example.com'],
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
            'user.password_reset' => [
                'category' => 'auth',
                'label' => 'User password reset',
                'description' => 'Password reset link sent to a regular user from the public forgot-password flow.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Reset your Sayzio password',
                'body' => "Hi {{name}},\n\nA password reset was requested for your Sayzio account. Open the link below to set a new password. This link expires in 60 minutes.\n\n{{reset_url}}\n\nIf you didn't request this, you can safely ignore this email — your password will stay unchanged.",
                'variables' => [
                    'name' => ['label' => 'Recipient name', 'sample' => 'Sam Carter'],
                    'reset_url' => ['label' => 'Reset link', 'sample' => 'https://sayzio.app/user/reset-password/abc'],
                ],
            ],
            'security.password_changed' => [
                'category' => 'security',
                'label' => 'Password changed',
                'description' => 'Security notification sent when the account password is changed or reset.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your Sayzio password was {{action}}',
                'body' => "Hi {{name}},\n\nYour Sayzio account password was {{action}} on {{time}}. For safety we've signed out every other device and session.\n\nIf this was you, no action is needed. If you don't recognize this change, reset your password immediately from the sign-in page and review your recent logins.",
                'variables' => [
                    'name' => ['label' => 'Recipient name', 'sample' => 'Sam Carter'],
                    'action' => ['label' => 'changed | reset', 'sample' => 'changed'],
                    'time' => ['label' => 'When it happened', 'sample' => 'Jul 22, 2026 4:20 PM IST'],
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
            'events.new_nearby_alert' => [
                'category' => 'events',
                'label' => 'New event near you',
                'description' => 'Alerts an opted-in user about a single new public event created inside their saved location radius (Task #3593).',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New event near you: {{title}}',
                'body' => "A new event was just created near you:\n\n{{title}}\n{{when}}\n\n{{url}}\n\nYou're getting this because you turned on nearby event alerts. You can adjust or turn off alerts anytime from your notification settings.",
                'variables' => [
                    'title' => ['label' => 'Event title', 'sample' => 'Rooftop Launch Party'],
                    'when' => ['label' => 'Event date/time', 'sample' => 'Jul 12, 7:00 PM'],
                    'url' => ['label' => 'Event page URL', 'sample' => 'https://sayzio.app/rooftop-launch'],
                ],
            ],
            'events.new_nearby_digest' => [
                'category' => 'events',
                'label' => 'New events near you (daily digest)',
                'description' => 'Once-daily batched summary of new public events near an opted-in user (Task #3593).',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => '{{count}} new event(s) near you',
                'body' => "New public events were created near you today:\n\n{{list}}\n\nYou're getting this because you turned on nearby event alerts (daily digest). You can adjust or turn off alerts anytime from your notification settings.",
                'variables' => [
                    'count' => ['label' => 'Number of new events', 'sample' => '3'],
                    'list' => ['label' => 'Newline-separated event list', 'sample' => "Rooftop Launch Party — Jul 12, 7:00 PM (https://sayzio.app/rooftop-launch)"],
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
            'account.credentials' => [
                'category' => 'account',
                'label' => 'New account credentials',
                'description' => 'Sends a freshly admin-created account its sign-in email and temporary password.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your new {{app_name}} account',
                'body' => "An account has been created for you on {{app_name}}.\n\nEmail: {{email}}\nTemporary password: {{password}}\n\nSign in here: {{login_url}}\nPlease change your password after your first sign-in.",
                'variables' => [
                    'app_name' => ['label' => 'App name', 'sample' => 'Sayzio'],
                    'email' => ['label' => 'Account email', 'sample' => 'alex@example.com'],
                    'password' => ['label' => 'Temporary password', 'sample' => 'Tmp-8f2k9x'],
                    'login_url' => ['label' => 'Sign-in URL', 'sample' => 'https://sayzio.app/user/login'],
                ],
            ],
            'verification.approved' => [
                'category' => 'account',
                'label' => 'Profile verification approved',
                'description' => 'Notifies a user their profile verification (or re-verification) request was approved.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your verification was approved',
                'body' => "{{message}}\n\nView your verification status: {{url}}",
                'variables' => [
                    'message' => ['label' => 'Message', 'sample' => 'Congratulations! Your profile is now verified with the Blue tick as "Alex Rivera".'],
                    'url' => ['label' => 'Verification page URL', 'sample' => 'https://sayzio.app/user/settings/profile-verification'],
                ],
            ],
            'verification.rejected' => [
                'category' => 'account',
                'label' => 'Profile verification rejected',
                'description' => 'Notifies a user their profile verification (or re-verification) request was not approved, including the reviewer\'s note.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Update on your verification request',
                'body' => "{{message}}\n\nView your verification status: {{url}}",
                'variables' => [
                    'message' => ['label' => 'Message', 'sample' => 'Your verification request was not approved. Reviewer note: The proof documents were unreadable.'],
                    'url' => ['label' => 'Verification page URL', 'sample' => 'https://sayzio.app/user/settings/profile-verification'],
                ],
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
            'feature.launched' => [
                'category' => 'messaging',
                'label' => 'Coming-soon feature launched',
                'description' => 'Sent once to a user who clicked "Notify me" on a coming-soon feature, when that feature becomes available.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.feature-launched',
                'subject' => '{{feature}} is now available on Sayzio',
                'variables' => [
                    'name' => ['label' => 'Recipient name', 'sample' => 'Alex Rivera'],
                    'feature' => ['label' => 'Feature name', 'sample' => 'Dialer'],
                    'blurb' => ['label' => 'Short feature description', 'sample' => 'A built-in number pad and call history that resolves phone numbers to the right biolink.'],
                    'feature_url' => ['label' => 'Link to open the feature', 'sample' => 'https://sayzio.app/user/dialer'],
                ],
                'sample_view' => [
                    'subject' => 'Dialer is now available on Sayzio',
                    'userName' => 'Alex Rivera',
                    'featureLabel' => 'Dialer',
                    'blurb' => 'A built-in number pad and call history that resolves phone numbers to the right biolink.',
                    'capabilities' => ['Number-pad dialer with recents and favourites', 'Phone → biolink resolution with caller ID'],
                    'featureUrl' => 'https://sayzio.app/user/dialer',
                ],
            ],
            'app.launched' => [
                'category' => 'messaging',
                'label' => 'Mobile app launched',
                'description' => 'Sent once to everyone on the mobile-app launch list (the "coming soon" modal) the moment an admin sets a store URL and the app goes live.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.app-launched',
                'subject' => 'The {{app_name}} app is here — download it now',
                'variables' => [
                    'app_name' => ['label' => 'App / brand name', 'sample' => 'Sayzio'],
                    'play_url' => ['label' => 'Google Play link', 'sample' => 'https://play.google.com/store/apps/details?id=app.sayzio'],
                    'app_url' => ['label' => 'App Store link', 'sample' => 'https://apps.apple.com/app/sayzio/id123456789'],
                    'store_url' => ['label' => 'Primary store link (the one the visitor clicked, else the first live store)', 'sample' => 'https://play.google.com/store/apps/details?id=app.sayzio'],
                ],
                'sample_view' => [
                    'subject' => 'The Sayzio app is here — download it now',
                    'appName' => 'Sayzio',
                    'playUrl' => 'https://play.google.com/store/apps/details?id=app.sayzio',
                    'appUrl' => 'https://apps.apple.com/app/sayzio/id123456789',
                    'storeUrl' => 'https://play.google.com/store/apps/details?id=app.sayzio',
                ],
            ],
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
            'contact.follow_up_reminder' => [
                'category' => 'messaging',
                'label' => 'Contact follow-up reminder',
                'description' => 'Alerts the owner when a scheduled follow-up for a contact/lead is due.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Follow-up reminder · {{contact_name}}',
                'body' => "It's time to follow up with {{contact_name}}.{{note_line}}\n\nView contact: {{contact_url}}",
                'pref_type' => 'contact.follow_up_reminder',
                'variables' => [
                    'contact_name' => ['label' => 'Contact name', 'sample' => 'Sam Carter'],
                    'note_line' => ['label' => 'Reminder note (blank if none)', 'sample' => "\n\nNote: Discuss renewal pricing."],
                    'contact_url' => ['label' => 'Contact URL', 'sample' => 'https://sayzio.app/user/contacts/1'],
                ],
            ],
            'contacts.google_reauth' => [
                'category' => 'messaging',
                'label' => 'Google Contacts reconnect needed',
                'description' => 'One-time alert when a Google Contacts connection expires and syncing pauses until the user reconnects.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Google Contacts sync is paused — please reconnect',
                'body' => "Google has revoked or expired the connection to {{account_email}}, so contact syncing is paused.\n\nReconnect your Google account to resume syncing: {{reconnect_url}}",
                'pref_type' => 'contacts.google_reauth',
                'variables' => [
                    'account_email' => ['label' => 'Connected Google account email', 'sample' => 'you@gmail.com'],
                    'reconnect_url' => ['label' => 'Reconnect link', 'sample' => 'https://sayzio.app/user/contacts'],
                ],
            ],
            'contacts.google_reauth_reminder' => [
                'category' => 'messaging',
                'label' => 'Google Contacts reconnect reminder',
                'description' => 'One-time follow-up sent when a Google Contacts connection has stayed disconnected for a week after the initial alert.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Reminder: Google Contacts sync is still paused',
                'body' => "It's been a week since the connection to {{account_email}} expired, and contact syncing is still paused.\n\nReconnect your Google account to resume syncing: {{reconnect_url}}",
                'pref_type' => 'contacts.google_reauth_reminder',
                'variables' => [
                    'account_email' => ['label' => 'Connected Google account email', 'sample' => 'you@gmail.com'],
                    'reconnect_url' => ['label' => 'Reconnect link', 'sample' => 'https://sayzio.app/user/contacts'],
                ],
            ],
            'store.new_order' => [
                'category' => 'messaging',
                'label' => 'New store order request',
                'description' => 'Alerts a store owner about a new order request.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New order request · {{count}} item(s)',
                'body' => "{{customer}} · {{count}} item(s) · {{currency}} {{total}} on \"{{link_title}}\".\n\nManage requests: {{orders_url}}",
                'pref_type' => 'store.new_order',
                'variables' => [
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'count' => ['label' => 'Item count', 'sample' => '3'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                    'total' => ['label' => 'Order total', 'sample' => '24.00'],
                    'link_title' => ['label' => 'Store title', 'sample' => "Sam's Shop"],
                    'orders_url' => ['label' => 'Requests dashboard URL', 'sample' => 'https://sayzio.app/user/links/1/store/orders'],
                ],
            ],
            'service_booking.new_request' => [
                'category' => 'messaging',
                'label' => 'New service booking request',
                'description' => 'Alerts a service booking owner about a new booking request.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New booking request · {{when}}',
                'body' => "{{customer}} requested {{services}} for {{when}} · est. {{currency}} {{total}} on \"{{link_title}}\".\n\nManage bookings: {{bookings_url}}",
                'pref_type' => 'service_booking.new_request',
                'variables' => [
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'services' => ['label' => 'Requested services', 'sample' => 'Haircut'],
                    'when' => ['label' => 'Requested slot', 'sample' => 'Mon, Jan 5 · 9:00 AM'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                    'total' => ['label' => 'Estimated total', 'sample' => '40.00'],
                    'link_title' => ['label' => 'Page title', 'sample' => "Sam's Studio"],
                    'bookings_url' => ['label' => 'Bookings dashboard URL', 'sample' => 'https://sayzio.app/user/links/1/service-booking/bookings'],
                ],
            ],
            'service_booking.visitor_change' => [
                'category' => 'messaging',
                'label' => 'Booking changed by visitor (owner)',
                'description' => 'Alerts a service booking owner when a visitor reschedules or cancels their booking from the status page.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Booking {{action}} · {{when}}',
                'body' => "{{customer}} {{action}} their booking for {{services}} on \"{{link_title}}\".\n\nWhen: {{when}}\n\nManage bookings: {{bookings_url}}",
                'pref_type' => 'service_booking.visitor_change',
                'variables' => [
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'action' => ['label' => 'What they did', 'sample' => 'rescheduled'],
                    'services' => ['label' => 'Booked services', 'sample' => 'Haircut'],
                    'when' => ['label' => 'Appointment slot', 'sample' => 'Mon, Jan 5 · 9:00 AM'],
                    'link_title' => ['label' => 'Page title', 'sample' => "Sam's Studio"],
                    'bookings_url' => ['label' => 'Bookings dashboard URL', 'sample' => 'https://sayzio.app/user/links/1/service-booking/bookings'],
                ],
            ],
            'service_booking.staff_booking' => [
                'category' => 'messaging',
                'label' => 'Booking update for team member (staff)',
                'description' => 'Emails an assigned team member when a booking is placed, rescheduled or cancelled for them (requires a notification email on the staff record).',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Booking {{action}} for you · {{when}}',
                'body' => "Hi {{staff_name}},\n\nA booking on \"{{link_title}}\" was {{action}} for you.\n\nCustomer: {{customer}}\nServices: {{services}}\nWhen: {{when}}",
                'pref_type' => null,
                'variables' => [
                    'staff_name' => ['label' => 'Team member name', 'sample' => 'Priya'],
                    'action' => ['label' => 'What happened', 'sample' => 'placed'],
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'services' => ['label' => 'Booked services', 'sample' => 'Haircut'],
                    'when' => ['label' => 'Appointment slot', 'sample' => 'Mon, Jan 5 · 9:00 AM'],
                    'link_title' => ['label' => 'Page title', 'sample' => "Sam's Studio"],
                ],
            ],
            'service_booking.staff_reminder' => [
                'category' => 'messaging',
                'label' => 'Booking appointment reminder (staff)',
                'description' => 'Sent to the assigned team member a configurable time before a confirmed appointment (requires a notification email on the staff record).',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Reminder: your appointment with {{customer}} is {{lead_label}} away · {{when}}',
                'body' => "Hi {{staff_name}},\n\nJust a reminder that your appointment on \"{{link_title}}\" is coming up in {{lead_label}}.\n\nCustomer: {{customer}}\nServices: {{services}}\nWhen: {{when}}",
                'pref_type' => null,
                'variables' => [
                    'staff_name' => ['label' => 'Team member name', 'sample' => 'Priya'],
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'services' => ['label' => 'Booked services', 'sample' => 'Haircut'],
                    'when' => ['label' => 'Appointment slot', 'sample' => 'Mon, Jan 5 · 9:00 AM'],
                    'lead_label' => ['label' => 'Lead time label', 'sample' => '24 hours'],
                    'link_title' => ['label' => 'Page title', 'sample' => "Sam's Studio"],
                ],
            ],
            'service_booking.request_received' => [
                'category' => 'messaging',
                'label' => 'Booking request received (visitor)',
                'description' => 'Confirms to a visitor that their booking request was received, with a link to track its status.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Booking request received · {{when}}',
                'body' => "Hi {{customer}},\n\nThanks — we've received your booking request for {{services}} on \"{{link_title}}\".\n\nWhen: {{when}}\nEstimated total: {{currency}} {{total}} (estimate only — no payment taken)\n\nWe'll let you know as soon as it's confirmed. Track your booking anytime here:\n{{status_url}}",
                'pref_type' => null,
                'variables' => [
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'services' => ['label' => 'Requested services', 'sample' => 'Haircut'],
                    'when' => ['label' => 'Requested slot', 'sample' => 'Mon, Jan 5 · 9:00 AM'],
                    'currency' => ['label' => 'Currency', 'sample' => 'USD'],
                    'total' => ['label' => 'Estimated total', 'sample' => '40.00'],
                    'link_title' => ['label' => 'Page title', 'sample' => "Sam's Studio"],
                    'status_url' => ['label' => 'Tokenized status URL', 'sample' => 'https://sayzio.app/sb/booking/abc123'],
                ],
            ],
            'service_booking.payment_confirmed' => [
                'category' => 'messaging',
                'label' => 'Booking payment confirmed (visitor)',
                'description' => 'Sent to the visitor when their payment for a service booking is accepted and the appointment is confirmed.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Payment confirmed — your booking is set · {{when}}',
                'body' => "Hi {{customer}},\n\nYour payment of {{currency}} {{amount}} has been received and your booking for {{services}} on \"{{link_title}}\" is confirmed.\n\nWhen: {{when}}\n\nView your booking here:\n{{status_url}}",
                'pref_type' => null,
                'variables' => [
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'services' => ['label' => 'Booked services', 'sample' => 'Haircut & Colour'],
                    'when' => ['label' => 'Appointment slot', 'sample' => 'Mon, Jan 5 · 9:00 AM'],
                    'currency' => ['label' => 'Payment currency', 'sample' => 'USD'],
                    'amount' => ['label' => 'Amount paid', 'sample' => '25.00'],
                    'link_title' => ['label' => 'Page title', 'sample' => "Sam's Studio"],
                    'status_url' => ['label' => 'Tokenized status URL', 'sample' => 'https://sayzio.app/sb/booking/abc123'],
                ],
            ],
            'service_booking.reminder' => [
                'category' => 'messaging',
                'label' => 'Booking appointment reminder (visitor)',
                'description' => 'Sent to the visitor a configurable time before their confirmed appointment.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Reminder: your appointment is {{lead_label}} away · {{when}}',
                'body' => "Hi {{customer}},\n\nJust a reminder that your appointment for {{services}} on \"{{link_title}}\" is coming up in {{lead_label}}.\n\nWhen: {{when}}\n\nView the full details here:\n{{status_url}}",
                'pref_type' => null,
                'variables' => [
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'services' => ['label' => 'Booked services', 'sample' => 'Haircut'],
                    'when' => ['label' => 'Appointment slot', 'sample' => 'Mon, Jan 5 · 9:00 AM'],
                    'lead_label' => ['label' => 'Lead time label', 'sample' => '24 hours'],
                    'link_title' => ['label' => 'Page title', 'sample' => "Sam's Studio"],
                    'status_url' => ['label' => 'Tokenized status URL', 'sample' => 'https://sayzio.app/sb/booking/abc123'],
                ],
            ],
            'service_booking.status_changed' => [
                'category' => 'messaging',
                'label' => 'Booking status updated (visitor)',
                'description' => 'Notifies a visitor when the provider changes their booking status (confirmed / declined / completed / cancelled).',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your booking is now {{status}} · {{when}}',
                'body' => "Hi {{customer}},\n\nYour booking for {{services}} on \"{{link_title}}\" is now {{status}}.\n\nWhen: {{when}}\n\nView the full details here:\n{{status_url}}",
                'pref_type' => null,
                'variables' => [
                    'customer' => ['label' => 'Customer name', 'sample' => 'Sam Carter'],
                    'services' => ['label' => 'Requested services', 'sample' => 'Haircut'],
                    'when' => ['label' => 'Requested slot', 'sample' => 'Mon, Jan 5 · 9:00 AM'],
                    'status' => ['label' => 'New status', 'sample' => 'Confirmed'],
                    'link_title' => ['label' => 'Page title', 'sample' => "Sam's Studio"],
                    'status_url' => ['label' => 'Tokenized status URL', 'sample' => 'https://sayzio.app/sb/booking/abc123'],
                ],
            ],
            // Delivery Project two-way updates (Task #3566).
            'delivery_project.client_comment' => [
                'category' => 'messaging',
                'label' => 'New client comment (team)',
                'description' => 'Alerts the workspace team when a client or buyer posts a comment/question on a delivery project.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New comment on "{{project_title}}"',
                'body' => "{{author_name}} commented on \"{{project_title}}\":\n\n{{body}}\n\nView the project: {{project_url}}",
                'pref_type' => 'delivery_project.comment',
                'variables' => [
                    'author_name' => ['label' => 'Comment author', 'sample' => 'Sam Carter'],
                    'project_title' => ['label' => 'Project title', 'sample' => 'Kitchen remodel'],
                    'body' => ['label' => 'Comment body', 'sample' => 'When will the cabinets arrive?'],
                    'project_url' => ['label' => 'Project dashboard URL', 'sample' => 'https://sayzio.app/user/delivery-projects/1'],
                ],
            ],
            'delivery_project.team_reply' => [
                'category' => 'messaging',
                'label' => 'Team reply (client)',
                'description' => 'Notifies a client/buyer when a workspace member replies to their comment on a shared delivery project.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New update on your project "{{project_title}}"',
                'body' => "Hi {{client_name}},\n\n{{author_name}} replied on \"{{project_title}}\":\n\n{{body}}\n\nView your project: {{project_url}}",
                'pref_type' => null,
                'variables' => [
                    'client_name' => ['label' => 'Client name', 'sample' => 'Sam Carter'],
                    'author_name' => ['label' => 'Team member', 'sample' => 'Alex Rivera'],
                    'project_title' => ['label' => 'Project title', 'sample' => 'Kitchen remodel'],
                    'body' => ['label' => 'Reply body', 'sample' => 'The cabinets ship Monday.'],
                    'project_url' => ['label' => 'Public share URL', 'sample' => 'https://sayzio.app/dp/abc123'],
                ],
            ],
            'delivery_project.task_completed' => [
                'category' => 'messaging',
                'label' => 'Delivery step completed (client)',
                'description' => 'Tells a client/buyer when a task/step on their delivery project is completed, with overall progress.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'A step is done on "{{project_title}}"',
                'body' => "Hi {{client_name}},\n\nGood news — \"{{task_title}}\" is now complete on your project \"{{project_title}}\".\n\nOverall progress: {{progress}}%\n\nTrack your project: {{project_url}}",
                'pref_type' => null,
                'variables' => [
                    'client_name' => ['label' => 'Client name', 'sample' => 'Sam Carter'],
                    'task_title' => ['label' => 'Completed step', 'sample' => 'Install cabinets'],
                    'project_title' => ['label' => 'Project title', 'sample' => 'Kitchen remodel'],
                    'progress' => ['label' => 'Overall progress %', 'sample' => '75'],
                    'project_url' => ['label' => 'Public share URL', 'sample' => 'https://sayzio.app/dp/abc123'],
                ],
            ],
            'delivery_project.completed' => [
                'category' => 'messaging',
                'label' => 'Project completed (client)',
                'description' => 'Tells a client/buyer when their delivery project is marked complete.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'Your project "{{project_title}}" is complete',
                'body' => "Hi {{client_name}},\n\nYour project \"{{project_title}}\" is now complete. Thank you!\n\nView the final delivery: {{project_url}}",
                'pref_type' => null,
                'variables' => [
                    'client_name' => ['label' => 'Client name', 'sample' => 'Sam Carter'],
                    'project_title' => ['label' => 'Project title', 'sample' => 'Kitchen remodel'],
                    'project_url' => ['label' => 'Public share URL', 'sample' => 'https://sayzio.app/dp/abc123'],
                ],
            ],
            'delivery_project.warranty_reminder' => [
                'category' => 'messaging',
                'label' => 'Warranty reminder (client)',
                'description' => 'Reminds a client/buyer that their delivery project warranty is ending soon or has expired.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => '{{headline}} · {{project_title}}',
                'body' => "Hi {{client_name}},\n\n{{message}}\n\nWarranty date: {{expires_at}}\n\nView your project: {{project_url}}",
                'pref_type' => null,
                'variables' => [
                    'client_name' => ['label' => 'Client name', 'sample' => 'Sam Carter'],
                    'headline' => ['label' => 'Headline', 'sample' => 'Warranty ending soon'],
                    'message' => ['label' => 'Body message', 'sample' => 'The warranty for your project is ending soon.'],
                    'project_title' => ['label' => 'Project title', 'sample' => 'Kitchen remodel'],
                    'expires_at' => ['label' => 'Warranty date', 'sample' => 'Jul 4, 2026'],
                    'project_url' => ['label' => 'Public share URL', 'sample' => 'https://sayzio.app/dp/abc123'],
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
            'inbox.reply' => [
                'category' => 'messaging',
                'label' => 'Inbox reply',
                'description' => 'A reply a creator (or the autopilot agent) sends back to a form/subscriber/sponsorship contact from the unified inbox. Body is composed by the creator and rendered at the call site.',
                'format' => 'html',
                'body_type' => 'dynamic',
                'subject' => 'Re: {{thread_subject}}',
                'body' => '{{body}}',
                'variables' => [
                    'thread_subject' => ['label' => 'Original thread subject', 'sample' => 'Your message'],
                    'body' => ['label' => 'Reply body (rendered HTML)', 'sample' => '<p>Thanks for reaching out!</p>'],
                ],
            ],
            'subscriber.broadcast' => [
                'category' => 'messaging',
                'label' => 'Subscriber broadcast',
                'description' => 'A message a creator composes and sends to their email subscribers from the Subscribers compose screen. Body is composed at the call site.',
                'format' => 'html',
                'body_type' => 'dynamic',
                'subject' => 'Update',
                'body' => '{{body}}',
                'variables' => [
                    'body' => ['label' => 'Broadcast body (rendered HTML)', 'sample' => '<p>Here is what is new this week.</p>'],
                ],
            ],
            'follower.instant_update' => [
                'category' => 'messaging',
                'label' => 'Follower activity (instant)',
                'description' => 'Emailed immediately to a follower who has instant follower-update notifications on, when a creator they follow posts new activity.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => 'New activity from a creator you follow',
                'body' => '{{creator_name}}: {{message}}',
                'variables' => [
                    'creator_name' => ['label' => 'Creator name', 'sample' => 'Alex Rivera'],
                    'message' => ['label' => 'Activity message', 'sample' => 'just posted a new update.'],
                ],
            ],
            'form.notification' => [
                'category' => 'messaging',
                'label' => 'Form submission notification',
                'description' => 'Notifies the form owner (or a configured recipient) of a new form submission. Body is composed at the call site and may go out through a user-selected email integration.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => 'New form submission',
                'body' => 'You have a new form submission.',
                'variables' => [],
            ],
            'inbox.forward' => [
                'category' => 'messaging',
                'label' => 'Inbox forwarding delivery',
                'description' => 'Forwards a new inbox item (form/subscriber/DM) to a creator-configured forwarding email address. Body is composed at the call site.',
                'format' => 'text',
                'body_type' => 'dynamic',
                'subject' => '[Sayzio Inbox] New item',
                'body' => 'A new inbox item was received.',
                'variables' => [],
            ],
            'client_portal.magic_link' => [
                'category' => 'messaging',
                'label' => 'Client portal magic link',
                'description' => 'Emails a client/teammate a magic link to access a client portal. Sends through the workspace billing company SMTP when configured, otherwise the platform mailer.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => '{{portal_name}} — your portal access',
                'body' => "You've been given access to the {{portal_name}} client portal.\n\nOpen: {{url}}\n\nThis link expires in {{expires_in}} day(s).",
                'variables' => [
                    'portal_name' => ['label' => 'Portal / brand name', 'sample' => 'Acme Studio'],
                    'url' => ['label' => 'Magic link URL', 'sample' => 'https://sayzio.app/portal/start/abc123'],
                    'expires_in' => ['label' => 'Days until the link expires', 'sample' => '30'],
                ],
            ],
            'blog.comment_approved' => [
                'category' => 'messaging',
                'label' => 'Blog comment approved',
                'description' => 'Tells a commenter their blog comment was approved and is now live.',
                'format' => 'html',
                'body_type' => 'inline',
                'subject' => 'Your comment was approved',
                'body' => '<p>Hi {{name}},</p><p>Your comment has been approved and is now live.</p><p><a href="{{url}}">View your comment</a></p>',
                'variables' => [
                    'name' => ['label' => 'Commenter name', 'sample' => 'Alex Rivera'],
                    'url' => ['label' => 'Comment URL', 'sample' => 'https://sayzio.app/blog/hello#comment-1'],
                ],
            ],
            'blog.comment_reply' => [
                'category' => 'messaging',
                'label' => 'Blog comment reply',
                'description' => 'Tells a commenter a staff member replied to their blog comment.',
                'format' => 'html',
                'body_type' => 'inline',
                'subject' => 'New reply on your blog comment',
                'body' => '<p>Hi {{name}},</p><p><strong>{{reply_author}}</strong> replied to your comment:</p><blockquote style="border-left:3px solid #7c3aed;padding-left:12px;color:#374151;">{{reply_body}}</blockquote><p><a href="{{url}}">View the reply</a></p>',
                'variables' => [
                    'name' => ['label' => 'Commenter name', 'sample' => 'Alex Rivera'],
                    'reply_author' => ['label' => 'Staff member name', 'sample' => 'Sam Carter'],
                    'reply_body' => ['label' => 'Reply body (rendered HTML)', 'sample' => 'Thanks for the kind words!'],
                    'url' => ['label' => 'Reply URL', 'sample' => 'https://sayzio.app/blog/hello#comment-2'],
                ],
            ],

            // ----------------------------------------------------------------
            // Zio Digest (Task #5620) — delivered via the SendGrid HTTP API,
            // logged through the same email_logs conventions.
            // ----------------------------------------------------------------
            'digest.issue' => [
                'category' => 'digest',
                'label' => 'Zio Digest issue',
                'description' => 'A broadcast Zio Digest sent to the selected audience (body composed in the digest editor, delivered via SendGrid).',
                'format' => 'html',
                'body_type' => 'dynamic',
                'subject' => '{{subject}}',
                'body' => '{{body}}',
                'variables' => [
                    'subject' => ['label' => 'Digest title', 'sample' => 'This week at Sayzio'],
                ],
            ],
            'digest.test' => [
                'category' => 'digest',
                'label' => 'Zio Digest test send',
                'description' => 'A preview/test copy of a Zio Digest an admin sends to their own inbox before broadcasting.',
                'format' => 'html',
                'body_type' => 'dynamic',
                'subject' => '{{subject}}',
                'body' => '{{body}}',
                'variables' => [
                    'subject' => ['label' => 'Test subject', 'sample' => '[TEST] This week at Sayzio'],
                ],
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
            'newsletter.test' => [
                'category' => 'newsletter',
                'label' => 'Newsletter test send',
                'description' => 'A preview/test copy of a drafted newsletter issue an admin sends to their own inbox to check rendering. Body is the draft composed in the issue editor.',
                'format' => 'html',
                'body_type' => 'dynamic',
                'subject' => '{{subject}}',
                'body' => '{{body}}',
                'variables' => [
                    'subject' => ['label' => 'Test subject', 'sample' => '[TEST] This month at Sayzio'],
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
            'contact.relay' => [
                'category' => 'system',
                'label' => 'Contact form relay',
                'description' => 'Relays a marketing "Contact us" page submission to the configured contact recipient inbox.',
                'format' => 'text',
                'body_type' => 'inline',
                'subject' => '[Sayzio Contact] {{subject}}',
                'body' => "New contact message from {{sender_name}} <{{sender_email}}>\n\nSubject: {{subject}}\n\n{{message}}",
                'variables' => [
                    'sender_name' => ['label' => 'Sender name', 'sample' => 'Alex Rivera'],
                    'sender_email' => ['label' => 'Sender email', 'sample' => 'alex@example.com'],
                    'subject' => ['label' => 'Message subject', 'sample' => 'Question about pricing'],
                    'message' => ['label' => 'Message body', 'sample' => 'Hi, I wanted to ask...'],
                ],
            ],
            'contact.inbox_reply' => [
                'category' => 'system',
                'label' => 'Contact inbox reply',
                'description' => 'An admin reply to a visitor message submitted through the contact form, sent from the Contact Inbox.',
                'format' => 'html',
                'body_type' => 'view',
                'view' => 'emails.contact-inbox-reply',
                'subject' => '{{reply_subject}}',
                'variables' => [
                    'recipient_name'   => ['label' => 'Sender name', 'sample' => 'Alex Rivera'],
                    'reply_subject'    => ['label' => 'Reply subject', 'sample' => 'Re: Question about pricing'],
                    'reply_body'       => ['label' => 'Reply body', 'sample' => 'Thanks for reaching out! Here is the answer to your question.'],
                    'original_message' => ['label' => 'Original message body', 'sample' => 'Hi, I wanted to ask about pricing...'],
                    'app_name'         => ['label' => 'Application name', 'sample' => 'Sayzio'],
                ],
                'sample_view' => [
                    'reply_body'       => 'Thanks for reaching out! Here is the answer to your question.',
                    'original_message' => 'Hi, I wanted to ask about pricing...',
                ],
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
        'digest'       => 'Zio Digest',
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
