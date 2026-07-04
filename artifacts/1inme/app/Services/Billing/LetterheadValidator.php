<?php

namespace App\Services\Billing;

use Illuminate\Http\UploadedFile;

/**
 * Validates a letterhead image upload (BillingCompany default or a
 * per-invoice override) before it's persisted: file type/size via Laravel's
 * own `image`/`mimes`/`max` rules, plus a pixel-dimension + aspect-ratio
 * sanity check against the chosen page orientation so a wildly mismatched
 * image (e.g. a square logo used as a full-page background) is rejected with
 * a clear message instead of silently rendering stretched/cropped.
 */
class LetterheadValidator
{
    public const MAX_KB = 5120; // 5MB
    public const MIN_WIDTH = 400;
    public const MIN_HEIGHT = 400;
    public const MAX_WIDTH = 6000;
    public const MAX_HEIGHT = 6000;

    /** Base Laravel validation rules for the `letterhead` file input. */
    public static function rules(): array
    {
        return [
            'nullable',
            'image',
            'mimes:jpeg,jpg,png,webp',
            'max:' . self::MAX_KB,
        ];
    }

    /**
     * Dimension + aspect-ratio check against the requested page orientation.
     * Returns null when valid, or a human-readable error message.
     */
    public static function validateDimensions(UploadedFile $file, string $orientation = 'portrait'): ?string
    {
        $size = @getimagesize($file->getRealPath());
        if ($size === false) {
            return 'The letterhead file does not appear to be a valid image.';
        }

        [$width, $height] = $size;

        if ($width < self::MIN_WIDTH || $height < self::MIN_HEIGHT) {
            return sprintf(
                'The letterhead image is too small (%dx%d px). It must be at least %dx%d px.',
                $width, $height, self::MIN_WIDTH, self::MIN_HEIGHT
            );
        }
        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            return sprintf(
                'The letterhead image is too large (%dx%d px). It must be at most %dx%d px.',
                $width, $height, self::MAX_WIDTH, self::MAX_HEIGHT
            );
        }

        $orientation = $orientation === 'landscape' ? 'landscape' : 'portrait';
        $ratio = $width / max(1, $height);
        // A4 aspect ratio is ~0.707 (portrait) or ~1.414 (landscape). Allow a
        // generous tolerance band around it so common photo/scan ratios pass,
        // while still rejecting an image whose orientation is flatly wrong
        // (e.g. a wide landscape banner picked for a portrait document).
        if ($orientation === 'portrait' && $ratio > 1.05) {
            return 'This image looks like a landscape image but the letterhead orientation is set to portrait. Choose a taller image or switch the orientation.';
        }
        if ($orientation === 'landscape' && $ratio < 0.95) {
            return 'This image looks like a portrait image but the letterhead orientation is set to landscape. Choose a wider image or switch the orientation.';
        }

        return null;
    }

    /** @return array{width:int,height:int}|null */
    public static function dimensions(UploadedFile $file): ?array
    {
        $size = @getimagesize($file->getRealPath());
        if ($size === false) return null;
        return ['width' => (int) $size[0], 'height' => (int) $size[1]];
    }
}
