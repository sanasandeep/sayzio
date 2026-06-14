<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An Expo push token registered by a 1inme-mobile install (task #1403).
 *
 * @property int $id
 * @property int $user_id
 * @property string $token
 * @property string|null $platform
 * @property string|null $device_name
 * @property \Illuminate\Support\Carbon|null $last_used_at
 */
class DevicePushToken extends Model
{
    protected $table = 'device_push_tokens';

    protected $fillable = [
        'user_id',
        'token',
        'platform',
        'device_name',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
