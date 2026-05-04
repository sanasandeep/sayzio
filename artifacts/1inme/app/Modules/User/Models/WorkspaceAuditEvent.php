<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Append-only audit ledger for sensitive workspace actions. Distinct from
 * the general activity feed — these rows survive forever, are emailed to
 * the workspace owner (subject to per-action prefs) and form a hash chain
 * so any retro-edit of an older row breaks every later row's hash.
 *
 * No public `update`/`delete` endpoints exist for this model.
 */
class WorkspaceAuditEvent extends Model
{
    use BelongsToWorkspace;

    protected $table = 'workspace_audit_events';

    public $timestamps = false;

    protected $fillable = [
        'workspace_id', 'actor_user_id', 'action',
        'target_type', 'target_id', 'target_label',
        'ip', 'payload', 'occurred_at', 'prev_hash', 'hash',
        'reported_unauthorized_at', 'reported_by_user_id',
    ];

    protected $casts = [
        'payload'                 => 'array',
        'occurred_at'             => 'datetime',
        'reported_unauthorized_at'=> 'datetime',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function reports()
    {
        return $this->hasMany(WorkspaceAuditReport::class, 'workspace_audit_event_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * Append a new audit event with hash-chain link. Locks the per-workspace
     * tail row inside a transaction so two concurrent writes can't share a
     * predecessor and silently fork the chain.
     */
    public static function appendChained(array $attributes): self
    {
        return DB::transaction(function () use ($attributes) {
            $workspaceId = (int) $attributes['workspace_id'];

            // Lock the tail row for this workspace so we get a stable
            // predecessor hash even under concurrent inserts.
            $prev = static::query()
                ->withoutWorkspaceScope()
                ->where('workspace_id', $workspaceId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $attributes['prev_hash']   = $prev?->hash;
            $attributes['occurred_at'] = $attributes['occurred_at'] ?? now();
            $attributes['hash']        = static::computeHash($attributes);

            return static::create($attributes);
        });
    }

    /**
     * Canonical hash over (prev_hash | workspace | actor | action | target |
     * ip | occurred_at | payload-json). SHA-256 is more than sufficient for
     * tamper-evidence on append-only application logs.
     */
    public static function computeHash(array $attrs): string
    {
        $payloadJson = isset($attrs['payload'])
            ? json_encode($attrs['payload'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR)
            : '';

        $occurred = $attrs['occurred_at'] ?? now();
        if ($occurred instanceof \DateTimeInterface) {
            $occurred = $occurred->format('Y-m-d H:i:s');
        }

        $material = implode('|', [
            (string) ($attrs['prev_hash']     ?? ''),
            (string) ($attrs['workspace_id']  ?? ''),
            (string) ($attrs['actor_user_id'] ?? ''),
            (string) ($attrs['action']        ?? ''),
            (string) ($attrs['target_type']   ?? ''),
            (string) ($attrs['target_id']     ?? ''),
            (string) ($attrs['target_label']  ?? ''),
            (string) ($attrs['ip']            ?? ''),
            (string) $occurred,
            (string) $payloadJson,
        ]);

        return hash('sha256', $material);
    }

    /**
     * Re-compute the chain end-to-end for a workspace and report any
     * mismatched rows. Used by the investigation view's integrity badge.
     *
     * @return array{ok: bool, broken_at: ?int, count: int}
     */
    public static function verifyChain(int $workspaceId): array
    {
        $rows = static::query()
            ->withoutWorkspaceScope()
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get();

        $prev = null;
        foreach ($rows as $row) {
            if ((string) $row->prev_hash !== (string) ($prev?->hash ?? '')) {
                return ['ok' => false, 'broken_at' => $row->id, 'count' => $rows->count()];
            }
            $expected = static::computeHash([
                'prev_hash'     => $row->prev_hash,
                'workspace_id'  => $row->workspace_id,
                'actor_user_id' => $row->actor_user_id,
                'action'        => $row->action,
                'target_type'   => $row->target_type,
                'target_id'     => $row->target_id,
                'target_label'  => $row->target_label,
                'ip'            => $row->ip,
                'occurred_at'   => $row->occurred_at,
                'payload'       => $row->payload,
            ]);
            if ($expected !== $row->hash) {
                return ['ok' => false, 'broken_at' => $row->id, 'count' => $rows->count()];
            }
            $prev = $row;
        }

        return ['ok' => true, 'broken_at' => null, 'count' => $rows->count()];
    }
}
