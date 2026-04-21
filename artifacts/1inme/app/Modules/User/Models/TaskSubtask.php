<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TaskSubtask extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'card_id', 'title', 'completed', 'position'];
    protected $casts = ['completed' => 'boolean'];

    public function card() { return $this->belongsTo(TaskCard::class, 'card_id'); }

    /** Lets the BelongsToWorkspace trait auto-derive workspace_id from the parent card. */
    public function parentForWorkspace()
    {
        return $this->card_id ? TaskCard::query()->withoutWorkspaceScope()->find($this->card_id) : null;
    }
}
