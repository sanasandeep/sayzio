<?php

namespace App\Modules\User\Services;

use App\Modules\User\Models\Resume;

/**
 * Static read-only "will this PDF survive an ATS?" scanner.
 *
 * The checker introspects the resume's structured data (sections JSON +
 * ordered items) plus its template metadata and produces a checklist of
 * pass / warn / fail items. It is intentionally side-effect free — it
 * never writes back to the resume — so the same report can be requested
 * repeatedly (right before a PDF export, from a "review" panel in the
 * editor, etc.) without churning the row.
 *
 * Each check carries a `link` field that points at an `open` key the
 * editor's Alpine state uses, so the UI can deep-link the warning back
 * into the offending section.
 *
 * Keyword coverage is computed only when the caller passes a target
 * role/JD blob. When omitted it's left out of the report entirely
 * rather than reported as a passing check, so the UI doesn't claim a
 * win for a check the user hasn't opted into.
 */
class ResumeAtsChecker
{
    public const STATUS_PASS = 'pass';
    public const STATUS_WARN = 'warn';
    public const STATUS_FAIL = 'fail';

    /** Minimum body text length below which the resume reads as "thin". */
    private const MIN_TEXT_DENSITY = 400;

    /** Headings styles considered exotic enough to risk ATS parse issues. */
    private const EXOTIC_HEADING_STYLES = ['display', 'mono'];

    /** English stopwords stripped from JD keyword extraction. */
    private const STOPWORDS = [
        'the','and','for','with','you','your','our','are','will','that','this','from',
        'into','have','has','was','were','but','not','any','all','can','use','using',
        'who','why','what','how','when','where','which','their','they','them','its',
        'about','within','across','also','more','than','then','such','these','those',
        'his','her','him','she','one','two','per','etc','able','must','should','would',
        'could','may','might','some','many','few','most','other','every','each',
        'role','team','work','working','years','year','plus','etc','via','upon',
        'job','position','candidate','responsibilities','requirements','required',
        'preferred','experience','skills','ability','strong','solid','proven',
        'including','include','includes','well','good','great','excellent',
    ];

    /**
     * Run every check and return the structured report.
     *
     * @param Resume                          $resume
     * @param array{target_role?:string|null} $opts
     * @return array<string,mixed>
     */
    public static function check(Resume $resume, array $opts = []): array
    {
        $resume->loadMissing('items');
        $sections = $resume->getMergedSections();
        $header   = $sections['header'] ?? [];
        $summary  = (string) ($sections['summary'] ?? '');
        $tplStyle = (array) (($resume->templateMeta()['style'] ?? []));
        $itemsByType = $resume->items->groupBy('section_type');

        $bodyText = self::collectBodyText($summary, $itemsByType);
        $checks = [];

        // ── Contact email (FAIL when missing) ─────────────────────────
        $email = trim((string) ($header['email'] ?? ''));
        $checks[] = self::result(
            id: 'contact_email',
            label: 'Contact email',
            status: $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)
                ? self::STATUS_PASS : self::STATUS_FAIL,
            message: $email === ''
                ? 'Add an email address — most ATS pipelines reject resumes without one.'
                : (filter_var($email, FILTER_VALIDATE_EMAIL)
                    ? 'A reachable email was found.'
                    : 'Email looks invalid — double-check the address.'),
            link: 'header',
        );

        // ── Contact phone (WARN) ──────────────────────────────────────
        $phone = preg_replace('/\D+/', '', (string) ($header['phone'] ?? '')) ?? '';
        $checks[] = self::result(
            id: 'contact_phone',
            label: 'Contact phone',
            status: strlen($phone) >= 7 ? self::STATUS_PASS : self::STATUS_WARN,
            message: strlen($phone) >= 7
                ? 'Phone number present.'
                : 'Add a phone number — recruiters often filter on it.',
            link: 'header',
        );

        // ── Experience section present (FAIL) ─────────────────────────
        $expCount = $itemsByType->get('experience', collect())->count();
        $checks[] = self::result(
            id: 'section_experience',
            label: 'Experience section',
            status: $expCount > 0 ? self::STATUS_PASS : self::STATUS_FAIL,
            message: $expCount > 0
                ? "Found $expCount experience entr" . ($expCount === 1 ? 'y' : 'ies') . '.'
                : 'Add at least one experience entry — most ATS templates expect an "Experience" heading.',
            link: 'experience',
        );

