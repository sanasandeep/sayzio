<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class TaskActivity extends Model
{
    public $timestamps = false;
    protected $fillable = ['card_id', 'user_id', 'type', 'data', 'created_at'];
    protected $casts    = ['data' => 'array', 'created_at' => 'datetime'];

    public function card() { return $this->belongsTo(TaskCard::class, 'card_id'); }
    public function user() { return $this->belongsTo(User::class, 'user_id'); }

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
