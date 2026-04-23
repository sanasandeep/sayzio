<?php

namespace App\Modules\Common\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SiteAssistantConversation extends Model
{
    protected $fillable = [
        'visitor_token', 'user_id', 'bound_user_id', 'surface',
        'visitor_name', 'visitor_email', 'visitor_ip', 'visitor_ua',
        'last_route', 'last_page_title',
        'is_disabled', 'handed_off', 'contact_message_id',
        'turns_count', 'credits_spent', 'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'is_disabled'      => 'bool',
            'handed_off'       => 'bool',
            'turns_count'      => 'int',
            'credits_spent'    => 'int',
            'last_message_at'  => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SiteAssistantMessage::class, 'conversation_id');
    }
}
