<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Root config for a Service Booking page (Task #3085) — one per `service_booking`
 * link. Mirrors RestaurantMenu: a display/booking mode, currency, accent colour,
 * a tax line stored in the `settings` JSON, plus scheduling knobs (slot length,
 * lead time, booking window, timezone) that drive available-slot generation.
 */
class ServiceBooking extends Model
{
    public const MODE_DISPLAY = 'display';
    public const MODE_BOOKING = 'booking';

    protected $fillable = [
        'link_id', 'user_id', 'mode', 'currency', 'accent_color',
        'slot_length_minutes', 'lead_time_minutes', 'max_days_ahead',
        'timezone', 'settings',
    ];

    protected $attributes = [
        'accent_color' => '#3d6bff',
    ];

    protected function casts(): array
    {
        return [
            'slot_length_minutes' => 'integer',
            'lead_time_minutes'   => 'integer',
            'max_days_ahead'      => 'integer',
            'settings'            => 'array',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->hasMany(ServiceBookingCategory::class)->orderBy('sort_order')->orderBy('id');
    }

    public function services()
    {
        return $this->hasMany(ServiceBookingService::class)->orderBy('sort_order')->orderBy('id');
    }

    public function availabilityRules()
    {
        return $this->hasMany(ServiceBookingAvailabilityRule::class)->orderBy('day_of_week')->orderBy('start_time');
    }

    public function blockedDates()
    {
        return $this->hasMany(ServiceBookingBlockedDate::class)->orderBy('date');
    }

    public function requests()
    {
        return $this->hasMany(ServiceBookingRequest::class)->latest();
    }

    public function staff()
    {
        return $this->hasMany(ServiceBookingStaff::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isBookingMode(): bool
    {
        return $this->mode === self::MODE_BOOKING;
    }

    public function effectiveTimezone(): string
    {
        return \App\Support\PlatformTimezone::resolve($this->timezone);
    }

    // ── Tax / GST settings (stored in the `settings` JSON) ───────────

    public function taxEnabled(): bool
    {
        return (bool) ($this->settings['tax']['enabled'] ?? false);
    }

    public function taxRate(): float
    {
        return (float) ($this->settings['tax']['rate'] ?? 0);
    }

    public function taxInclusive(): bool
    {
        return (bool) ($this->settings['tax']['inclusive'] ?? false);
    }

    public function taxLabel(): string
    {
        $label = trim((string) ($this->settings['tax']['label'] ?? ''));

        return $label !== '' ? $label : 'GST';
    }

    // ── Buffers (Task #6325, stored in the `settings` JSON) ──────────

    /** Page-level default buffer before each appointment, in minutes. */
    public function bufferBeforeMinutes(): int
    {
        return max(0, (int) ($this->settings['buffers']['before'] ?? 0));
    }

    /** Page-level default buffer after each appointment, in minutes. */
    public function bufferAfterMinutes(): int
    {
        return max(0, (int) ($this->settings['buffers']['after'] ?? 0));
    }

    // ── Visitor self-service (Task #6325) ────────────────────────────

    public function selfServiceAllowsCancel(): bool
    {
        return (bool) ($this->settings['self_service']['allow_cancel'] ?? true);
    }

    public function selfServiceAllowsReschedule(): bool
    {
        return (bool) ($this->settings['self_service']['allow_reschedule'] ?? true);
    }

    /** Hours before the appointment after which self-service changes lock. */
    public function selfServiceCutoffHours(): int
    {
        return max(0, (int) ($this->settings['self_service']['cutoff_hours'] ?? 24));
    }

    // ── Google Calendar sync (Task #6325) ────────────────────────────

    public function calendarSyncEnabled(): bool
    {
        return (bool) ($this->settings['calendar_sync']['enabled'] ?? false);
    }

    /** Page-level (owner) calendar account id used when no staff calendar applies. */
    public function calendarSyncAccountId(): ?int
    {
        $id = (int) ($this->settings['calendar_sync']['account_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /** True when any active staff members exist (switches slot math to per-staff). */
    public function hasStaff(): bool
    {
        return $this->staff()->where('is_active', true)->exists();
    }
}
