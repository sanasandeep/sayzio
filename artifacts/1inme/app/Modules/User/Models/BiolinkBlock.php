<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;

class BiolinkBlock extends Model
{
    protected $fillable = [
        'link_id', 'type', 'settings', 'sort_order', 'is_active',
        'start_date', 'end_date',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'is_active' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    public const TYPES = [
        'link' => ['label' => 'Link', 'icon' => 'fa-link', 'category' => 'content'],
        'heading' => ['label' => 'Heading', 'icon' => 'fa-heading', 'category' => 'content'],
        'paragraph' => ['label' => 'Paragraph', 'icon' => 'fa-paragraph', 'category' => 'content'],
        'image' => ['label' => 'Image', 'icon' => 'fa-image', 'category' => 'content'],
        'video' => ['label' => 'Video', 'icon' => 'fa-video', 'category' => 'content'],
        'audio' => ['label' => 'Audio', 'icon' => 'fa-music', 'category' => 'content'],
        'divider' => ['label' => 'Divider', 'icon' => 'fa-minus', 'category' => 'layout'],
        'spacer' => ['label' => 'Spacer', 'icon' => 'fa-arrows-alt-v', 'category' => 'layout'],
        'avatar' => ['label' => 'Avatar', 'icon' => 'fa-user-circle', 'category' => 'content'],
        'socials' => ['label' => 'Social Links', 'icon' => 'fa-share-alt', 'category' => 'social'],
        'faq' => ['label' => 'FAQ', 'icon' => 'fa-question-circle', 'category' => 'engagement'],
        'email_collector' => ['label' => 'Email Collector', 'icon' => 'fa-envelope', 'category' => 'engagement'],
        'map' => ['label' => 'Map', 'icon' => 'fa-map-marker-alt', 'category' => 'content'],
        'custom_html' => ['label' => 'Custom HTML', 'icon' => 'fa-code', 'category' => 'advanced'],
        'youtube' => ['label' => 'YouTube', 'icon' => 'fa-youtube', 'category' => 'social'],
        'spotify' => ['label' => 'Spotify', 'icon' => 'fa-spotify', 'category' => 'social'],
        'countdown' => ['label' => 'Countdown', 'icon' => 'fa-clock', 'category' => 'engagement'],
        'cta_button' => ['label' => 'CTA Button', 'icon' => 'fa-hand-pointer', 'category' => 'content'],
    ];

    public const CATEGORIES = [
        'content' => 'Content',
        'layout' => 'Layout',
        'social' => 'Social Media',
        'engagement' => 'Engagement',
        'advanced' => 'Advanced',
    ];

    public function link()
    {
        return $this->belongsTo(Link::class);
    }

    public function isVisible(): bool
    {
        if (!$this->is_active) return false;
        if ($this->start_date && $this->start_date->isFuture()) return false;
        if ($this->end_date && $this->end_date->isPast()) return false;
        return true;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }
}
