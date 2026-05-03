{{--
  Server-side resume renderer — mirrors the Alpine renderPreview() in
  user/resume/editor.blade.php so the public page (and the biolink
  Resume block) shows the same layout, theme, fonts and density.

  Required props:
    $resume — App\Modules\User\Models\Resume (loaded with `items`)

  Optional props:
    $compact — bool. When true, scales down padding for embedding
               inside narrow containers (biolink block).
--}}
@php
    /** @var \App\Modules\User\Models\Resume $resume */
    $compact = $compact ?? false;

    $tpl   = $resume->templateMeta();
    $theme = $resume->colorThemeMeta();
    $tokens = $theme['tokens'] ?? [
        'primary' => '#111827', 'accent' => '#4b5563',
        'text' => '#1f2937', 'muted' => '#6b7280', 'background' => '#ffffff',
    ];
    $defaultStyle = [
        'layout' => 'single', 'headings' => 'sans', 'density' => 'comfortable',
        'header_style' => 'rule', 'divider' => 'rule', 'accent' => 'none', 'title_style' => 'uppercase',
    ];
    $style  = ($tpl['style'] ?? []) + $defaultStyle;
    $layout = $style['layout'];

    $sections = $resume->getMergedSections();
    $h        = $sections['header'] ?? [];
    $summary  = (string) ($sections['summary'] ?? '');
    $custom   = $sections['custom_sections'] ?? [];

    $items = $resume->items->groupBy('section_type');
    $get = fn (string $t) => ($items[$t] ?? collect())->map(fn ($i) => $i->data ?? [])->all();

    $fmtMonth = function ($s) {
        if (!$s) return '';
        if (preg_match('/^(\d{4})-(\d{2})$/', (string) $s, $m)) {
            $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return $months[(int)$m[2]-1] . ' ' . $m[1];
        }
        return (string) $s;
    };
    $dateRange = function ($s, $e, $current = false) use ($fmtMonth) {
        $parts = array_filter([$fmtMonth($s), $current ? 'Present' : $fmtMonth($e)]);
        return implode(' – ', $parts);
    };

    $fontClass = match ($style['headings']) {
        'serif'   => 'serif',
        'display' => 'display',
        'mono'    => 'mono',
        default   => '',
    };
    $densityClass = match ($style['density']) {
        'tight'    => 'tight',
        'spacious' => 'spacious',
        default    => '',
    };

    $rrClasses = trim(implode(' ', array_filter([
        'rr',
        'rr-layout-' . $layout,
        'rr-h-' . $style['header_style'],
        'rr-d-' . $style['divider'],
        'rr-a-' . $style['accent'],
        'rr-t-' . $style['title_style'],
    ])));

    $monogram = '';
    $name = trim((string) ($h['name'] ?? ''));
    if ($name !== '') {
        $parts = preg_split('/\s+/', $name);
        $monogram = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    }
@endphp

