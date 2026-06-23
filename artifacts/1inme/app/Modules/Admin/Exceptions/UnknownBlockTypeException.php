<?php

namespace App\Modules\Admin\Exceptions;

/**
 * Thrown while building a template preview when a snapshot block uses a
 * type the renderer cannot handle. Carries the offending block's type and
 * its human-readable position in the snapshot tree (e.g. "block #3" or
 * "block #2 → child #1") so the preview-error page can point staff at the
 * exact block to fix instead of a generic "can't be previewed" message.
 *
 * Extends \InvalidArgumentException so existing callers that catch that
 * (e.g. the apply paths) keep working unchanged.
 */
class UnknownBlockTypeException extends \InvalidArgumentException
{
    public function __construct(
        public readonly string $blockType,
        public readonly string $position,
    ) {
        $label = $blockType === '' ? '(missing type)' : $blockType;
        parent::__construct("Unknown block type \"{$label}\" at {$position}.");
    }
}