        // ── Education section present (WARN) ──────────────────────────
        $eduCount = $itemsByType->get('education', collect())->count();
        $checks[] = self::result(
            id: 'section_education',
            label: 'Education section',
            status: $eduCount > 0 ? self::STATUS_PASS : self::STATUS_WARN,
            message: $eduCount > 0
                ? "Found $eduCount education entr" . ($eduCount === 1 ? 'y' : 'ies') . '.'
                : 'Add an education entry — many ATS rules flag resumes without one.',
            link: 'education',
        );

        // ── Header photo / icons (WARN) ───────────────────────────────
        $hasPhoto = !empty($header['photo_user_file_id']);
        $checks[] = self::result(
            id: 'header_photo',
            label: 'Header photo',
            status: $hasPhoto ? self::STATUS_WARN : self::STATUS_PASS,
            message: $hasPhoto
                ? 'Header photo detected. Many ATS parsers can\'t read images and may drop nearby contact info — consider removing it for ATS submissions.'
                : 'No header photo — ATS parsers see contact info cleanly.',
            link: 'header',
        );

        // ── Multi-column / sidebar layout (WARN) ──────────────────────
        $layout = (string) ($tplStyle['layout'] ?? 'single');
        $isMultiCol = $layout !== 'single';
        $checks[] = self::result(
            id: 'layout_columns',
            label: 'Single-column layout',
            status: $isMultiCol ? self::STATUS_WARN : self::STATUS_PASS,
            message: $isMultiCol
                ? 'Current template uses a multi-column layout. Some ATS parsers read columns left-to-right top-to-bottom and jumble your content — switch to a single-column template if parsing matters.'
                : 'Single-column layout — ATS-friendly.',
            link: 'design',
        );

        // ── Heading font (WARN for display/mono) ──────────────────────
        $headings = (string) ($tplStyle['headings'] ?? 'sans');
        $exotic = in_array($headings, self::EXOTIC_HEADING_STYLES, true);
        $checks[] = self::result(
            id: 'font_family',
            label: 'Standard fonts',
            status: $exotic ? self::STATUS_WARN : self::STATUS_PASS,
            message: $exotic
                ? "Template uses a $headings heading font. Decorative or monospaced fonts can be substituted unpredictably during PDF text extraction — pick a serif or sans template for the safest parse."
                : 'Headings use a standard ' . $headings . ' typeface.',
            link: 'design',
        );

        // ── Text density (WARN) ───────────────────────────────────────
        $density = mb_strlen($bodyText);
        $checks[] = self::result(
            id: 'text_density',
            label: 'Body text density',
            status: $density >= self::MIN_TEXT_DENSITY ? self::STATUS_PASS : self::STATUS_WARN,
            message: $density >= self::MIN_TEXT_DENSITY
                ? "Resume has $density characters of body text — plenty for a parser."
                : "Only $density characters of body text. Flesh out your summary and experience descriptions — short resumes often score below ATS keyword thresholds.",
            link: 'summary',
        );

        // ── Optional keyword coverage ─────────────────────────────────
        $report = self::summarize($checks);
        $targetRole = isset($opts['target_role']) ? trim((string) $opts['target_role']) : '';
        if ($targetRole !== '') {
            $kw = self::keywordCoverage($targetRole, $bodyText, $header, $summary);
            $coveragePct = $kw['coverage_pct'];

            // Roll keyword coverage into the checklist so the badge logic
            // doesn't have to special-case it.
            if ($coveragePct >= 60) {
                $status = self::STATUS_PASS;
                $msg = "Strong overlap with the role — $coveragePct% of its keywords appear in your resume.";
            } elseif ($coveragePct >= 30) {
                $status = self::STATUS_WARN;
                $msg = "Moderate overlap — $coveragePct% of role keywords matched. Consider weaving more in.";
            } else {
                $status = self::STATUS_WARN;
                $msg = "Low overlap — only $coveragePct% of role keywords matched. Many ATS rank by keyword density.";
            }
            $checks[] = self::result(
                id: 'keyword_coverage',
                label: 'Keyword coverage',
                status: $status,
                message: $msg,
                link: 'summary',
            );

            $report = self::summarize($checks);
            $report['keywords'] = $kw;
        }