<div class="resume-render preview-page {{ $rrClasses }} {{ $fontClass }} {{ $densityClass }} {{ $compact ? 'compact' : '' }}"
     style="background: {{ $tokens['background'] }}; color: {{ $tokens['text'] }}; --rr-accent: {{ $tokens['accent'] }};">

    {{-- ── Header ────────────────────────────────────────────── --}}
    <header class="rr-header" data-monogram="{{ $monogram }}" style="border-color: {{ $tokens['primary'] }};">
        @if (!empty($h['photo_url']) && in_array($style['header_style'], ['photo-left', 'sidebar-photo'], true))
            <img src="{{ $h['photo_url'] }}" alt="" class="rr-header-photo"
                 style="width:64px;height:64px;border-radius:50%;object-fit:cover;float:left;margin-right:14px;border:1px solid {{ $tokens['primary'] }}33;">
        @endif
        <h1 class="pv-name" style="color: {{ $tokens['primary'] }};">{{ $h['name'] ?: 'Your name' }}</h1>
        @if(!empty($h['headline']))
            <p class="pv-headline" style="color: {{ $tokens['accent'] }};">{{ $h['headline'] }}</p>
        @endif
        <div class="pv-contact" style="color: {{ $tokens['muted'] }};">
            @foreach (['email','phone','location','website'] as $f)
                @if(!empty($h[$f]))<span>{{ $h[$f] }}</span>@endif
            @endforeach
        </div>
    </header>

    @php
        $sectionBox = function (string $title, string $body, string $key = '') use ($tokens) {
            if ($body === '') return '';
            return '<section class="pv-section" data-key="'.e($key).'"><h2 style="color:'.$tokens['primary'].'; border-color:'.$tokens['primary'].'">'
                . e($title) . '</h2>' . $body . '</section>';
        };

        $expBlock = function (array $arr) use ($tokens, $dateRange) {
            $out = '';
            foreach ($arr as $d) {
                $sub = e($d['company'] ?? '');
                if (!empty($d['location'])) $sub .= '<span style="color:'.$tokens['muted'].'"> · '.e($d['location']).'</span>';
                $out .= '<div class="pv-item"><div class="pv-item-row">'
                    . '<div><div class="pv-item-title">'.e($d['role'] ?? '').'</div>'
                    . '<div class="pv-item-sub" style="color:'.$tokens['accent'].'">'.$sub.'</div></div>'
                    . '<div class="pv-item-meta" style="color:'.$tokens['muted'].'">'.e($dateRange($d['start_date'] ?? null, $d['end_date'] ?? null, $d['is_current'] ?? false)).'</div>'
                    . '</div>';
                if (!empty($d['description'])) $out .= '<div class="pv-item-desc">'.e($d['description']).'</div>';
                $out .= '</div>';
            }
            return $out;
        };

        $eduBlock = function (array $arr) use ($tokens, $dateRange) {
            $out = '';
            foreach ($arr as $d) {
                $sub = trim(implode(', ', array_filter([$d['degree'] ?? null, $d['field'] ?? null])));
                $out .= '<div class="pv-item"><div class="pv-item-row">'
                    . '<div><div class="pv-item-title">'.e($d['school'] ?? '').'</div>'
                    . '<div class="pv-item-sub" style="color:'.$tokens['accent'].'">'.e($sub).'</div></div>'
                    . '<div class="pv-item-meta" style="color:'.$tokens['muted'].'">'.e($dateRange($d['start_date'] ?? null, $d['end_date'] ?? null, false)).'</div>'
                    . '</div>';
                if (!empty($d['description'])) $out .= '<div class="pv-item-desc">'.e($d['description']).'</div>';
                $out .= '</div>';
            }
            return $out;
        };

        $skillBlock = function (array $arr) use ($tokens) {
            if (!$arr) return '';
            $pills = '';
            foreach ($arr as $d) {
                $lvl = !empty($d['level']) ? ' '.str_repeat('★', max(0, min(5, (int)$d['level']))) : '';
                $pills .= '<span class="pv-skill-pill">'.e($d['name'] ?? '').$lvl.'</span>';
            }
            return '<div class="pv-skill-row" style="color:'.$tokens['accent'].'">'.$pills.'</div>';
        };

        $projBlock = function (array $arr, bool $portfolio = false) use ($tokens, $dateRange) {
            if (!$arr) return '';
            if ($portfolio) {
                $cards = '';
                foreach ($arr as $d) {
                    $cards .= '<div class="pv-portfolio-card">'
                        . '<div class="pv-item-title" style="color:'.$tokens['primary'].'">'.e($d['name'] ?? '').'</div>'
                        . '<div class="pv-item-sub" style="color:'.$tokens['muted'].'">'.e($d['role'] ?? '').'</div>';
                    if (!empty($d['description'])) $cards .= '<div class="pv-item-desc" style="color:'.$tokens['text'].'">'.e($d['description']).'</div>';
                    if (!empty($d['url'])) $cards .= '<div style="margin-top:6px"><a class="pv-link" style="color:'.$tokens['accent'].'" href="'.e($d['url']).'" rel="noopener nofollow">'.e($d['url']).'</a></div>';
                    $cards .= '</div>';
                }
                return '<div class="pv-portfolio-grid" style="color:'.$tokens['accent'].'">'.$cards.'</div>';
            }
            $out = '';
            foreach ($arr as $d) {
                $out .= '<div class="pv-item"><div class="pv-item-row">'
                    . '<div><div class="pv-item-title">'.e($d['name'] ?? '').'</div>'
                    . '<div class="pv-item-sub" style="color:'.$tokens['accent'].'">'.e($d['role'] ?? '').'</div></div>'
                    . '<div class="pv-item-meta" style="color:'.$tokens['muted'].'">'.e($dateRange($d['start_date'] ?? null, $d['end_date'] ?? null, false)).'</div>'
                    . '</div>';
                if (!empty($d['description'])) $out .= '<div class="pv-item-desc">'.e($d['description']).'</div>';
                if (!empty($d['url'])) $out .= '<div><a class="pv-link" style="color:'.$tokens['accent'].'" href="'.e($d['url']).'" rel="noopener nofollow">'.e($d['url']).'</a></div>';
                $out .= '</div>';
            }
            return $out;
        };

        $certBlock = function (array $arr) use ($tokens, $fmtMonth) {
            $out = '';
            foreach ($arr as $d) {
                $meta = e($fmtMonth($d['issued_on'] ?? null));
                if (!empty($d['expires_on'])) $meta .= ' – '.e($fmtMonth($d['expires_on']));
                $out .= '<div class="pv-item"><div class="pv-item-row">'
                    . '<div><div class="pv-item-title">'.e($d['name'] ?? '').'</div>'
                    . '<div class="pv-item-sub" style="color:'.$tokens['accent'].'">'.e($d['issuer'] ?? '').'</div></div>'
                    . '<div class="pv-item-meta" style="color:'.$tokens['muted'].'">'.$meta.'</div>'
                    . '</div>';
                if (!empty($d['credential_url'])) $out .= '<div><a class="pv-link" style="color:'.$tokens['accent'].'" href="'.e($d['credential_url']).'" rel="noopener nofollow">'.e($d['credential_url']).'</a></div>';
                $out .= '</div>';
            }
            return $out;
        };

        $awardBlock = function (array $arr) use ($tokens, $fmtMonth) {
            $out = '';
            foreach ($arr as $d) {
                $out .= '<div class="pv-item"><div class="pv-item-row">'
                    . '<div><div class="pv-item-title">'.e($d['title'] ?? '').'</div>'
                    . '<div class="pv-item-sub" style="color:'.$tokens['accent'].'">'.e($d['issuer'] ?? '').'</div></div>'
                    . '<div class="pv-item-meta" style="color:'.$tokens['muted'].'">'.e($fmtMonth($d['date'] ?? null)).'</div>'
                    . '</div>';
                if (!empty($d['description'])) $out .= '<div class="pv-item-desc">'.e($d['description']).'</div>';
                $out .= '</div>';
            }
            return $out;
        };

        $langBlock = function (array $arr) use ($tokens) {
            if (!$arr) return '';
            $pills = '';
            foreach ($arr as $d) {
                $extra = !empty($d['proficiency']) ? ' · '.e($d['proficiency']) : '';
                $pills .= '<span class="pv-skill-pill">'.e($d['name'] ?? '').$extra.'</span>';
            }
            return '<div class="pv-skill-row" style="color:'.$tokens['accent'].'">'.$pills.'</div>';
        };

        $linkBlock = function (array $arr) use ($tokens) {
            if (!$arr) return '';
            $out = '<div style="display:flex; flex-direction:column; gap:3px">';
            foreach ($arr as $d) {
                $label = $d['label'] ?? ($d['url'] ?? '');
                $out .= '<a class="pv-link" style="color:'.$tokens['accent'].'" href="'.e($d['url'] ?? '#').'" rel="noopener nofollow">'.e($label).'</a>';
            }
            return $out . '</div>';
        };

        $customBlocks = function () use ($custom, $items, $tokens, $sectionBox, $fmtMonth) {
            $out = '';
            $allCustom = ($items['custom'] ?? collect())->map(fn ($i) => $i->data ?? [])->all();
            foreach ($custom as $s) {
                $key = $s['key'] ?? null;
                if (!$key) continue;
                $matching = array_values(array_filter($allCustom, fn ($d) => ($d['custom_section_key'] ?? null) === $key));
                if (!$matching) continue;
                $body = '';
                foreach ($matching as $d) {
                    $body .= '<div class="pv-item"><div class="pv-item-row">'
                        . '<div><div class="pv-item-title">'.e($d['title'] ?? '').'</div>'
                        . '<div class="pv-item-sub" style="color:'.$tokens['accent'].'">'.e($d['subtitle'] ?? '').'</div></div>'
                        . '<div class="pv-item-meta" style="color:'.$tokens['muted'].'">'.e($fmtMonth($d['date'] ?? null)).'</div>'
                        . '</div>';
                    if (!empty($d['description'])) $body .= '<div class="pv-item-desc">'.e($d['description']).'</div>';
                    if (!empty($d['url'])) $body .= '<div><a class="pv-link" style="color:'.$tokens['accent'].'" href="'.e($d['url']).'" rel="noopener nofollow">'.e($d['url']).'</a></div>';
                    $body .= '</div>';
                }
                $out .= $sectionBox($s['title'] ?? $key, $body, 'custom');
            }
            return $out;
        };

        $summaryBlock = $summary !== '' ? $sectionBox('Profile', '<div class="pv-summary">'.e($summary).'</div>', 'summary') : '';
    @endphp

    @if ($layout === 'sidebar' || $layout === 'sidebar-right')
        @php
            $side = $sectionBox('Skills', $skillBlock($get('skills')), 'skills')
                  . $sectionBox('Languages', $langBlock($get('languages')), 'languages')
                  . $sectionBox('Links', $linkBlock($get('links')), 'links');
            $main = $summaryBlock
                  . $sectionBox('Experience', $expBlock($get('experience')), 'experience')
                  . $sectionBox('Projects', $projBlock($get('projects'), false), 'projects')
                  . $sectionBox('Education', $eduBlock($get('education')), 'education')
                  . $sectionBox('Certifications', $certBlock($get('certifications')), 'certifications')
                  . $sectionBox('Awards', $awardBlock($get('awards')), 'awards')
                  . $customBlocks();
        @endphp
        <div class="pv-sidebar">
            <div class="pv-side-col">{!! $side !!}</div>
            <div>{!! $main !!}</div>
        </div>
    @elseif ($layout === 'portfolio' || $layout === 'portfolio-grid')
        {!! $summaryBlock !!}
        {!! $sectionBox('Featured projects', $projBlock($get('projects'), true), 'projects') !!}
        {!! $sectionBox('Experience', $expBlock($get('experience')), 'experience') !!}
        {!! $sectionBox('Skills', $skillBlock($get('skills')), 'skills') !!}
        {!! $sectionBox('Education', $eduBlock($get('education')), 'education') !!}
        {!! $sectionBox('Awards', $awardBlock($get('awards')), 'awards') !!}
        {!! $sectionBox('Languages', $langBlock($get('languages')), 'languages') !!}
        {!! $sectionBox('Links', $linkBlock($get('links')), 'links') !!}
        {!! $customBlocks() !!}
    @elseif ($layout === 'two-col')
        {!! $summaryBlock !!}
        <div class="pv-twocol">
            {!! $sectionBox('Experience', $expBlock($get('experience')), 'experience') !!}
            {!! $sectionBox('Education', $eduBlock($get('education')), 'education') !!}
            {!! $sectionBox('Projects', $projBlock($get('projects'), false), 'projects') !!}
            {!! $sectionBox('Skills', $skillBlock($get('skills')), 'skills') !!}
            {!! $sectionBox('Certifications', $certBlock($get('certifications')), 'certifications') !!}
            {!! $sectionBox('Awards', $awardBlock($get('awards')), 'awards') !!}
            {!! $sectionBox('Languages', $langBlock($get('languages')), 'languages') !!}
            {!! $sectionBox('Links', $linkBlock($get('links')), 'links') !!}
            {!! $customBlocks() !!}
        </div>
    @else
        {{-- single / compact / timeline --}}
        {!! $summaryBlock !!}
        {!! $sectionBox('Experience', $expBlock($get('experience')), 'experience') !!}
        {!! $sectionBox('Projects', $projBlock($get('projects'), false), 'projects') !!}
        {!! $sectionBox('Education', $eduBlock($get('education')), 'education') !!}
        {!! $sectionBox('Skills', $skillBlock($get('skills')), 'skills') !!}
        {!! $sectionBox('Certifications', $certBlock($get('certifications')), 'certifications') !!}
        {!! $sectionBox('Awards', $awardBlock($get('awards')), 'awards') !!}
        {!! $sectionBox('Languages', $langBlock($get('languages')), 'languages') !!}
        {!! $sectionBox('Links', $linkBlock($get('links')), 'links') !!}
        {!! $customBlocks() !!}
    @endif
</div>
