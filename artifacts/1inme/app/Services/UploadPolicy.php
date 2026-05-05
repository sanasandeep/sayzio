<?php

namespace App\Services;

use App\Modules\User\Models\User;

class UploadPolicy
{
    /**
     * Registry of every upload location in the app.
     * Each context defines the baseline (used when the plan does not override).
     *
     *   key => [
     *     'label'      => human-readable name shown in admin plan editor,
     *     'group'      => optional group label for UI organization,
     *     'max_mb'     => default max size in megabytes,
     *     'extensions' => default allowed extensions (lowercase, no dot),
     *     'multiple'   => whether the location accepts multiple files,
     *   ]
     */
    public const CONTEXTS = [
        // ── Link in Bio: page appearance ──────────────────────────────────
        'link.background_image'  => ['label' => 'Bio Page Background Image',  'group' => 'Bio Appearance',     'max_mb' => 5,  'extensions' => ['jpg','jpeg','png','webp','gif'],          'multiple' => false],
        'link.slideshow_image'   => ['label' => 'Bio Page Slideshow Images',  'group' => 'Bio Appearance',     'max_mb' => 5,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => true],
        'link.video_file'        => ['label' => 'Bio Page Background Video', 'group' => 'Bio Appearance',     'max_mb' => 50, 'extensions' => ['mp4','webm'],                              'multiple' => false],
        'link.bg_fallback_image' => ['label' => 'Bio Video Fallback Image',   'group' => 'Bio Appearance',     'max_mb' => 5,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => false],

        // ── Link: SEO + favicon (on links/edit and create-url) ────────────
        'link.seo_image'         => ['label' => 'Link SEO / OG Image',        'group' => 'Link SEO',           'max_mb' => 2,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => false],
        'link.favicon'           => ['label' => 'Link Favicon',               'group' => 'Link SEO',           'max_mb' => 1,  'extensions' => ['ico','png','svg','jpg','jpeg'],           'multiple' => false],

        // ── Bio Page advanced (settings/advanced) ─────────────────────────
        'link.og_image_upload'   => ['label' => 'Bio OG / Social Share Image','group' => 'Bio Advanced',       'max_mb' => 2,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => false],
        'link.favicon_upload'    => ['label' => 'Bio Favicon',                'group' => 'Bio Advanced',       'max_mb' => 1,  'extensions' => ['ico','png','svg','jpg','jpeg'],           'multiple' => false],
        'link.apple_touch_upload'=> ['label' => 'Bio Apple Touch Icon',       'group' => 'Bio Advanced',       'max_mb' => 1,  'extensions' => ['png'],                                     'multiple' => false],
        'link.icon_512_upload'   => ['label' => 'Bio PWA 512x512 Icon',       'group' => 'Bio Advanced',       'max_mb' => 2,  'extensions' => ['png'],                                     'multiple' => false],

        // ── vCard ─────────────────────────────────────────────────────────
        'vcf.photo'              => ['label' => 'Contact Card Photo',        'group' => 'Contact Card',       'max_mb' => 5,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => false],

        // ── Forms ─────────────────────────────────────────────────────────
        'forms.logo'             => ['label' => 'Form Logo',                  'group' => 'Forms',              'max_mb' => 2,  'extensions' => ['jpg','jpeg','png','webp','svg'],          'multiple' => false],
        'forms.cover'            => ['label' => 'Form Cover Image',           'group' => 'Forms',              'max_mb' => 5,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => false],
        'forms.card_image'       => ['label' => 'Form Card Image',            'group' => 'Forms',              'max_mb' => 5,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => false],
        'form_field.file'        => ['label' => 'Form Builder File Field',    'group' => 'Forms',              'max_mb' => 10, 'extensions' => ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','webp','zip'], 'multiple' => false],

        // ── Intros ────────────────────────────────────────────────────────
        'splash.logo'            => ['label' => 'Intro Logo',                 'group' => 'Intros',             'max_mb' => 2,  'extensions' => ['jpg','jpeg','png','webp','svg'],          'multiple' => false],
        'splash.favicon'         => ['label' => 'Intro Favicon',              'group' => 'Intros',             'max_mb' => 1,  'extensions' => ['ico','png','svg','jpg','jpeg'],           'multiple' => false],
        'splash.og'              => ['label' => 'Intro OG Image',             'group' => 'Intros',             'max_mb' => 2,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => false],

        // ── QR Studio ─────────────────────────────────────────────────────
        'qr.logo'                => ['label' => 'QR Code Logo Overlay',       'group' => 'QR Studio',          'max_mb' => 2,  'extensions' => ['jpg','jpeg','png','webp','svg'],          'multiple' => false],

        // ── Verification ──────────────────────────────────────────────────
        'verification.logo'      => ['label' => 'Verification Logo',          'group' => 'Verification',       'max_mb' => 2,  'extensions' => ['jpg','jpeg','png','webp','svg'],          'multiple' => false],
        'verification.proof'     => ['label' => 'Verification Proof Documents','group' => 'Verification',      'max_mb' => 5,  'extensions' => ['pdf','jpg','jpeg','png','webp'],          'multiple' => true],

        // ── File Share (uses plan-wide max_file_size_mb by default) ───────
        'link.file_share'        => ['label' => 'File Share Upload',          'group' => 'File Share',         'max_mb' => 5,  'extensions' => [],                                          'multiple' => false],

        // ── Biolink Wizard ────────────────────────────────────────────────
        'biolink.avatar'         => ['label' => 'Wizard Avatar / Cover',      'group' => 'Bio Wizard',         'max_mb' => 5,  'extensions' => ['jpg','jpeg','png','webp'],                'multiple' => false],
    ];

    /**
     * Resolve the effective policy for a context for the given user.
     * Returns: ['max_mb' => int, 'extensions' => string[], 'multiple' => bool, 'accept' => string, 'label' => string]
     */
    public static function for(string $key, ?User $user = null): array
    {
        $base = self::CONTEXTS[$key] ?? null;
        if (!$base) {
            // Unknown context — degrade safely to a generic 5MB image policy.
            $base = ['label' => $key, 'max_mb' => 5, 'extensions' => ['jpg','jpeg','png','webp'], 'multiple' => false];
        }

        $maxMb      = (int) $base['max_mb'];
        $extensions = array_values(array_unique(array_map('strtolower', $base['extensions'] ?? [])));
        $multiple   = (bool) ($base['multiple'] ?? false);

        // Holders of `user.files.access_any` get an effectively unlimited
        // upload policy: 10 GB cap (well above any reverse-proxy upload
        // limit) and no extension filter.
        if ($user && method_exists($user, 'hasPermission') && $user->hasPermission('user.files.access_any')) {
            return [
                'key'        => $key,
                'label'      => $base['label'] ?? $key,
                'max_mb'     => 10240,
                'extensions' => [],
                'multiple'   => $multiple,
                'accept'     => '',
            ];
        }

        if ($user) {
            // Per-context override stored at features.upload_limits.<key>
            $override = $user->getPlanFeature('upload_limits', []);
            if (is_array($override) && isset($override[$key]) && is_array($override[$key])) {
                $row = $override[$key];
                if (isset($row['max_mb']) && (int) $row['max_mb'] > 0) {
                    $maxMb = (int) $row['max_mb'];
                }
                if (isset($row['extensions'])) {
                    $exts = is_array($row['extensions'])
                        ? $row['extensions']
                        : array_filter(array_map('trim', explode(',', (string) $row['extensions'])));
                    $exts = array_values(array_unique(array_map(
                        fn($e) => ltrim(strtolower((string) $e), '.'),
                        $exts
                    )));
                    if (!empty($exts)) {
                        $extensions = $exts;
                    }
                }
            }

            // Special case: file_share defaults to the plan-wide max_file_size_mb
            // when no per-context override was supplied.
            if ($key === 'link.file_share'
                && (!is_array($override) || !isset($override[$key]['max_mb']))) {
                $maxMb = (int) $user->getPlanFeature('max_file_size_mb', $maxMb);
            }
        }

        return [
            'key'        => $key,
            'label'      => $base['label'] ?? $key,
            'max_mb'     => $maxMb,
            'extensions' => $extensions,
            'multiple'   => $multiple,
            'accept'     => self::buildAccept($extensions),
        ];
    }

    /**
     * Build a Laravel validation rule string for an upload field.
     * Returns e.g. "nullable|file|mimes:jpg,png|max:5120"
     */
    public static function rule(string $key, ?User $user = null, bool $required = false): string
    {
        $p = self::for($key, $user);
        $rules = [];
        $rules[] = $required ? 'required' : 'nullable';
        $rules[] = 'file';
        if (!empty($p['extensions'])) {
            $rules[] = 'mimes:' . implode(',', $p['extensions']);
        }
        $rules[] = 'max:' . ((int) $p['max_mb'] * 1024);
        return implode('|', $rules);
    }

    /**
     * Convenience: return a comma-separated <input accept="..."> string.
     */
    private static function buildAccept(array $extensions): string
    {
        if (empty($extensions)) return '';
        return implode(',', array_map(fn($e) => '.' . $e, $extensions));
    }

    /**
     * For the admin plan editor: return all contexts with the plan's current overrides applied.
     *
     * @return array<string, array{label:string, group:string, max_mb:int, extensions:array, multiple:bool}>
     */
    public static function contextsForPlan(array $planFeatures = []): array
    {
        $overrides = $planFeatures['upload_limits'] ?? [];
        $rows = [];
        foreach (self::CONTEXTS as $key => $base) {
            $maxMb = (int) $base['max_mb'];
            $exts  = $base['extensions'] ?? [];
            if (isset($overrides[$key]) && is_array($overrides[$key])) {
                if (isset($overrides[$key]['max_mb']) && (int) $overrides[$key]['max_mb'] > 0) {
                    $maxMb = (int) $overrides[$key]['max_mb'];
                }
                if (isset($overrides[$key]['extensions'])) {
                    $raw = $overrides[$key]['extensions'];
                    $exts = is_array($raw) ? $raw : array_filter(array_map('trim', explode(',', (string) $raw)));
                }
            }
            $rows[$key] = [
                'label'      => $base['label'] ?? $key,
                'group'      => $base['group'] ?? 'Other',
                'max_mb'     => $maxMb,
                'extensions' => array_values(array_map(fn($e) => ltrim(strtolower((string) $e), '.'), $exts)),
                'multiple'   => (bool) ($base['multiple'] ?? false),
                'default_max_mb'     => (int) ($base['max_mb'] ?? 5),
                'default_extensions' => $base['extensions'] ?? [],
            ];
        }
        return $rows;
    }
}
