<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;

final class CreatorProfilePresentation
{
    public const THEMES = ['paper' => 'Paper', 'midnight' => 'Midnight', 'warm' => 'Warm studio', 'mint' => 'Fresh mint'];

    public const LAYOUTS = ['classic' => 'Classic', 'centered' => 'Spotlight', 'editorial' => 'Editorial', 'minimal' => 'Compact'];

    public static function resolve(array $value): array
    {
        return [
            'theme' => is_string($value['theme'] ?? null) && array_key_exists($value['theme'], self::THEMES) ? $value['theme'] : 'paper',
            'layout' => in_array($value['layout'] ?? '', array_keys(self::LAYOUTS), true) ? $value['layout'] : 'classic',
            'mode' => in_array($value['mode'] ?? '', ['light', 'dark'], true) ? $value['mode'] : (($value['theme'] ?? '') === 'midnight' ? 'dark' : 'light'),
            'avatar_shape' => in_array($value['avatar_shape'] ?? '', ['circle', 'rounded', 'square'], true) ? $value['avatar_shape'] : 'rounded',
            'avatar_size' => in_array((int) ($value['avatar_size'] ?? 96), [80, 96, 128], true) ? (int) ($value['avatar_size'] ?? 96) : 96,
            'avatar_x' => max(0, min(100, (int) ($value['avatar_x'] ?? 50))),
            'avatar_y' => max(0, min(100, (int) ($value['avatar_y'] ?? 50))),
        ];
    }

    public static function forUser(User $user): array
    {
        return self::resolve((array) (($user->profile_showcase ?? [])['presentation'] ?? []));
    }

    public static function vcard(User $user): string
    {
        $escape = fn ($text) => str_replace(["\\", "\r", "\n", ';', ','], ["\\\\", '', '\\n', '\\;', '\\,'], (string) $text);
        $lines = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:'.$escape($user->name), 'N:;'.$escape($user->name).';;;', 'URL:'.$escape(route('creator-profile.show', $user->handle))];
        // Only owner-published contact fields, never the account email/phone.
        if ($user->isSectionVisible('contact')) {
            $showcase = $user->resolvedProfileShowcase();
            $buttons = $user->isSectionVisible('cta') ? array_filter(array_merge([$showcase['cta']['primary']], $showcase['cta']['secondary'])) : [];
            if ($user->isSectionVisible('socials') && !empty($user->socials['email'])) $buttons[] = ['kind' => 'email', 'value' => $user->socials['email']];
            foreach ($buttons as $button) {
                $value = trim((string) ($button['value'] ?? ''));
                if (($button['kind'] ?? '') === 'email' && filter_var($value, FILTER_VALIDATE_EMAIL)) $lines[] = 'EMAIL:'.$escape($value);
                if (in_array($button['kind'] ?? '', ['call', 'whatsapp'], true) && preg_match('/^\+?[0-9 ()-]{5,40}$/', $value)) $lines[] = 'TEL:'.$escape($value);
            }
        }
        $lines[] = 'END:VCARD';
        $folded = [];
        foreach (array_unique($lines) as $line) {
            $prefix = '';
            while (strlen($prefix.$line) > 75) {
                $chunk = mb_strcut($line, 0, 75 - strlen($prefix), 'UTF-8');
                $folded[] = $prefix.$chunk;
                $line = substr($line, strlen($chunk));
                $prefix = ' ';
            }
            $folded[] = $prefix.$line;
        }
        return implode("\r\n", $folded)."\r\n";
    }
}
