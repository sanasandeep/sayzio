<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use Illuminate\Http\Request;

/**
 * One definition of what a page background accepts, and one place that
 * turns a submitted form into stored settings.
 *
 * Sana, 2026-09-23, on the resume editor: "backgrounds are not working."
 *
 * They were not. Every page type that offers a background renders the
 * SAME picker partial (user.links.partials.biolink-background-card), but
 * until now each save path wrote its own validation rules for it, and
 * the resume's copy disagreed with the picker in two places that matter:
 *
 *   - `gradient_colors` was validated as an ARRAY. The picker posts it as
 *     a JSON STRING. So every gradient came back 422 and nothing saved --
 *     including, because a 422 rejects the whole request, the colour and
 *     blur the creator had set in the same submit.
 *   - `background_image` and `torn_image` were validated as STRINGS. They
 *     are file uploads. An uploaded photo was dropped on the floor, and
 *     `background_image_asset` -- the platform gallery pick -- was not in
 *     the accepted list at all, so the library did nothing either.
 *
 * A second, laxer copy of a rule set is how that happens. So there is one
 * copy now: rules() is what the picker is allowed to send, absorb() is
 * what happens to it afterwards, and a page type that wants a background
 * calls both rather than writing its own.
 *
 * Note the division of labour with PageBackground: that class READS the
 * stored settings and paints a page. This one WRITES them.
 */
