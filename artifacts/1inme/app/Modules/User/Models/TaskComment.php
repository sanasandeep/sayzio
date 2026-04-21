<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TaskComment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'card_id', 'user_id', 'body'];

    public function card() { return $this->belongsTo(TaskCard::class, 'card_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }

    public function parentForWorkspace()
    {
        return $this->card_id ? TaskCard::query()->withoutWorkspaceScope()->find($this->card_id) : null;
    }
}