        $report['checks'] = $checks;
        return $report;
    }

    // ── helpers ───────────────────────────────────────────────────────

    /**
     * @return array<string,mixed>
     */
    private static function result(
        string $id,
        string $label,
        string $status,
        string $message,
        ?string $link = null,
    ): array {
        return [
            'id'      => $id,
            'label'   => $label,
            'status'  => $status,
            'message' => $message,
            'link'    => $link,
        ];
    }

    /**
     * @param array<int,array<string,mixed>> $checks
     * @return array<string,mixed>
     */
    private static function summarize(array $checks): array
    {
        $counts = [self::STATUS_PASS => 0, self::STATUS_WARN => 0, self::STATUS_FAIL => 0];
        foreach ($checks as $c) {
            $counts[$c['status']] = ($counts[$c['status']] ?? 0) + 1;
        }
        $overall = $counts[self::STATUS_FAIL] > 0 ? self::STATUS_FAIL
            : ($counts[self::STATUS_WARN] > 0 ? self::STATUS_WARN : self::STATUS_PASS);
        return [
            'overall_status' => $overall,
            'pass_count'     => $counts[self::STATUS_PASS],
            'warn_count'     => $counts[self::STATUS_WARN],
            'fail_count'     => $counts[self::STATUS_FAIL],
            'has_unresolved' => $counts[self::STATUS_WARN] + $counts[self::STATUS_FAIL] > 0,
        ];
    }

    /**
     * Concatenate every piece of body text into one searchable blob —
     * used for both density and keyword coverage so the two checks see
     * exactly the same content.
     */
    private static function collectBodyText(string $summary, $itemsByType): string
    {
        $parts = [$summary];
        foreach ($itemsByType as $type => $items) {
            foreach ($items as $item) {
                $d = (array) ($item->data ?? []);
                foreach (['company', 'role', 'school', 'degree', 'field', 'name',
                          'title', 'subtitle', 'issuer', 'group', 'description', 'label'] as $k) {
                    if (!empty($d[$k])) $parts[] = (string) $d[$k];
                }
            }
        }
        return trim(implode(' ', array_filter($parts, fn ($p) => $p !== '')));
    }

    /**
     * Keyword coverage is intentionally simple: tokenize the JD into
     * unique 3+ char words (minus stopwords), and check how many appear
     * anywhere in the resume body. Returns capped lists so an enormous
     * JD doesn't blow up the response payload.
     *
     * @return array{coverage_pct:int,matched:array<int,string>,missing:array<int,string>,total:int}
     */
    private static function keywordCoverage(string $targetRole, string $bodyText, array $header, string $summary): array
    {
        $resumeText = mb_strtolower($bodyText . ' ' . ($header['headline'] ?? '') . ' ' . $summary);
        $stop = array_flip(self::STOPWORDS);

        $tokens = preg_split('/[^\p{L}\p{N}+#.\-]+/u', mb_strtolower($targetRole)) ?: [];
        $seen = [];
        $unique = [];
        foreach ($tokens as $tok) {
            $tok = trim($tok, ".-");
            if ($tok === '' || mb_strlen($tok) < 3) continue;
            if (isset($stop[$tok])) continue;
            if (isset($seen[$tok])) continue;
            $seen[$tok] = true;
            $unique[] = $tok;
        }
        if (!$unique) {
            return ['coverage_pct' => 0, 'matched' => [], 'missing' => [], 'total' => 0];
        }

        $matched = [];
        $missing = [];
        foreach ($unique as $kw) {
            // Word-boundary match so "react" doesn't match "reactor".
            $pattern = '/\b' . preg_quote($kw, '/') . '\b/u';
            if (preg_match($pattern, $resumeText)) {
                $matched[] = $kw;
            } else {
                $missing[] = $kw;
            }
        }

        $total = count($unique);
        $coverage = (int) round((count($matched) / max(1, $total)) * 100);

        return [
            'coverage_pct' => $coverage,
            'matched'      => array_slice($matched, 0, 40),
            'missing'      => array_slice($missing, 0, 40),
            'total'        => $total,
        ];
    }
}
