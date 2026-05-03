<?php

namespace App\Modules\User\Services;

/**
 * Registry of visual templates available for the Resume / Portfolio
 * builder. Each template advertises which sections it natively supports
 * so the editor can hide section pickers a template can't lay out, and
 * the public renderer / PDF exporter can read from one source of truth.
 *
 * Templates are intentionally just metadata + a thumbnail path here —
 * the actual layout lives in Blade partials added by the renderer task.
 *
 * Style schema (all keys optional, sensible defaults applied by the
 * renderer):
 *  - layout        : single | sidebar | sidebar-right | two-col |
 *                    portfolio | portfolio-grid | timeline | compact
 *  - headings      : sans | serif | display | mono
 *  - density       : tight | comfortable | spacious
 *  - header_style  : rule | banner | block | split | monogram |
 *                    centered | minimal | underline | photo-left |
 *                    sidebar-photo
 *  - divider       : rule | dot | none | accent-bar | double
 *  - accent        : none | left-rail | top-bar | right-rail | corner
 *  - title_style   : uppercase | underline | pill | numbered |
 *                    bracket | bar | plain | capitalized
 */
class ResumeTemplateRegistry
{
    /**
     * Section keys that any template MAY render. The editor uses this
     * to validate user input; templates whitelist a subset.
     */
    public const ALL_SECTIONS = [
        'header', 'summary', 'experience', 'education', 'skills',
        'projects', 'certifications', 'awards', 'languages', 'links',
        'custom',
    ];

    /** Default common section list used by most templates. */
    private const SECTIONS_DEFAULT = [
        'header', 'summary', 'experience', 'education', 'skills',
        'projects', 'certifications', 'awards', 'languages', 'links',
        'custom',
    ];

    /** Categories the picker UI groups templates into, in display order. */
    public const CATEGORIES = [
        'professional' => 'Professional',
        'modern'       => 'Modern',
        'creative'     => 'Creative',
        'academic'     => 'Academic',
        'technical'    => 'Technical',
        'executive'    => 'Executive',
        'portfolio'    => 'Portfolio',
        'minimal'      => 'Minimal',
    ];

    /** @return array<int, array<string,mixed>> */
    public static function all(): array
    {
        $defaults = self::SECTIONS_DEFAULT;
        $classicSections = ['header', 'summary', 'experience', 'education', 'skills', 'certifications', 'awards', 'languages', 'links', 'custom'];

        $defs = [
            // ─── Existing legacy templates (IDs preserved) ─────────────
            ['id' => 'classic',  'cat' => 'professional', 'name' => 'Classic',
             'desc' => 'Traditional single-column layout with serif headings — the safe choice for formal applications.',
             'sections' => $classicSections,
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'comfortable']],

            ['id' => 'modern',   'cat' => 'modern',       'name' => 'Modern',
             'desc' => 'Two-column layout with a sidebar for skills and contact info — clean and recruiter-friendly.',
             'style' => ['layout' => 'sidebar', 'headings' => 'sans', 'density' => 'comfortable']],

            ['id' => 'compact',  'cat' => 'professional', 'name' => 'Compact',
             'desc' => 'Tight typography that fits a long career on a single page — great for senior CVs.',
             'sections' => $classicSections,
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'tight']],

            ['id' => 'creative', 'cat' => 'creative',     'name' => 'Creative',
             'desc' => 'Portfolio-first layout with a featured projects strip — perfect for designers, photographers, and creators.',
             'sections' => ['header', 'summary', 'projects', 'experience', 'skills', 'education', 'awards', 'languages', 'links', 'custom'],
             'style' => ['layout' => 'portfolio', 'headings' => 'display', 'density' => 'spacious']],

