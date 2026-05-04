<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Resume;
use App\Modules\User\Models\ResumeSectionItem;
use App\Modules\User\Models\UserFile;

/**
 * Shared serialization for Resume payloads.
 *
 * Both the web ResumeController (session auth, Blade editor) and the
 * mobile API ResumeController (Sanctum bearer tokens) call into here
 * so the JSON shape stays identical no matter which client is editing.
 *
 * It also performs the "broken photo cleanup" side-effect that the web
 * controller used to do inline — when the stored header photo id no
 * longer resolves to a UserFile we clear the reference so the editor
 * doesn't keep showing a broken-image affordance.
 */
class ResumePresenter
{
    /** @return array<string,mixed> */
    public static function present(Resume $resume): array
    {
        $items    = $resume->items->map(fn ($i) => self::presentItem($i))->groupBy('section_type');
        $sections = $resume->getMergedSections();

        $sections['header']['photo_url'] = null;
        $photoId = $sections['header']['photo_user_file_id'] ?? null;
        if ($photoId) {
            $file = UserFile::where('id', $photoId)
                ->where('user_id', $resume->user_id)
                ->first();
            if ($file) {
                $sections['header']['photo_url'] = $file->url;
            } else {
                $sections['header']['photo_user_file_id'] = null;
                $persisted = $resume->sections ?? [];
                if (isset($persisted['header']['photo_user_file_id'])) {
                    $persisted['header']['photo_user_file_id'] = null;
                    $resume->update(['sections' => $persisted]);
                }
            }
        }

        $owner  = $resume->user;
        $handle = $owner?->handle;
        // Default versions stay at /{handle}/resume.pdf so old shared
        // links keep working; non-default versions get a stable
        // /v/{slug}.pdf suffix that names the version.
        $pdfPath = $resume->is_default
            ? '/resume.pdf'
            : '/resume/v/' . $resume->effectiveSlug() . '.pdf';
        $publicUrl = ($handle && $resume->is_public_pdf)
            ? url('/' . $handle . $pdfPath)
            : null;
        // Same shape for the HTML page link surfaced to the editor.
        $publicPagePath = $resume->is_default
            ? '/resume'
            : '/resume/v/' . $resume->effectiveSlug();
        $publicPageUrl = $handle
            ? url('/' . $handle . $publicPagePath)
            : null;

        return [
            'id'             => $resume->id,
            // Multi-version metadata. Defaults still resolve at the
            // bare /{handle}/resume URL; non-default versions get a
            // /v/{slug} suffix so each can be shared on its own.
            'name'           => $resume->displayName(),
            'slug'           => $resume->effectiveSlug(),
            'is_default'     => (bool) $resume->is_default,
            'template_id'    => $resume->template_id,
            'template'       => $resume->templateMeta(),
            'color_theme_id' => $resume->color_theme_id,
            'color_theme'    => $resume->colorThemeMeta(),
            'sections'       => $sections,
            'items'          => $items,
            'is_public_pdf'  => (bool) $resume->is_public_pdf,
            'public_pdf_url' => $publicUrl,
            'public_url'     => $publicPageUrl,
            'handle'         => $handle,
            'is_public'        => (bool) $resume->is_public,
            'visibility'       => $resume->visibility ?: 'public',
            'allow_indexing'   => $resume->allow_indexing === null ? true : (bool) $resume->allow_indexing,
            'has_password'     => filled($resume->password),
            'expires_at'       => optional($resume->expires_at)->toIso8601String(),
            'is_share_expired' => $resume->isShareExpired(),
            'share_revision'   => (int) ($resume->share_revision ?? 0),
            'view_count'       => (int) ($resume->view_count ?? 0),
            'meta_description' => $resume->meta_description,
            'updated_at'     => optional($resume->updated_at)->toIso8601String(),
        ];
    }

    /**
     * Lightweight summary of every version on the user's account, used
     * by the version switcher in the editor. Intentionally compact — no
     * items, no sections — so the bootstrap payload stays small even
     * when the user keeps the maximum number of versions.
     *
     * @param  iterable<int,Resume> $versions
     * @return array<int,array<string,mixed>>
     */
    public static function presentVersions(iterable $versions): array
    {
        $out = [];
        foreach ($versions as $v) {
            $out[] = [
                'id'         => (int) $v->id,
                'name'       => $v->displayName(),
                'slug'       => $v->effectiveSlug(),
                'is_default' => (bool) $v->is_default,
                'is_public'  => (bool) $v->is_public,
                'updated_at' => optional($v->updated_at)->toIso8601String(),
                'view_count' => (int) ($v->view_count ?? 0),
            ];
        }
        return $out;
    }

    /** @return array<string,mixed> */
    public static function presentItem(ResumeSectionItem $item): array
    {
        return [
            'id'           => $item->id,
            'section_type' => $item->section_type,
            'position'     => $item->position,
            'data'         => $item->data ?? [],
        ];
    }
}
