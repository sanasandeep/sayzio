<?php

namespace App\Modules\Common\Support;

use App\Modules\Admin\Models\AppSetting;

final class SiteCustomCode
{
    public const SETTING_KEY = 'site_custom_code';

    public const AREAS = ['marketing' => 'Marketing pages', 'dashboard' => 'User dashboard'];

    public const FIELDS = ['css', 'head_js', 'body_js', 'head_html', 'body_start_html', 'body_end_html'];

    public static function settings(): array
    {
        $stored = AppSetting::get(self::SETTING_KEY, []);
        $result = [];
        foreach (self::AREAS as $area => $label) {
            $row = is_array($stored[$area] ?? null) ? $stored[$area] : [];
            $result[$area] = ['enabled' => (bool) ($row['enabled'] ?? false)];
            foreach (self::FIELDS as $field) {
                $result[$area][$field] = (string) ($row[$field] ?? '');
            }
        }

        return $result;
    }

    public static function inject(string $html, string $area): string
    {
        $code = self::settings()[$area] ?? [];
        if (empty($code['enabled'])) {
            return $html;
        }
        $head = $code['head_html'];
        if ($code['css'] !== '') {
            $head .= "\n<style data-site-custom-css>".$code['css'].'</style>';
        }
        if ($code['head_js'] !== '') {
            $head .= "\n<script data-site-custom-head-js>".$code['head_js'].'</script>';
        }
        $end = $code['body_end_html'];
        if ($code['body_js'] !== '') {
            $end .= "\n<script data-site-custom-body-js>".$code['body_js'].'</script>';
        }
        // Calculate offsets on the original document, then insert back to front.
        // Snippets may themselves contain HTML-like strings; never re-scan them.
        $edits = [];
        // Ignore comments and raw-text elements when finding document boundaries.
        // For example, a script comment can literally mention "<body>".
        preg_match_all('~<!--.*?-->|<(script|style|textarea)\\b[^>]*>.*?</\\1\\s*>|</head\\s*>|<body\\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*>|</body\\s*>~is', $html, $tokens, PREG_OFFSET_CAPTURE);
        $headPos = $bodyStart = $bodyEnd = null;
        foreach ($tokens[0] as [$token, $offset]) {
            if (preg_match('~^</head\\s*>$~i', $token) && $headPos === null) {
                $headPos = $offset;
            } elseif (preg_match('~^<body\\b~i', $token) && $bodyStart === null) {
                $bodyStart = $offset + strlen($token);
            } elseif (preg_match('~^</body\\s*>$~i', $token)) {
                $bodyEnd = $offset;
            }
        }
        if ($headPos !== null) {
            $edits[] = [$headPos, $head."\n"];
        }
        if ($bodyStart !== null) {
            $edits[] = [$bodyStart, "\n".$code['body_start_html']];
        }
        if ($bodyEnd !== null) {
            $edits[] = [$bodyEnd, $end."\n"];
        }
        usort($edits, fn ($a, $b) => $b[0] <=> $a[0]);
        foreach ($edits as [$pos, $snippet]) {
            $html = substr_replace($html, $snippet, $pos, 0);
        }

        return $html;
    }
}
