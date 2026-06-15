<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewAnswer extends Model
{
    protected $fillable = ['review_id', 'question_id', 'prompt', 'answer'];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function question()
    {
        return $this->belongsTo(ReviewQuestion::class, 'question_id');
    }
}
