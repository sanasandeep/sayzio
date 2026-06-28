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

    public function vaultClient()
    {
        return $this->belongsTo(\App\Modules\User\Models\VaultClient::class, 'vault_client_id');
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
