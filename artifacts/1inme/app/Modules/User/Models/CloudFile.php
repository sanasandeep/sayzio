<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class CloudFile extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'added_by_user_id', 'connection_id', 'provider',
        'remote_id', 'name', 'mime', 'size', 'link',
        'thumbnail_url', 'parent_folder_path', 'added_at',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'size'     => 'integer',
    ];

    public function addedBy()
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function connection()
    {
        return $this->belongsTo(CloudConnection::class);
    }

    public function providerLabel(): string
    {
        return CloudProviderApp::PROVIDER_LABELS[$this->provider] ?? $this->provider;
    }

    public function providerIcon(): string
    {
        return CloudProviderApp::PROVIDER_ICONS[$this->provider] ?? 'fa-cloud';
    }

    public function humanSize(): string
    {
        $b = (int) $this->size;
        if ($b <= 0) return '—';
        $u = ['B','KB','MB','GB','TB'];
        $i = (int) floor(log($b, 1024));
        $i = min($i, count($u) - 1);
        return round($b / (1024 ** $i), $i ? 1 : 0) . ' ' . $u[$i];
    }
}
