<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class LinkClick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'link_id', 'alias', 'viewer_user_id', 'block_id', 'block_type', 'destination_url',
        'ip_address', 'country_code', 'city', 'latitude', 'longitude',
        'browser', 'os', 'device_type', 'referrer', 'source', 'user_agent', 'channel', 'is_bot',
        'is_throttled',
        'language', 'utm_params', 'matched_rule_id', 'clicked_at',
    ];

    protected function casts(): array
    {
        return [
            'utm_params'    => 'array',
            'clicked_at'    => 'datetime',
            'latitude'      => 'float',
            'longitude'     => 'float',
            'is_bot'        => 'bool',
            'is_throttled'  => 'bool',
        ];
    }

    /**
     * Apply a global scope so that every Eloquent query against this model
     * (including the `Link::clicks()` relation) excludes rows flagged as
     * bot/scraper traffic. Creator-facing analytics — totals, uniques,
     * source/browser/country breakdowns, dashboards — therefore reflect
     * real humans by default. Use {@see self::withBots()} to opt back in
     * (e.g. admin diagnostics, recount jobs that intentionally touch raw
     * traffic).
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new class implements Scope {
            public function apply(Builder $builder, Model $model): void
            {
                $builder->where($model->qualifyColumn('is_bot'), false);
                // Throttled rows (per-biolink rate limits, JS-challenge
                // failures) are also kept out of every default analytics
                // surface so creator totals stay human-only. Schema may
                // pre-date the column on older installs; fall back to
                // the bot-only filter in that case.
                try {
                    if (\Illuminate\Support\Facades\Schema::hasColumn($model->getTable(), 'is_throttled')) {
                        $builder->where($model->qualifyColumn('is_throttled'), false);
                    }
                } catch (\Throwable $e) {
                    // Ignore — keep the bot-only filter active.
                }
            }
        });
    }

    /**
     * Query helper that bypasses the bot-exclusion global scope. Use sparingly
     * — only for places that genuinely need to see bot rows (admin tools,
     * data exports, deletions, cleanup jobs).
     */
    public static function withBots(): Builder
    {
        return static::query()->withoutGlobalScopes();
    }

    public function link()
    {
        return $this->belongsTo(Link::class);
    }
}
