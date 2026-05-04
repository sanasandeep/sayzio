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
        $publicUrl = ($handle && $resume->is_public_pdf)
            ? url('/' . $handle . '/resume.pdf')
            : null;

        return [
            'id'             => $resume->id,
            'template_id'    => $resume->template_id,
            'template'       => $resume->templateMeta(),
            'color_theme_id' => $resume->color_theme_id,
            'color_theme'    => $resume->colorThemeMeta(),
            'sections'       => $sections,
            'items'          => $items,
            'is_public_pdf'  => (bool) $resume->is_public_pdf,
            'public_pdf_url' => $publicUrl,
            'handle'         => $handle,
            'is_public'        => (bool) $resume->is_public,
            'visibility'       => $resume->visibility ?: 'public',
            'allow_indexing'   => $resume->allow_indexing === null ? true : (bool) $resume->allow_indexing,
            'has_password'     => filled($resume->password),
            'view_count'       => (int) ($resume->view_count ?? 0),
            'meta_description' => $resume->meta_description,
            'updated_at'     => optional($resume->updated_at)->toIso8601String(),
        ];
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
