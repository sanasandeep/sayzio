@php
    use Illuminate\Support\Str;

    $primary    = $theme['primary']    ?? '#111827';
    $accent     = $theme['accent']     ?? '#4b5563';
    $textColor  = $theme['text']       ?? '#1f2937';
    $muted      = $theme['muted']      ?? '#6b7280';
    $bg         = $theme['background'] ?? '#ffffff';

    $style      = $template['style'] ?? [];
    $layout     = $style['layout']        ?? 'single';
    $headings   = $style['headings']      ?? 'sans';
    $density    = $style['density']       ?? 'comfortable';
    $headerStl  = $style['header_style']  ?? 'rule';
    $divider    = $style['divider']       ?? 'rule';
    $accentStl  = $style['accent']        ?? 'none';
    $titleStl   = $style['title_style']   ?? 'uppercase';

    // dompdf has only the bundled "DejaVu Sans/Serif" embedded fonts to
    // guarantee selectable text + Unicode support. Map the editor's font
    // axis onto those families. Mono falls back to DejaVu Sans Mono if
    // available; dompdf substitutes when missing.
    $bodyFont   = $headings === 'serif' ? 'DejaVu Serif'
                : ($headings === 'mono' ? 'DejaVu Sans Mono' : 'DejaVu Sans');
    $headFont   = $bodyFont;

    $baseFontPx  = $density === 'tight' ? 10.5 : ($density === 'spacious' ? 12.0 : 11.0);
    if ($layout === 'compact') $baseFontPx = min($baseFontPx, 10.0);
    $linePct     = $density === 'tight' ? 1.35 : ($density === 'spacious' ? 1.6 : 1.5);
    if ($layout === 'compact') $linePct = min($linePct, 1.3);
    $pageMargin  = $density === 'tight' ? '14mm' : ($density === 'spacious' ? '20mm' : '16mm');
    if ($layout === 'compact') $pageMargin = '12mm';

    $paperSizeCss = ($paperSize ?? 'a4') === 'letter' ? 'letter' : 'A4';

    // --- Title style → CSS bits applied to h2.section-title -----------
    // Each title style produces a visually distinct treatment so the same
    // section header looks different across templates.
    $titleTransform = match ($titleStl) {
        'capitalized' => 'capitalize',
        'plain', 'bracket', 'numbered', 'pill' => 'none',
        default       => 'uppercase',
    };
    $titleLetterSp  = in_array($titleStl, ['uppercase'], true) ? '1.5pt' : '0';
    $titleExtraCss  = '';
    if ($titleStl === 'pill') {
        $titleExtraCss = "display:inline-block;background:{$primary};color:#fff;padding:2pt 8pt;border-radius:8pt;border:0;";
    } elseif ($titleStl === 'bar') {
        $titleExtraCss = "border-left:3pt solid {$primary};padding-left:6pt;";
    } elseif ($titleStl === 'underline') {
        $titleExtraCss = "font-size:" . ($baseFontPx + 2) . "pt;";
    }

    // --- Divider style → border-bottom for h2.section-title ----------
    $titleBorder = match ($divider) {
        'double'     => "2.5pt double {$primary}",
        'dot'        => "1pt dotted {$muted}",
        'accent-bar' => "2.5pt solid {$accent}",
        'none'       => '0',
        default      => "1pt solid {$primary}",
    };
    // Pill/underline style overrides the bottom border so it doesn't
    // double-up visually.
    if ($titleStl === 'pill') $titleBorder = '0';
    if ($titleStl === 'underline' && $divider === 'rule') $titleBorder = "2pt solid {$primary}";

    // --- Accent treatment → small visual mark on every .item ---------
    $itemExtraCss = '';
    $bodyExtraCss = '';
    if ($accentStl === 'left-rail') {
        $itemExtraCss = "border-left:2pt solid {$accent};padding-left:8pt;";
    } elseif ($accentStl === 'right-rail') {
        $itemExtraCss = "border-right:2pt solid {$accent};padding-right:8pt;";
    } elseif ($accentStl === 'corner') {
        $itemExtraCss = "border-top:1pt solid {$accent};padding-top:3pt;";
    } elseif ($accentStl === 'top-bar') {
        $bodyExtraCss = "border-top:4pt solid {$accent};padding-top:8pt;";
    }

    $fmtMonth = function ($s) {
        if (!$s) return '';
        if (preg_match('/^(\d{4})-(\d{2})$/', (string)$s, $m)) {
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            $idx = max(1, min(12, (int)$m[2])) - 1;
            return $months[$idx] . ' ' . $m[1];
        }
        return (string)$s;
    };
    $dateRange = function ($s, $e, $current = false) use ($fmtMonth) {
        $parts = array_filter([$fmtMonth($s), $current ? 'Present' : $fmtMonth($e)], fn($v) => $v !== '' && $v !== null);
        return implode(' – ', $parts);
    };

    $items = collect($itemsByType ?? collect());
    $byType = function (string $type) use ($items) {
        return collect($items->get($type, collect()))->sortBy('position')->values();
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Resume</title>
<style>
    @page { size: {{ $paperSizeCss }}; margin: {{ $pageMargin }}; }
    html, body {
        margin: 0; padding: 0;
        background: {{ $bg }};
        color: {{ $textColor }};
        font-family: '{{ $bodyFont }}', sans-serif;
        font-size: {{ $baseFontPx }}pt;
        line-height: {{ $linePct }};
    }
    body { {!! $bodyExtraCss !!} }

    /* ── Header variants ───────────────────────────────────────── */
    .name {
        font-family: '{{ $headFont }}', sans-serif;
        font-size: {{ $baseFontPx + 11 }}pt;
        font-weight: bold;
        color: {{ $primary }};
        margin: 0 0 2pt 0;
        line-height: 1.1;
    }
    .headline { color: {{ $accent }}; font-size: {{ $baseFontPx + 1 }}pt; margin: 0 0 4pt 0; }
    .contact  { color: {{ $muted }}; font-size: {{ $baseFontPx - 1 }}pt; margin: 0 0 8pt 0; }
    .contact .sep { color: {{ $muted }}; opacity: 0.6; }

    .header-rule { border: 0; border-bottom: 1.5pt solid {{ $primary }}; margin: 0 0 12pt 0; }

    /* Banner: solid primary background, name in white. */
    .header-banner {
        background: {{ $primary }};
        color: #fff;
        padding: 14pt 16pt;
        margin: -{{ $pageMargin }} -{{ $pageMargin }} 14pt -{{ $pageMargin }};
    }
    .header-banner .name { color: #fff; }
    .header-banner .headline { color: rgba(255,255,255,0.85); }
    .header-banner .contact  { color: rgba(255,255,255,0.75); }
    .header-banner .contact a { color: rgba(255,255,255,0.75) !important; }

    /* Block: solid block, name on top of color band, body below. */
    .header-block {
        background: {{ $primary }};
        color: #fff;
        padding: 12pt 14pt;
        border-radius: 4pt;
        margin: 0 0 12pt 0;
    }
    .header-block .name, .header-block .headline { color: #fff; }
    .header-block .contact { color: rgba(255,255,255,0.8); }
    .header-block .contact a { color: rgba(255,255,255,0.8) !important; }

    /* Centered: text-align center, decorative rule under. */
    .header-centered { text-align: center; padding-bottom: 6pt; margin-bottom: 10pt; }
    .header-centered.with-rule { border-bottom: 1pt solid {{ $primary }}; }

    /* Underline: thick underline only on name. */
    .header-underline .name { border-bottom: 3pt solid {{ $primary }}; padding-bottom: 3pt; display: inline-block; }
    .header-underline { margin-bottom: 12pt; }

    /* Minimal: no rule, smaller name. */
    .header-minimal .name { font-size: {{ $baseFontPx + 7 }}pt; }
    .header-minimal { margin-bottom: 10pt; }

    /* Split: name on left, contact on right, via 2-col table. */
    .header-split-table { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    .header-split-table td { vertical-align: top; padding: 0; }
    .header-split-table td.right { text-align: right; }
    .header-split-table td.right .contact { text-align: right; }

    /* Photo + text in a 2-column table because dompdf doesn't honor flex. */
    .header-table { width: 100%; border-collapse: collapse; margin: 0 0 4pt 0; }
    .header-table > tbody > tr > td { vertical-align: top; padding: 0; }
    .header-photo-cell { width: 70pt; padding-right: 12pt !important; }
    .header-photo {
        width: 60pt; height: 60pt;
        border: 1pt solid {{ $primary }};
        border-radius: 30pt;
    }

    /* ── Section title variants ────────────────────────────────── */
    h2.section-title {
        font-family: '{{ $headFont }}', sans-serif;
        font-size: {{ $baseFontPx }}pt;
        font-weight: bold;
        letter-spacing: {{ $titleLetterSp }};
        text-transform: {{ $titleTransform }};
        color: {{ $primary }};
        margin: 10pt 0 4pt 0;
        padding-bottom: 2pt;
        border-bottom: {{ $titleBorder }};
        page-break-after: avoid;
        {!! $titleExtraCss !!}
    }

    /* ── Item / section base ───────────────────────────────────── */
    .section { margin-bottom: 6pt; }
    .item    { margin-bottom: 7pt; page-break-inside: avoid; {!! $itemExtraCss !!} }
    .item-table { width: 100%; border-collapse: collapse; }
    .item-table td { vertical-align: top; padding: 0; }
    .item-table .meta { text-align: right; color: {{ $muted }}; font-size: {{ $baseFontPx - 1 }}pt; white-space: nowrap; }
    .item-title { font-weight: bold; font-size: {{ $baseFontPx + 1 }}pt; color: {{ $textColor }}; }
    .item-sub   { color: {{ $accent }}; font-size: {{ $baseFontPx }}pt; }
    .item-sub .muted { color: {{ $muted }}; }
    .item-desc  { font-size: {{ $baseFontPx }}pt; margin-top: 2pt; white-space: pre-wrap; }
    .summary    { font-size: {{ $baseFontPx }}pt; line-height: {{ $linePct }}; white-space: pre-wrap; }
    a.link { color: {{ $accent }}; text-decoration: underline; word-break: break-all; }
    .pill { display: inline-block; border: 1pt solid {{ $accent }}; color: {{ $accent }}; padding: 1pt 6pt; border-radius: 8pt; font-size: {{ $baseFontPx - 1 }}pt; margin: 0 4pt 4pt 0; }
    .skill-list, .lang-list { margin: 0; padding: 0; }
    .links-list { margin: 0; padding: 0; list-style: none; }
    .links-list li { margin: 0 0 2pt 0; }

    /* ── Layout helpers ────────────────────────────────────────── */
    .sidebar-table { width: 100%; border-collapse: collapse; }
    .sidebar-table > tbody > tr > td { vertical-align: top; padding: 0; }
    .sidebar-table > tbody > tr > td.side {
        width: 32%;
        padding-right: 14pt;
        border-right: 0.6pt solid {{ $muted }};
    }
    .sidebar-table > tbody > tr > td.main { padding-left: 14pt; }
    /* Mirror for sidebar-right. */
    .sidebar-table.right > tbody > tr > td.side {
        padding-right: 0; padding-left: 14pt;
        border-right: 0; border-left: 0.6pt solid {{ $muted }};
    }
    .sidebar-table.right > tbody > tr > td.main { padding-left: 0; padding-right: 14pt; }

    /* Two-column section table. */
    .twocol-table { width: 100%; border-collapse: collapse; }
    .twocol-table > tbody > tr > td { vertical-align: top; padding: 0; width: 50%; }
    .twocol-table > tbody > tr > td.tc-left  { padding-right: 10pt; }
    .twocol-table > tbody > tr > td.tc-right { padding-left: 10pt; }

    /* Timeline: items have date on the left, content on the right. */
    .timeline-item { margin-bottom: 7pt; page-break-inside: avoid; {!! $itemExtraCss !!} }
    .timeline-table { width: 100%; border-collapse: collapse; }
    .timeline-table td { vertical-align: top; padding: 0; }
    .timeline-table td.tl-date {
        width: 70pt; padding-right: 10pt;
        color: {{ $accent }}; font-size: {{ $baseFontPx - 1 }}pt;
        border-right: 1pt solid {{ $accent }};
    }
    .timeline-table td.tl-body { padding-left: 10pt; }

    /* Portfolio "featured projects" grid via a 2-col table. */
    .portfolio-table { width: 100%; border-collapse: separate; border-spacing: 6pt; }
    .portfolio-table td { vertical-align: top; width: 50%; padding: 0; }
    .portfolio-card { border: 0.6pt solid {{ $accent }}; border-radius: 6pt; padding: 6pt 8pt; }

    /* Numbered title counter, applied via .numbered wrapper. */
    .numbered { counter-reset: section; }
    .numbered h2.section-title:before {
        counter-increment: section;
        content: counter(section, decimal-leading-zero) ".  ";
        color: {{ $accent }};
    }
    .bracket h2.section-title:before { content: "[ "; color: {{ $accent }}; }
    .bracket h2.section-title:after  { content: " ]"; color: {{ $accent }}; }
</style>
</head>
<body>

@php
    $h = [
        'name'     => $header['name']     ?? '',
        'headline' => $header['headline'] ?? '',
        'email'    => $header['email']    ?? '',
        'phone'    => $header['phone']    ?? '',
        'location' => $header['location'] ?? '',
        'website'  => $header['website']  ?? '',
    ];
    $contactBits = array_values(array_filter([
        $h['email'], $h['phone'], $h['location'], $h['website'],
    ], fn ($v) => $v !== null && $v !== ''));
    $photoSrc = $header['photo_data_uri'] ?? null;
    $monogram = '';
    if (!empty($h['name'])) {
        $parts = preg_split('/\s+/', trim($h['name']));
        foreach (array_slice($parts, 0, 2) as $p) $monogram .= mb_substr($p, 0, 1);
        $monogram = mb_strtoupper($monogram);
    }

    $renderContact = function ($mutedColor) use ($contactBits) {
        if (empty($contactBits)) return '';
        $out = '<div class="contact">';
        foreach ($contactBits as $i => $bit) {
            if ($i > 0) $out .= '<span class="sep"> · </span>';
            $isEmail = filter_var($bit, FILTER_VALIDATE_EMAIL);
            $isUrl   = preg_match('~^https?://~i', $bit);
            $isPhone = !$isEmail && !$isUrl && preg_match('/^[+0-9 ()-]+$/', $bit);
            $colorAttr = ' style="color:' . $mutedColor . ';"';
            if ($isEmail) {
                $out .= '<a class="link" href="mailto:' . e($bit) . '"' . $colorAttr . '>' . e($bit) . '</a>';
            } elseif ($isUrl) {
                $out .= '<a class="link" href="' . e($bit) . '"' . $colorAttr . '>' . e($bit) . '</a>';
            } elseif ($isPhone) {
                $out .= '<a class="link" href="tel:' . e(preg_replace('/[^+0-9]/', '', $bit)) . '"' . $colorAttr . '>' . e($bit) . '</a>';
            } else {
                $out .= e($bit);
            }
        }
        return $out . '</div>';
    };

    $renderHeaderInner = function () use ($h, $renderContact, $muted) {
        $out  = '<div class="name">' . e($h['name'] !== '' ? $h['name'] : 'Your name') . '</div>';
        if ($h['headline'] !== '') {
            $out .= '<div class="headline">' . e($h['headline']) . '</div>';
        }
        $out .= $renderContact($muted);
        return $out;
    };
@endphp

{{-- Header variants — each maps a header_style value to a distinct
     PDF rendering. Photo overrides for photo-left / sidebar-photo. --}}
@if ($photoSrc && in_array($headerStl, ['photo-left', 'sidebar-photo', 'rule', 'block', 'banner', 'split'], true))
    <table class="header-table"><tbody><tr>
        <td class="header-photo-cell">
            <img class="header-photo" src="{{ $photoSrc }}" alt="">
        </td>
        <td>{!! $renderHeaderInner() !!}</td>
    </tr></tbody></table>
    @if ($headerStl === 'rule' || $headerStl === 'photo-left')
        <hr class="header-rule">
    @endif
@elseif ($headerStl === 'banner')
    <div class="header-banner">{!! $renderHeaderInner() !!}</div>
@elseif ($headerStl === 'block')
    <div class="header-block">{!! $renderHeaderInner() !!}</div>
@elseif ($headerStl === 'centered')
    <div class="header-centered with-rule">{!! $renderHeaderInner() !!}</div>
@elseif ($headerStl === 'underline')
    <div class="header-underline">{!! $renderHeaderInner() !!}</div>
@elseif ($headerStl === 'minimal')
    <div class="header-minimal">{!! $renderHeaderInner() !!}</div>
@elseif ($headerStl === 'split')
    <table class="header-split-table"><tbody><tr>
        <td>
            <div class="name">{{ $h['name'] !== '' ? $h['name'] : 'Your name' }}</div>
            @if ($h['headline'] !== '')<div class="headline">{{ $h['headline'] }}</div>@endif
        </td>
        <td class="right">{!! $renderContact($muted) !!}</td>
    </tr></tbody></table>
    <hr class="header-rule">
@else
    {{-- 'rule' default --}}
    {!! $renderHeaderInner() !!}
    <hr class="header-rule">
@endif

@php
    // Section render helpers — Blade-safe HTML strings composed by the
    // layout switch below so the same partials feed every layout.
    $tplSections = $template['sections'] ?? \App\Modules\User\Services\ResumeTemplateRegistry::ALL_SECTIONS;
    $supports    = fn (string $key) => in_array($key, $tplSections, true);
    $isTimeline  = $layout === 'timeline';

    $itemHtml = function ($title, $sub, $meta, $extra = '') use ($isTimeline) {
        if ($isTimeline) {
            return '<div class="timeline-item"><table class="timeline-table"><tr>'
                .'<td class="tl-date">'.$meta.'</td>'
                .'<td class="tl-body"><div class="item-title">'.$title.'</div>'
                .'<div class="item-sub">'.$sub.'</div>'.$extra.'</td>'
                .'</tr></table></div>';
        }
        return '<div class="item"><table class="item-table"><tr>'
            .'<td><div class="item-title">'.$title.'</div>'
            .'<div class="item-sub">'.$sub.'</div></td>'
            .'<td class="meta">'.$meta.'</td>'
            .'</tr></table>'.$extra.'</div>';
    };

    $renderExperience = function ($rows) use ($accent, $muted, $dateRange, $itemHtml) {
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $meta = e($dateRange($d['start_date'] ?? null, $d['end_date'] ?? null, !empty($d['is_current'])));
            $loc  = !empty($d['location']) ? ' <span class="muted">· '.e($d['location']).'</span>' : '';
            $desc = !empty($d['description'])
                ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
            $out .= $itemHtml(e($d['role'] ?? ''), e($d['company'] ?? '').$loc, $meta, $desc);
        }
        return $out;
    };

    $renderEducation = function ($rows) use ($dateRange, $itemHtml) {
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $sub = trim(implode(', ', array_filter([$d['degree'] ?? null, $d['field'] ?? null])));
            $meta = e($dateRange($d['start_date'] ?? null, $d['end_date'] ?? null));
            $desc = !empty($d['description'])
                ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
            $out .= $itemHtml(e($d['school'] ?? ''), e($sub), $meta, $desc);
        }
        return $out;
    };

    $renderSkills = function ($rows) {
        if ($rows->isEmpty()) return '';
        $html = '<div class="skill-list">';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $lvl = !empty($d['level']) ? ' '.str_repeat('★', max(0, min(5, (int)$d['level']))) : '';
            $html .= '<span class="pill">'.e($d['name'] ?? '').e($lvl).'</span>';
        }
        return $html.'</div>';
    };

    $renderProjects = function ($rows, $portfolio = false) use ($accent, $muted, $primary, $textColor, $dateRange, $itemHtml) {
        if ($rows->isEmpty()) return '';
        if ($portfolio) {
            $cells = [];
            foreach ($rows as $it) {
                $d = $it->data ?? [];
                $url = !empty($d['url'])
                    ? '<div style="margin-top:3pt"><a class="link" href="'.e($d['url']).'">'.e($d['url']).'</a></div>' : '';
                $desc = !empty($d['description'])
                    ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
                $cells[] = '<td><div class="portfolio-card">'
                    .'<div class="item-title" style="color:'.$primary.'">'.e($d['name'] ?? '').'</div>'
                    .'<div class="item-sub" style="color:'.$muted.'">'.e($d['role'] ?? '').'</div>'
                    .$desc.$url.'</div></td>';
            }
            $html = '<table class="portfolio-table"><tbody>';
            for ($i = 0; $i < count($cells); $i += 2) {
                $left  = $cells[$i];
                $right = $cells[$i + 1] ?? '<td>&nbsp;</td>';
                $html .= '<tr>'.$left.$right.'</tr>';
            }
            return $html.'</tbody></table>';
        }
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $meta = e($dateRange($d['start_date'] ?? null, $d['end_date'] ?? null));
            $desc = !empty($d['description']) ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
            $url  = !empty($d['url']) ? '<div><a class="link" href="'.e($d['url']).'">'.e($d['url']).'</a></div>' : '';
            $out .= $itemHtml(e($d['name'] ?? ''), e($d['role'] ?? ''), $meta, $desc.$url);
        }
        return $out;
    };

    $renderCerts = function ($rows) use ($fmtMonth, $itemHtml) {
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $meta = e($fmtMonth($d['issued_on'] ?? null));
            if (!empty($d['expires_on'])) $meta .= ' – '.e($fmtMonth($d['expires_on']));
            $url = !empty($d['credential_url'])
                ? '<div><a class="link" href="'.e($d['credential_url']).'">'.e($d['credential_url']).'</a></div>' : '';
            $out .= $itemHtml(e($d['name'] ?? ''), e($d['issuer'] ?? ''), $meta, $url);
        }
        return $out;
    };

    $renderAwards = function ($rows) use ($fmtMonth, $itemHtml) {
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $meta = e($fmtMonth($d['date'] ?? null));
            $desc = !empty($d['description']) ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
            $out .= $itemHtml(e($d['title'] ?? ''), e($d['issuer'] ?? ''), $meta, $desc);
        }
        return $out;
    };

    $renderLangs = function ($rows) {
        if ($rows->isEmpty()) return '';
        $html = '<div class="lang-list">';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $extra = !empty($d['proficiency']) ? ' · '.ucfirst($d['proficiency']) : '';
            $html .= '<span class="pill">'.e($d['name'] ?? '').e($extra).'</span>';
        }
        return $html.'</div>';
    };

    $renderLinks = function ($rows) {
        if ($rows->isEmpty()) return '';
        $html = '<ul class="links-list">';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $label = $d['label'] ?? ($d['url'] ?? '');
            $url   = $d['url'] ?? '#';
            $html .= '<li><a class="link" href="'.e($url).'">'.e($label).'</a></li>';
        }
        return $html.'</ul>';
    };

    $renderCustom = function () use ($customSections, $byType, $fmtMonth, $itemHtml) {
        $rows = $byType('custom');
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($customSections as $sec) {
            $key   = $sec['key'] ?? null;
            $title = $sec['title'] ?? $key;
            if (!$key) continue;
            $bucket = $rows->filter(fn ($it) => (($it->data['custom_section_key'] ?? null) === $key));
            if ($bucket->isEmpty()) continue;
            $out .= '<div class="section"><h2 class="section-title">'.e($title).'</h2>';
            foreach ($bucket as $it) {
                $d = $it->data ?? [];
                $meta = e($fmtMonth($d['date'] ?? null));
                $desc = !empty($d['description']) ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
                $url  = !empty($d['url']) ? '<div><a class="link" href="'.e($d['url']).'">'.e($d['url']).'</a></div>' : '';
                $out .= $itemHtml(e($d['title'] ?? ''), e($d['subtitle'] ?? ''), $meta, $desc.$url);
            }
            $out .= '</div>';
        }
        return $out;
    };

    $sectionBox = function (string $title, string $body) {
        if ($body === '') return '';
        return '<div class="section"><h2 class="section-title">'.e($title).'</h2>'.$body.'</div>';
    };

    // Pre-render section bodies once.
    $secSummary  = !empty($summary) ? '<div class="section"><h2 class="section-title">Profile</h2><div class="summary">'.e($summary).'</div></div>' : '';
    $secExp      = $sectionBox('Experience',     $renderExperience($byType('experience')));
    $secProj     = $sectionBox('Projects',       $renderProjects($byType('projects'), false));
    $secProjFeat = $sectionBox('Featured projects', $renderProjects($byType('projects'), true));
    $secEdu      = $sectionBox('Education',      $renderEducation($byType('education')));
    $secSkills   = $sectionBox('Skills',         $renderSkills($byType('skills')));
    $secCerts    = $sectionBox('Certifications', $renderCerts($byType('certifications')));
    $secAwards   = $sectionBox('Awards',         $renderAwards($byType('awards')));
    $secLangs    = $sectionBox('Languages',      $renderLangs($byType('languages')));
    $secLinks    = $sectionBox('Links',          $renderLinks($byType('links')));
    $secCustom   = $renderCustom();

    // Title-style wrapper for counter-based rendering (numbered/bracket).
    $titleWrapClass = match ($titleStl) {
        'numbered' => 'numbered',
        'bracket'  => 'bracket',
        default    => '',
    };
