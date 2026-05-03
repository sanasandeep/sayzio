@php
    use Illuminate\Support\Str;

    $primary    = $theme['primary']    ?? '#111827';
    $accent     = $theme['accent']     ?? '#4b5563';
    $textColor  = $theme['text']       ?? '#1f2937';
    $muted      = $theme['muted']      ?? '#6b7280';
    $bg         = $theme['background'] ?? '#ffffff';

    $style      = $template['style'] ?? [];
    $layout     = $style['layout']   ?? 'single';
    $headings   = $style['headings'] ?? 'sans';
    $density    = $style['density']  ?? 'comfortable';

    // dompdf has only the bundled "DejaVu Sans/Serif" embedded fonts to
    // guarantee selectable text + Unicode support across systems. Map
    // the editor's font axis onto those families so the PDF still feels
    // tonally similar to the live preview.
    $bodyFont   = $headings === 'serif' ? 'DejaVu Serif' : 'DejaVu Sans';
    $headFont   = $bodyFont;

    $baseFontPx  = $density === 'tight' ? 10.5 : ($density === 'spacious' ? 12.0 : 11.0);
    $linePct     = $density === 'tight' ? 1.35 : ($density === 'spacious' ? 1.6 : 1.5);
    $pageMargin  = $density === 'tight' ? '14mm' : ($density === 'spacious' ? '20mm' : '16mm');

    $paperSizeCss = ($paperSize ?? 'a4') === 'letter' ? 'letter' : 'A4';

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
    .header-table { width: 100%; border-collapse: collapse; margin: 0 0 4pt 0; }
    .header-table > tbody > tr > td { vertical-align: top; padding: 0; }
    .header-photo-cell { width: 70pt; padding-right: 12pt !important; }
    .header-photo {
        width: 60pt; height: 60pt;
        border: 1pt solid {{ $primary }};
        border-radius: 30pt;
    }

    h2.section-title {
        font-family: '{{ $headFont }}', sans-serif;
        font-size: {{ $baseFontPx }}pt;
        font-weight: bold;
        letter-spacing: 1.5pt;
        text-transform: uppercase;
        color: {{ $primary }};
        margin: 10pt 0 4pt 0;
        padding-bottom: 2pt;
        border-bottom: 1pt solid {{ $primary }};
        page-break-after: avoid;
    }
    .section { margin-bottom: 6pt; }
    .item    { margin-bottom: 7pt; page-break-inside: avoid; }
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

    /* Sidebar layout uses a 2-column table since dompdf doesn't honor flex/grid. */
    .sidebar-table { width: 100%; border-collapse: collapse; }
    .sidebar-table > tbody > tr > td { vertical-align: top; padding: 0; }
    .sidebar-table > tbody > tr > td.side {
        width: 32%;
        padding-right: 14pt;
        border-right: 0.6pt solid {{ $muted }};
    }
    .sidebar-table > tbody > tr > td.main { padding-left: 14pt; }

    /* Portfolio "featured projects" grid via a 2-col table. */
    .portfolio-table { width: 100%; border-collapse: separate; border-spacing: 6pt; }
    .portfolio-table td { vertical-align: top; width: 50%; padding: 0; }
    .portfolio-card { border: 0.6pt solid {{ $accent }}; border-radius: 6pt; padding: 6pt 8pt; }
</style>
</head>
<body>

@php
    $renderHeader = function () use ($header, $primary, $accent, $muted) {
        $bits = array_values(array_filter([
            $header['email']    ?? null,
            $header['phone']    ?? null,
            $header['location'] ?? null,
            $header['website']  ?? null,
        ], fn ($v) => $v !== null && $v !== ''));
        return [
            'name'     => $header['name']     ?? '',
            'headline' => $header['headline'] ?? '',
            'contact'  => $bits,
            'website'  => $header['website']  ?? '',
            'email'    => $header['email']    ?? '',
        ];
    };
    $h = $renderHeader();
@endphp

