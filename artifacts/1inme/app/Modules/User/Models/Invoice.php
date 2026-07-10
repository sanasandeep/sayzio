<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'number', 'financial_year', 'seq', 'user_id', 'currency',
        'subtotal_minor', 'tax_total_minor', 'grand_total_minor',
        'billing_address_snapshot', 'merchant_snapshot',
        'line_items', 'tax_breakdown', 'reverse_charge_note',
        'place_of_supply', 'issued_at',
        'subscription_id', 'gateway', 'status', 'paid_at',
        // Client-invoice (kanban -> Stripe) extensions:
        'kind', 'workspace_id', 'vault_client_id', 'recipient_email',
        'discount_minor', 'due_date', 'notes_md', 'sent_at',
        // Invoicing & accounting suite extensions:
        'billing_company_id', 'recurring_invoice_id', 'inbox_thread_id',
        'amount_paid_minor', 'paid_method',
        // Contact/lead recipients + letterhead override (Task #3522):
        'contact_id', 'recipient_name', 'recipient_address',
        'letterhead_path', 'letterhead_orientation',
        'letterhead_margin_top', 'letterhead_margin_right',
        'letterhead_margin_bottom', 'letterhead_margin_left',
        'letterhead_width', 'letterhead_height',
    ];

    protected function casts(): array
    {
        return [
            'billing_address_snapshot' => 'array',
            'merchant_snapshot'        => 'array',
            'line_items'               => 'array',
            'tax_breakdown'            => 'array',
            'subtotal_minor'           => 'integer',
            'tax_total_minor'          => 'integer',
            'grand_total_minor'        => 'integer',
            'seq'                      => 'integer',
            'issued_at'                => 'datetime',
            'paid_at'                  => 'datetime',
            'discount_minor'           => 'integer',
            'due_date'                 => 'date',
            'sent_at'                  => 'datetime',
            'amount_paid_minor'        => 'integer',
            'contact_id'               => 'integer',
            'letterhead_margin_top'    => 'integer',
            'letterhead_margin_right'  => 'integer',
            'letterhead_margin_bottom' => 'integer',
            'letterhead_margin_left'   => 'integer',
            'letterhead_width'         => 'integer',
            'letterhead_height'        => 'integer',
        ];
    }

    public function billingCompany()
    {
        return $this->belongsTo(\App\Modules\User\Models\BillingCompany::class, 'billing_company_id');
    }

    public function recurringInvoice()
    {
        return $this->belongsTo(\App\Modules\User\Models\RecurringInvoice::class, 'recurring_invoice_id');
    }

    public function receipts()
    {
        return $this->hasMany(\App\Modules\User\Models\Receipt::class);
    }

    public function latestReceipt()
    {
        return $this->hasOne(\App\Modules\User\Models\Receipt::class)->latestOfMany();
    }

    /** Net amount still owed after partial payments + refunds. */
    public function refundedTotalMinor(): int
    {
        return (int) $this->refunds()->where('status', 'succeeded')->sum('amount_minor');
    }

    public function balanceMinor(): int
    {
        return max(0, (int) $this->grand_total_minor - (int) $this->amount_paid_minor);
    }

    public function isClientInvoice(): bool
    {
        return ($this->kind ?? 'subscription') === 'client';
    }

    /**
     * Per-invoice secret embedded in the public, signed pay URL and validated
     * server-side on every hit (see ClientInvoiceController::payPage /
     * payHandoff). Lazily generated + persisted so any pay URL built for this
     * invoice always carries a token. Rotating it (see rotatePayLinkToken)
     * immediately invalidates every previously-issued link.
     */
    public function payLinkToken(): string
    {
        if (empty($this->pay_link_token)) {
            $this->forceFill(['pay_link_token' => \Illuminate\Support\Str::random(40)])->save();
        }
        return (string) $this->pay_link_token;
    }

    /**
     * Mint a fresh pay-link token, revoking every link issued with the old one.
     * Called when the recipient changes or the invoice is (re)sent, so a
     * mis-sent link cannot be used to view/pay after the owner corrects it.
     */
    public function rotatePayLinkToken(): void
    {
        $this->forceFill(['pay_link_token' => \Illuminate\Support\Str::random(40)])->save();
    }

    /** Timing-safe check that a request-supplied token is the current one. */
    public function payLinkTokenMatches(?string $token): bool
    {
        return !empty($this->pay_link_token)
            && is_string($token)
            && hash_equals((string) $this->pay_link_token, $token);
    }

    /**
     * Build the public, temporary-signed pay URL for this invoice. Centralizes
     * signing + token embedding so every caller (web, API, service, recurring
     * auto-send) produces revocable, expiring links identically.
     */
    public function payLinkUrl(int $days = 30): string
    {
        return \Illuminate\Support\Facades\URL::temporarySignedRoute(
            'client-invoice.pay',
            now()->addDays($days),
            ['invoice' => $this->getKey(), 't' => $this->payLinkToken()]
        );
    }

    /**
     * The Emailer key used when a client invoice is emailed with its pay link
     * (see ClientInvoiceService::markSent). The latest email_logs row for this
     * key + invoice is what drives the persistent "last send failed" indicator
     * in the UI.
     */
    public const SEND_EMAIL_KEY = 'billing.client_invoice';

    /**
     * Whether the most recent attempt to email this invoice's pay link failed
     * (the invoice is therefore NOT delivered). Derived from the latest
     * email_logs row so the signal survives reloads, unlike a one-shot flash.
     */
    public function lastSendFailed(): bool
    {
        return \App\Modules\Common\Models\EmailLog::query()
            ->where('email_key', self::SEND_EMAIL_KEY)
            ->where('related_type', $this->getMorphClass())
            ->where('related_id', (string) $this->getKey())
            ->orderByDesc('id')
            ->value('status') === 'failed';
    }

    /**
     * A short, human-friendly reason the most recent send attempt failed,
     * derived from the latest email_logs `error` for this invoice. Returns null
     * when the latest attempt did NOT fail (or there is no attempt logged).
     * The raw transport error is sanitized via EmailErrorSummary so no
     * sensitive/internal detail is exposed to creators.
     */
    public function lastSendFailedReason(): ?string
    {
        $row = \App\Modules\Common\Models\EmailLog::query()
            ->where('email_key', self::SEND_EMAIL_KEY)
            ->where('related_type', $this->getMorphClass())
            ->where('related_id', (string) $this->getKey())
            ->orderByDesc('id')
            ->first(['status', 'error']);

        if (!$row || $row->status !== 'failed') {
            return null;
        }

        return \App\Modules\Common\Support\EmailErrorSummary::summarize($row->error);
    }

    /**
     * Batch the "last send attempt failed" signal for a collection of invoices
     * so list screens don't N+1 the email_logs table. Returns a map keyed by
     * invoice id => bool (true when the latest send attempt failed).
     *
     * @param  iterable<\App\Modules\User\Models\Invoice|int|string>  $invoices
     * @return array<int,bool>
     */
    public static function sendFailedMap(iterable $invoices): array
    {
        $ids = collect($invoices)
            ->map(fn ($i) => (string) ($i instanceof self ? $i->getKey() : $i))
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->all();
        if (empty($ids)) return [];

        // Ordered by id DESC so the first row seen per related_id is the latest.
        $rows = \App\Modules\Common\Models\EmailLog::query()
            ->where('email_key', self::SEND_EMAIL_KEY)
            ->where('related_type', (new self)->getMorphClass())
            ->whereIn('related_id', $ids)
            ->orderByDesc('id')
            ->get(['related_id', 'status']);

        $map = [];
        foreach ($rows as $r) {
            if (!array_key_exists((int) $r->related_id, $map)) {
                $map[(int) $r->related_id] = $r->status === 'failed';
            }
        }
        return $map;
    }

    public function vaultClient()
    {
        return $this->belongsTo(\App\Modules\User\Models\VaultClient::class, 'vault_client_id');
    }

    public function contact()
    {
        return $this->belongsTo(\App\Modules\User\Models\Contact::class, 'contact_id');
    }

    /** Whether this invoice has its own letterhead override (vs. inheriting the company's). */
    public function hasLetterheadOverride(): bool
    {
        return (bool) $this->letterhead_path;
    }

    public function workspace()
    {
        return $this->belongsTo(\App\Modules\User\Models\Workspace::class, 'workspace_id');
    }

    public function sourceCards()
    {
        return $this->belongsToMany(
            \App\Modules\User\Models\TaskCard::class,
            'client_invoice_cards',
            'invoice_id',
            'card_id'
        )->withTimestamps();
    }

    public function scopeClient($q)      { return $q->where('kind', 'client'); }
    public function scopeSubscription($q){ return $q->where('kind', 'subscription'); }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function paymentAttempts()
    {
        return $this->hasMany(PaymentAttempt::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function creditNotes()
    {
        return $this->hasMany(CreditNote::class);
    }
}
