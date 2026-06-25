<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * One row per successful login that used the master override password.
 * Append-only: rows are created by {@see self::record()} and never
 * updated. `created_at` is DB-defaulted (useCurrent) and the table has
 * no `updated_at`, so timestamps are disabled.
 */
class MasterPasswordLogin extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'guard', 'target_id', 'target_name', 'target_email',
        'ip', 'user_agent', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * Best-effort audit write. A logging failure must never block or roll
     * back the login it is recording.
     *
     * @param  string  $guard   one of web|api|admin
     * @param  object  $target  the account that was accessed (User or Admin)
     */
    public static function record(string $guard, object $target, Request $request): void
    {
        try {
            self::create([
                'guard'        => $guard,
                'target_id'    => $target->id ?? null,
                'target_name'  => $target->name ?? null,
                'target_email' => $target->email ?? null,
                'ip'           => $request->ip(),
                'user_agent'   => mb_substr((string) $request->userAgent(), 0, 512),
                'created_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('MasterPasswordLogin audit failed: ' . $e->getMessage(), [
                'guard'  => $guard,
                'target' => $target->id ?? null,
            ]);
        }
    }

    /** Human label for the login surface, used in the viewer. */
    public function guardLabel(): string
    {
        return match ($this->guard) {
            'web'   => 'Web login',
            'api'   => 'Mobile / API',
            'admin' => 'Admin panel',
            default => ucfirst((string) $this->guard),
        };
    }
}
