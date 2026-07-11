<?php

namespace App\Modules\Admin\Models;

use App\Modules\User\Models\Invoice;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Model;

class CustomPlanRequest extends Model
{
    protected $fillable = [
        'name', 'email', 'company',
        'requirements', 'expected_volume', 'budget', 'preferred_cycle', 'message',
        'user_id', 'status',
        'admin_notes', 'decline_reason',
        'provisioned_plan_id', 'invoice_id', 'assigned_email', 'offer_cycle',
        'handled_by', 'handled_at',
    ];

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public static array $statuses = [
        'new'       => ['label' => 'New',       'color' => 'blue'],
        'reviewing' => ['label' => 'Reviewing', 'color' => 'amber'],
        'approved'  => ['label' => 'Approved',  'color' => 'green'],
        'paid'      => ['label' => 'Paid',       'color' => 'purple'],
        'declined'  => ['label' => 'Declined',   'color' => 'red'],
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function provisionedPlan()
    {
        return $this->belongsTo(Plan::class, 'provisioned_plan_id');
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function handledBy()
    {
        return $this->belongsTo(\App\Modules\Admin\Models\Admin::class, 'handled_by');
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['new', 'reviewing'], true);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function statusLabel(): string
    {
        return self::$statuses[$this->status]['label'] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::$statuses[$this->status]['color'] ?? 'gray';
    }

    /**
     * Find an approved-but-unpaid offer for the given user email.
     * Used by the payment-alert banner on sign-in.
     */
    public static function pendingOfferForEmail(string $email): ?self
    {
        return static::where('assigned_email', $email)
            ->where('status', 'approved')
            ->whereNotNull('provisioned_plan_id')
            ->orderByDesc('id')
            ->first();
    }
}
