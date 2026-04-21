<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TaskActivity extends Model
{
    use BelongsToWorkspace;

    public $timestamps = false;
    protected $fillable = ['workspace_id', 'card_id', 'user_id', 'type', 'data', 'created_at'];
    protected $casts    = ['data' => 'array', 'created_at' => 'datetime'];

    public function card() { return $this->belongsTo(TaskCard::class, 'card_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }

    public function parentForWorkspace()
    {
        return $this->card_id ? TaskCard::query()->withoutWorkspaceScope()->find($this->card_id) : null;
    }

    public static function log(int $cardId, ?int $userId, string $type, array $data = []): self
    {
        return static::create([
            'card_id'    => $cardId,
            'user_id'    => $userId,
            'type'       => $type,
            'data'       => $data,
            'created_at' => now(),
        ]);
    }
}
