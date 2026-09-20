<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One admin-made addition to, or override of, a compiled-in background
 * catalog. See the create_bg_catalog_entries_table migration for what a
 * row means; see CatalogOverrides for how the catalogs read them.
 */
class BgCatalogEntry extends Model
{
    /** The seven compiled-in catalogs, and what each is called in the admin. */
    public const KINDS = [
        'preset'     => 'Preset',
        'gradient'   => 'Gradient',
        'mesh'       => 'Mesh',
        'pattern'    => 'Pattern',
        'tiles'      => 'Tiles',
        'torn'       => 'Torn look',
        'torn_style' => 'Tear shape',
    ];

    protected $fillable = [
        'kind', 'entry_key', 'label', 'payload', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'payload'    => 'array',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // Every catalog read goes through a forever-cache, so a row that
        // changes without busting it is a change nobody sees until the
        // cache expires -- which it never does.
        static::saved(fn () => \App\Modules\User\Support\CatalogOverrides::flush());
        static::deleted(fn () => \App\Modules\User\Support\CatalogOverrides::flush());
    }
}
