<?php

namespace App\Modules\User\Services;

/**
 * Color theme presets for the Resume / Portfolio renderer. Each preset
 * is a small palette consumed by both the Blade view and the PDF
 * exporter (so the on-screen page and the downloaded PDF stay visually
 * identical). Tokens map to:
 *
 *   primary    → headings, name, section dividers
 *   accent     → links, secondary highlights, bullets
 *   text       → body copy
 *   muted      → meta info (dates, locations, captions)
 *   background → page background
 */
class ResumeColorThemeRegistry
{
    /** @return array<int, array<string,mixed>> */
    public static function all(): array
    {
        return [
            [
                'id'    => 'graphite',
                'name'  => 'Graphite',
                'tokens' => [
                    'primary' => '#111827', 'accent' => '#4b5563',
                    'text'    => '#1f2937', 'muted'  => '#6b7280',
                    'background' => '#ffffff',
                ],
            ],
            [
                'id'    => 'ocean',
                'name'  => 'Ocean',
                'tokens' => [
                    'primary' => '#0c4a6e', 'accent' => '#0284c7',
                    'text'    => '#0f172a', 'muted'  => '#64748b',
                    'background' => '#ffffff',
                ],
            ],
            [
                'id'    => 'forest',
                'name'  => 'Forest',
                'tokens' => [
                    'primary' => '#14532d', 'accent' => '#16a34a',
                    'text'    => '#1f2937', 'muted'  => '#65786b',
                    'background' => '#ffffff',
                ],
            ],
            [
                'id'    => 'sunset',
                'name'  => 'Sunset',
                'tokens' => [
                    'primary' => '#7c2d12', 'accent' => '#ea580c',
                    'text'    => '#1f2937', 'muted'  => '#7c6f63',
                    'background' => '#fffaf5',
                ],
            ],
            [
                'id'    => 'plum',
                'name'  => 'Plum',
                'tokens' => [
                    'primary' => '#581c87', 'accent' => '#9333ea',
                    'text'    => '#1f2937', 'muted'  => '#736b7c',
                    'background' => '#ffffff',
                ],
            ],
            [
                'id'    => 'mono',
                'name'  => 'Mono',
                'tokens' => [
                    'primary' => '#000000', 'accent' => '#000000',
                    'text'    => '#000000', 'muted'  => '#555555',
                    'background' => '#ffffff',
                ],
            ],
        ];
    }

    /** @return array<int, string> */
    public static function ids(): array
    {
        return array_column(self::all(), 'id');
    }

    public static function find(?string $id): ?array
    {
        if (!$id) return null;
        foreach (self::all() as $theme) {
            if ($theme['id'] === $id) return $theme;
        }
        return null;
    }

    public static function isValid(?string $id): bool
    {
        return $id !== null && in_array($id, self::ids(), true);
    }

    public static function defaultId(): string
    {
        return 'graphite';
    }
}
