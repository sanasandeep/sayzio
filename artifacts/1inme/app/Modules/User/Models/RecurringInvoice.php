<?php

namespace App\Modules\User\Models;

use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * A recurring/subscription invoice template that auto-generates client
 * invoices on a schedule. Distinct from platform CreatorSubscription.
 */
class RecurringInvoice extends Model
{
    protected $fillable = [
        'user_id', 'workspace_id', 'billing_company_id', 'vault_client_id',
        'title', 'recipient_email', 'currency', 'line_items', 'discount_minor',
        'tax_rule_id', 'notes_md', 'interval', 'interval_count', 'start_date',
        'end_date', 'max_occurrences', 'occurrences_count', 'next_run_date',
        'last_run_at', 'status', 'auto_send',
    ];

    protected function casts(): array
    {
        return [
            'line_items'        => 'array',
            'discount_minor'    => 'integer',
            'interval_count'    => 'integer',
            'start_date'        => 'date',
            'end_date'          => 'date',
            'max_occurrences'   => 'integer',
            'occurrences_count' => 'integer',
            'next_run_date'     => 'date',
            'last_run_at'       => 'datetime',
            'auto_send'         => 'boolean',
        ];
    }

    public function user()           { return $this->belongsTo(User::class); }
    public function billingCompany() { return $this->belongsTo(BillingCompany::class); }
    public function vaultClient()    { return $this->belongsTo(VaultClient::class, 'vault_client_id'); }
    public function invoices()       { return $this->hasMany(Invoice::class, 'recurring_invoice_id'); }

    /** Advance next_run_date by one interval from the given date. */
    public function advanceFrom(Carbon $from): Carbon
    {
        $n = max(1, (int) $this->interval_count);
        return match ($this->interval) {
            'weekly'    => $from->copy()->addWeeks($n),
            'quarterly' => $from->copy()->addMonths(3 * $n),
            'yearly'    => $from->copy()->addYears($n),
            default     => $from->copy()->addMonths($n), // monthly
        };
    }

    public function isExhausted(): bool
    {
        if ($this->max_occurrences && $this->occurrences_count >= $this->max_occurrences) return true;
        if ($this->end_date && $this->next_run_date && $this->next_run_date->gt($this->end_date)) return true;
        return false;
    }
}
