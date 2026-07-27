<?php

namespace App\Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A changelog entry for one product surface (web, marketing, mobile, dialer,
 * zio_browser, extension, api_server, docs). Backs the admin
 * "Versions & Releases" hub. Zio Browser rows are auto-inserted from the
 * cached GitHub release feed (source = github); everything else is
 * admin-managed (source = manual) or backfilled by the seeder (source = seed).
 */
class Release extends Model
{
    protected $table = 'releases';

    protected $fillable = [
        'surface',
        'version',
        'released_at',
        'notes',
        'source',
    ];

    protected $casts = [
        'released_at' => 'date',
    ];

    /** Canonical surface keys → human labels, in display order. */
    public const SURFACES = [
        'web'         => 'Web App (Laravel)',
        'marketing'   => 'Marketing Site',
        'mobile'      => 'Mobile App',
        'dialer'      => 'Zio Dialer',
        'zio_browser' => 'Zio Browser',
        'extension'   => 'Zio Extension',
        'api_server'  => 'API Server (Node)',
        'docs'        => 'Docs',
    ];

    public static function label(string $surface): string
    {
        return self::SURFACES[$surface] ?? $surface;
    }

    /**
     * Latest known release for a surface, preferring released_at then id.
     */
    public static function latestFor(string $surface): ?self
    {
        return static::where('surface', $surface)
            ->orderByRaw('released_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->first();
    }
}
