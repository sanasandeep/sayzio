<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TaskAttachment extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'workspace_id', 'card_id', 'uploaded_by_user_id',
        'original_name', 'mime', 'size_bytes', 'disk', 'path',
    ];

    public function card()     { return $this->belongsTo(TaskCard::class, 'card_id'); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by_user_id'); }

    public function url(): string
    {
        // Always serve attachments through the controller download route so we
        // can force `Content-Disposition: attachment` and re-check workspace
        // authorization. Direct public-disk URLs would let an attacker host
        // active content (svg/html) on the same origin.
        return route('user.tasks.attachments.download', $this);
    }

    public function humanSize(): string
    {
        $b = (int) $this->size_bytes;
        if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
        if ($b >= 1024) return round($b / 1024) . ' KB';
        return $b . ' B';
    }
}
