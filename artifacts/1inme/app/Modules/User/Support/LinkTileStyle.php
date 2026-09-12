<?php

namespace App\Modules\User\Support;

/**
 * How a link's icon tile looks on My Links: icon, label and the three
 * colours the tile is painted with.
 *
 * This exists because the two views disagreed. The list rows read a
 * hand-written rgba map that lived in the blade and covered 7 of the 18
 * types -- so a Slides link, a Restaurant Menu and a Store Menu all fell
 * through to the Short Link colour and rendered identically. The grid tiles
 * ignored the type altogether and tinted by FOLDER colour, which for a link
 * in no folder meant blue. Same link, two views, two different colours, and
 * neither of them the type's own.
 *
 * The colour now comes from one place: the `tint` on each type in
 * {@see LinkTypeCategories}, in the same colour family as the badge that
 * type already wears on the Create Link picker. Adding a type stays a
 * one-line change there.
 *
 * The tile keeps its file-extension pass for File Share links, so a PDF
 * still looks like a PDF rather than a generic file -- and now does so in
 * both views, not just the list.
 */
class LinkTileStyle
{
    /** Used when a type carries no tint, or is not in the catalog at all. */
    public const FALLBACK_TINT = '#a78bfa';

    /**
     * Extension => [icon, hex], or extension => group key below.
     */
    private const FILE_ICONS = [
        'pdf'  => ['fa-file-pdf', '#ef4444'],
        'doc'  => 'word',  'docx' => 'word',  'rtf'  => 'word',  'odt' => 'word',
        'xls'  => 'excel', 'xlsx' => 'excel', 'csv'  => 'excel', 'ods' => 'excel',
        'ppt'  => 'ppt',   'pptx' => 'ppt',   'odp'  => 'ppt',
        'jpg'  => 'img',   'jpeg' => 'img',   'png'  => 'img',   'gif' => 'img',
        'webp' => 'img',   'svg'  => 'img',   'bmp'  => 'img',   'avif' => 'img',
        'mp4'  => 'video', 'mov'  => 'video', 'avi'  => 'video', 'webm' => 'video', 'mkv' => 'video',
        'mp3'  => 'audio', 'wav'  => 'audio', 'ogg'  => 'audio', 'flac' => 'audio', 'm4a' => 'audio',
        'zip'  => 'zip',   'rar'  => 'zip',   '7z'   => 'zip',   'tar' => 'zip',    'gz'  => 'zip',
        'txt'  => 'text',  'md'   => 'text',  'log'  => 'text',
        'js'   => 'code',  'ts'   => 'code',  'php'  => 'code',  'py'  => 'code',
        'html' => 'code',  'css'  => 'code',  'json' => 'code',  'xml' => 'code',
    ];

    private const FILE_GROUPS = [
        'word'  => ['fa-file-word',       '#3b82f6'],
        'excel' => ['fa-file-excel',      '#10b981'],
        'ppt'   => ['fa-file-powerpoint', '#f97316'],
        'img'   => ['fa-file-image',      '#ec4899'],
        'video' => ['fa-file-video',      '#5c83ff'],
        'audio' => ['fa-file-audio',      '#06b6d4'],
        'zip'   => ['fa-file-zipper',     '#eab308'],
        'text'  => ['fa-file-lines',      '#94a3b8'],
        'code'  => ['fa-file-code',       '#6e61ff'],
    ];

    /**
     * @return array{icon:string,label:string,color:string,bg:string,border:string}
     */
    public static function for(object $link): array
    {
        $types = LinkTypeCategories::types();
        $meta  = $types[$link->type] ?? $types['url'] ?? [];

        $icon  = (string) ($meta['icon'] ?? 'fa-link');
        $label = (string) ($meta['label'] ?? 'Link');
        $tint  = self::normalizeHex((string) ($meta['tint'] ?? '')) ?? self::FALLBACK_TINT;

        // A File Share link shows what it actually holds.
        if (($link->type ?? null) === 'file' && ($link->fileLink ?? null)) {
            $ext = strtolower(pathinfo((string) ($link->fileLink->original_name ?? ''), PATHINFO_EXTENSION));
            $hit = self::FILE_ICONS[$ext] ?? null;

            if (is_string($hit)) {
                $hit = self::FILE_GROUPS[$hit] ?? null;
            }

            if (is_array($hit)) {
                [$icon, $tint] = $hit;
                $label = strtoupper($ext !== '' ? $ext : 'FILE');
            }
        }

        return self::paint($icon, $label, $tint);
    }

    /**
     * The tile's three colours, all derived from the one hex -- so a new
     * type needs a tint and nothing else, and the fill can never drift out
     * of step with the icon the way two hand-written values do.
     *
     * @return array{icon:string,label:string,color:string,bg:string,border:string}
     */
    private static function paint(string $icon, string $label, string $hex): array
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return [
            'icon'   => $icon,
            'label'  => $label,
            'color'  => $hex,
            'bg'     => "rgba($r,$g,$b,0.10)",
            'border' => "rgba($r,$g,$b,0.22)",
        ];
    }

    /** Six-digit hex only; anything else is not a colour we can take apart. */
    private static function normalizeHex(string $hex): ?string
    {
        $hex = trim($hex);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $hex) === 1 ? strtolower($hex) : null;
    }
}
