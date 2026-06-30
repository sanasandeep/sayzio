<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A grant that lets a team workspace or account-badge group USE (or
 * use + EDIT) one of the owner's AI resources — a Mind (knowledge
 * base) or a Persona agent. Task #2909.
 *
 * The owner keeps ownership of the underlying resource; this row only
 * widens who may reach it. AI / coin costs are always charged to the
 * acting user, never the owner (charging lives in the runtime
 * services and is unaffected by sharing).
 *
 * Access is resolved live against the recipient's current
 * memberships/badges, so revocation (member removed, badge detached)
 * is automatic — these rows are an allow-list keyed on the audience,
 * not a per-user grant.
 */
class AiResourceShare extends Model
{
    protected $table = 'ai_resource_shares';

    public const RESOURCE_MIND    = 'mind';
    public const RESOURCE_PERSONA = 'persona';

    public const AUDIENCE_WORKSPACE = 'workspace';
    public const AUDIENCE_BADGE     = 'badge';

    public const ACCESS_USE  = 'use';
    public const ACCESS_EDIT = 'edit';

    public const RESOURCE_TYPES = [self::RESOURCE_MIND, self::RESOURCE_PERSONA];
    public const AUDIENCE_TYPES = [self::AUDIENCE_WORKSPACE, self::AUDIENCE_BADGE];
    public const ACCESS_LEVELS  = [self::ACCESS_USE, self::ACCESS_EDIT];

    protected $fillable = [
        'resource_type', 'resource_id', 'owner_user_id',
        'audience_type', 'audience_id', 'access',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function grantsEdit(): bool
    {
        return $this->access === self::ACCESS_EDIT;
    }
}
