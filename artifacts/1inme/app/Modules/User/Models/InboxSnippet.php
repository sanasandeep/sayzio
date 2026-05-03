<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

class InboxSnippet extends Model
{
    use BelongsToWorkspace;

    protected $table = 'inbox_snippets';

    protected $fillable = [
        'workspace_id', 'created_by_user_id', 'shortcut', 'label', 'body',
    ];
}