class PageBackgroundInput
{
    /**
     * Validation rules for every background field the shared picker can
     * post, keyed exactly as the form names them.
     *
     * $user is needed because the upload rules are plan-aware (size and
     * MIME limits come from UploadPolicy).
     *
     * @return array<string, mixed>
     */
    public static function rules(?User $user): array
    {
        return [
            'background_type' => 'nullable|string|in:color,gradient,image,slideshow,video,template,preset,torn,tiles,mesh,pattern',
            // CSS background preset key from BgPresetCatalog. Only stored when
            // background_type === 'preset'; the public renderer resolves CSS from the
            // catalog server-side, so raw CSS is never accepted from the client.
            'bg_preset_key' => ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/',
                function ($attribute, $value, $fail) {
                    if ($value && !\App\Modules\User\Support\BgPresetCatalog::findByKey($value)) {
                        $fail('The selected background preset is not valid.');
                    }
                }
            ],
            'background_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'background_gradient' => 'nullable|string|max:500',
            'background_image' => \App\Services\UploadPolicy::rule('link.background_image', $user),
            // Platform gallery pick (Task #6015): S3 object key from the
            // curated `assets/` gallery folders. Validated by
            // prefix + safe filename (no S3 round-trip); resolved to the
            // public CDN URL below. Available on every plan.
            'background_image_asset' => ['nullable', 'string', 'max:300',
                function ($attribute, $value, $fail) {
                    // Task #6232: the picker now spans every curated folder a
                    // background may come from, not just biolink-backgrounds,
                    // because the Stock tab that served the other two was
                    // merged into it. Still validated by prefix + safe
                    // filename, so a request cannot point this at an
                    // arbitrary S3 object.
                    if ($value && !\App\Modules\User\Support\BackgroundImageGallery::accepts($value)) {
                        $fail('The selected gallery background is not valid.');
                    }
                }
            ],
            // Torn-paper composite: backdrop photo visible beyond the jagged
            // torn edge of a solid paper sheet.
            'torn_image' => \App\Services\UploadPolicy::rule('link.background_image', $user),
            'torn_paper_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            // Tear variant key (Task #6204): clip paths are resolved
            // server-side from TornStyleCatalog, never from client input.
            'torn_style' => ['nullable', 'string', 'max:30',
                function ($attribute, $value, $fail) {
                    if ($value && !\App\Modules\User\Support\TornStyleCatalog::isValidStyle($value)) {
                        $fail('The selected tear style is not valid.');
                    }
                }
            ],
            // Backdrop colors beyond the tear (hex only — the renderer
            // builds the gradient itself; a backdrop photo wins over these).
            'torn_backdrop_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'torn_backdrop_color2' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            // Tiles / Mesh / Pattern background types (Task #6204): only
            // catalog KEYS are accepted; all CSS resolves server-side.
            'tiles_palette' => ['nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) {
                    if ($value && !\App\Modules\User\Support\TilesBgCatalog::isValidPalette($value)) {
                        $fail('The selected tile palette is not valid.');
                    }
                }
            ],
            'tiles_layout' => 'nullable|string|in:uniform,metro,brick',
            'tiles_animate' => 'nullable|string|in:0,1',
            'mesh_preset' => ['nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) {
                    if ($value && !\App\Modules\User\Support\MeshGradientCatalog::isValidKey($value)) {
                        $fail('The selected mesh gradient is not valid.');
                    }
                }
            ],
            'pattern_preset' => ['nullable', 'string', 'max:40',
                function ($attribute, $value, $fail) {
                    if ($value && !\App\Modules\User\Support\PatternCatalog::isValidKey($value)) {
                        $fail('The selected pattern is not valid.');
                    }
                }
            ],
            'gradient_colors' => 'nullable|string|max:2000',
            'gradient_angle' => 'nullable|integer|min:0|max:360',
            'gradient_type' => 'nullable|string|in:linear,radial,conic',
            // Preset id from the GradientCatalog grid. Empty = custom (the
            // user manually edited stops). Stored alongside gradient_colors
            // so the picker can re-highlight the chosen preset on edit.
            'gradient_preset_id' => 'nullable|string|max:60|regex:/^[a-z0-9\-]+$/',
            'slideshow_images' => 'nullable|array|max:10',
            'slideshow_images.*' => \App\Services\UploadPolicy::rule('link.slideshow_image', $user, true),
            'slideshow_interval' => 'nullable|integer|min:1|max:30',
            'video_url' => 'nullable|string|max:500',
            'video_file' => \App\Services\UploadPolicy::rule('link.video_file', $user),
            'bg_template_id' => 'nullable|integer|exists:bg_templates,id',
            'bg_attachment' => 'nullable|string|in:fixed,scroll',
            'bg_fallback_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_fallback_image' => \App\Services\UploadPolicy::rule('link.bg_fallback_image', $user),
            'bg_blur' => 'nullable|integer|min:0|max:100',
            'bg_overlay_color' => ['nullable','string','max:20','regex:/^#[0-9a-fA-F]{3,8}$/'],
            'bg_overlay_opacity' => 'nullable|integer|min:0|max:100',
            // Transparency of the page preset background itself (Task #5970),
            // distinct from the overlay opacity above. 100 = fully opaque.
            'bg_preset_opacity' => 'nullable|integer|min:0|max:100',
        ];
    }

    /**
     * Fold a validated background submission into the settings array that
     * will be stored, handling everything that is not a plain scalar:
     * file uploads go to the user's vault, a gallery pick resolves to its
     * CDN URL, and the gradient stop list is decoded from its JSON.
     *
     * Scalars are NOT copied here -- the caller merges those itself,
     * because each page type has its own rules about which keys it keeps
     * (a design-locked link drops them; a resume keeps only the fields
     * PageBackground reads). This method owns only the parts where
     * getting it wrong means a silent no-op.
     *
     * @param  array<string,mixed>  $validated  the validated request data
     * @param  array<string,mixed>  $existing   background settings so far
     * @return array<string,mixed>  $existing with the media keys applied
     *
     * @throws \RuntimeException on a quota or size failure from the vault
     */
    public static function absorb(Request $request, array $validated, User $user, array $existing): array
    {
        // Downscale + re-encode raster photos so a background does not
        // bloat the vault with a full-res camera dump. Videos are stored
        // as-is, as they are everywhere else.
        $vault = function ($file, array $compress = []) use ($user) {
            $opts = [];
            if ($compress !== []) {
                $opts['compress_image'] = true;
                $opts['max_width']  = (int) ($compress['max_width']  ?? 1600);
                $opts['max_height'] = (int) ($compress['max_height'] ?? 1600);
                $opts['quality']    = (int) ($compress['quality']    ?? 85);
            }

            return UserFile::createFromUpload($file, $user, $opts)->url;
        };

        if ($request->hasFile('background_image')) {
            $existing['background_image'] = $vault($request->file('background_image'), ['max_width' => 1920, 'max_height' => 1920]);
        } elseif (! empty($validated['background_image_asset'])) {
            // Platform gallery pick -- store the public CDN URL directly.
            // No copy into the user's vault: platform assets never count
            // against user storage.
            $existing['background_image'] = PlatformAssetCatalog::urlForKey($validated['background_image_asset']);
        }

        if ($request->hasFile('torn_image')) {
            $existing['torn_image'] = $vault($request->file('torn_image'), ['max_width' => 1920, 'max_height' => 1920]);
        }

        // The picker serializes the stop list to JSON in a hidden input.
        // Anything that is not a JSON array is ignored rather than stored,
        // so a mangled payload leaves the previous gradient standing.
        if (! empty($validated['gradient_colors'])) {
            $decoded = json_decode((string) $validated['gradient_colors'], true);
            if (is_array($decoded)) {
                $existing['gradient_colors'] = $decoded;
            }
        }

        if (array_key_exists('gradient_preset_id', $validated)) {
            $existing['gradient_preset_id'] = (string) ($validated['gradient_preset_id'] ?? '');
        }

        $slideshowFiles = $request->file('slideshow_images');
        if (is_array($slideshowFiles) && $slideshowFiles !== []) {
            $slides = $existing['slideshow_images'] ?? [];
            foreach ($slideshowFiles as $file) {
                $slides[] = $vault($file, ['max_width' => 1600, 'max_height' => 1600]);
            }
            $existing['slideshow_images'] = array_slice($slides, 0, 10);
        }

        if ($request->hasFile('video_file')) {
            $existing['video_file'] = $vault($request->file('video_file'));
        }

        if ($request->hasFile('bg_fallback_image')) {
            $existing['bg_fallback_image'] = $vault($request->file('bg_fallback_image'), ['max_width' => 1920, 'max_height' => 1920]);
        }

        if ($request->has('remove_slideshow_images')) {
            $drop     = array_map('intval', (array) $request->input('remove_slideshow_images', []));
            $slides   = $existing['slideshow_images'] ?? [];
            $existing['slideshow_images'] = array_values(array_diff_key($slides, array_flip($drop)));
        }

        return $existing;
    }

    /**
     * The scalar keys a page background stores -- everything rules()
     * accepts minus the ones absorb() handles, which would otherwise be
     * copied across as an UploadedFile or a raw JSON string.
     *
     * @return array<int, string>
     */
    public static function scalarKeys(): array
    {
        return array_values(array_diff(PageBackground::FIELDS, self::MEDIA_KEYS));
    }

    /** Keys absorb() owns; never copied straight from the request. */
    public const MEDIA_KEYS = [
        'background_image', 'torn_image', 'gradient_colors',
        'slideshow_images', 'video_file', 'bg_fallback_image',
    ];
}