@php
    $photoSrc = $header['photo_data_uri'] ?? null;
    $renderHeaderText = function () use ($h, $muted) {
        $out  = '<div class="name">' . e($h['name'] !== '' ? $h['name'] : 'Your name') . '</div>';
        if ($h['headline'] !== '') {
            $out .= '<div class="headline">' . e($h['headline']) . '</div>';
        }
        if (!empty($h['contact'])) {
            $out .= '<div class="contact">';
            foreach ($h['contact'] as $i => $bit) {
                if ($i > 0) $out .= '<span class="sep"> · </span>';
                $isEmail = filter_var($bit, FILTER_VALIDATE_EMAIL);
                $isUrl   = preg_match('~^https?://~i', $bit);
                $isPhone = !$isEmail && !$isUrl && preg_match('/^[+0-9 ()-]+$/', $bit);
                if ($isEmail) {
                    $out .= '<a class="link" href="mailto:' . e($bit) . '" style="color: ' . $muted . ';">' . e($bit) . '</a>';
                } elseif ($isUrl) {
                    $out .= '<a class="link" href="' . e($bit) . '" style="color: ' . $muted . ';">' . e($bit) . '</a>';
                } elseif ($isPhone) {
                    $out .= '<a class="link" href="tel:' . e(preg_replace('/[^+0-9]/', '', $bit)) . '" style="color: ' . $muted . ';">' . e($bit) . '</a>';
                } else {
                    $out .= e($bit);
                }
            }
            $out .= '</div>';
        }
        return $out;
    };
@endphp

@if ($photoSrc)
    {{-- Photo + text in a 2-column table because dompdf doesn't honor flex/float reliably. --}}
    <table class="header-table"><tbody><tr>
        <td class="header-photo-cell">
            <img class="header-photo" src="{{ $photoSrc }}" alt="">
        </td>
        <td>{!! $renderHeaderText() !!}</td>
    </tr></tbody></table>
@else
    {!! $renderHeaderText() !!}
@endif
<hr class="header-rule">

@php
    // Section render helpers — each returns Blade-safe HTML string and is
    // composed by the layout switch below so the same partials feed
    // single, sidebar, and portfolio layouts.
    $tplSections = $template['sections'] ?? \App\Modules\User\Services\ResumeTemplateRegistry::ALL_SECTIONS;
    $supports    = fn (string $key) => in_array($key, $tplSections, true);
@endphp

@if (!empty($summary))
    @if ($supports('summary') || true)
        <div class="section">
            <h2 class="section-title">Profile</h2>
            <div class="summary">{{ $summary }}</div>
        </div>
    @endif
@endif

@php
    $renderExperience = function ($rows) use ($accent, $muted, $dateRange) {
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $meta = e($dateRange($d['start_date'] ?? null, $d['end_date'] ?? null, !empty($d['is_current'])));
            $loc  = !empty($d['location']) ? ' <span class="muted">· '.e($d['location']).'</span>' : '';
            $desc = !empty($d['description'])
                ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
            $out .= '<div class="item"><table class="item-table"><tr>'
                .'<td><div class="item-title">'.e($d['role'] ?? '').'</div>'
                .'<div class="item-sub">'.e($d['company'] ?? '').$loc.'</div></td>'
                .'<td class="meta">'.$meta.'</td>'
                .'</tr></table>'.$desc.'</div>';
        }
        return $out;
    };

    $renderEducation = function ($rows) use ($dateRange) {
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $sub = trim(implode(', ', array_filter([$d['degree'] ?? null, $d['field'] ?? null])));
            $meta = e($dateRange($d['start_date'] ?? null, $d['end_date'] ?? null));
            $desc = !empty($d['description'])
                ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
            $out .= '<div class="item"><table class="item-table"><tr>'
                .'<td><div class="item-title">'.e($d['school'] ?? '').'</div>'
                .'<div class="item-sub">'.e($sub).'</div></td>'
                .'<td class="meta">'.$meta.'</td>'
                .'</tr></table>'.$desc.'</div>';
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

    $renderProjects = function ($rows, $portfolio = false) use ($accent, $muted, $primary, $textColor, $dateRange) {
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
            // Pair into rows of 2.
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
            $out .= '<div class="item"><table class="item-table"><tr>'
                .'<td><div class="item-title">'.e($d['name'] ?? '').'</div>'
                .'<div class="item-sub">'.e($d['role'] ?? '').'</div></td>'
                .'<td class="meta">'.$meta.'</td></tr></table>'
                .$desc.$url.'</div>';
        }
        return $out;
    };

    $renderCerts = function ($rows) use ($fmtMonth) {
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $meta = e($fmtMonth($d['issued_on'] ?? null));
            if (!empty($d['expires_on'])) $meta .= ' – '.e($fmtMonth($d['expires_on']));
            $url = !empty($d['credential_url'])
                ? '<div><a class="link" href="'.e($d['credential_url']).'">'.e($d['credential_url']).'</a></div>' : '';
            $out .= '<div class="item"><table class="item-table"><tr>'
                .'<td><div class="item-title">'.e($d['name'] ?? '').'</div>'
                .'<div class="item-sub">'.e($d['issuer'] ?? '').'</div></td>'
                .'<td class="meta">'.$meta.'</td></tr></table>'.$url.'</div>';
        }
        return $out;
    };

    $renderAwards = function ($rows) use ($fmtMonth) {
        if ($rows->isEmpty()) return '';
        $out = '';
        foreach ($rows as $it) {
            $d = $it->data ?? [];
            $meta = e($fmtMonth($d['date'] ?? null));
            $desc = !empty($d['description']) ? '<div class="item-desc">'.e($d['description']).'</div>' : '';
            $out .= '<div class="item"><table class="item-table"><tr>'
                .'<td><div class="item-title">'.e($d['title'] ?? '').'</div>'
                .'<div class="item-sub">'.e($d['issuer'] ?? '').'</div></td>'
                .'<td class="meta">'.$meta.'</td></tr></table>'.$desc.'</div>';
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

    $renderCustom = function () use ($customSections, $byType, $fmtMonth) {
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
                $out .= '<div class="item"><table class="item-table"><tr>'
                    .'<td><div class="item-title">'.e($d['title'] ?? '').'</div>'
                    .'<div class="item-sub">'.e($d['subtitle'] ?? '').'</div></td>'
                    .'<td class="meta">'.$meta.'</td></tr></table>'
                    .$desc.$url.'</div>';
            }
            $out .= '</div>';
        }
        return $out;
    };

    $sectionBox = function (string $title, string $body) {
        if ($body === '') return '';
        return '<div class="section"><h2 class="section-title">'.e($title).'</h2>'.$body.'</div>';
    };
