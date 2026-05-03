<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Resume;
use App\Modules\User\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Renders a Resume model into a print-optimised PDF using dompdf.
 *
 * The Blade view (`user.resume.print`) mirrors the live editor preview
 * so the on-screen page and the downloaded PDF stay visually identical
 * (same template layout, same color theme, same content).
 *
 * Generated PDFs are cached for a short window keyed by resume content
 * + paper size, so repeated downloads of an unchanged resume don't pay
 * the Chromium-style render cost again. Any item edit bumps the
 * resume's effective version (max(updated_at) across resume + items),
 * which naturally invalidates the cache without explicit busts.
 */
class ResumePdfRenderer
{
    public const SIZE_A4     = 'a4';
    public const SIZE_LETTER = 'letter';

    /** Cache TTL for a generated PDF (seconds). */
    private const CACHE_TTL = 600;

    /**
     * Generate (or fetch from cache) the PDF binary for $resume at $size.
     *
     * @return array{filename:string, body:string, size:string}
     */
    public function render(Resume $resume, User $user, string $size = self::SIZE_A4): array
    {
        $size = $this->normalizeSize($size);
        $resume->loadMissing('items');

        $version = $this->resumeVersion($resume);
        $cacheKey = sprintf('resume_pdf:%d:%s:%s', $resume->id, $version, $size);

        $body = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($resume, $size) {
            return $this->generate($resume, $size);
        });

        return [
            'filename' => $this->filename($resume, $user),
            'body'     => $body,
            'size'     => $size,
        ];
    }

    /**
     * Bust any cached PDFs for this resume. Called after content updates.
     * Cache keys also include a content hash so this is mostly a hygiene
     * measure — stale entries would expire on TTL anyway.
     */
    public function invalidate(Resume $resume): void
    {
        // We don't track every (version,size) tuple; rely on TTL + the
        // version segment of the cache key. Nothing to do.
    }

    public function normalizeSize(?string $size): string
    {
        $s = strtolower((string) $size);
        return $s === self::SIZE_LETTER ? self::SIZE_LETTER : self::SIZE_A4;
    }

    /**
     * A short, stable hash that changes whenever the resume's rendered
     * content changes (header, summary, sections, items, template,
     * theme). Item rows don't `touch` the parent resume, so we mix the
     * items' max(updated_at) into the version segment ourselves.
     */
    private function resumeVersion(Resume $resume): string
    {
        $itemMax = $resume->items->max('updated_at');
        $parts = [
            $resume->updated_at?->getTimestamp() ?: 0,
            $itemMax?->getTimestamp() ?: 0,
            $resume->template_id,
            $resume->color_theme_id,
        ];
        return substr(sha1(implode('|', $parts)), 0, 12);
    }

    private function generate(Resume $resume, string $size): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', [resource_path('views')]);

        $html = view('user.resume.print', [
            'resume'  => $resume,
            'header'  => $resume->getMergedSections()['header'] ?? [],
            'summary' => $resume->getMergedSections()['summary'] ?? '',
            'customSections' => $resume->getMergedSections()['custom_sections'] ?? [],
            'itemsByType'    => $resume->items->groupBy('section_type'),
            'template'       => $resume->templateMeta(),
            'theme'          => $resume->colorThemeMeta()['tokens'] ?? [],
            'paperSize'      => $size,
        ])->render();

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper($size, 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function filename(Resume $resume, User $user): string
    {
        $name = trim((string) ($resume->getMergedSections()['header']['name'] ?? ''));
        if ($name === '') $name = (string) ($user->name ?? 'resume');
        $slug = Str::slug($name);
        if ($slug === '') $slug = 'resume';
        return $slug . '-resume.pdf';
    }
}
