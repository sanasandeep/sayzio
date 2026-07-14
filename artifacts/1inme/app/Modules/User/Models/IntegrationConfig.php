<?php

namespace App\Modules\User\Models;


use App\Modules\User\Concerns\BelongsToWorkspace;
use App\Modules\User\Support\IntegrationConfigRegistry;
use Illuminate\Database\Eloquent\Model;

class IntegrationConfig extends Model
{
    
    use BelongsToWorkspace;
protected $fillable = [
        'user_id', 'kind', 'provider', 'name',
        'is_active', 'is_default', 'credentials', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'is_active'   => 'boolean',
            'is_default'  => 'boolean',
            'credentials' => 'encrypted:array',
            'meta'        => 'array',
        ];
    }

    public function user() { return $this->belongsTo(User::class); }

    public function scopeKind($q, string $kind)         { return $q->where('kind', $kind); }
    public function scopeProvider($q, string $provider) { return $q->where('provider', $provider); }
    public function scopeActive($q)                     { return $q->where('is_active', true); }

    public function providerLabel(): string
    {
        return IntegrationConfigRegistry::providers($this->kind)[$this->provider]['label'] ?? ucfirst($this->provider);
    }

    public function providerIcon(): string
    {
        return IntegrationConfigRegistry::providers($this->kind)[$this->provider]['icon'] ?? 'fa-plug';
    }

    public function providerColor(): string
    {
        return IntegrationConfigRegistry::providers($this->kind)[$this->provider]['color'] ?? '#3d6bff'; // fallback = brand accent (blue)
    }

    /** Mask all credential values (preserve key shape) for safe display. */
    public function maskedCredentials(): array
    {
        $out = [];
        foreach ((array) $this->credentials as $k => $v) {
            $s = (string) $v;
            $out[$k] = strlen($s) > 6 ? substr($s, 0, 3) . str_repeat('•', 8) . substr($s, -3) : str_repeat('•', max(8, strlen($s)));
        }
        return $out;
    }
}
