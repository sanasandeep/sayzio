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
        'gateway_charge_id', 'paid_at', 'line_items', 'refunded_at',
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
            'line_items' => 'array',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * Unified contact linking (Task #6501): sniff the submitter's identity
     * out of the payload and tie the submission to the owner's Contact off
     * the hot path. Spam submissions are never linked.
     */
    protected static function booted(): void
    {
        static::created(function (FormSubmission $submission): void {
            if ($submission->is_spam) {
                return;
            }
            $ownerId = Form::withoutGlobalScope('workspace')->whereKey($submission->form_id)->value('user_id');
            if (!$ownerId) {
                return;
            }
            $identity = \App\Modules\User\Services\Contacts\ContactIdentityResolver::identityFromFormData((array) $submission->data);
            \App\Jobs\LinkCaptureToContactJob::forRecord(
                $submission, (int) $ownerId, $identity['email'], $identity['phone'], $identity['name'], 'form'
            );
        });
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /** A paid-form submission whose charge has cleared. */
    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    /** A paid submission that has already been refunded. */
    public function isRefunded(): bool
    {
        return $this->payment_status === 'refunded';
    }

    /**
     * Only a still-paid submission (not pending, free, or already
     * refunded) can be refunded by the owner.
     */
    public function isRefundable(): bool
    {
        return $this->isPaid();
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
