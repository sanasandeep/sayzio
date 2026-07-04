<?php

namespace App\Modules\User\Models;

use App\Modules\User\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Model;

/**
 * A single "paste a competitor URL" analysis run (Task #3532). See the
 * `competitor_teardowns` migration and {@see \App\Services\AI\CompetitorTeardownService}
 * for the fetch → AI-score → optional "build me a better version" pipeline.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $actor_user_id
 * @property string $competitor_url
 * @property string $status pending|processing|completed|failed
 * @property array|null $extracted
 * @property array|null $analysis
 * @property int $credits_spent
 * @property string|null $error
 * @property int|null $built_link_id
 */
class CompetitorTeardown extends Model
{
    use BelongsToWorkspace;

    protected $fillable = [
        'user_id', 'actor_user_id', 'competitor_url', 'status',
        'extracted', 'analysis', 'credits_spent', 'error', 'built_link_id',
    ];

    protected function casts(): array
    {
        return [
            'extracted'     => 'array',
            'analysis'      => 'array',
            'credits_spent' => 'integer',
        ];
    }

    public function link()
    {
        return $this->belongsTo(Link::class, 'built_link_id');
    }
}
