<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;

class SiteAssistantResponseTemplate extends Model
{
    protected $fillable = ['key', 'label', 'kind', 'payload', 'is_active'];

    protected function casts(): array
    {
        return [
            'payload'   => 'array',
            'is_active' => 'bool',
        ];
    }
}
