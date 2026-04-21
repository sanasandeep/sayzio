<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class TaskLabel extends Model
{
    use BelongsToWorkspace;

    protected $fillable = ['workspace_id', 'board_id', 'name', 'color'];

    public function board() { return $this->belongsTo(TaskBoard::class, 'board_id'); }
    public function cards()
    {
        return $this->belongsToMany(TaskCard::class, 'task_card_labels', 'label_id', 'card_id');
    }
}
