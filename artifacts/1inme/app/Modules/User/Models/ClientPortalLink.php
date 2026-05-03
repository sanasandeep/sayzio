<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ClientPortalLink extends Model
{
    use BelongsToWorkspace;

    protected $table = 'client_portal_links';

    protected $fillable = [
        'portal_id', 'workspace_id', 'email', 'token',
        'expires_at', 'revoked_at', 'sent_at', 'last_used_at',
    ];

    protected $casts = [
        'expires_at'   => 'datetime',
        'revoked_at'   => 'datetime',
        'sent_at'      => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function portal()
    {
        return $this->belongsTo(ClientPortal::class, 'portal_id');
    }

    public static function newToken(): string
    {
        return Str::random(56);
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        return true;
    }

    public function statusLabel(): string
    {
        if ($this->revoked_at) return 'Revoked';
        if ($this->expires_at && $this->expires_at->isPast()) return 'Expired';
        if ($this->last_used_at) return 'Active';
        return 'Sent';
    }
}