@endphp

@if ($layout === 'sidebar')
    {{-- Modern: sidebar (skills/languages/links) + main column. --}}
    <table class="sidebar-table"><tbody><tr>
        <td class="side">
            {!! $sectionBox('Skills',    $renderSkills($byType('skills'))) !!}
            {!! $sectionBox('Languages', $renderLangs($byType('languages'))) !!}
            {!! $sectionBox('Links',     $renderLinks($byType('links'))) !!}
        </td>
        <td class="main">
            {!! $sectionBox('Experience',     $renderExperience($byType('experience'))) !!}
            {!! $sectionBox('Projects',       $renderProjects($byType('projects'), false)) !!}
            {!! $sectionBox('Education',      $renderEducation($byType('education'))) !!}
            {!! $sectionBox('Certifications', $renderCerts($byType('certifications'))) !!}
            {!! $sectionBox('Awards',         $renderAwards($byType('awards'))) !!}
            {!! $renderCustom() !!}
        </td>
    </tr></tbody></table>
@elseif ($layout === 'portfolio')
    {{-- Creative: featured projects up top. --}}
    {!! $sectionBox('Featured projects', $renderProjects($byType('projects'), true)) !!}
    {!! $sectionBox('Experience',        $renderExperience($byType('experience'))) !!}
    {!! $sectionBox('Skills',            $renderSkills($byType('skills'))) !!}
    {!! $sectionBox('Education',         $renderEducation($byType('education'))) !!}
    {!! $sectionBox('Awards',            $renderAwards($byType('awards'))) !!}
    {!! $sectionBox('Languages',         $renderLangs($byType('languages'))) !!}
    {!! $sectionBox('Links',             $renderLinks($byType('links'))) !!}
    {!! $renderCustom() !!}
@else
    {{-- Classic / Compact: single column. --}}
    {!! $sectionBox('Experience',     $renderExperience($byType('experience'))) !!}
    {!! $sectionBox('Projects',       $renderProjects($byType('projects'), false)) !!}
    {!! $sectionBox('Education',      $renderEducation($byType('education'))) !!}
    {!! $sectionBox('Skills',         $renderSkills($byType('skills'))) !!}
    {!! $sectionBox('Certifications', $renderCerts($byType('certifications'))) !!}
    {!! $sectionBox('Awards',         $renderAwards($byType('awards'))) !!}
    {!! $sectionBox('Languages',      $renderLangs($byType('languages'))) !!}
    {!! $sectionBox('Links',          $renderLinks($byType('links'))) !!}
    {!! $renderCustom() !!}
@endif

</body>
</html>