@endphp

<div class="{{ $titleWrapClass }}">

@switch($layout)
    @case('sidebar')
        <table class="sidebar-table"><tbody><tr>
            <td class="side">
                {!! $secSkills !!}{!! $secLangs !!}{!! $secLinks !!}
            </td>
            <td class="main">
                {!! $secSummary !!}{!! $secExp !!}{!! $secProj !!}{!! $secEdu !!}{!! $secCerts !!}{!! $secAwards !!}{!! $secCustom !!}
            </td>
        </tr></tbody></table>
        @break

    @case('sidebar-right')
        <table class="sidebar-table right"><tbody><tr>
            <td class="main">
                {!! $secSummary !!}{!! $secExp !!}{!! $secProj !!}{!! $secEdu !!}{!! $secCerts !!}{!! $secAwards !!}{!! $secCustom !!}
            </td>
            <td class="side">
                {!! $secSkills !!}{!! $secLangs !!}{!! $secLinks !!}
            </td>
        </tr></tbody></table>
        @break

    @case('two-col')
        {!! $secSummary !!}
        <table class="twocol-table"><tbody><tr>
            <td class="tc-left">{!! $secExp !!}{!! $secEdu !!}{!! $secProj !!}</td>
            <td class="tc-right">{!! $secSkills !!}{!! $secCerts !!}{!! $secAwards !!}{!! $secLangs !!}{!! $secLinks !!}{!! $secCustom !!}</td>
        </tr></tbody></table>
        @break

    @case('portfolio')
    @case('portfolio-grid')
        {!! $secSummary !!}{!! $secProjFeat !!}{!! $secExp !!}{!! $secSkills !!}{!! $secEdu !!}{!! $secAwards !!}{!! $secLangs !!}{!! $secLinks !!}{!! $secCustom !!}
        @break

    @case('timeline')
        {{-- Timeline: items are pre-rendered with date-on-left via $itemHtml. --}}
        {!! $secSummary !!}{!! $secExp !!}{!! $secProj !!}{!! $secEdu !!}{!! $secSkills !!}{!! $secCerts !!}{!! $secAwards !!}{!! $secLangs !!}{!! $secLinks !!}{!! $secCustom !!}
        @break

    @default
        {{-- single, compact, anything else --}}
        {!! $secSummary !!}{!! $secExp !!}{!! $secProj !!}{!! $secEdu !!}{!! $secSkills !!}{!! $secCerts !!}{!! $secAwards !!}{!! $secLangs !!}{!! $secLinks !!}{!! $secCustom !!}
@endswitch

</div>

</body>
</html>