            // ─── Professional ─────────────────────────────────────────
            ['id' => 'executive-serif', 'cat' => 'professional', 'name' => 'Executive Serif',
             'desc' => 'Stately serif with a centered nameplate and accent-rule dividers.',
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'centered', 'divider' => 'accent-bar', 'title_style' => 'uppercase']],
            ['id' => 'ivy-formal', 'cat' => 'professional', 'name' => 'Ivy Formal',
             'desc' => 'Old-school CV with small-cap section titles and ruled margins.',
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'double', 'title_style' => 'capitalized']],
            ['id' => 'corporate-block', 'cat' => 'professional', 'name' => 'Corporate Block',
             'desc' => 'Solid header band with a confident, blocky structure.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'block', 'divider' => 'rule', 'title_style' => 'bar', 'accent' => 'top-bar']],
            ['id' => 'consulting-grey', 'cat' => 'professional', 'name' => 'Consulting Grey',
             'desc' => 'Muted, reportlike layout with quiet rules between sections.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'underline']],
            ['id' => 'legal-brief', 'cat' => 'professional', 'name' => 'Legal Brief',
             'desc' => 'Justified serif body with numbered section headings.',
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'tight',
                         'header_style' => 'minimal', 'divider' => 'rule', 'title_style' => 'numbered']],
            ['id' => 'notary-rule', 'cat' => 'professional', 'name' => 'Notary Rule',
             'desc' => 'Document-style header with a thick top rule and tight spacing.',
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'tight',
                         'header_style' => 'underline', 'divider' => 'dot', 'title_style' => 'capitalized', 'accent' => 'top-bar']],
            ['id' => 'banker-blue', 'cat' => 'professional', 'name' => 'Banker',
             'desc' => 'Quietly confident two-column layout with right-side meta.',
             'style' => ['layout' => 'sidebar-right', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'uppercase']],

            // ─── Modern ───────────────────────────────────────────────
            ['id' => 'nordic-clean', 'cat' => 'modern', 'name' => 'Nordic Clean',
             'desc' => 'Generous whitespace, thin rules, calm sans typography.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'spacious',
                         'header_style' => 'minimal', 'divider' => 'rule', 'title_style' => 'plain']],
            ['id' => 'monochrome-grid', 'cat' => 'modern', 'name' => 'Monochrome Grid',
             'desc' => 'Two balanced columns and consistent grid rules.',
             'style' => ['layout' => 'two-col', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'split', 'divider' => 'rule', 'title_style' => 'underline']],
            ['id' => 'soft-card', 'cat' => 'modern', 'name' => 'Soft Card',
             'desc' => 'Pill-shaped section titles and rounded accent dividers.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'block', 'divider' => 'accent-bar', 'title_style' => 'pill']],
            ['id' => 'inline-accent', 'cat' => 'modern', 'name' => 'Inline Accent',
             'desc' => 'Left-rail accent bar runs the full height of the page.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'bar', 'accent' => 'left-rail']],
            ['id' => 'swiss-rule', 'cat' => 'modern', 'name' => 'Swiss Rule',
             'desc' => 'Strict alignment, hairline rules, all-caps headings.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'tight',
                         'header_style' => 'underline', 'divider' => 'rule', 'title_style' => 'uppercase']],
            ['id' => 'lined-modern', 'cat' => 'modern', 'name' => 'Lined Modern',
             'desc' => 'Sidebar with skills + a lined main column for experience.',
             'style' => ['layout' => 'sidebar', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'split', 'divider' => 'rule', 'title_style' => 'underline']],
            ['id' => 'midnight-sans', 'cat' => 'modern', 'name' => 'Midnight Sans',
             'desc' => 'Bold sans with high-contrast accent bar above the name.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'banner', 'divider' => 'rule', 'title_style' => 'uppercase', 'accent' => 'top-bar']],
            ['id' => 'two-tone', 'cat' => 'modern', 'name' => 'Two Tone',
             'desc' => 'Right-hand sidebar with quiet accent fills.',
             'style' => ['layout' => 'sidebar-right', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'accent-bar', 'title_style' => 'pill']],

            // ─── Creative ─────────────────────────────────────────────
            ['id' => 'bold-banner', 'cat' => 'creative', 'name' => 'Bold Banner',
             'desc' => 'Full-width color banner with display-weight headings.',
             'style' => ['layout' => 'single', 'headings' => 'display', 'density' => 'spacious',
                         'header_style' => 'banner', 'divider' => 'accent-bar', 'title_style' => 'bar']],
            ['id' => 'magazine-cover', 'cat' => 'creative', 'name' => 'Magazine Cover',
             'desc' => 'Editorial split header with oversized name and tagline.',
             'style' => ['layout' => 'two-col', 'headings' => 'display', 'density' => 'spacious',
                         'header_style' => 'split', 'divider' => 'double', 'title_style' => 'numbered']],
            ['id' => 'accent-burst', 'cat' => 'creative', 'name' => 'Accent Burst',
             'desc' => 'Asymmetric accent corner block with playful section titles.',
             'style' => ['layout' => 'single', 'headings' => 'display', 'density' => 'comfortable',
                         'header_style' => 'block', 'divider' => 'accent-bar', 'title_style' => 'pill', 'accent' => 'corner']],
            ['id' => 'split-canvas', 'cat' => 'creative', 'name' => 'Split Canvas',
             'desc' => 'Half-color canvas with profile copy on one side.',
             'style' => ['layout' => 'sidebar', 'headings' => 'display', 'density' => 'comfortable',
                         'header_style' => 'sidebar-photo', 'divider' => 'none', 'title_style' => 'bar']],
            ['id' => 'brutalist', 'cat' => 'creative', 'name' => 'Brutalist',
             'desc' => 'Stark, oversized type with thick black rules.',
             'style' => ['layout' => 'single', 'headings' => 'display', 'density' => 'tight',
                         'header_style' => 'banner', 'divider' => 'double', 'title_style' => 'bracket']],
            ['id' => 'duotone', 'cat' => 'creative', 'name' => 'Duotone',
             'desc' => 'Two-color story with accent-on-accent section titles.',
             'style' => ['layout' => 'sidebar', 'headings' => 'display', 'density' => 'spacious',
                         'header_style' => 'photo-left', 'divider' => 'accent-bar', 'title_style' => 'pill']],
            ['id' => 'studio-folio', 'cat' => 'creative', 'name' => 'Studio Folio',
             'desc' => 'Designer-style layout with accent corner mark and big headings.',
             'style' => ['layout' => 'single', 'headings' => 'display', 'density' => 'spacious',
                         'header_style' => 'centered', 'divider' => 'accent-bar', 'title_style' => 'bracket', 'accent' => 'corner']],

            // ─── Academic ─────────────────────────────────────────────
            ['id' => 'academic-classic', 'cat' => 'academic', 'name' => 'Academic Classic',
             'desc' => 'Long-form CV with serif body and ruled section headings.',
             'sections' => $classicSections,
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'centered', 'divider' => 'rule', 'title_style' => 'uppercase']],
            ['id' => 'journal-cv', 'cat' => 'academic', 'name' => 'Journal CV',
             'desc' => 'Two-column layout reminiscent of an academic journal.',
             'sections' => $classicSections,
             'style' => ['layout' => 'two-col', 'headings' => 'serif', 'density' => 'tight',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'capitalized']],
            ['id' => 'doctoral', 'cat' => 'academic', 'name' => 'Doctoral',
             'desc' => 'Quiet sidebar for awards/languages, generous experience body.',
             'style' => ['layout' => 'sidebar', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'minimal', 'divider' => 'rule', 'title_style' => 'underline']],
            ['id' => 'lecture-notes', 'cat' => 'academic', 'name' => 'Lecture Notes',
             'desc' => 'Numbered headings, dotted dividers — feels like a syllabus.',
             'sections' => $classicSections,
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'tight',
                         'header_style' => 'rule', 'divider' => 'dot', 'title_style' => 'numbered']],
            ['id' => 'lab-report', 'cat' => 'academic', 'name' => 'Lab Report',
             'desc' => 'Strict rules, monospace meta, tight body for technical CVs.',
             'style' => ['layout' => 'single', 'headings' => 'mono', 'density' => 'tight',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'bracket']],
            ['id' => 'thesis-cover', 'cat' => 'academic', 'name' => 'Thesis Cover',
             'desc' => 'Title-page nameplate with double rules above and below.',
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'centered', 'divider' => 'double', 'title_style' => 'capitalized']],

            // ─── Technical ────────────────────────────────────────────
            ['id' => 'devops-mono', 'cat' => 'technical', 'name' => 'DevOps Mono',
             'desc' => 'Monospaced body with bracketed section titles.',
             'style' => ['layout' => 'single', 'headings' => 'mono', 'density' => 'tight',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'bracket']],
            ['id' => 'tech-monospace', 'cat' => 'technical', 'name' => 'Monospace',
             'desc' => 'Pure monospace résumé with hairline rules.',
             'style' => ['layout' => 'single', 'headings' => 'mono', 'density' => 'comfortable',
                         'header_style' => 'underline', 'divider' => 'rule', 'title_style' => 'plain']],
            ['id' => 'terminal', 'cat' => 'technical', 'name' => 'Terminal',
             'desc' => 'Prompt-style headings and dot-leader meta lines.',
             'style' => ['layout' => 'single', 'headings' => 'mono', 'density' => 'tight',
                         'header_style' => 'minimal', 'divider' => 'dot', 'title_style' => 'bracket']],
            ['id' => 'github-readme', 'cat' => 'technical', 'name' => 'README',
             'desc' => 'Markdown-flavored layout with bar-style headings.',
             'style' => ['layout' => 'single', 'headings' => 'mono', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'bar']],
            ['id' => 'systems-engineer', 'cat' => 'technical', 'name' => 'Systems Engineer',
             'desc' => 'Two-column technical CV with sidebar skill stack.',
             'style' => ['layout' => 'sidebar', 'headings' => 'mono', 'density' => 'tight',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'uppercase']],
            ['id' => 'data-engineer', 'cat' => 'technical', 'name' => 'Data Engineer',
             'desc' => 'Right-hand sidebar for tooling, dense main column.',
             'style' => ['layout' => 'sidebar-right', 'headings' => 'mono', 'density' => 'tight',
                         'header_style' => 'split', 'divider' => 'rule', 'title_style' => 'numbered']],
            ['id' => 'ic-engineer', 'cat' => 'technical', 'name' => 'IC Engineer',
             'desc' => 'Timeline-driven experience flow for individual contributors.',
             'style' => ['layout' => 'timeline', 'headings' => 'sans', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'uppercase']],

            // ─── Executive ────────────────────────────────────────────
            ['id' => 'executive-bold', 'cat' => 'executive', 'name' => 'Executive Bold',
             'desc' => 'Powerful banner header with display headings.',
             'style' => ['layout' => 'single', 'headings' => 'display', 'density' => 'spacious',
                         'header_style' => 'banner', 'divider' => 'accent-bar', 'title_style' => 'uppercase']],
            ['id' => 'boardroom', 'cat' => 'executive', 'name' => 'Boardroom',
             'desc' => 'Centered nameplate, columned achievements, double rules.',
             'style' => ['layout' => 'two-col', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'centered', 'divider' => 'double', 'title_style' => 'capitalized']],
            ['id' => 'cfo-numbered', 'cat' => 'executive', 'name' => 'CFO',
             'desc' => 'Numbered sections and right-rail meta — executive bio feel.',
             'style' => ['layout' => 'sidebar-right', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'numbered', 'accent' => 'right-rail']],
            ['id' => 'ceo-portrait', 'cat' => 'executive', 'name' => 'CEO Portrait',
             'desc' => 'Side portrait, oversized name, calm body type.',
             'style' => ['layout' => 'sidebar', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'sidebar-photo', 'divider' => 'rule', 'title_style' => 'uppercase']],
            ['id' => 'partner-page', 'cat' => 'executive', 'name' => 'Partner Page',
             'desc' => 'Left-rail accent strip with serif body and quiet rules.',
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'spacious',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'uppercase', 'accent' => 'left-rail']],
            ['id' => 'chairperson', 'cat' => 'executive', 'name' => 'Chairperson',
             'desc' => 'Stately centered header with pill section titles.',
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'spacious',
                         'header_style' => 'centered', 'divider' => 'accent-bar', 'title_style' => 'pill']],

            // ─── Portfolio ────────────────────────────────────────────
            ['id' => 'portfolio-grid', 'cat' => 'portfolio', 'name' => 'Portfolio Grid',
             'desc' => 'Image-friendly project grid with experience underneath.',
             'sections' => ['header', 'summary', 'projects', 'experience', 'skills', 'education', 'awards', 'links', 'custom'],
             'style' => ['layout' => 'portfolio-grid', 'headings' => 'display', 'density' => 'spacious',
                         'header_style' => 'banner', 'divider' => 'accent-bar', 'title_style' => 'bar']],
            ['id' => 'case-study', 'cat' => 'portfolio', 'name' => 'Case Study',
             'desc' => 'Long-form project narrative followed by experience.',
             'sections' => ['header', 'summary', 'projects', 'experience', 'skills', 'education', 'awards', 'links', 'custom'],
             'style' => ['layout' => 'portfolio', 'headings' => 'serif', 'density' => 'spacious',
                         'header_style' => 'split', 'divider' => 'rule', 'title_style' => 'numbered']],
            ['id' => 'designer-folio', 'cat' => 'portfolio', 'name' => 'Designer Folio',
             'desc' => 'Display headings, accent corner, project-led ordering.',
             'sections' => ['header', 'summary', 'projects', 'experience', 'skills', 'awards', 'links', 'custom'],
             'style' => ['layout' => 'portfolio', 'headings' => 'display', 'density' => 'spacious',
                         'header_style' => 'centered', 'divider' => 'accent-bar', 'title_style' => 'pill', 'accent' => 'corner']],
            ['id' => 'photographer-folio', 'cat' => 'portfolio', 'name' => 'Photographer',
             'desc' => 'Banner header and minimal type to let project work breathe.',
             'sections' => ['header', 'summary', 'projects', 'experience', 'awards', 'links', 'custom'],
             'style' => ['layout' => 'portfolio-grid', 'headings' => 'display', 'density' => 'spacious',
                         'header_style' => 'banner', 'divider' => 'none', 'title_style' => 'plain']],
            ['id' => 'illustrator-folio', 'cat' => 'portfolio', 'name' => 'Illustrator',
             'desc' => 'Playful pill headings, accent strip, project grid.',
             'sections' => ['header', 'summary', 'projects', 'skills', 'awards', 'links', 'custom'],
             'style' => ['layout' => 'portfolio-grid', 'headings' => 'display', 'density' => 'comfortable',
                         'header_style' => 'block', 'divider' => 'accent-bar', 'title_style' => 'pill', 'accent' => 'top-bar']],
            ['id' => 'art-director', 'cat' => 'portfolio', 'name' => 'Art Director',
             'desc' => 'Two-column gallery focus, with experience tucked alongside.',
             'sections' => ['header', 'summary', 'projects', 'experience', 'awards', 'links', 'custom'],
             'style' => ['layout' => 'portfolio', 'headings' => 'display', 'density' => 'comfortable',
                         'header_style' => 'split', 'divider' => 'accent-bar', 'title_style' => 'bracket']],

            // ─── Minimal ──────────────────────────────────────────────
            ['id' => 'minimal-line', 'cat' => 'minimal', 'name' => 'Minimal Line',
             'desc' => 'Hairline rules, plain headings, lots of whitespace.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'spacious',
                         'header_style' => 'minimal', 'divider' => 'rule', 'title_style' => 'plain']],
            ['id' => 'monospace-min', 'cat' => 'minimal', 'name' => 'Mono Minimal',
             'desc' => 'Monospace nameplate with no-fuss section titles.',
             'style' => ['layout' => 'single', 'headings' => 'mono', 'density' => 'comfortable',
                         'header_style' => 'minimal', 'divider' => 'none', 'title_style' => 'plain']],
            ['id' => 'ultra-compact', 'cat' => 'minimal', 'name' => 'Ultra Compact',
             'desc' => 'Tight everything — fits a long career into one page.',
             'sections' => $classicSections,
             'style' => ['layout' => 'compact', 'headings' => 'sans', 'density' => 'tight',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'uppercase']],
            ['id' => 'whitespace-pro', 'cat' => 'minimal', 'name' => 'Whitespace Pro',
             'desc' => 'Airy single column with calm sans typography.',
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'spacious',
                         'header_style' => 'minimal', 'divider' => 'none', 'title_style' => 'underline']],
            ['id' => 'typewriter', 'cat' => 'minimal', 'name' => 'Typewriter',
             'desc' => 'Monospace, dot-leader meta, classic typewriter feel.',
             'style' => ['layout' => 'single', 'headings' => 'mono', 'density' => 'comfortable',
                         'header_style' => 'underline', 'divider' => 'dot', 'title_style' => 'capitalized']],
            ['id' => 'essence', 'cat' => 'minimal', 'name' => 'Essence',
             'desc' => 'Almost ornament-free — just your name, body and rules.',
             'sections' => $classicSections,
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'spacious',
                         'header_style' => 'centered', 'divider' => 'none', 'title_style' => 'plain']],
            ['id' => 'paper-clean', 'cat' => 'minimal', 'name' => 'Paper Clean',
             'desc' => 'Document-style layout with a quiet rule under each heading.',
             'sections' => $classicSections,
             'style' => ['layout' => 'single', 'headings' => 'serif', 'density' => 'comfortable',
                         'header_style' => 'rule', 'divider' => 'rule', 'title_style' => 'underline']],
            ['id' => 'zen', 'cat' => 'minimal', 'name' => 'Zen',
             'desc' => 'Centered nameplate over a single calm rule.',
             'sections' => $classicSections,
             'style' => ['layout' => 'single', 'headings' => 'sans', 'density' => 'spacious',
                         'header_style' => 'centered', 'divider' => 'none', 'title_style' => 'plain']],
        ];

        $out = [];
        foreach ($defs as $d) {
            $cat = $d['cat'] ?? 'professional';
            $out[] = [
                'id'          => $d['id'],
                'name'        => $d['name'],
                'category'    => $cat,
                'category_label' => self::CATEGORIES[$cat] ?? ucfirst($cat),
                'description' => $d['desc'],
                'thumbnail'   => '/img/resume-templates/' . $d['id'] . '.svg',
                'sections'    => $d['sections'] ?? $defaults,
                'style'       => $d['style'] + [
                    'layout'       => 'single',
                    'headings'     => 'sans',
                    'density'      => 'comfortable',
                    'header_style' => 'rule',
                    'divider'      => 'rule',
                    'accent'       => 'none',
                    'title_style'  => 'uppercase',
                ],
            ];
        }
        return $out;
    }

    /** @return array<int, string> */
    public static function ids(): array
    {
        return array_column(self::all(), 'id');
    }

    /** @return array<string, string> */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Templates filtered to a single category id.
     *
     * @return array<int, array<string,mixed>>
     */
    public static function inCategory(string $cat): array
    {
        return array_values(array_filter(self::all(), fn ($t) => ($t['category'] ?? null) === $cat));
    }

    public static function find(?string $id): ?array
    {
        if (!$id) return null;
        foreach (self::all() as $tpl) {
            if ($tpl['id'] === $id) return $tpl;
        }
        return null;
    }

    public static function isValid(?string $id): bool
    {
        return $id !== null && in_array($id, self::ids(), true);
    }

    public static function defaultId(): string
    {
        return 'classic';
    }

    /**
     * Templates available to a particular user, honoring the
     * `resume.templates` plan-feature key. Defaults to "all" so that
     * nothing is gated until product/billing decides to lock specific
     * templates behind paid tiers.
     *
     * Plan-feature value shapes accepted:
     *   - '*' / null / ''       → every template
     *   - array<string>         → whitelist of template ids
     *
     * Super admins always see every template (handled inside
     * User::getPlanFeature).
     *
     * @return array<int, array<string,mixed>>
     */
    public static function availableFor($user): array
    {
        $allowed = $user ? $user->getPlanFeature('resume.templates', '*') : '*';
        if ($allowed === '*' || $allowed === null || $allowed === '' || !is_array($allowed)) {
            return self::all();
        }
        return array_values(array_filter(
            self::all(),
            fn (array $t) => in_array($t['id'], $allowed, true)
        ));
    }

    public static function userCanUse($user, string $id): bool
    {
        foreach (self::availableFor($user) as $tpl) {
            if ($tpl['id'] === $id) return true;
        }
        return false;
    }
}
