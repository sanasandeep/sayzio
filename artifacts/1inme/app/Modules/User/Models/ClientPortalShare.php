<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class ClientPortalShare extends Model
{
    use BelongsToWorkspace;

    protected $table = 'client_portal_shares';

    protected $fillable = [
        'portal_id', 'workspace_id', 'shareable_type', 'shareable_id',
        'label', 'settings', 'position',
        'requires_approval', 'approval_status', 'approval_decided_at',
        'approval_decided_email', 'approval_comment',
    ];

    protected $casts = [
        'settings'             => 'array',
        'requires_approval'    => 'boolean',
        'approval_decided_at'  => 'datetime',
    ];

    public const TYPE_BOARD            = 'task_board';
    public const TYPE_CLOUD_FOLDER     = 'cloud_folder';
    public const TYPE_DRAFT_POST       = 'creator_post';
    public const TYPE_INVOICE          = 'invoice';
    public const TYPE_LINK_PERFORMANCE = 'link_performance';
    public const TYPE_DELIVERY_PROJECT = 'delivery_project';

    public const TYPES = [
        self::TYPE_BOARD            => 'Kanban board',
        self::TYPE_CLOUD_FOLDER     => 'File folder',
        self::TYPE_DRAFT_POST       => 'Draft post',
        self::TYPE_INVOICE          => 'Invoice',
        self::TYPE_LINK_PERFORMANCE => 'Performance report',
        self::TYPE_DELIVERY_PROJECT => 'Delivery project',
    ];

    public function portal()
    {
        return $this->belongsTo(ClientPortal::class, 'portal_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->shareable_type] ?? $this->shareable_type;
    }

    public function isApprovable(): bool
    {
        return $this->requires_approval && in_array($this->shareable_type, [self::TYPE_DRAFT_POST], true);
    }
}
