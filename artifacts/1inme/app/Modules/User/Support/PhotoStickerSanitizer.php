<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\BiolinkBlock;
use App\Modules\User\Models\UserFile;

/**
 * Task #5939 / #5957 — validate custom sticker overlay entries for image
 * blocks. Accepts a JSON string (editor hidden input) or an array
 * (templates/variants/mobile API). Every surviving entry references an
 * image file owned by the current workspace owner; anything else fails
 * closed (dropped silently). The public `url` is always re-derived from
 * the file row, never trusted from the client — the persisted
 * `/f/{id}/{filename}` string is what authorizes anonymous serving via
 * UserFile::isReferencedByPublicRecord().
 *
 * Shared between the web editor (User\BiolinkBlockController) and the
 * mobile /api/v1 block save path (Api\BiolinkBlockController) so the
 * clamping rules (pos preset allowlist, size 24–160, rotate ±180,
 * dx/dy ±80, max-entry cap) can never drift between the two surfaces.
 */
class PhotoStickerSanitizer
{
    public static function sanitize(mixed $raw): array
    {
        $list = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($list) || $list === []) return [];

        $ownerId = (int) (workspace_owner_id() ?? 0);
        if ($ownerId <= 0) return [];

        $ids = [];
        foreach ($list as $entry) {
            if (is_array($entry) && (int) ($entry['file_id'] ?? 0) > 0) {
                $ids[] = (int) $entry['file_id'];
            }
        }
        if ($ids === []) return [];

        $files = UserFile::whereIn('id', array_unique($ids))
            ->where('user_id', $ownerId)
            ->where('type', 'image')
            ->where('scan_status', '!=', 'flagged')
            ->get()
            ->keyBy('id');

        $clean = [];
        foreach ($list as $entry) {
            if (!is_array($entry)) continue;
            $fileId = (int) ($entry['file_id'] ?? 0);
            $file = $files->get($fileId);
            if (!$file) continue; // foreign / missing / non-image / flagged → fail closed

            $pos = (string) ($entry['pos'] ?? 'top_right');
            if (!in_array($pos, BiolinkBlock::PHOTO_STICKER_POSITIONS, true)) $pos = 'top_right';

            $clean[] = [
                'file_id' => $fileId,
                'url'     => $file->url_path,
                'pos'     => $pos,
                'size'    => max(24, min(160, (int) ($entry['size'] ?? 64))),
                'rotate'  => max(-180, min(180, (int) ($entry['rotate'] ?? 0))),
                'dx'      => max(-80, min(80, (int) ($entry['dx'] ?? 0))),
                'dy'      => max(-80, min(80, (int) ($entry['dy'] ?? 0))),
            ];
            if (count($clean) >= BiolinkBlock::PHOTO_STICKER_MAX) break;
        }

        return $clean;
    }
}
