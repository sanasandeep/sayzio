<?php

namespace App\Modules\User\Support;

/** Editable fallback fields come from the content schema, including empty lists. */
final class BlockEditorFields
{
    public static function defaultsForType(string $type): array
    {
        $extra = match ($type) {
            'rsvp' => ['event_link_id' => 0, 'heading' => 'RSVP to'],
            'socials', 'socials_multi', 'socials_custom' => ['size' => 'md', 'style' => 'rounded'],
            'file' => ['icon' => 'fa-file'],
            default => [],
        };
        return array_replace($extra, BlockDefaults::contentForType($type));
    }

    public static function schema(array $defaults, array $saved = [], int $depth = 0): array
    {
        if ($depth > 6) return [];
        $fields = [];
        foreach (array_unique(array_merge(array_keys($defaults), array_keys($saved))) as $key) {
            if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/', $key)) continue;
            $value = $saved[$key] ?? $defaults[$key] ?? '';
            $seed = $defaults[$key] ?? $value;
            if (is_array($value) || is_array($seed)) {
                $value = is_array($value) ? $value : [];
                $seed = is_array($seed) ? $seed : [];
                if (array_is_list($seed) && array_is_list($value)) {
                    $rowDefaults = []; $rowSaved = [];
                    foreach ($seed as $row) if (is_array($row)) $rowDefaults = array_replace($rowDefaults, $row);
                    foreach ($value as $row) if (is_array($row)) $rowSaved = array_replace($rowSaved, $row);
                    $children = self::schema($rowDefaults, $rowSaved, $depth + 1);
                    $fields[$key] = ['kind' => 'list', 'fields' => $children, 'scalar' => $children === []];
                } else {
                    $fields[$key] = ['kind' => 'object', 'fields' => self::schema($seed, $value, $depth + 1)];
                }
            } else {
                $fields[$key] = ['kind' => is_bool($seed) ? 'boolean' : (is_numeric($seed) && !is_string($seed) ? 'number' : 'text')];
            }
        }
        return $fields;
    }

    public static function emptyValue(array $field): mixed
    {
        return match ($field['kind']) {
            'list' => [],
            'object' => self::emptyObject($field['fields']),
            'boolean' => false,
            'number' => 0,
            default => '',
        };
    }

    public static function emptyObject(array $fields): array
    {
        $result = [];
        foreach ($fields as $key => $field) $result[$key] = self::emptyValue($field);
        return $result;
    }

    public static function values(array $fields, array $saved, array $defaults = []): array
    {
        $result = [];
        foreach ($fields as $key => $field) {
            $value = array_key_exists($key, $saved) ? $saved[$key] : ($defaults[$key] ?? self::emptyValue($field));
            if ($field['kind'] === 'list') {
                $result[$key] = [];
                foreach (is_array($value) ? $value : [] as $row) {
                    $result[$key][] = $field['scalar'] ? (is_scalar($row) ? (string) $row : '') : self::values($field['fields'], is_array($row) ? $row : []);
                }
            } elseif ($field['kind'] === 'object') {
                $result[$key] = self::values($field['fields'], is_array($value) ? $value : []);
            } elseif ($field['kind'] === 'boolean') {
                $result[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } else {
                $result[$key] = is_scalar($value) ? $value : '';
            }
        }
        return $result;
    }
    public static function normalizeEmptyLists(array $settings, array $defaults): array
    {
        foreach ($defaults as $key => $seed) {
            if (!is_array($seed) || !array_key_exists($key, $settings)) continue;
            if ($settings[$key] === '' || $settings[$key] === null) {
                $settings[$key] = [];
            } elseif (is_array($settings[$key])) {
                if (array_is_list($seed)) {
                    $rowSeed = [];
                    foreach ($seed as $row) if (is_array($row)) $rowSeed = array_replace($rowSeed, $row);
                    foreach ($settings[$key] as &$row) if (is_array($row)) $row = self::normalizeEmptyLists($row, $rowSeed);
                    unset($row);
                } else {
                    $settings[$key] = self::normalizeEmptyLists($settings[$key], $seed);
                }
            }
        }
        return $settings;
    }

    public static function sanitizeColors(array $settings, int $depth = 0): array
    {
        if ($depth > 8) return $settings;
        foreach ($settings as $key => $value) {
            if (is_string($key) && str_starts_with($key, '_')) continue;
            if (is_array($value)) {
                $settings[$key] = self::sanitizeColors($value, $depth + 1);
            } elseif (is_string($key) && preg_match('/(?:^color$|_color$|_bg$)/', $key)) {
                $styleKey = $key === 'bg_color' ? 'bg_color' : 'text_color';
                $safe = BlockStyleSanitizer::sanitize([$styleKey => $value]);
                if (isset($safe[$styleKey])) $settings[$key] = $safe[$styleKey];
                else unset($settings[$key]);
            }
        }
        return $settings;
    }

}
