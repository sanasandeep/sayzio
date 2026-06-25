<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'form_id', 'data', 'files', 'ip', 'user_agent', 'referrer',
        'country', 'is_spam', 'spam_reason', 'is_read', 'is_starred',
        'payment_status', 'amount_cents', 'currency', 'gateway',
        'gateway_charge_id', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'files' => 'array',
            'is_spam' => 'boolean',
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** A paid-form submission whose charge has cleared. */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /**
     * "Completed" submissions for every owner-facing count and list. A paid
     * form's 'pending' rows are unpaid / abandoned checkout attempts and must
     * never be treated as real submissions. Free-form rows ('none' or a legacy
     * NULL payment_status) are always included.
     */
    public function scopeCompleted($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('payment_status')->orWhere('payment_status', '!=', 'pending');
        });
    }

    /** A paid-form submission awaiting the customer's gateway return. */
    public function isAwaitingPayment(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    /**
     * Parent record used by the BelongsToWorkspace trait to derive
     * workspace_id when a submission is created from a public visitor
     * (no current_workspace bound). Without this, public submissions
     * land with NULL workspace_id and are then hidden by the global
     * scope when the owner views their inbox.
     */
    public function parentForWorkspace()
    {
        if ($this->form_id) {
            return Form::withoutGlobalScope('workspace')->find($this->form_id);
        }
        return null;
    }
}
