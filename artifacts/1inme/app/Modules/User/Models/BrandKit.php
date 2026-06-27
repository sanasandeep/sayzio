<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persistent, AI-generated brand identity owned by a creator (Task #2662).
 *
 * The structured payload (palette / fonts / voice / taglines / bio /
 * recommended block theme + the source it was generated from) lives in the
 * `config` array so the shape can evolve without migrations. Helpers here
 * give callers a typed view onto the most-used slices without re-reading the
 * raw array everywhere.
 */
class BrandKit extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'config',
        'is_default',
    ];

    protected $casts = [
        'config'     => 'array',
        'is_default' => 'bool',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array{primary?:string,secondary?:string,accent?:string,neutrals?:list<string>} */
    public function palette(): array
    {
        return is_array($this->config['palette'] ?? null) ? $this->config['palette'] : [];
    }

    /** @return array{heading?:string,body?:string} */
    public function fonts(): array
    {
        return is_array($this->config['fonts'] ?? null) ? $this->config['fonts'] : [];
    }

    public function blockTheme(): string
    {
        return (string) ($this->config['block_theme'] ?? '');
    }
}
