<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class ClientPortal extends Model
{
    use BelongsToWorkspace;

    protected $table = 'client_portals';

    protected $fillable = [
        'workspace_id', 'vault_client_id', 'created_by_user_id',
        'name', 'brand_name', 'brand_color', 'brand_logo_url',
        'welcome_message', 'is_enabled', 'last_seen_at',
    ];

    protected $casts = [
        'is_enabled'   => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }

    public function vaultClient()
    {
        return $this->belongsTo(VaultClient::class, 'vault_client_id');
    }

    public function shares()
    {
        return $this->hasMany(ClientPortalShare::class, 'portal_id')->orderBy('position');
    }

    public function links()
    {
        return $this->hasMany(ClientPortalLink::class, 'portal_id')->orderByDesc('id');
    }

    public function actions()
    {
        return $this->hasMany(ClientPortalAction::class, 'portal_id')->orderByDesc('occurred_at');
    }

    public function brandingName(): string
    {
        return $this->brand_name ?: ($this->workspace?->name ?? config('app.name'));
    }

    public function brandingColor(): string
    {
        $c = $this->brand_color ?: '#7c3aed';
        return preg_match('/^#?[0-9A-Fa-f]{3,8}$/', $c) ? (str_starts_with($c, '#') ? $c : '#' . $c) : '#7c3aed';
    }
}
