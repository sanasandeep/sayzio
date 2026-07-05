<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class EventTicketTier extends Model
{
    protected $table = 'event_ticket_tiers';

    protected $fillable = [
        'link_id', 'name', 'description', 'price_cents', 'currency',
        'capacity', 'sold_count', 'sales_start', 'sales_end',
        'sort_order', 'is_active',
        'capacity_alerted_near_at', 'capacity_alerted_full_at',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'capacity'    => 'integer',
            'sold_count'  => 'integer',
            'sales_start' => 'datetime',
            'sales_end'   => 'datetime',
            'sort_order'  => 'integer',
            'is_active'   => 'boolean',
            'capacity_alerted_near_at' => 'datetime',
            'capacity_alerted_full_at' => 'datetime',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function tickets()
    {
        return $this->hasMany(EventTicket::class, 'tier_id');
    }

    public function isFree(): bool
    {
        return (int) $this->price_cents <= 0;
    }

    public function isOnSale(): bool
    {
        if (!$this->is_active) return false;
        $now = now();
        if ($this->sales_start && $now->lt($this->sales_start)) return false;
        if ($this->sales_end && $now->gt($this->sales_end)) return false;
        return true;
    }

    public function remainingCapacity(): ?int
    {
        if ($this->capacity === null) return null;
        return max(0, (int) $this->capacity - (int) $this->sold_count);
    }

    public function isSoldOut(): bool
    {
        return $this->capacity !== null && $this->remainingCapacity() <= 0;
    }

    /**
     * Fraction of capacity sold (0.0–1.0), or null for unbounded tiers.
     */
    public function capacityFilledRatio(): ?float
    {
        if ($this->capacity === null) return null;
        $cap = (int) $this->capacity;
        if ($cap <= 0) return null;
        return min(1.0, (int) $this->sold_count / $cap);
    }

    public function priceLabel(): string
    {
        if ($this->isFree()) return 'Free';
        return strtoupper($this->currency) . ' ' . number_format($this->price_cents / 100, 2);
    }
}
