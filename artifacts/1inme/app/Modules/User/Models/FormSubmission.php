<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class FormSubmission extends Model
{
    protected $fillable = [
        'form_id', 'data', 'files', 'ip', 'user_agent', 'referrer',
        'country', 'is_spam', 'is_read', 'is_starred',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'files' => 'array',
            'is_spam' => 'boolean',
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
        ];
    }

    public function form()
    {
        return $this->belongsTo(Form::class);
    }
}
