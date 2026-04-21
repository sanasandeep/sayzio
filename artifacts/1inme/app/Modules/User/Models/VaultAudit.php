<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only audit log of vault actions. No public update/delete endpoints.
 */
class VaultAudit extends Model
{
    use BelongsToWorkspace;

    protected $table = 'vault_audit';
    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'actor_user_id', 'action',
        'target_type', 'target_id', 'target_label', 'ip', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /** Append a new entry. Stamps occurred_at and the requester IP automatically. */
    public static function record(string $action, string $targetType, ?int $targetId, ?string $label = null, ?int $actorId = null): self
    {
        return self::create([
            'actor_user_id' => $actorId ?: auth()->id(),
            'action'        => $action,
            'target_type'   => $targetType,
            'target_id'     => $targetId,
            'target_label'  => $label,
            'ip'            => request()?->ip(),
            'occurred_at'   => now(),
        ]);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
