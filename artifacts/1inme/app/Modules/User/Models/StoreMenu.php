<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class StoreMenu extends Model
{
    public const MODE_DISPLAY = 'display';
    public const MODE_ORDER   = 'order';

    protected $fillable = [
        'link_id', 'user_id', 'mode', 'currency', 'accent_color', 'settings',
    ];

    protected $attributes = [
        'accent_color' => '#3d6bff',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
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
        return $this->hasMany(StoreCategory::class, 'menu_id')->orderBy('sort_order')->orderBy('id');
    }

    public function products()
    {
        return $this->hasMany(StoreProduct::class, 'menu_id')->orderBy('sort_order')->orderBy('id');
    }

    public function orders()
    {
        return $this->hasMany(StoreOrder::class, 'menu_id')->latest();
    }

    public function isOrderMode(): bool
    {
        return $this->mode === self::MODE_ORDER;
    }

    /** Whether the store is currently accepting order requests (order mode + toggle). */
    public function acceptingOrders(): bool
    {
        if (!$this->isOrderMode()) {
            return false;
        }

        // Absent => accepting (so a store created before the toggle keeps working).
        return (bool) ($this->settings['accepting_orders'] ?? true);
    }

    /** Optional WhatsApp number stored in the settings JSON (digits only, no migration). */
    public function whatsappNumber(): ?string
    {
        $raw = trim((string) ($this->settings['whatsapp_number'] ?? ''));

        return $raw !== '' ? $raw : null;
    }
}
