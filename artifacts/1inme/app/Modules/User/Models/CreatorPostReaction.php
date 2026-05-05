<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per (viewer, post) — the unique index in the schema enforces
 * "at most one reaction per post per viewer". The branded reaction set
 * is intentionally fixed and shared platform-wide so the icons render
 * the same on web and mobile.
 */
class CreatorPostReaction extends Model
{
    public $timestamps = false;
    protected $fillable = ['post_id', 'viewer_user_id', 'reaction', 'created_at'];
    protected $casts    = ['created_at' => 'datetime'];

    /**
     * The platform-wide branded reaction set used everywhere on
     * /@handle posts. Each entry has a stable `key` (what's stored),
     * a human label, a Font Awesome icon class, and an accent color
     * so the icon style stays consistent on every surface.
     *
     * Order matters — the UI renders the row in this order.
     */
    public const REACTIONS = [
        ['key' => 'fire',       'label' => 'Fire',       'icon' => 'fas fa-fire',          'color' => '#f97316'],
        ['key' => 'mind_blown', 'label' => 'Mind-blown', 'icon' => 'fas fa-explosion',     'color' => '#a855f7'],
        ['key' => 'heart_eyes', 'label' => 'Heart-eyes', 'icon' => 'fas fa-heart',         'color' => '#ef4444'],
        ['key' => 'clap',       'label' => 'Clap',       'icon' => 'fas fa-hands-clapping','color' => '#eab308'],
        ['key' => 'wow',        'label' => 'Wow',        'icon' => 'fas fa-face-surprise', 'color' => '#06b6d4'],
        ['key' => 'bookmark',   'label' => 'Bookmark',   'icon' => 'fas fa-bookmark',      'color' => '#10b981'],
    ];

    public static function reactionKeys(): array
    {
        return array_column(self::REACTIONS, 'key');
    }

    public static function reactionByKey(string $key): ?array
    {
        foreach (self::REACTIONS as $r) if ($r['key'] === $key) return $r;
        return null;
    }

    public function post()   { return $this->belongsTo(CreatorPost::class, 'post_id'); }
    public function viewer() { return $this->belongsTo(User::class, 'viewer_user_id'); }
}
