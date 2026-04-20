<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-managed gateway configuration. Credentials are encrypted at rest
 * via Laravel's `encrypted:array` cast so the raw JSON never lives in the
 * database in plaintext; admin UI must never echo decrypted values back.
 */
class GatewaySetting extends Model
{
    protected $fillable = [
        'gateway_slug', 'display_name', 'mode',
        'credentials_encrypted', 'is_enabled', 'sort_order',
    ];

    protected $hidden = ['credentials_encrypted'];

    protected function casts(): array
    {
        return [
            'credentials_encrypted' => 'encrypted:array',
            'is_enabled'            => 'boolean',
            'sort_order'            => 'integer',
        ];
    }

    public function credentials(): array
    {
        return (array) ($this->credentials_encrypted ?? []);
    }

    public function credential(string $key, $default = null)
    {
        return $this->credentials()[$key] ?? $default;
    }
}
