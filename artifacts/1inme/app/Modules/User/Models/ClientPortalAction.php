<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use App\Modules\User\Models\FeedEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ClientPortalAction extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;
    protected $table = 'client_portal_actions';

    protected $fillable = [
        'portal_id', 'workspace_id', 'link_id', 'email',
        'action', 'target_type', 'target_id', 'data',
        'ip', 'user_agent', 'occurred_at',
    ];

    protected $casts = [
        'data'        => 'array',
        'occurred_at' => 'datetime',
    ];

    public static function record(ClientPortal $portal, ?ClientPortalLink $link, string $action, ?string $targetType = null, ?int $targetId = null, array $data = []): self
    {
        $req = request();
        $row = self::create([
            'portal_id'    => $portal->id,
            'workspace_id' => $portal->workspace_id,
            'link_id'      => $link?->id,
            'email'        => $link?->email,
            'action'       => $action,
            'target_type'  => $targetType,
            'target_id'    => $targetId,
            'data'         => $data ?: null,
            'ip'           => $req?->ip(),
            'user_agent'   => $req ? mb_substr((string) $req->userAgent(), 0, 500) : null,
            'occurred_at'  => now(),
        ]);

        // Surface every client action in the workspace activity feed so the
        // owner can see views/downloads/decisions alongside the rest of the
        // workspace timeline. Wrapped in try/catch — a feed write must never
        // break the underlying portal interaction.
        try {
            FeedEvent::create([
                'user_id'      => $portal->workspace->owner_user_id,
                'type'         => 'portal_' . $action,
                'subject_id'   => $portal->id,
                'subject_type' => ClientPortal::class,
                'data'         => [
                    'action'      => $action,
                    'portal'      => $portal->name,
                    'email'       => $link?->email,
                    'target_type' => $targetType,
                    'target_id'   => $targetId,
                    'meta'        => $data ?: null,
                ],
                'occurred_at'  => now(),
                'visibility'   => 'public',
            ]);
        } catch (\Throwable $e) {
            // swallow — audit row already persisted
        }

        return $row;
    }

    public function portal()
    {
        return $this->belongsTo(ClientPortal::class, 'portal_id');
    }
}
