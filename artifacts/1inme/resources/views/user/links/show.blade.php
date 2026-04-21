@extends('user.layouts.app')
@section('title', $link->title ?: $link->alias)

@section('content')
@php
    $countryNames = ['US'=>'United States','IN'=>'India','GB'=>'United Kingdom','CA'=>'Canada','AU'=>'Australia','DE'=>'Germany','FR'=>'France','BR'=>'Brazil','JP'=>'Japan','CN'=>'China','RU'=>'Russia','MX'=>'Mexico','ES'=>'Spain','IT'=>'Italy','NL'=>'Netherlands','SE'=>'Sweden','SG'=>'Singapore','ZA'=>'South Africa','AE'=>'UAE','PK'=>'Pakistan','BD'=>'Bangladesh','ID'=>'Indonesia','TR'=>'Turkey','PH'=>'Philippines','TH'=>'Thailand','VN'=>'Vietnam','KR'=>'South Korea'];
    $blockTypes = \App\Modules\User\Models\BiolinkBlock::TYPES;
    $qs = request()->query();
    $buildUrl = fn($overrides = []) => route('user.links.show', $link) . '?' . http_build_query(array_merge($qs, $overrides));
    $flag = function($cc) {
        if (!$cc || strlen($cc) !== 2 || !ctype_alpha($cc)) return '🏳️';
        $cc = strtoupper($cc);
        return mb_chr(0x1F1E6 + ord($cc[0]) - ord('A')) . mb_chr(0x1F1E6 + ord($cc[1]) - ord('A'));
    };
    $languageNames = [
        'en'=>'English','es'=>'Spanish','pt'=>'Portuguese','fr'=>'French','de'=>'German',
        'it'=>'Italian','nl'=>'Dutch','sv'=>'Swedish','no'=>'Norwegian','da'=>'Danish',
        'fi'=>'Finnish','pl'=>'Polish','ru'=>'Russian','uk'=>'Ukrainian','cs'=>'Czech',
        'sk'=>'Slovak','ro'=>'Romanian','hu'=>'Hungarian','el'=>'Greek','tr'=>'Turkish',
        'ar'=>'Arabic','he'=>'Hebrew','fa'=>'Persian','ur'=>'Urdu','hi'=>'Hindi',
        'bn'=>'Bengali','pa'=>'Punjabi','ta'=>'Tamil','te'=>'Telugu','ml'=>'Malayalam',
        'mr'=>'Marathi','gu'=>'Gujarati','kn'=>'Kannada','th'=>'Thai','vi'=>'Vietnamese',
        'id'=>'Indonesian','ms'=>'Malay','tl'=>'Filipino','fil'=>'Filipino',
        'zh'=>'Chinese','ja'=>'Japanese','ko'=>'Korean','ca'=>'Catalan','eu'=>'Basque',
        'gl'=>'Galician','bg'=>'Bulgarian','hr'=>'Croatian','sr'=>'Serbian','sl'=>'Slovenian',
        'lt'=>'Lithuanian','lv'=>'Latvian','et'=>'Estonian','is'=>'Icelandic','ga'=>'Irish',
        'cy'=>'Welsh','sq'=>'Albanian','mk'=>'Macedonian','bs'=>'Bosnian','af'=>'Afrikaans',
        'sw'=>'Swahili','am'=>'Amharic','zu'=>'Zulu','xh'=>'Xhosa','yo'=>'Yoruba',
        'ig'=>'Igbo','ha'=>'Hausa','az'=>'Azerbaijani','ka'=>'Georgian','hy'=>'Armenian',
        'kk'=>'Kazakh','uz'=>'Uzbek','ky'=>'Kyrgyz','mn'=>'Mongolian','my'=>'Burmese',
        'km'=>'Khmer','lo'=>'Lao','si'=>'Sinhala','ne'=>'Nepali',
    ];
    $regionNames = $countryNames + [
        'GB'=>'UK','US'=>'US','CA'=>'Canada','AU'=>'Australia','NZ'=>'New Zealand',
        'IE'=>'Ireland','ZA'=>'South Africa','MX'=>'Mexico','AR'=>'Argentina','CL'=>'Chile',
        'CO'=>'Colombia','PE'=>'Peru','VE'=>'Venezuela','BR'=>'Brazil','PT'=>'Portugal',
        'ES'=>'Spain','FR'=>'France','BE'=>'Belgium','CH'=>'Switzerland','AT'=>'Austria',
        'DE'=>'Germany','LU'=>'Luxembourg','IT'=>'Italy','NL'=>'Netherlands','HK'=>'Hong Kong',
        'TW'=>'Taiwan','CN'=>'China','SG'=>'Singapore','MO'=>'Macau','JP'=>'Japan','KR'=>'Korea',
        'IN'=>'India','PK'=>'Pakistan','BD'=>'Bangladesh','LK'=>'Sri Lanka','PH'=>'Philippines',
        'ID'=>'Indonesia','MY'=>'Malaysia','TH'=>'Thailand','VN'=>'Vietnam','RU'=>'Russia',
        'UA'=>'Ukraine','PL'=>'Poland','CZ'=>'Czechia','SK'=>'Slovakia','HU'=>'Hungary',
        'RO'=>'Romania','BG'=>'Bulgaria','GR'=>'Greece','TR'=>'Turkey','IL'=>'Israel',
        'SA'=>'Saudi Arabia','AE'=>'UAE','EG'=>'Egypt','MA'=>'Morocco','NG'=>'Nigeria',
        'KE'=>'Kenya','SE'=>'Sweden','NO'=>'Norway','DK'=>'Denmark','FI'=>'Finland','IS'=>'Iceland',
    ];
    $languageLabel = function($code) use ($languageNames, $regionNames, $flag) {
        $raw = (string) $code;
        if ($raw === '' || !preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $raw)) {
            return ['name' => $raw !== '' ? $raw : 'Unknown', 'flag' => '', 'region' => null];
        }
        $parts = explode('-', $raw);
        $lang = strtolower($parts[0]);
        $region = null;
        for ($i = 1; $i < count($parts); $i++) {
            $p = $parts[$i];
            if (strlen($p) === 2 && ctype_alpha($p)) { $region = strtoupper($p); break; }
            if (strlen($p) === 3 && ctype_digit($p)) { $region = $p; break; }
        }
        $langName = $languageNames[$lang] ?? null;
        if (!$langName) return ['name' => $raw, 'flag' => '', 'region' => $region];
        $name = $langName;
        $flagStr = '';
        if ($region !== null) {
            if ($region === '419') {
                $name .= ' (Latin America)';
                $flagStr = '🌎';
            } elseif ($region === '005') {
                $name .= ' (South America)';
                $flagStr = '🌎';
            } elseif (ctype_alpha($region)) {
                $regionName = $regionNames[$region] ?? $region;
                $name .= ' (' . $regionName . ')';
                $flagStr = $flag($region);
            } else {
                $name .= ' (' . $region . ')';
            }
        }
        return ['name' => $name, 'flag' => $flagStr, 'region' => $region];
    };
    function _fmtSecs($s){ $s=(int)$s; if($s<60) return $s.'s'; $m=intdiv($s,60); $r=$s%60; if($m<60) return $m.'m '.$r.'s'; $h=intdiv($m,60); return $h.'h '.($m%60).'m'; }
    function _fmtMs($ms){ return _fmtSecs(intdiv((int)$ms,1000)); }
@endphp

@push('styles')
<style>
    /* Hero is supplied globally by user/layouts/app.blade.php — no local overrides here */

    /* ============ Period Pills ============ */
    .period-bar {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 18px;
        padding: 10px 14px;
        backdrop-filter: blur(20px);
    }
    .pill {
        padding: 7px 13px;
        border-radius: 11px;
        font-size: 11px;
        font-weight: 600;
        transition: all .2s ease;
        color: var(--text-muted);
    }
    .pill:hover { background: var(--bg-glass-hover); color: var(--text-primary); transform: translateY(-1px); }
    .pill-active {
        background: linear-gradient(135deg, #7c3aed, #8b5cf6);
        color: #fff !important;
        box-shadow: 0 6px 18px rgba(124,58,237,0.4);
    }
    .pill-active-soft {
        background: rgba(124,58,237,0.18);
        border: 1px solid rgba(124,58,237,0.4);
        color: #ddd6fe !important;
    }
    html.light-mode .pill-active-soft { color: #6d28d9 !important; background: rgba(124,58,237,0.14); }

    /* ============ Stat Tile ============ */
    .stat-tile {
        position: relative;
        padding: 18px 18px 16px;
        border-radius: 20px;
        background: linear-gradient(160deg, var(--tile-bg-from, rgba(124,58,237,0.10)) 0%, var(--bg-glass) 70%);
        border: 1px solid var(--tile-border, rgba(124,58,237,0.22));
        overflow: hidden;
        transition: transform .25s ease, box-shadow .25s ease;
    }
    .stat-tile:hover {
        transform: translateY(-3px);
        box-shadow: 0 18px 48px rgba(0,0,0,0.28), 0 0 32px var(--tile-glow, rgba(124,58,237,0.18));
    }
    .stat-tile::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 3px;
        background: var(--tile-accent, linear-gradient(90deg, #8b5cf6, #a78bfa));
    }
    .stat-tile::after {
        content: ""; position: absolute; right: -30px; bottom: -30px;
        width: 120px; height: 120px; border-radius: 50%;
        background: var(--tile-accent, linear-gradient(135deg,#8b5cf6,#a78bfa));
        opacity: 0.08; filter: blur(20px); pointer-events: none;
    }
    .stat-tile-head {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 10px;
    }
    .stat-tile-label {
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;
        color: var(--text-faint);
    }
    .stat-tile-icon {
        width: 32px; height: 32px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        background: var(--tile-accent, linear-gradient(135deg,#8b5cf6,#a78bfa));
        color: #fff; font-size: 12px;
        box-shadow: 0 8px 20px var(--tile-glow, rgba(124,58,237,0.35)), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    .stat-tile-value {
        font-size: 26px; font-weight: 800; line-height: 1.05;
        background: var(--tile-accent, linear-gradient(135deg,#8b5cf6,#a78bfa));
        -webkit-background-clip: text; background-clip: text; color: transparent;
        letter-spacing: -0.02em;
    }
    .stat-tile-sub { font-size: 10px; color: var(--text-faint); margin-top: 4px; }
    html.light-mode .stat-tile-label { color: rgba(26,16,37,0.7); }
    html.light-mode .stat-tile-sub { color: rgba(26,16,37,0.6); }

    /* ============ Calm KPI hero (top 3) ============ */
    .kpi-hero {
        position: relative;
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 18px;
        padding: 18px 20px;
        backdrop-filter: blur(20px);
        transition: transform .2s ease, border-color .2s ease;
    }
    .kpi-hero:hover { transform: translateY(-2px); border-color: rgba(124,58,237,0.25); }
    .kpi-hero-head {
        display: flex; align-items: center; justify-content: space-between;
        margin-bottom: 10px;
    }
    .kpi-hero-label {
        font-size: 11px; font-weight: 600; letter-spacing: 0.02em;
        color: var(--text-muted);
    }
    .kpi-hero-icon {
        width: 28px; height: 28px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        background: rgba(124,58,237,0.1);
        color: var(--accent);
        font-size: 11px;
    }
    .kpi-hero-value {
        font-size: 32px; font-weight: 800; line-height: 1.05;
        color: var(--text-primary);
        letter-spacing: -0.025em;
    }
    .kpi-hero-sub {
        font-size: 11px; color: var(--text-faint); margin-top: 4px;
    }
    html.light-mode .kpi-hero-sub { color: rgba(26,16,37,0.6); }

    /* ============ Quick-stats strip (rest) ============ */
    .kpi-strip {
        background: var(--bg-card);
        border: 1px solid var(--border-glass);
        border-radius: 18px;
        backdrop-filter: blur(20px);
        padding: 6px 4px;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0;
    }
    @media (min-width: 640px) { .kpi-strip { grid-template-columns: repeat(4, 1fr); } }
    @media (min-width: 1024px) { .kpi-strip { grid-template-columns: repeat(8, 1fr); } }
    .kpi-cell {
        padding: 14px 14px;
        border-right: 1px solid var(--border-subtle);
        border-bottom: 1px solid var(--border-subtle);
        display: flex; flex-direction: column; gap: 4px;
    }
    .kpi-cell:nth-child(2n) { border-right: none; }
    @media (min-width: 640px) {
        .kpi-cell { border-right: 1px solid var(--border-subtle); }
        .kpi-cell:nth-child(2n) { border-right: 1px solid var(--border-subtle); }
        .kpi-cell:nth-child(4n) { border-right: none; }
        .kpi-cell:nth-last-child(-n+4) { border-bottom: none; }
    }
    @media (min-width: 1024px) {
        .kpi-cell { border-bottom: none; border-right: 1px solid var(--border-subtle); }
        .kpi-cell:nth-child(4n) { border-right: 1px solid var(--border-subtle); }
        .kpi-cell:nth-child(8n) { border-right: none; }
        .kpi-cell:last-child { border-right: none; }
    }
    .kpi-cell-head {
        display: flex; align-items: center; gap: 6px;
        font-size: 10px; font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase; letter-spacing: 0.04em;
    }
    .kpi-cell-head i {
        font-size: 10px; color: var(--text-faint);
        width: 14px; text-align: center;
    }
    .kpi-cell-value {
        font-size: 18px; font-weight: 700;
        color: var(--text-primary);
        letter-spacing: -0.02em;
    }

    /* ============ Comparison Tile (used in Block-Level Clicks) ============ */
    .cmp-tile {
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 14px;
        padding: 14px 16px;
        backdrop-filter: blur(14px);
        transition: all .18s ease;
    }
    .cmp-tile:hover { border-color: rgba(139,92,246,0.35); transform: translateY(-1px); }
    .cmp-tile-head {
        font-size: 10px; font-weight: 700;
        text-transform: uppercase; letter-spacing: 0.08em;
        color: var(--text-faint);
        display: flex; align-items: center; gap: 6px;
        margin-bottom: 8px;
    }
    .cmp-tile-head i { font-size: 10px; color: #8b5cf6; }
    .cmp-tile-row {
        display: flex; align-items: center; justify-content: space-between;
        gap: 8px;
    }
    .cmp-tile-value {
        font-size: 24px; font-weight: 800;
        letter-spacing: -0.02em;
        color: var(--text-primary);
        line-height: 1.05;
    }
    .cmp-tile-value-sm {
        font-size: 16px; font-weight: 700;
        letter-spacing: -0.01em;
        color: var(--text-primary);
        line-height: 1.15; min-width: 0;
    }
    .cmp-tile-sub {
        margin-top: 6px;
        font-size: 10.5px;
        color: var(--text-faint);
    }

    /* ============ Delta pill (up/down vs prev period) ============ */
    .delta-pill {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 10.5px; font-weight: 700;
        padding: 3px 8px; border-radius: 999px;
        white-space: nowrap;
        letter-spacing: 0.02em;
    }
    .delta-pill i { font-size: 8.5px; }
    .delta-up   { background: rgba(16,185,129,0.15);  color: #6ee7b7; border: 1px solid rgba(16,185,129,0.30); }
    .delta-down { background: rgba(239,68,68,0.15);   color: #fca5a5; border: 1px solid rgba(239,68,68,0.30); }
    .delta-flat { background: rgba(148,163,184,0.15); color: #cbd5e1; border: 1px solid rgba(148,163,184,0.30); }
    html.light-mode .delta-up   { color: #047857; background: rgba(16,185,129,0.12); }
    html.light-mode .delta-down { color: #b91c1c; background: rgba(239,68,68,0.12); }
    html.light-mode .delta-flat { color: #475569; background: rgba(148,163,184,0.12); }

    /* ============ Section Card ============ */
    .section-card {
        position: relative;
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        border-radius: 14px;
        padding: 28px 32px;
        overflow: hidden;
    }
    .section-card::before {
        content: ""; position: absolute; top: 0; left: 0; right: 0; height: 2px;
        background: var(--sc-accent, linear-gradient(90deg, #8b5cf6, #a78bfa));
        opacity: 0.7;
    }
    .section-head {
        display: flex; align-items: center; justify-content: space-between;
        gap: 10px; margin-bottom: 18px; flex-wrap: wrap;
    }
    .section-title {
        display: flex; align-items: center; gap: 12px;
        font-size: 13px; font-weight: 700; color: var(--text-primary);
    }
    .section-icon {
        width: 36px; height: 36px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 13px;
        background: var(--sc-accent, linear-gradient(135deg, #8b5cf6, #a78bfa));
        box-shadow: 0 8px 22px var(--sc-glow, rgba(124,58,237,0.35)), inset 0 1px 0 rgba(255,255,255,0.25);
    }
    .section-pill {
        font-size: 10px; font-weight: 700; padding: 4px 10px; border-radius: 999px;
        background: var(--sc-glow, rgba(124,58,237,0.12));
        color: var(--sc-color, #ddd6fe);
        border: 1px solid var(--sc-border, rgba(124,58,237,0.25));
    }
    html.light-mode .section-pill {
        background: rgba(124,58,237,0.10);
        color: #6d28d9;
        border-color: rgba(124,58,237,0.25);
    }
    /* Light-mode overrides for inline pastel text colors so badges stay legible on white cards */
    html.light-mode [style*="color: #86efac"],
    html.light-mode [style*="color:#86efac"]   { color: #047857 !important; }   /* green pill */
    html.light-mode [style*="color: #d8b4fe"],
    html.light-mode [style*="color:#d8b4fe"]   { color: #6b21a8 !important; }   /* violet badge */
    html.light-mode [style*="color: #5eead4"],
    html.light-mode [style*="color:#5eead4"]   { color: #0f766e !important; }   /* teal badge */
    html.light-mode [style*="color: #ddd6fe"],
    html.light-mode [style*="color:#ddd6fe"]   { color: #5b21b6 !important; }
    html.light-mode [style*="color: #a5b4fc"],
    html.light-mode [style*="color:#a5b4fc"]   { color: #3730a3 !important; }
    html.light-mode [style*="color: #c4b5fd"],
    html.light-mode [style*="color:#c4b5fd"]   { color: #1d4ed8 !important; }
    html.light-mode [style*="color: #f9a8d4"],
    html.light-mode [style*="color:#f9a8d4"]   { color: #be185d !important; }
    html.light-mode [style*="color: #fcd34d"],
    html.light-mode [style*="color:#fcd34d"]   { color: #b45309 !important; }
    html.light-mode [style*="color: #fdba74"],
    html.light-mode [style*="color:#fdba74"]   { color: #c2410c !important; }
    html.light-mode [style*="color: #6ee7b7"],
    html.light-mode [style*="color:#6ee7b7"]   { color: #047857 !important; }
    html.light-mode [style*="color: #cbd5e1"],
    html.light-mode [style*="color:#cbd5e1"]   { color: #334155 !important; }

    /* ============ Fancy Table ============ */
    .fancy-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12.5px; }
    .fancy-table thead th {
        position: sticky; top: 0; z-index: 1;
        font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.07em;
        color: var(--text-faint);
        padding: 10px 12px; text-align: left;
        background: linear-gradient(180deg, var(--bg-glass-light), var(--bg-glass));
        border-bottom: 1px solid var(--border-glass-light);
    }
    .fancy-table thead th.text-right { text-align: right; }
    .fancy-table tbody td { padding: 11px 12px; color: var(--text-muted); border-bottom: 1px solid var(--border-glass); vertical-align: middle; }
    .fancy-table tbody tr { transition: background .15s ease; }
    .fancy-table tbody tr:hover { background: var(--bg-glass-hover); }
    .fancy-table tbody tr:hover td:first-child { color: var(--text-primary); }
    .fancy-table tbody tr:last-child td { border-bottom: 0; }

    .rank-badge {
        display: inline-flex; align-items: center; justify-content: center;
        width: 24px; height: 24px; border-radius: 8px;
        font-size: 10px; font-weight: 800;
        background: var(--bg-glass-input); color: var(--text-faint);
        border: 1px solid var(--border-glass);
        margin-right: 8px;
    }
    .rank-1 { background: linear-gradient(135deg,#fbbf24,#f59e0b); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(245,158,11,0.5); }
    .rank-2 { background: linear-gradient(135deg,#cbd5e1,#94a3b8); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(148,163,184,0.4); }
    .rank-3 { background: linear-gradient(135deg,#f97316,#ea580c); color: #fff; border-color: transparent; box-shadow: 0 4px 12px rgba(234,88,12,0.4); }

    /* Inline horizontal bar inside a table cell */
    .bar-cell { position: relative; min-width: 120px; }
    .bar-track {
        position: relative; height: 8px; border-radius: 999px;
        background: var(--bg-glass-input); overflow: hidden;
    }
    .bar-fill {
        position: absolute; top: 0; left: 0; bottom: 0; border-radius: 999px;
        background: var(--bar-color, linear-gradient(90deg, #8b5cf6, #ec4899));
        box-shadow: 0 0 12px var(--bar-glow, rgba(124,58,237,0.5));
    }

    /* List rows with progress bar (referrers, UTM) */
    .progress-row {
        position: relative;
        padding: 10px 14px;
        border-radius: 12px;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        overflow: hidden;
        transition: transform .15s ease;
    }
    .progress-row:hover { transform: translateX(2px); }
    .progress-row::before {
        content: ""; position: absolute; left: 0; top: 0; bottom: 0;
        width: var(--pr-width, 0%);
        background: var(--pr-color, linear-gradient(90deg, rgba(124,58,237,0.18), rgba(236,72,153,0.10)));
        z-index: 0;
    }
    .progress-row > * { position: relative; z-index: 1; }
    .progress-favicon {
        width: 22px; height: 22px; border-radius: 7px;
        display: flex; align-items: center; justify-content: center;
        background: var(--bg-glass);
        border: 1px solid var(--border-glass);
        font-size: 10px; color: var(--text-muted); overflow: hidden;
        flex-shrink: 0;
    }
    .progress-favicon img { width: 100%; height: 100%; object-fit: cover; }

    /* Table action button */
    .table-action {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 12px; border-radius: 11px;
        font-size: 11px; font-weight: 600;
        background: var(--bg-glass-input);
        border: 1px solid var(--border-glass);
        color: var(--text-muted);
        transition: all .2s ease;
    }
    .table-action:hover { background: linear-gradient(135deg,#7c3aed,#8b5cf6); color: #fff; border-color: transparent; box-shadow: 0 6px 18px rgba(124,58,237,0.4); }

    .stat-tile-value-sm { font-size: 22px; }
    @media (max-width: 640px) {
        .stat-tile-value { font-size: 22px; }
    }
</style>
@endpush

{{-- ===================== HERO ===================== --}}
@php
    // Resolve a favicon URL: explicit field → biolink page favicon
    // → Google's S2 favicon for the long URL's domain.
    $favSrc = null;
    if (!empty($link->favicon)) {
        $favSrc = $link->favicon;
    } elseif (!empty($link->settings['biolink']['favicons']['icon_512'])) {
        $favSrc = $link->settings['biolink']['favicons']['icon_512'];
    } elseif (!empty($link->settings['biolink']['favicons']['apple_touch_icon'])) {
        $favSrc = $link->settings['biolink']['favicons']['apple_touch_icon'];
    } elseif (!empty($link->long_url)) {
        $host = parse_url($link->long_url, PHP_URL_HOST);
        if ($host) $favSrc = 'https://www.google.com/s2/favicons?sz=64&domain=' . urlencode($host);
    } elseif ($link->type === 'biolink') {
        $favSrc = url('favicon.ico');
    }
    $heroActions = [
        ['label' => 'Export CSV', 'url' => route('user.links.clicks.export', $link).'?'.http_build_query($qs), 'icon' => 'fa-file-csv', 'class' => 'btn-ghost'],
        ['label' => 'QR', 'url' => route('user.links.qrcode', $link), 'icon' => 'fa-qrcode', 'class' => 'btn-ghost'],
    ];
    if ($link->type === 'biolink') {
        $heroActions[] = ['label' => 'Edit Blocks', 'url' => route('user.links.blocks.editor', $link), 'icon' => 'fa-th-large', 'class' => 'btn-primary'];
    }
    $heroActions[] = ['label' => 'Edit', 'url' => route('user.links.edit', $link), 'icon' => 'fa-edit', 'class' => 'btn-ghost'];
    $heroActions[] = ['label' => $link->hasSplashEnabled() ? 'Intro · On' : 'Intro', 'url' => route('user.links.splash', $link), 'icon' => 'fa-rocket', 'class' => 'btn-ghost'];
@endphp
@include('user.partials.page-hero', [
    'title'    => $link->title ?: $link->alias,
    'icon'     => $link->type === 'biolink' ? 'fa-th-large' : 'fa-link',
    'favicon'  => $favSrc,
    'url'      => $link->getShortUrl(),
    'chips'    => [
        ['icon' => 'fa-circle text-emerald-400', 'text' => ($link->is_active ?? true) ? 'Active' : 'Inactive'],
        ['icon' => $link->type === 'biolink' ? 'fa-th-large' : 'fa-link', 'text' => \App\Modules\User\Models\Link::typeLabel($link->type)],
        ['icon' => 'fa-calendar', 'text' => $link->created_at?->format('M d, Y')],
    ],
    'back'     => route('user.links.index'),
    'actions'  => $heroActions,
])

@include('user.links.partials.analytics-tabs', ['link' => $link, 'active' => 'overview'])

{{-- ===================== PERIOD CONTROLS ===================== --}}
<div class="period-bar mb-6">
    <div class="flex flex-wrap items-center gap-2">
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);"><i class="fas fa-clock text-violet-400"></i> Period</span>
        @foreach(['today'=>'Today','7d'=>'7d','30d'=>'30d','90d'=>'90d','year'=>'Year','all'=>'All'] as $k=>$lbl)
            <a href="{{ $buildUrl(['period'=>$k]) }}" class="pill {{ ($period ?? '30d')===$k ? 'pill-active' : '' }}">{{ $lbl }}</a>
        @endforeach
        <span class="mx-3 h-5 w-px" style="background: var(--border-glass);"></span>
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);"><i class="fas fa-layer-group text-violet-400"></i> Group</span>
        @foreach(['day'=>'Day','week'=>'Week','month'=>'Month','year'=>'Year'] as $k=>$lbl)
            <a href="{{ $buildUrl(['group'=>$k]) }}" class="pill {{ ($groupBy ?? 'day')===$k ? 'pill-active-soft' : '' }}">{{ $lbl }}</a>
        @endforeach
        <span class="mx-3 h-5 w-px" style="background: var(--border-glass);"></span>
        <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);"><i class="fas fa-mobile-screen text-violet-400"></i> Source</span>
        @foreach(['' => 'All', 'mobile_app' => 'Mobile app', 'web' => 'Web'] as $sk => $slbl)
            <a href="{{ $buildUrl(['source' => $sk === '' ? null : $sk]) }}" class="pill {{ (($sourceFilter ?? '') === $sk) ? 'pill-active' : '' }}">{{ $slbl }}</a>
        @endforeach
        <span class="mx-3 h-5 w-px hidden md:inline-block" style="background: var(--border-glass);"></span>
        <form method="GET" class="flex items-center gap-2 ml-auto">
            <input type="hidden" name="period" value="custom">
            <input type="hidden" name="group" value="{{ $groupBy }}">
            <input type="date" name="from" value="{{ request('from', $startDate->format('Y-m-d')) }}" class="theme-input text-xs py-1.5 px-2">
            <span class="text-xs" style="color:var(--text-faint);">to</span>
            <input type="date" name="to" value="{{ request('to', $endDate->format('Y-m-d')) }}" class="theme-input text-xs py-1.5 px-2">
            <button class="pill pill-active"><i class="fas fa-check text-[9px]"></i> Apply</button>
        </form>
    </div>
</div>

@php
    $sourceLabels = ['mobile_app' => 'Mobile app', 'web' => 'Web'];
    $activeFilters = [];
    if (!empty($aliasFilter)) {
        $activeFilters[] = ['key' => 'alias', 'label' => 'Alias', 'value' => '/' . $aliasFilter, 'icon' => 'fa-link', 'clearUrl' => $buildUrl(['alias' => null])];
    }
    if (!empty($sourceFilter)) {
        $activeFilters[] = ['key' => 'source', 'label' => 'Source', 'value' => $sourceLabels[$sourceFilter] ?? $sourceFilter, 'icon' => 'fa-mobile-screen', 'clearUrl' => $buildUrl(['source' => null])];
    }
    if (!empty($countryFilter)) {
        $activeFilters[] = ['key' => 'country', 'label' => 'Country', 'value' => ($flag($countryFilter) . ' ' . ($countryNames[$countryFilter] ?? $countryFilter)), 'icon' => 'fa-globe', 'clearUrl' => $buildUrl(['country' => null])];
    }
    if (!empty($deviceFilter)) {
        $activeFilters[] = ['key' => 'device', 'label' => 'Device', 'value' => ucfirst($deviceFilter), 'icon' => 'fa-display', 'clearUrl' => $buildUrl(['device' => null])];
    }
    if (!empty($languageFilter)) {
        $lf = $languageLabel($languageFilter);
        $langValue = ($lf['flag'] ? $lf['flag'] . ' ' : '') . $lf['name'];
        $activeFilters[] = ['key' => 'language', 'label' => 'Language', 'value' => $langValue, 'icon' => 'fa-language', 'clearUrl' => $buildUrl(['language' => null]), 'title' => 'Remove Language filter (' . $languageFilter . ')'];
    }
    $clearAllUrl = $buildUrl(['alias' => null, 'source' => null, 'country' => null, 'device' => null, 'language' => null]);
@endphp

@if(!empty($activeFilters))
{{-- ===================== ACTIVE FILTERS SUMMARY ===================== --}}
<div class="glass rounded-2xl px-4 py-3 mb-3 flex flex-wrap items-center gap-2">
    <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);">
        <i class="fas fa-filter text-violet-400"></i> Active filters
    </span>
    @foreach($activeFilters as $f)
        <a href="{{ $f['clearUrl'] }}"
           class="pill pill-active-soft inline-flex items-center gap-1.5"
           title="{{ $f['title'] ?? ('Remove ' . $f['label'] . ' filter') }}">
            <i class="fas {{ $f['icon'] }} text-[9px] opacity-70"></i>
            <span class="opacity-70">{{ $f['label'] }}:</span>
            <span class="font-bold">{{ $f['value'] }}</span>
            <i class="fas fa-times text-[9px] ml-0.5 opacity-80"></i>
        </a>
    @endforeach
    <a href="{{ $clearAllUrl }}" class="pill ml-auto inline-flex items-center gap-1.5" style="color: var(--text-muted);" title="Clear all filters">
        <i class="fas fa-xmark text-[10px]"></i> Clear all
    </a>
</div>
@endif

@if(count($availableAliases ?? []) > 1)
{{-- ===================== ALIAS FILTER ===================== --}}
<div class="glass rounded-2xl px-4 py-3 mb-3 flex flex-wrap items-center gap-2">
    <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);"><i class="fas fa-link text-violet-400"></i> Alias</span>
    <a href="{{ $buildUrl(['alias' => null]) }}" class="pill {{ empty($aliasFilter) ? 'pill-active' : '' }}">All</a>
    @foreach($availableAliases as $al)
        @php $isPrimary = ($al === $link->alias); $count = optional($aliasBreakdown->firstWhere('alias', $al))->total ?? 0; @endphp
        <a href="{{ $buildUrl(['alias' => $al]) }}" class="pill {{ ($aliasFilter === $al) ? 'pill-active' : '' }}" title="{{ $isPrimary ? 'Primary alias' : 'Alternative alias' }}">
            @if($isPrimary)<i class="fas fa-star text-[8px] mr-1 text-yellow-400"></i>@endif
            /{{ $al }} <span class="ml-1 opacity-60">({{ number_format($count) }})</span>
        </a>
    @endforeach
    @if($aliasFilter)
        <span class="ml-auto text-[11px]" style="color: var(--text-faint);"><i class="fas fa-filter mr-1"></i>Showing only clicks via <code>/{{ $aliasFilter }}</code></span>
    @endif
</div>
@endif

{{-- ===================== DANGER ZONE — RESET STATS ===================== --}}
<div class="glass rounded-2xl px-4 py-3 mb-3 flex flex-wrap items-center gap-2">
    <span class="text-[10px] uppercase tracking-wider font-bold mr-1" style="color: var(--text-faint);"><i class="fas fa-triangle-exclamation text-amber-400"></i> Reset</span>
    @if($aliasFilter)
        <form method="POST" action="{{ route('user.links.reset-stats', $link) }}?alias={{ urlencode($aliasFilter) }}" class="inline"
              onsubmit="return confirm('Delete ALL click data for /{{ $aliasFilter }} on this link?\n\nThis cannot be undone. Engagement sessions are preserved.');">
            @csrf
            @method('DELETE')
            <button type="submit" class="pill" style="background:rgba(245,158,11,0.15); color:#fbbf24; border-color:rgba(245,158,11,0.3);">
                <i class="fas fa-eraser text-[9px]"></i> Reset /{{ $aliasFilter }}
            </button>
        </form>
    @endif
    <form method="POST" action="{{ route('user.links.reset-stats', $link) }}" class="inline"
          onsubmit="return confirm('Delete ALL analytics data for this link?\n\n• Clicks\n• Page sessions\n• Block views\n\nThis cannot be undone.') && confirm('Are you absolutely sure? Type-check: this will wipe every analytics record for this link.');">
        @csrf
        @method('DELETE')
        <button type="submit" class="pill" style="background:rgba(239,68,68,0.15); color:#fca5a5; border-color:rgba(239,68,68,0.3);">
            <i class="fas fa-trash text-[9px]"></i> Reset all stats
        </button>
    </form>
    <span class="text-[11px] ml-2" style="color: var(--text-faint);">Deletes analytics data permanently. Link itself and its aliases are preserved.</span>
</div>

{{-- ===================== PRIMARY KPIs (3 hero numbers) ===================== --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-5">
    <div class="kpi-hero">
        <div class="kpi-hero-head">
            <span class="kpi-hero-label">Total clicks</span>
            <div class="kpi-hero-icon"><i class="fas fa-mouse-pointer"></i></div>
        </div>
        <div class="kpi-hero-value">{{ number_format($totalInRange) }}</div>
        <div class="kpi-hero-sub">in selected range</div>
    </div>
    <div class="kpi-hero">
        <div class="kpi-hero-head">
            <span class="kpi-hero-label">Unique visitors</span>
            <div class="kpi-hero-icon"><i class="fas fa-fingerprint"></i></div>
        </div>
        <div class="kpi-hero-value">{{ number_format($uniqueInRange) }}</div>
        <div class="kpi-hero-sub">distinct people</div>
    </div>
    <div class="kpi-hero">
        <div class="kpi-hero-head">
            <span class="kpi-hero-label">Sessions</span>
            <div class="kpi-hero-icon"><i class="fas fa-user-clock"></i></div>
        </div>
        <div class="kpi-hero-value">{{ number_format($totalSessions) }}</div>
        <div class="kpi-hero-sub">avg {{ _fmtSecs($avgSessionSeconds) }} on page</div>
    </div>
</div>

@include('user.links.partials.performance-coach')

{{-- ===================== SECONDARY METRICS (compact strip) ===================== --}}
<div class="kpi-strip mb-6">
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-eye"></i> Page visits</div>
        <div class="kpi-cell-value">{{ number_format($pageVisitsInRange) }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-th-large"></i> Block clicks</div>
        <div class="kpi-cell-value">{{ number_format($blockClicksInRange) }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-hourglass-half"></i> Engaged time</div>
        <div class="kpi-cell-value">{{ _fmtSecs($totalEngagedSeconds) }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-running"></i> Bounce rate</div>
        <div class="kpi-cell-value">{{ $bounceRate }}%</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-stopwatch"></i> Avg. time</div>
        <div class="kpi-cell-value">{{ _fmtSecs($avgSessionSeconds) }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-infinity"></i> All-time clicks</div>
        <div class="kpi-cell-value">{{ number_format($link->total_clicks) }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-users"></i> All-time unique</div>
        <div class="kpi-cell-value">{{ number_format($link->unique_clicks) }}</div>
    </div>
    <div class="kpi-cell">
        <div class="kpi-cell-head"><i class="fas fa-percentage"></i> Conversion</div>
        <div class="kpi-cell-value">{{ $totalInRange > 0 ? round(($uniqueInRange / $totalInRange) * 100) : 0 }}%</div>
    </div>
</div>

{{-- ===================== CLICKS OVER TIME ===================== --}}
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#7c3aed,#ec4899); --sc-glow: rgba(124,58,237,0.35); --sc-color: #ddd6fe; --sc-border: rgba(124,58,237,0.3);">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-chart-line"></i></div> Clicks Over Time <span class="text-[11px] font-medium ml-1" style="color:var(--text-faint);">({{ ucfirst($groupBy) }})</span></div>
        <span class="section-pill"><i class="fas fa-calendar-week"></i> {{ $startDate->format('M d, Y') }} → {{ $endDate->format('M d, Y') }}</span>
    </div>
    @if($clicksOverTime->isEmpty())
        <p class="text-sm text-center py-12" style="color: var(--text-faint);">No click data in this range</p>
    @else
        <div style="height: 320px;"><canvas id="clicksChart"></canvas></div>
    @endif
</div>

{{-- ===================== BROWSER / OS / DEVICE ===================== --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-7">
    <div class="section-card" style="--sc-accent: linear-gradient(90deg,#6366f1,#818cf8); --sc-glow: rgba(99,102,241,0.35); --sc-color: #a5b4fc; --sc-border: rgba(99,102,241,0.3);">
        <div class="section-head">
            <div class="section-title"><div class="section-icon"><i class="fas fa-globe"></i></div> Browsers</div>
            @if(!empty($browserFilter))
                <a href="{{ $buildUrl(['browser' => null]) }}" class="section-pill" title="Clear browser filter"><i class="fas fa-times mr-1"></i>{{ $browserFilter }}</a>
            @endif
        </div>
        @if($browserStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else
            <div style="height: 240px;"><canvas id="browserChart"></canvas></div>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <a href="{{ $buildUrl(['browser' => null]) }}" class="pill {{ empty($browserFilter) ? 'pill-active' : '' }}">All</a>
                @foreach($browserStats->take(8) as $row)
                    <a href="{{ $buildUrl(['browser' => $row->browser]) }}" class="pill {{ ($browserFilter ?? '') === $row->browser ? 'pill-active' : '' }}">
                        {{ $row->browser }} <span class="ml-1 opacity-60">({{ number_format($row->count) }})</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    <div class="section-card" style="--sc-accent: linear-gradient(90deg,#10b981,#34d399); --sc-glow: rgba(16,185,129,0.35); --sc-color: #6ee7b7; --sc-border: rgba(16,185,129,0.3);">
        <div class="section-head">
            <div class="section-title"><div class="section-icon"><i class="fas fa-laptop"></i></div> Operating Systems</div>
            @if(!empty($osFilter))
                <a href="{{ $buildUrl(['os' => null]) }}" class="section-pill" title="Clear OS filter"><i class="fas fa-times mr-1"></i>{{ $osFilter }}</a>
            @endif
        </div>
        @if($osStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else
            <div style="height: 240px;"><canvas id="osChart"></canvas></div>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <a href="{{ $buildUrl(['os' => null]) }}" class="pill {{ empty($osFilter) ? 'pill-active' : '' }}">All</a>
                @foreach($osStats->take(8) as $row)
                    <a href="{{ $buildUrl(['os' => $row->os]) }}" class="pill {{ ($osFilter ?? '') === $row->os ? 'pill-active' : '' }}">
                        {{ $row->os }} <span class="ml-1 opacity-60">({{ number_format($row->count) }})</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
    <div class="section-card" style="--sc-accent: linear-gradient(90deg,#f59e0b,#fbbf24); --sc-glow: rgba(245,158,11,0.35); --sc-color: #fcd34d; --sc-border: rgba(245,158,11,0.3);">
        <div class="section-head">
            <div class="section-title"><div class="section-icon"><i class="fas fa-mobile-alt"></i></div> Devices</div>
            @if(!empty($deviceFilter))
                <a href="{{ $buildUrl(['device' => null]) }}" class="section-pill" title="Clear device filter"><i class="fas fa-times mr-1"></i>{{ ucfirst($deviceFilter) }}</a>
            @endif
        </div>
        @if($deviceStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else
            <div style="height: 220px;"><canvas id="deviceChart"></canvas></div>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <a href="{{ $buildUrl(['device' => null]) }}" class="pill {{ empty($deviceFilter) ? 'pill-active' : '' }}">All</a>
                @foreach($deviceStats as $row)
                    @continue(!in_array($row->device_type, ['mobile', 'desktop', 'tablet'], true))
                    <a href="{{ $buildUrl(['device' => $row->device_type]) }}" class="pill {{ ($deviceFilter ?? '') === $row->device_type ? 'pill-active' : '' }}">
                        {{ ucfirst($row->device_type) }} <span class="ml-1 opacity-60">({{ number_format($row->count) }})</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

{{-- ===================== TRAFFIC SOURCE (mobile app vs web) ===================== --}}
@php
    $sourceLabelMap = ['mobile_app' => 'Mobile app', 'web' => 'Web', 'unknown' => 'Unknown'];
    $sourceTotal = (int) $sourceStats->sum('count');
@endphp
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#06b6d4,#22d3ee); --sc-glow: rgba(6,182,212,0.35); --sc-color: #67e8f9; --sc-border: rgba(6,182,212,0.3);">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-mobile-screen"></i></div> Traffic source</div>
        <div class="text-xs" style="color: var(--text-faint);">Where each visit came from — the in-app viewer or the web</div>
    </div>
    @if($sourceStats->isEmpty() || $sourceTotal === 0)
        <p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-center">
            <div style="height: 220px;"><canvas id="sourceChart"></canvas></div>
            <div class="space-y-2">
                @foreach($sourceStats as $row)
                    @php
                        $label = $sourceLabelMap[$row->source] ?? ucfirst(str_replace('_', ' ', $row->source));
                        $pct = $sourceTotal > 0 ? round(($row->count / $sourceTotal) * 100, 1) : 0;
                    @endphp
                    <div class="flex items-center justify-between text-sm" style="color: var(--text-faint);">
                        <span>{{ $label }}</span>
                        <span><strong style="color: var(--text);">{{ number_format($row->count) }}</strong> &middot; {{ $pct }}%</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

{{-- ===================== LANGUAGES ===================== --}}
@php
    $languageTotal = (int) $languageStats->sum('count');
    // Toggle between per-locale view (default, current behaviour) and a
    // rolled-up "by base language" view that sums all regional variants
    // (en-US + en-GB + en-CA → English). Filter pills are only clickable
    // in locale mode because the click log stores the full locale string.
    $languageMode = request()->query('lang_mode') === 'base' ? 'base' : 'locale';
    $languageGroups = [];
    if ($languageMode === 'base') {
        foreach ($languageStats as $row) {
            // Normalize separators — some clients send `en_US` instead of the
            // BCP-47 `en-US`, and we want both to bucket into the same base.
            $raw = str_replace('_', '-', (string) $row->language);
            $parts = explode('-', $raw);
            $base = strtolower($parts[0] ?? $raw);
            if (!preg_match('/^[a-z]{2,3}$/', $base)) {
                $base = $raw; // fall back to raw value when it isn't a parseable language tag
            }
            if (!isset($languageGroups[$base])) {
                $languageGroups[$base] = ['count' => 0, 'locales' => []];
            }
            $languageGroups[$base]['count'] += (int) $row->count;
            $languageGroups[$base]['locales'][] = ['language' => $raw, 'count' => (int) $row->count];
        }
        uasort($languageGroups, fn($a, $b) => $b['count'] <=> $a['count']);
        foreach ($languageGroups as &$g) {
            usort($g['locales'], fn($a, $b) => $b['count'] <=> $a['count']);
        }
        unset($g);
    }
@endphp
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#8b5cf6,#ec4899); --sc-glow: rgba(139,92,246,0.35); --sc-color: #d8b4fe; --sc-border: rgba(139,92,246,0.3);">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-language"></i></div> Languages</div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="text-xs" style="color: var(--text-faint);">Browser language sent by each visitor</div>
            <div class="inline-flex items-center gap-1 p-0.5 rounded-lg" role="group" aria-label="Languages grouping"
                 style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
                <a href="{{ $buildUrl(['lang_mode' => null]) }}"
                   class="pill {{ $languageMode === 'locale' ? 'pill-active' : '' }}"
                   title="Show every browser locale separately (en-US, en-GB, …)">By locale</a>
                <a href="{{ $buildUrl(['lang_mode' => 'base']) }}"
                   class="pill {{ $languageMode === 'base' ? 'pill-active' : '' }}"
                   title="Roll regional variants up to the base language (English, Spanish, …)">By language</a>
            </div>
            @if(!empty($languageFilter))
                @php $lf = $languageLabel($languageFilter); @endphp
                <a href="{{ $buildUrl(['language' => null]) }}" class="section-pill" title="Clear language filter ({{ $languageFilter }})"><i class="fas fa-times mr-1"></i>@if($lf['flag'])<span class="mr-1">{{ $lf['flag'] }}</span>@endif{{ $lf['name'] }}</a>
            @endif
        </div>
    </div>
    @if($languageStats->isEmpty() || $languageTotal === 0)
        <p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
    @elseif($languageMode === 'base')
        <div class="flex flex-wrap items-center gap-2">
            @foreach($languageGroups as $base => $group)
                @php
                    $pct = $languageTotal > 0 ? round(($group['count'] / $languageTotal) * 100, 1) : 0;
                    $bl = $languageLabel($base);
                    $topLocales = array_slice($group['locales'], 0, 5);
                    $tooltipParts = [];
                    foreach ($topLocales as $loc) {
                        $ll = $languageLabel($loc['language']);
                        // Prefer the human region name (e.g. "United States")
                        // over the raw tag (e.g. "en-US") so the tooltip reads
                        // as a list of regions, per the spec.
                        $regionLabel = null;
                        if (!empty($ll['region'])) {
                            $regionLabel = $regionNames[$ll['region']] ?? $ll['region'];
                        }
                        $label = $regionLabel ?: ($loc['language'] !== '' ? $loc['language'] : 'Unknown');
                        $tooltipParts[] = $label . ' · ' . number_format($loc['count']);
                    }
                    $extra = count($group['locales']) - count($topLocales);
                    if ($extra > 0) $tooltipParts[] = '+' . $extra . ' more';
                    $tooltip = $bl['name'] . ' · ' . $pct . '% of clicks · ' . implode(', ', $tooltipParts);
                @endphp
                <span class="pill" title="{{ $tooltip }}" style="cursor: default;">
                    {{ $bl['name'] }}
                    <span class="ml-1 opacity-60">({{ number_format($group['count']) }})</span>
                    @if(count($group['locales']) > 1)
                        <span class="ml-1 opacity-50 text-[10px]">{{ count($group['locales']) }} variants</span>
                    @endif
                </span>
            @endforeach
        </div>
    @else
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ $buildUrl(['language' => null]) }}" class="pill {{ empty($languageFilter) ? 'pill-active' : '' }}">All</a>
            @foreach($languageStats as $row)
                @php
                    $pct = $languageTotal > 0 ? round(($row->count / $languageTotal) * 100, 1) : 0;
                    $ll = $languageLabel($row->language);
                @endphp
                <a href="{{ $buildUrl(['language' => $row->language]) }}" class="pill {{ ($languageFilter ?? '') === $row->language ? 'pill-active' : '' }}" title="{{ $row->language }} · {{ $pct }}% of clicks">
                    @if($ll['flag'])<span class="mr-1">{{ $ll['flag'] }}</span>@endif{{ $ll['name'] }} <span class="ml-1 opacity-60">({{ number_format($row->count) }})</span>
                </a>
            @endforeach
        </div>
    @endif
</div>

{{-- ===================== GEOGRAPHIC HEATMAP ===================== --}}
<div class="section-card mb-7"
     style="--sc-accent: linear-gradient(90deg,#f97316,#ef4444); --sc-glow: rgba(249,115,22,0.35); --sc-color: #fdba74; --sc-border: rgba(249,115,22,0.3);">
    <div class="section-head">
        <div class="section-title">
            <div class="section-icon"><i class="fas fa-map-marked-alt"></i></div>
            Geographic Heatmap
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <span id="heatmap-meta" class="section-pill" style="display:none;"></span>
            <span id="heatmap-live-meta" class="section-pill" style="display:none; background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.4); color: #86efac;">
                <span class="live-dot" style="display:inline-block;width:8px;height:8px;border-radius:999px;background:#22c55e;margin-right:6px;box-shadow:0 0 0 0 rgba(34,197,94,0.7);animation:livePulseDot 1.4s infinite;"></span>
                <span id="heatmap-live-meta-text">0 live visitors right now</span>
            </span>
            <button type="button" id="heatmap-live-toggle" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold inline-flex items-center gap-1.5"
                    style="background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);"
                    aria-pressed="false" title="Show clicks happening right now">
                <i class="fas fa-circle text-[8px]" style="opacity:0.5;"></i>
                <span>Live</span>
            </button>
            <button type="button" id="heatmap-download" class="table-action" title="Download as image"
                style="font-size: 11px;">
                <i class="fas fa-download"></i> <span class="hidden sm:inline">Download</span>
            </button>
            <div class="relative inline-block" id="heatmap-share-wrap">
                <button type="button" id="heatmap-share" class="table-action" title="Share snapshot"
                    style="font-size: 11px;">
                    <i class="fas fa-share-nodes"></i> <span class="hidden sm:inline">Share</span>
                </button>
                <div id="heatmap-share-menu"
                    class="absolute right-0 mt-2 w-60 rounded-xl p-1.5 z-50"
                    style="display:none; background: var(--bg-glass); border: 1px solid var(--border-glass); backdrop-filter: blur(20px); box-shadow: 0 18px 48px rgba(0,0,0,0.35);">
                    <button type="button" data-share-action="copy" class="w-full text-left px-3 py-2 rounded-lg text-[12px] inline-flex items-center gap-2 hover:bg-white/5" style="color: var(--text-secondary);">
                        <i class="fas fa-copy w-4 text-center"></i> Copy image to clipboard
                    </button>
                    <button type="button" data-share-action="x" class="w-full text-left px-3 py-2 rounded-lg text-[12px] inline-flex items-center gap-2 hover:bg-white/5" style="color: var(--text-secondary);">
                        <i class="fab fa-twitter w-4 text-center"></i> Share to X (copies image)
                    </button>
                    <button type="button" data-share-action="linkedin" class="w-full text-left px-3 py-2 rounded-lg text-[12px] inline-flex items-center gap-2 hover:bg-white/5" style="color: var(--text-secondary);">
                        <i class="fab fa-linkedin w-4 text-center"></i> Share to LinkedIn (copies image)
                    </button>
                </div>
            </div>
            <span id="heatmap-share-toast" x-cloak
                class="section-pill" style="display:none; background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.4); color: #86efac;"></span>
        </div>
    </div>
    <style>
        @keyframes livePulseDot {
            0%   { box-shadow: 0 0 0 0 rgba(34,197,94,0.7); }
            70%  { box-shadow: 0 0 0 8px rgba(34,197,94,0); }
            100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); }
        }
        .live-pin {
            position: relative;
            width: 16px; height: 16px;
            pointer-events: none;
        }
        .live-pin::before, .live-pin::after {
            content: '';
            position: absolute;
            left: 50%; top: 50%;
            border-radius: 999px;
            transform: translate(-50%, -50%);
        }
        .live-pin::before {
            width: 12px; height: 12px;
            background: #22c55e;
            box-shadow: 0 0 12px rgba(34,197,94,0.9), 0 0 0 2px rgba(255,255,255,0.85);
            z-index: 2;
        }
        .live-pin::after {
            width: 12px; height: 12px;
            background: rgba(34,197,94,0.55);
            animation: livePinPulse 1.6s ease-out infinite;
            z-index: 1;
        }
        @keyframes livePinPulse {
            0%   { width: 12px; height: 12px; opacity: 0.7; }
            100% { width: 56px; height: 56px; opacity: 0; }
        }
        .live-pin.fading::before, .live-pin.fading::after {
            transition: opacity 1.5s ease-out;
            opacity: 0;
        }
    </style>
    <div id="heatmap-empty" style="display:none; padding: 2rem 0; text-align:center; color: var(--text-faint); font-size: 0.875rem;">
        <i class="fas fa-globe-americas" style="font-size: 2rem; opacity: 0.4; margin-bottom: 0.75rem; display:block;"></i>
        No geographic data yet for this period — clicks will appear on the map as they come in.
    </div>
    <div id="heatmap-container" style="position: relative; height: 420px; border-radius: 14px; overflow: hidden; border: 1px solid var(--border-glass); background: var(--bg-glass-input);">
        <div id="heatmap" style="width:100%; height:100%;"></div>
        <div id="heatmap-loading" style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; color: var(--text-faint); font-size: 0.85rem; background: var(--bg-glass-input);">
            <i class="fas fa-spinner fa-spin mr-2"></i> Loading map…
        </div>
    </div>
</div>

{{-- ===================== COUNTRIES / CITIES ===================== --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-7">
    <div class="section-card" style="--sc-accent: linear-gradient(90deg,#3b82f6,#a78bfa); --sc-glow: rgba(59,130,246,0.35); --sc-color: #c4b5fd; --sc-border: rgba(59,130,246,0.3);">
        <div class="section-head"><div class="section-title"><div class="section-icon"><i class="fas fa-flag"></i></div> Top Countries</div>
            @if(!empty($countryFilter))
                <a href="{{ $buildUrl(['country' => null]) }}" class="section-pill" title="Clear country filter"><i class="fas fa-times mr-1"></i>{{ $countryNames[$countryFilter] ?? $countryFilter }}</a>
            @elseif(!$countryStats->isEmpty())
                <span class="section-pill">{{ $countryStats->count() }} regions</span>
            @endif
        </div>
        @if($countryStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else
        <div class="overflow-y-auto max-h-80 -mx-2 px-2">
            <table class="fancy-table">
                <thead><tr><th>#</th><th>Country</th><th class="text-right">Clicks</th><th>Share</th></tr></thead>
                <tbody>
                @php $totalC = $countryStats->sum('count') ?: 1; $maxC = $countryStats->max('count') ?: 1; @endphp
                @foreach($countryStats as $i => $stat)
                @php
                    $pct = round(($stat->count / $totalC) * 100, 1);
                    $w = round(($stat->count / $maxC) * 100, 1);
                    $isActiveCountry = ($countryFilter ?? '') === $stat->country_code;
                    $rowHref = $buildUrl(['country' => $isActiveCountry ? null : $stat->country_code]);
                @endphp
                <tr onclick="window.location='{{ $rowHref }}'" style="cursor:pointer; {{ $isActiveCountry ? 'background: rgba(59,130,246,0.08);' : '' }}" title="{{ $isActiveCountry ? 'Click to clear filter' : 'Click to filter analytics by this country' }}">
                    <td style="width:38px;"><span class="rank-badge {{ $i<3 ? 'rank-'.($i+1) : '' }}">{{ $i+1 }}</span></td>
                    <td style="color: var(--text-primary);">
                        <span class="text-lg mr-2 align-middle">{{ $flag($stat->country_code) }}</span>
                        <span class="font-medium">{{ $countryNames[$stat->country_code] ?? $stat->country_code }}</span>
                        <span class="text-[10px] ml-1" style="color: var(--text-faint);">{{ $stat->country_code }}</span>
                        @if($isActiveCountry)<i class="fas fa-filter text-[9px] ml-1 text-blue-400"></i>@endif
                    </td>
                    <td class="text-right font-bold" style="color: var(--text-primary);">{{ number_format($stat->count) }}</td>
                    <td class="bar-cell" style="width: 38%;">
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $w }}%; --bar-color: linear-gradient(90deg,#3b82f6,#a78bfa); --bar-glow: rgba(59,130,246,0.4);"></div>
                        </div>
                        <div class="text-[10px] mt-1" style="color: var(--text-faint);">{{ $pct }}%</div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
    <div class="section-card" style="--sc-accent: linear-gradient(90deg,#ec4899,#f472b6); --sc-glow: rgba(236,72,153,0.35); --sc-color: #f9a8d4; --sc-border: rgba(236,72,153,0.3);">
        <div class="section-head"><div class="section-title"><div class="section-icon"><i class="fas fa-city"></i></div> Top Cities</div>
            @if(!$cityStats->isEmpty())<span class="section-pill">{{ $cityStats->count() }} cities</span>@endif
        </div>
        @if($cityStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No data</p>
        @else
        <div class="overflow-y-auto max-h-80 -mx-2 px-2">
            <table class="fancy-table">
                <thead><tr><th>#</th><th>City</th><th>Country</th><th class="text-right">Clicks</th><th>Share</th></tr></thead>
                <tbody>
                @php $maxCity = $cityStats->max('count') ?: 1; $totalCity = $cityStats->sum('count') ?: 1; @endphp
                @foreach($cityStats as $i => $stat)
                @php $w = round(($stat->count / $maxCity) * 100, 1); $pct = round(($stat->count / $totalCity) * 100, 1); @endphp
                <tr>
                    <td style="width:38px;"><span class="rank-badge {{ $i<3 ? 'rank-'.($i+1) : '' }}">{{ $i+1 }}</span></td>
                    <td style="color: var(--text-primary);" class="font-medium">{{ $stat->city }}</td>
                    <td><span class="text-base align-middle">{{ $flag($stat->country_code) }}</span> <span style="color:var(--text-muted);">{{ $stat->country_code }}</span></td>
                    <td class="text-right font-bold" style="color: var(--text-primary);">{{ number_format($stat->count) }}</td>
                    <td class="bar-cell" style="width: 30%;">
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $w }}%; --bar-color: linear-gradient(90deg,#ec4899,#f472b6); --bar-glow: rgba(236,72,153,0.4);"></div>
                        </div>
                        <div class="text-[10px] mt-1" style="color: var(--text-faint);">{{ $pct }}%</div>
                    </td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</div>

{{-- ===================== BIOLINK BLOCKS ===================== --}}
@if($link->type === 'biolink')
@php
    // Brand icon + colour table for resolving individual social platform clicks.
    // Mirrors the icon set used on the public bio page so the analytics row matches what the visitor saw.
    $socialIconMap = [
        'instagram'   => ['fab fa-instagram',   '#E4405F', 'Instagram'],
        'twitter'     => ['fab fa-x-twitter',   '#0f172a', 'X (Twitter)'],
        'x'           => ['fab fa-x-twitter',   '#0f172a', 'X (Twitter)'],
        'facebook'    => ['fab fa-facebook-f',  '#1877F2', 'Facebook'],
        'tiktok'      => ['fab fa-tiktok',      '#0f172a', 'TikTok'],
        'youtube'     => ['fab fa-youtube',     '#FF0000', 'YouTube'],
        'linkedin'    => ['fab fa-linkedin-in', '#0A66C2', 'LinkedIn'],
        'github'      => ['fab fa-github',      '#0f172a', 'GitHub'],
        'discord'     => ['fab fa-discord',     '#5865F2', 'Discord'],
        'telegram'    => ['fab fa-telegram',    '#26A5E4', 'Telegram'],
        'whatsapp'    => ['fab fa-whatsapp',    '#25D366', 'WhatsApp'],
        'snapchat'    => ['fab fa-snapchat',    '#facc15', 'Snapchat'],
        'pinterest'   => ['fab fa-pinterest',   '#BD081C', 'Pinterest'],
        'twitch'      => ['fab fa-twitch',      '#9146FF', 'Twitch'],
        'dribbble'    => ['fab fa-dribbble',    '#EA4C89', 'Dribbble'],
        'website'     => ['fas fa-globe',       '#7c3aed', 'Website'],
        'email'       => ['fas fa-envelope',    '#7c3aed', 'Email'],
        'spotify'     => ['fab fa-spotify',     '#1DB954', 'Spotify'],
        'soundcloud'  => ['fab fa-soundcloud',  '#FF5500', 'SoundCloud'],
        'apple'       => ['fab fa-apple',       '#0f172a', 'Apple'],
        'reddit'      => ['fab fa-reddit',      '#FF4500', 'Reddit'],
        'medium'      => ['fab fa-medium',      '#0f172a', 'Medium'],
        'behance'     => ['fab fa-behance',     '#1769FF', 'Behance'],
    ];

    // Sniff a platform key from a destination URL when the block-level platforms
    // map can't resolve it (rare — happens when the platform was renamed/removed
    // after the click was tracked).
    $sniffPlatform = function($url) {
        if (!$url) return null;
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        $host = preg_replace('/^www\./', '', $host);
        $map = [
            'instagram.com' => 'instagram', 'twitter.com' => 'twitter', 'x.com' => 'x',
            'facebook.com' => 'facebook', 'fb.com' => 'facebook',
            'tiktok.com' => 'tiktok', 'youtube.com' => 'youtube', 'youtu.be' => 'youtube',
            'linkedin.com' => 'linkedin', 'github.com' => 'github',
            'discord.com' => 'discord', 'discord.gg' => 'discord',
            't.me' => 'telegram', 'telegram.me' => 'telegram',
            'wa.me' => 'whatsapp', 'whatsapp.com' => 'whatsapp',
            'snapchat.com' => 'snapchat', 'pinterest.com' => 'pinterest',
            'twitch.tv' => 'twitch', 'dribbble.com' => 'dribbble',
            'spotify.com' => 'spotify', 'soundcloud.com' => 'soundcloud',
            'apple.com' => 'apple', 'reddit.com' => 'reddit',
            'medium.com' => 'medium', 'behance.net' => 'behance',
        ];
        if (isset($map[$host])) return $map[$host];
        foreach ($map as $needle => $key) {
            if (str_ends_with($host, '.'.$needle)) return $key;
        }
        if (str_starts_with(strtolower($url), 'mailto:')) return 'email';
        return null;
    };

    // Resolves the identity of a single click row.
    // For multi-link blocks (socials/socials_multi/socials_custom) we look up the
    // destination URL inside the block's `platforms` map so each platform shows its
    // own brand name + icon (Instagram, YouTube...) instead of the generic "Socials".
    $blockIdentity = function($id, $type, $destUrl = null) use ($blockMeta, $blockTypes, $socialIconMap, $sniffPlatform) {
        $info  = $blockTypes[$type] ?? ['label' => ucfirst($type ?? 'block'), 'icon' => 'fa-cube'];
        $meta  = $blockMeta[$id] ?? [];
        $title = $meta['title'] ?? null;
        $url   = $meta['url']   ?? null;
        $thumb = $meta['thumb'] ?? null;
        $isMultiLink = in_array($type, ['socials', 'socials_multi', 'socials_custom']);

        if ($isMultiLink && $destUrl) {
            $platforms = $meta['platforms'] ?? [];
            $platKey   = $platforms[$destUrl]['key'] ?? null;
            $platLabel = $platforms[$destUrl]['label'] ?? null;
            // Fallback: sniff platform from URL host (handles platforms removed after clicks were logged).
            if (!$platKey) $platKey = $sniffPlatform($destUrl);
            if (isset($socialIconMap[$platKey])) {
                [$ico, $color, $brandLabel] = $socialIconMap[$platKey];
                return [
                    'info'  => ['label' => $info['label'], 'icon' => $info['icon']],
                    'title' => $platLabel ?: $brandLabel,
                    'url'   => $destUrl,
                    'thumb' => null,
                    'platform' => [
                        'icon'  => $ico,
                        'color' => $color,
                        'label' => $platLabel ?: $brandLabel,
                    ],
                    'parent_title' => $meta['title'] ?? $info['label'],
                ];
            }
            // Unknown platform but still a sub-link of a socials block — show host as title.
            $host = parse_url($destUrl, PHP_URL_HOST) ?: \Illuminate\Support\Str::limit($destUrl, 30);
            return [
                'info'  => $info,
                'title' => $platLabel ?: $host,
                'url'   => $destUrl,
                'thumb' => null,
                'platform' => ['icon' => 'fas fa-link', 'color' => '#7c3aed', 'label' => $host],
                'parent_title' => $meta['title'] ?? $info['label'],
            ];
        }

        if (!$title && $url) { $title = parse_url($url, PHP_URL_HOST) ?: \Illuminate\Support\Str::limit($url, 50); }
        if (!$title) { $title = $info['label'] . ' #' . $id; }
        return compact('info', 'title', 'url', 'thumb');
    };
@endphp

@php
    // Helper: render a delta pill comparing current vs previous values.
    // Returns a small chip with arrow + percentage colour-coded (up=green, down=red).
    if (!function_exists('_blockDeltaPill')) {
        function _blockDeltaPill($curr, $prev) {
            $curr = (int) $curr; $prev = (int) $prev;
            if ($prev === 0 && $curr === 0) {
                return '<span class="delta-pill delta-flat" title="No data in either period">—</span>';
            }
            if ($prev === 0) {
                return '<span class="delta-pill delta-up" title="New in this period"><i class="fas fa-arrow-up"></i> NEW</span>';
            }
            if ($curr === 0) {
                return '<span class="delta-pill delta-down" title="Dropped to zero"><i class="fas fa-arrow-down"></i> -100%</span>';
            }
            $pct = round((($curr - $prev) / $prev) * 100, 1);
            if (abs($pct) < 0.05) {
                return '<span class="delta-pill delta-flat"><i class="fas fa-minus"></i> 0%</span>';
            }
            $cls = $pct > 0 ? 'delta-up' : 'delta-down';
            $ico = $pct > 0 ? 'fa-arrow-up' : 'fa-arrow-down';
            $sign = $pct > 0 ? '+' : '';
            return '<span class="delta-pill '.$cls.'" title="vs previous period: '.number_format($prev).' clicks"><i class="fas '.$ico.'"></i> '.$sign.number_format($pct, 1).'%</span>';
        }
    }
    $topBlock = $blockStats->first();
    // Compare the top row against itself in the previous period using
    // (block_id, destination_url) so a top-performing social platform
    // gets its own delta instead of the whole socials block aggregate.
    $topBlockPrev = 0;
    if ($topBlock) {
        $tk = $topBlock->block_id . '|' . ($topBlock->destination_url ?? '');
        $topBlockPrev = $blockStatsPrevByDest[$tk]->count ?? 0;
    }
    // Window labels — compact, e.g. "Apr 9 – Apr 16" / "vs Apr 2 – Apr 9"
    $rangeFmt = function($a, $b){
        $sameYear = $a->format('Y') === $b->format('Y');
        return $a->format('M j').' – '.$b->format($sameYear ? 'M j' : 'M j, Y');
    };
@endphp
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#8b5cf6,#d946ef); --sc-glow: rgba(139,92,246,0.35); --sc-color: #d8b4fe; --sc-border: rgba(139,92,246,0.3);">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-th-large"></i></div> Block-Level Clicks</div>
        <span class="section-pill" title="Each row = one block in your Link in Bio page. Clicks are tracked when a visitor taps the block on your public page."><i class="fas fa-link"></i> Internal block clicks · {{ $rangeFmt($startDate, $endDate) }} vs prev</span>
    </div>

    {{-- ===== Comparison KPIs (this period vs previous period of same length) ===== --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5 mb-6">
        <div class="cmp-tile">
            <div class="cmp-tile-head"><i class="fas fa-mouse-pointer"></i> Block clicks</div>
            <div class="cmp-tile-row">
                <div class="cmp-tile-value">{{ number_format($blockClicksInRange) }}</div>
                {!! _blockDeltaPill($blockClicksInRange, $blockClicksInRangePrev) !!}
            </div>
            <div class="cmp-tile-sub">prev: {{ number_format($blockClicksInRangePrev) }}</div>
        </div>
        <div class="cmp-tile">
            <div class="cmp-tile-head"><i class="fas fa-fingerprint"></i> Unique block visitors</div>
            <div class="cmp-tile-row">
                <div class="cmp-tile-value">{{ number_format($uniqueBlockClicksInRange) }}</div>
                {!! _blockDeltaPill($uniqueBlockClicksInRange, $uniqueBlockClicksPrev) !!}
            </div>
            <div class="cmp-tile-sub">prev: {{ number_format($uniqueBlockClicksPrev) }}</div>
        </div>
        <div class="cmp-tile">
            <div class="cmp-tile-head"><i class="fas fa-trophy"></i> Top performing block</div>
            @if($topBlock)
                @php $tb = $blockIdentity($topBlock->block_id, $topBlock->block_type, $topBlock->destination_url); @endphp
                <div class="cmp-tile-row">
                    <div class="cmp-tile-value-sm truncate" title="{{ $tb['title'] }}">{{ \Illuminate\Support\Str::limit($tb['title'], 22) }}</div>
                    {!! _blockDeltaPill($topBlock->count, $topBlockPrev) !!}
                </div>
                <div class="cmp-tile-sub">{{ number_format($topBlock->count) }} clicks · prev: {{ number_format($topBlockPrev) }}</div>
            @else
                <div class="cmp-tile-row">
                    <div class="cmp-tile-value-sm">—</div>
                </div>
                <div class="cmp-tile-sub">no blocks clicked yet</div>
            @endif
        </div>
    </div>

    @if($blockStats->isEmpty())
        <div class="text-center py-10 px-6 rounded-xl" style="background: var(--bg-glass-input); border: 1px dashed var(--border-glass);">
            <i class="fas fa-th-large text-2xl mb-2" style="color: var(--text-faint);"></i>
            <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">No block clicks recorded yet</p>
            <p class="text-xs" style="color: var(--text-faint);">Visit your public Link in Bio page and click a block — it will appear here within seconds.</p>
            <a href="{{ url('/' . $link->alias) }}" target="_blank" rel="noopener" class="btn-ghost text-xs py-2 mt-3 inline-flex">
                <i class="fas fa-external-link-alt text-[10px]"></i> Open my Link in Bio
            </a>
        </div>
    @else
    <div class="overflow-x-auto -mx-2 px-2">
        <table class="fancy-table">
            <thead><tr>
                <th>#</th><th>Block</th><th>Destination</th><th class="text-right">Clicks</th><th class="text-right">Unique</th><th class="text-right">vs Prev</th><th>Share</th>
            </tr></thead>
            <tbody>
            @php $maxB = $blockStats->max('count') ?: 1; $totalB = $blockStats->sum('count') ?: 1; @endphp
            @foreach($blockStats as $i => $b)
            @php
                $bi   = $blockIdentity($b->block_id, $b->block_type, $b->destination_url);
                $info = $bi['info'];
                $plat = $bi['platform'] ?? null;       // present only for socials* sub-link rows
                $w    = round(($b->count / $maxB) * 100, 1);
                $pct  = round(($b->count / $totalB) * 100, 1);
                // Per-(block + destination) prev count so each social platform gets its own delta
                $prevKey = $b->block_id . '|' . ($b->destination_url ?? '');
                $prev    = $blockStatsPrevByDest[$prevKey]->count ?? 0;
            @endphp
            <tr>
                <td style="width:38px;"><span class="rank-badge {{ $i<3 ? 'rank-'.($i+1) : '' }}">{{ $i+1 }}</span></td>
                <td style="color: var(--text-primary); min-width: 220px;">
                    <div class="flex items-center gap-2.5">
                        @if($plat)
                            {{-- Platform-specific brand icon (per social link inside a socials block) --}}
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg flex-shrink-0" style="background: {{ $plat['color'] }}1a; color: {{ $plat['color'] }}; border: 1px solid {{ $plat['color'] }}40;"><i class="{{ $plat['icon'] }} text-base"></i></span>
                        @elseif(!empty($bi['thumb']))
                            <span class="w-9 h-9 rounded-lg overflow-hidden flex-shrink-0" style="border: 1px solid var(--border-glass);"><img src="{{ $bi['thumb'] }}" class="w-full h-full object-cover" onerror="this.parentNode.innerHTML='<span class=\'inline-flex items-center justify-center w-full h-full\' style=\'background: linear-gradient(135deg,#8b5cf6,#d946ef); color:#fff;\'><i class=\'fas {{ $info['icon'] }}\'></i></span>'"></span>
                        @else
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg flex-shrink-0" style="background: linear-gradient(135deg,#8b5cf6,#d946ef); color:#fff;"><i class="fas {{ $info['icon'] }} text-xs"></i></span>
                        @endif
                        <div class="min-w-0">
                            <div class="text-sm font-semibold truncate" style="color: var(--text-primary); max-width: 240px;" title="{{ $bi['title'] }}">{{ $bi['title'] }}</div>
                            <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                <span class="text-[9.5px] px-1.5 py-0.5 rounded-md font-bold" style="background: rgba(139,92,246,0.15); color:#d8b4fe;">{{ $info['label'] }}</span>
                                @if($plat && !empty($bi['parent_title']))
                                    <span class="text-[9.5px] px-1.5 py-0.5 rounded-md" style="background: var(--bg-glass-input); color: var(--text-muted); border: 1px solid var(--border-glass);" title="Parent block">in {{ \Illuminate\Support\Str::limit($bi['parent_title'], 22) }}</span>
                                @endif
                                <span class="text-[9.5px]" style="color: var(--text-faint);">#{{ $b->block_id }}</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="text-xs truncate max-w-xs" style="color: var(--text-muted);">{{ $b->destination_url }}</td>
                <td class="text-right font-bold" style="color: var(--text-primary);">{{ number_format($b->count) }}</td>
                <td class="text-right" style="color: var(--text-muted);">{{ number_format($b->unique_count) }}</td>
                <td class="text-right">{!! _blockDeltaPill($b->count, $prev) !!}</td>
                <td class="bar-cell" style="width: 18%;">
                    <div class="bar-track">
                        <div class="bar-fill" style="width: {{ $w }}%; --bar-color: linear-gradient(90deg,#8b5cf6,#d946ef); --bar-glow: rgba(139,92,246,0.4);"></div>
                    </div>
                    <div class="text-[10px] mt-1" style="color: var(--text-faint);">{{ $pct }}%</div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif
</div>

<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#14b8a6,#2dd4bf); --sc-glow: rgba(20,184,166,0.35); --sc-color: #5eead4; --sc-border: rgba(20,184,166,0.3);">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-eye"></i></div> Block Engagement (Visibility)</div>
        <span class="section-pill"><i class="fas fa-clock"></i> Time visible on screen</span>
    </div>
    @if($blockEngagement->isEmpty())
        <p class="text-sm text-center py-8" style="color: var(--text-faint);">No view data yet. Visit the public Link in Bio page to start collecting engagement data.</p>
    @else

    {{-- Engagement summary widgets --}}
    @php
        $sumImp     = $blockEngagement->sum('impressions');
        $sumViewers = $blockEngagement->sum('unique_viewers');
        $sumTimeMs  = $blockEngagement->sum('total_ms');
        $sumClicks  = 0; foreach ($blockEngagement as $be) { $sumClicks += ($blockClickMap[$be->block_id] ?? 0); }
        $overallCtr = $sumImp > 0 ? round(($sumClicks / $sumImp) * 100, 1) : 0;
        $topByTime  = $blockEngagement->take(10);
    @endphp
    <div class="grid grid-cols-2 md:grid-cols-4 gap-5 mb-6">
        <div class="rounded-xl p-3.5" style="background: linear-gradient(135deg, rgba(20,184,166,0.15), rgba(20,184,166,0.04)); border: 1px solid rgba(20,184,166,0.3);">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Total Impressions</span>
                <i class="fas fa-eye text-teal-400 text-xs"></i>
            </div>
            <div class="text-xl font-extrabold" style="background: linear-gradient(135deg,#14b8a6,#2dd4bf); -webkit-background-clip:text; background-clip:text; color:transparent;">{{ number_format($sumImp) }}</div>
        </div>
        <div class="rounded-xl p-3.5" style="background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(99,102,241,0.04)); border: 1px solid rgba(99,102,241,0.3);">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Unique Viewers</span>
                <i class="fas fa-users text-indigo-400 text-xs"></i>
            </div>
            <div class="text-xl font-extrabold" style="background: linear-gradient(135deg,#6366f1,#818cf8); -webkit-background-clip:text; background-clip:text; color:transparent;">{{ number_format($sumViewers) }}</div>
        </div>
        <div class="rounded-xl p-3.5" style="background: linear-gradient(135deg, rgba(245,158,11,0.15), rgba(245,158,11,0.04)); border: 1px solid rgba(245,158,11,0.3);">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Total Visible Time</span>
                <i class="fas fa-hourglass-half text-amber-400 text-xs"></i>
            </div>
            <div class="text-xl font-extrabold" style="background: linear-gradient(135deg,#f59e0b,#fbbf24); -webkit-background-clip:text; background-clip:text; color:transparent;">{{ _fmtMs($sumTimeMs) }}</div>
        </div>
        <div class="rounded-xl p-3.5" style="background: linear-gradient(135deg, rgba(16,185,129,0.15), rgba(16,185,129,0.04)); border: 1px solid rgba(16,185,129,0.3);">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[10px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Overall CTR</span>
                <i class="fas fa-bullseye text-emerald-400 text-xs"></i>
            </div>
            <div class="text-xl font-extrabold" style="background: linear-gradient(135deg,#10b981,#34d399); -webkit-background-clip:text; background-clip:text; color:transparent;">{{ $overallCtr }}%</div>
            <div class="text-[10px] mt-0.5" style="color: var(--text-faint);">{{ number_format($sumClicks) }} clicks</div>
        </div>
    </div>

    {{-- Engagement chart: top 10 blocks by total visible time --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-6 mb-6">
        <div class="lg:col-span-3 rounded-xl p-4" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-xs font-bold" style="color: var(--text-primary);"><i class="fas fa-chart-bar text-teal-400 mr-1.5"></i> Top 10 Blocks by Visible Time</h4>
                <span class="text-[10px]" style="color: var(--text-faint);">{{ $blockEngagement->count() }} tracked</span>
            </div>
            <div style="height: 320px;"><canvas id="blockEngagementChart"></canvas></div>
        </div>
        <div class="lg:col-span-2 rounded-xl p-4" style="background: var(--bg-glass-input); border: 1px solid var(--border-glass);">
            <div class="flex items-center justify-between mb-3">
                <h4 class="text-xs font-bold" style="color: var(--text-primary);"><i class="fas fa-funnel-dollar text-teal-400 mr-1.5"></i> Engagement Funnel</h4>
            </div>
            @php
                $maxFunnel = max($sumImp, 1);
                $funnel = [
                    ['label'=>'Impressions','value'=>$sumImp,'color'=>'linear-gradient(90deg,#14b8a6,#2dd4bf)','glow'=>'rgba(20,184,166,0.4)','icon'=>'fa-eye'],
                    ['label'=>'Unique Viewers','value'=>$sumViewers,'color'=>'linear-gradient(90deg,#6366f1,#818cf8)','glow'=>'rgba(99,102,241,0.4)','icon'=>'fa-users'],
                    ['label'=>'Clicks','value'=>$sumClicks,'color'=>'linear-gradient(90deg,#10b981,#34d399)','glow'=>'rgba(16,185,129,0.4)','icon'=>'fa-mouse-pointer'],
                ];
            @endphp
            <div class="space-y-3">
                @foreach($funnel as $f)
                    @php $w = $maxFunnel > 0 ? round(($f['value'] / $maxFunnel) * 100, 1) : 0; @endphp
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs font-semibold" style="color: var(--text-primary);"><i class="fas {{ $f['icon'] }} mr-1.5" style="color:var(--text-faint);"></i>{{ $f['label'] }}</span>
                            <span class="text-sm font-bold" style="color: var(--text-primary);">{{ number_format($f['value']) }}</span>
                        </div>
                        <div class="bar-track" style="height: 12px;">
                            <div class="bar-fill" style="width: {{ $w }}%; --bar-color: {{ $f['color'] }}; --bar-glow: {{ $f['glow'] }};"></div>
                        </div>
                    </div>
                @endforeach
            </div>
            @if($sumImp > 0)
            <div class="mt-4 pt-3" style="border-top: 1px dashed var(--border-glass);">
                <div class="grid grid-cols-2 gap-2 text-center">
                    <div class="rounded-lg p-2" style="background: var(--bg-glass);">
                        <div class="text-[9px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">View → Click</div>
                        <div class="text-base font-bold" style="color: var(--text-primary);">{{ $sumViewers > 0 ? round(($sumClicks / $sumViewers) * 100, 1) : 0 }}%</div>
                    </div>
                    <div class="rounded-lg p-2" style="background: var(--bg-glass);">
                        <div class="text-[9px] uppercase tracking-wider font-bold" style="color: var(--text-faint);">Reach Ratio</div>
                        <div class="text-base font-bold" style="color: var(--text-primary);">{{ $sumImp > 0 ? round(($sumViewers / $sumImp) * 100, 1) : 0 }}%</div>
                    </div>
                </div>
            </div>
            @endif
        </div>
    </div>

    <div class="overflow-x-auto -mx-2 px-2">
        <table class="fancy-table">
            <thead><tr>
                <th>#</th><th>Block</th>
                <th class="text-right">Impressions</th>
                <th class="text-right">Viewers</th>
                <th class="text-right">Total Time</th>
                <th class="text-right">Avg / View</th>
                <th class="text-right">Clicks</th>
                <th class="text-right">CTR</th>
                <th>Engagement</th>
            </tr></thead>
            <tbody>
            @php $maxTime = $blockEngagement->max('total_ms') ?: 1; @endphp
            @foreach($blockEngagement as $i => $b)
            @php
                $bi     = $blockIdentity($b->block_id, $b->block_type);
                $info   = $bi['info'];
                $clicks = $blockClickMap[$b->block_id] ?? 0;
                $ctr    = $b->impressions > 0 ? round(($clicks / $b->impressions) * 100, 1) : 0;
                $tw     = round(($b->total_ms / $maxTime) * 100, 1);
            @endphp
            <tr>
                <td style="width:38px;"><span class="rank-badge {{ $i<3 ? 'rank-'.($i+1) : '' }}">{{ $i+1 }}</span></td>
                <td style="color: var(--text-primary); min-width: 220px;">
                    <div class="flex items-center gap-2.5">
                        @if(!empty($bi['thumb']))
                            <span class="w-9 h-9 rounded-lg overflow-hidden flex-shrink-0" style="border: 1px solid var(--border-glass);"><img src="{{ $bi['thumb'] }}" class="w-full h-full object-cover" onerror="this.parentNode.innerHTML='<span class=\'inline-flex items-center justify-center w-full h-full\' style=\'background: linear-gradient(135deg,#14b8a6,#2dd4bf); color:#fff;\'><i class=\'fas {{ $info['icon'] }}\'></i></span>'"></span>
                        @else
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg flex-shrink-0" style="background: linear-gradient(135deg,#14b8a6,#2dd4bf); color:#fff;"><i class="fas {{ $info['icon'] }} text-xs"></i></span>
                        @endif
                        <div class="min-w-0">
                            <div class="text-sm font-semibold truncate" style="color: var(--text-primary); max-width: 240px;" title="{{ $bi['title'] }}">{{ $bi['title'] }}</div>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="text-[9.5px] px-1.5 py-0.5 rounded-md font-bold" style="background: rgba(20,184,166,0.15); color:#5eead4;">{{ $info['label'] }}</span>
                                <span class="text-[9.5px]" style="color: var(--text-faint);">#{{ $b->block_id }}</span>
                            </div>
                        </div>
                    </div>
                </td>
                <td class="text-right" style="color: var(--text-muted);">{{ number_format($b->impressions) }}</td>
                <td class="text-right" style="color: var(--text-muted);">{{ number_format($b->unique_viewers) }}</td>
                <td class="text-right font-bold" style="color: var(--text-primary);">{{ _fmtMs($b->total_ms) }}</td>
                <td class="text-right" style="color: var(--text-muted);">{{ number_format(($b->avg_ms ?? 0)/1000, 1) }}s</td>
                <td class="text-right" style="color: var(--text-muted);">{{ $clicks }}</td>
                <td class="text-right">
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold" style="background: {{ $ctr >= 10 ? 'linear-gradient(135deg,#10b981,#34d399)' : ($ctr >= 3 ? 'linear-gradient(135deg,#f59e0b,#fbbf24)' : 'rgba(148,163,184,0.15)') }}; color: {{ $ctr >= 3 ? '#fff' : 'var(--text-muted)' }};">{{ $ctr }}%</span>
                </td>
                <td class="bar-cell" style="width: 18%; min-width: 120px;">
                    <div class="bar-track">
                        <div class="bar-fill" style="width: {{ $tw }}%; --bar-color: linear-gradient(90deg,#14b8a6,#2dd4bf); --bar-glow: rgba(20,184,166,0.4);"></div>
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @push('scripts')
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('blockEngagementChart');
        if (!el || !window.Chart) return;
        var isLight = document.documentElement.classList.contains('light-mode');
        var tickColor = isLight ? 'rgba(15,23,42,0.75)' : 'rgba(255,255,255,0.7)';
        var gridColor = isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.05)';
        var labels = @json($topByTime->map(function($b) use ($blockIdentity) { $bi = $blockIdentity($b->block_id, $b->block_type); return \Illuminate\Support\Str::limit($bi['title'], 32); })->values());
        var times  = @json($topByTime->map(fn($b) => round($b->total_ms / 1000, 1))->values());
        var imps   = @json($topByTime->map(fn($b) => (int)$b->impressions)->values());
        var ctx = el.getContext('2d');
        var grad = ctx.createLinearGradient(0,0,el.width,0);
        grad.addColorStop(0,'#14b8a6'); grad.addColorStop(1,'#2dd4bf');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Visible time (s)',
                    data: times,
                    backgroundColor: grad,
                    borderRadius: 8,
                    borderSkipped: false,
                    maxBarThickness: 22
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isLight ? 'rgba(255,255,255,0.98)' : 'rgba(20,15,40,0.95)',
                        titleColor: tickColor, bodyColor: tickColor,
                        borderColor: 'rgba(20,184,166,0.4)', borderWidth: 1,
                        padding: 10, cornerRadius: 10,
                        callbacks: {
                            label: function(c) {
                                var i = c.dataIndex;
                                var s = c.parsed.x;
                                var imp = imps[i] || 0;
                                return [' Time: ' + s + 's', ' Impressions: ' + imp];
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 10 } }, beginAtZero: true, border: { display: false } },
                    y: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10.5, weight: '600' }, autoSkip: false }, border: { display: false } }
                }
            }
        });
    });
    </script>
    @endpush
    @endif
</div>
@endif

{{-- ===================== REFERRERS / UTM ===================== --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-7">
    <div class="section-card" style="--sc-accent: linear-gradient(90deg,#10b981,#34d399); --sc-glow: rgba(16,185,129,0.35); --sc-color: #6ee7b7; --sc-border: rgba(16,185,129,0.3);">
        <div class="section-head"><div class="section-title"><div class="section-icon"><i class="fas fa-link"></i></div> Top Referrers</div></div>
        @if($topReferrers->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No referrer data</p>
        @else
        <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
            @php $maxR = $topReferrers->max('count') ?: 1; @endphp
            @foreach($topReferrers as $i => $ref)
            @php
                $host = parse_url($ref->referrer, PHP_URL_HOST) ?: $ref->referrer;
                $w = round(($ref->count / $maxR) * 100, 1);
            @endphp
            <div class="progress-row flex items-center gap-3" style="--pr-width: {{ $w }}%; --pr-color: linear-gradient(90deg, rgba(16,185,129,0.20), rgba(52,211,153,0.06));">
                <span class="rank-badge {{ $i<3 ? 'rank-'.($i+1) : '' }} m-0">{{ $i+1 }}</span>
                <div class="progress-favicon">
                    @if($host && $host !== $ref->referrer)
                        <img src="https://www.google.com/s2/favicons?sz=32&domain={{ urlencode($host) }}" alt="" onerror="this.style.display='none'; this.parentNode.innerHTML='<i class=\'fas fa-globe\'></i>'">
                    @else
                        <i class="fas fa-globe"></i>
                    @endif
                </div>
                <span class="truncate flex-1 text-sm font-medium" style="color: var(--text-primary);">{{ $host }}</span>
                <span class="font-bold text-sm" style="color: var(--text-primary);">{{ number_format($ref->count) }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
    <div class="section-card" style="--sc-accent: linear-gradient(90deg,#f59e0b,#fbbf24); --sc-glow: rgba(245,158,11,0.35); --sc-color: #fcd34d; --sc-border: rgba(245,158,11,0.3);">
        <div class="section-head"><div class="section-title"><div class="section-icon"><i class="fas fa-bullseye"></i></div> UTM Campaigns</div></div>
        @if($utmStats->isEmpty())<p class="text-sm text-center py-8" style="color: var(--text-faint);">No UTM data</p>
        @else
        <div class="space-y-2 max-h-80 overflow-y-auto pr-1">
            @php $maxU = $utmStats->max('count') ?: 1; @endphp
            @foreach($utmStats as $i => $u)
            @php
                $p = $u->utm_params; if (is_string($p)) $p = json_decode($p, true) ?: [];
                $w = round(($u->count / $maxU) * 100, 1);
            @endphp
            <div class="progress-row flex items-center gap-3" style="--pr-width: {{ $w }}%; --pr-color: linear-gradient(90deg, rgba(245,158,11,0.22), rgba(251,191,36,0.06));">
                <span class="rank-badge {{ $i<3 ? 'rank-'.($i+1) : '' }} m-0">{{ $i+1 }}</span>
                <div class="flex-1 truncate text-xs">
                    <span class="font-bold" style="color: var(--text-primary);">{{ $p['utm_source'] ?? '—' }}</span>
                    <span style="color: var(--text-faint);"> · {{ $p['utm_medium'] ?? '—' }}</span>
                    <span style="color: var(--text-faint);"> · {{ $p['utm_campaign'] ?? '—' }}</span>
                </div>
                <span class="font-bold text-sm" style="color: var(--text-primary);">{{ number_format($u->count) }}</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- ===================== RECENT CLICKS ===================== --}}
<div class="section-card mb-7" style="--sc-accent: linear-gradient(90deg,#8b5cf6,#a78bfa); --sc-glow: rgba(124,58,237,0.35); --sc-color: #ddd6fe; --sc-border: rgba(124,58,237,0.3);">
    <div class="section-head">
        <div class="section-title"><div class="section-icon"><i class="fas fa-list"></i></div> Recent Clicks</div>
        <a href="{{ route('user.links.clicks.export', $link) }}?{{ http_build_query($qs) }}" class="table-action"><i class="fas fa-file-csv"></i> Export full log</a>
    </div>
    <div id="recent-clicks-container" data-endpoint="{{ route('user.links.clicks.partial', $link) }}?{{ http_build_query($qs) }}">
        @include('user.links.partials.recent-clicks-table')
    </div>
</div>

{{-- ===================== LINK DETAILS ===================== --}}
<div class="section-card" style="--sc-accent: linear-gradient(90deg,#64748b,#94a3b8); --sc-glow: rgba(100,116,139,0.35); --sc-color: #cbd5e1; --sc-border: rgba(100,116,139,0.3);">
    <div class="section-head"><div class="section-title"><div class="section-icon"><i class="fas fa-info-circle"></i></div> Link Details</div></div>
    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        @if($link->long_url)<div class="p-3 rounded-xl" style="background: var(--bg-glass-input);"><dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Destination</dt><dd class="truncate" style="color: var(--text-primary);">{{ $link->long_url }}</dd></div>@endif
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);"><dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Alias</dt><dd style="color: var(--text-primary);">{{ $link->alias }}</dd></div>
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);"><dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Type</dt><dd class="capitalize" style="color: var(--text-primary);">{{ $link->type }}</dd></div>
        <div class="p-3 rounded-xl" style="background: var(--bg-glass-input);"><dt class="text-[10px] uppercase tracking-wider font-bold mb-1" style="color: var(--text-faint);">Created</dt><dd style="color: var(--text-primary);">{{ $link->created_at?->format('M d, Y · H:i') }}</dd></div>
    </dl>
</div>

@push('scripts')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.min.css">
<script src="https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js"></script>
<script>
(function () {
    @php
        $heatmapQs = http_build_query(request()->only(['period','from','to']));
        $heatmapHref = route('user.links.heatmap', $link) . ($heatmapQs ? ('?' . $heatmapQs) : '');
        $periodKey  = request('period', $period ?? '30d');
        $periodMap  = ['today'=>'Today','7d'=>'Last 7 days','30d'=>'Last 30 days','90d'=>'Last 90 days','year'=>'This year','all'=>'All time'];
        $periodLabelStr = $periodMap[$periodKey] ?? ucfirst($periodKey);
        if (request('from') || request('to')) {
            $periodLabelStr = trim((request('from') ?: '…') . ' → ' . (request('to') ?: '…'));
        }
        $linkSlugStr = $link->alias ?? $link->slug ?? ($link->short_url ?? '');
    @endphp
    const heatmapUrl = @json($heatmapHref);
    const heatmapPeriod = @json($periodLabelStr);
    const heatmapLinkSlug = @json($linkSlugStr);
    const heatmapLiveUrl = @json(route('user.links.heatmap.live', $link));
    const heatmapLiveStreamUrl = @json(route('user.links.heatmap.live.stream', $link));
    // Use Esri "Gray Canvas" raster tiles as the basemap — these show neutral
    // terrain shading with NO political boundaries drawn, so no country's
    // disputed-border lines (e.g. Kashmir) are imposed on the map. The heatmap
    // overlay is the only thing that conveys geography of clicks.
    const ESRI_ATTRIB = '&copy; <a href="https://www.esri.com/" target="_blank" rel="noopener">Esri</a>';
    const buildBasemapStyle = (tileUrl, bgColor) => ({
        version: 8,
        sources: {
            basemap: {
                type: 'raster',
                tiles: [tileUrl],
                tileSize: 256,
                attribution: ESRI_ATTRIB,
                maxzoom: 16,
            }
        },
        layers: [
            { id: 'bg',      type: 'background', paint: { 'background-color': bgColor } },
            { id: 'basemap', type: 'raster',     source: 'basemap' }
        ],
    });
    const STYLES = {
        dark:  buildBasemapStyle(
            'https://services.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Dark_Gray_Base/MapServer/tile/{z}/{y}/{x}',
            '#0a0a0f'
        ),
        light: buildBasemapStyle(
            'https://services.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
            '#f5f5f5'
        ),
    };

    let map = null;
    let currentStyle = document.documentElement.classList.contains('light-mode') ? 'light' : 'dark';
    let cachedGeoJson = null;

    function setActiveStyleBtn() {
        document.querySelectorAll('.heatmap-style-btn').forEach(btn => {
            const active = btn.dataset.style === currentStyle;
            btn.style.background = active ? 'rgba(249,115,22,0.18)' : 'transparent';
            btn.style.color = active ? 'var(--text-primary)' : 'var(--text-secondary)';
        });
    }

    function addHeatmapLayer(geojson, maxWeight) {
        if (!map.getSource('clicks')) {
            map.addSource('clicks', { type: 'geojson', data: geojson });
        } else {
            map.getSource('clicks').setData(geojson);
        }
        if (map.getLayer('clicks-heat')) map.removeLayer('clicks-heat');
        if (map.getLayer('clicks-points')) map.removeLayer('clicks-points');

        const max = Math.max(1, maxWeight || 1);
        map.addLayer({
            id: 'clicks-heat',
            type: 'heatmap',
            source: 'clicks',
            maxzoom: 11,
            paint: {
                'heatmap-weight': ['interpolate', ['linear'], ['get', 'weight'], 0, 0, max, 1],
                'heatmap-intensity': ['interpolate', ['linear'], ['zoom'], 0, 1, 11, 3],
                'heatmap-color': [
                    'interpolate', ['linear'], ['heatmap-density'],
                    0,    'rgba(0,0,0,0)',
                    0.15, 'rgba(56,189,248,0.55)',
                    0.35, 'rgba(124,58,237,0.75)',
                    0.55, 'rgba(236,72,153,0.85)',
                    0.75, 'rgba(249,115,22,0.92)',
                    1,    'rgba(239,68,68,1)'
                ],
                'heatmap-radius': ['interpolate', ['linear'], ['zoom'], 0, 8, 4, 18, 9, 36],
                'heatmap-opacity': ['interpolate', ['linear'], ['zoom'], 7, 0.95, 11, 0.55],
            },
        });
        map.addLayer({
            id: 'clicks-points',
            type: 'circle',
            source: 'clicks',
            minzoom: 6,
            paint: {
                'circle-radius': ['interpolate', ['linear'], ['get', 'weight'], 1, 4, max, 18],
                'circle-color': '#f97316',
                'circle-stroke-color': 'rgba(255,255,255,0.85)',
                'circle-stroke-width': 1,
                'circle-opacity': ['interpolate', ['linear'], ['zoom'], 6, 0.2, 9, 0.95],
            },
        });

        const popup = new maplibregl.Popup({ closeButton: false, closeOnClick: false, offset: 10 });
        // Build a popup for a given feature (used by both hover & tap).
        function showPopupFor(f) {
            const p = f.properties;
            // DOM text nodes only — XSS-safe with DB-sourced strings.
            const wrap = document.createElement('div');
            wrap.style.cssText = 'font:12px/1.4 Inter,sans-serif;color:#0f172a;min-width:140px;';
            const title = document.createElement('div');
            title.style.cssText = 'font-weight:700;display:flex;align-items:center;gap:6px;';
            const cc = (p.country || '').toUpperCase();
            if (cc.length === 2 && /^[A-Z]{2}$/.test(cc)) {
                const flag = String.fromCodePoint(0x1F1E6 + cc.charCodeAt(0) - 65,
                                                  0x1F1E6 + cc.charCodeAt(1) - 65);
                const flagSpan = document.createElement('span');
                flagSpan.textContent = flag;
                flagSpan.style.fontSize = '15px';
                title.appendChild(flagSpan);
            }
            const labelSpan = document.createElement('span');
            const cityName = p.city || 'Unknown city';
            labelSpan.textContent = cc ? (cityName + ', ' + cc) : cityName;
            title.appendChild(labelSpan);

            const counts = document.createElement('div');
            counts.style.cssText = 'margin-top:2px;color:#475569;';
            const w = parseInt(p.weight, 10) || 0;
            counts.textContent = w + ' click' + (w === 1 ? '' : 's');
            wrap.appendChild(title);
            wrap.appendChild(counts);
            popup.setLngLat(f.geometry.coordinates).setDOMContent(wrap).addTo(map);
        }
        // Desktop: hover.
        map.on('mouseenter', 'clicks-points', (e) => {
            map.getCanvas().style.cursor = 'pointer';
            showPopupFor(e.features[0]);
        });
        map.on('mouseleave', 'clicks-points', () => {
            map.getCanvas().style.cursor = '';
            popup.remove();
        });
        // Mobile / touch: tap on a marker to open its popup.
        map.on('click', 'clicks-points', (e) => {
            if (e.features && e.features[0]) showPopupFor(e.features[0]);
        });
    }

    function buildMap() {
        const el = document.getElementById('heatmap');
        if (!el || !window.maplibregl) return;
        map = new maplibregl.Map({
            container: el,
            style: STYLES[currentStyle],
            center: [10, 25],
            zoom: 1.4,
            attributionControl: { compact: true },
            preserveDrawingBuffer: true,
        });
        map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

        map.on('load', () => {
            fetch(heatmapUrl, { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(data => {
                    cachedGeoJson = data;
                    document.getElementById('heatmap-loading').style.display = 'none';

                    const meta = data.meta || {};
                    const metaEl = document.getElementById('heatmap-meta');
                    if ((data.features || []).length === 0) {
                        // Keep the map visible so Live mode can still drop pins on it,
                        // but show an inline hint above the map when there's no aggregate data.
                        document.getElementById('heatmap-empty').style.display = 'block';
                        ['heatmap-download', 'heatmap-share'].forEach(id => {
                            const b = document.getElementById(id);
                            if (b) {
                                b.disabled = true;
                                b.style.opacity = '0.45';
                                b.style.cursor = 'not-allowed';
                                b.title = 'No map data to export yet';
                            }
                        });
                        return;
                    }
                    metaEl.style.display = 'inline-flex';
                    metaEl.textContent = `${meta.total_clicks || 0} clicks · ${meta.point_count || 0} locations`;
                    addHeatmapLayer(data, meta.max_weight || 1);
                })
                .catch(err => {
                    console.error('Heatmap load failed', err);
                    document.getElementById('heatmap-loading').innerHTML =
                        '<span style="color:#ef4444;"><i class="fas fa-exclamation-triangle mr-2"></i>Failed to load map data</span>';
                });
        });
    }

    function switchStyle(s, fromUser) {
        if (!map || s === currentStyle) return;
        if (fromUser) userPickedStyle = true;
        currentStyle = s;
        setActiveStyleBtn();
        map.setStyle(STYLES[s]);
        map.once('styledata', () => {
            if (cachedGeoJson) addHeatmapLayer(cachedGeoJson, (cachedGeoJson.meta || {}).max_weight || 1);
        });
    }

    // Follow the app's theme toggle unless the user manually picked a basemap.
    let userPickedStyle = false;
    function syncMapToAppTheme() {
        if (userPickedStyle || !map) return;
        const wantLight = document.documentElement.classList.contains('light-mode');
        const target = wantLight ? 'light' : 'dark';
        if (target !== currentStyle) switchStyle(target, /*fromUser*/ false);
    }

    // ===== LIVE PINS =====
    // Each click in the last few minutes pulses on the map for ~30s, then fades out.
    // We dedupe by click id so the same click doesn't get re-added on every poll.
    const LIVE_POLL_MS = 10000;
    const LIVE_PIN_TTL_MS = 30000;
    const liveMarkers = new Map(); // id -> { marker, removeAt, timeoutId }
    // Persistent record of click ids we've ever rendered in this session, so a
    // click that already pulsed for ~30s is NOT re-animated when the backend
    // keeps returning it inside its 5-minute window. Entries are evicted after
    // the backend window so memory stays bounded.
    const seenClickIds = new Map(); // id -> evictAt (ms epoch)
    const SEEN_TTL_MS = 10 * 60 * 1000; // > server window (5 min) with margin
    let liveEnabled = false;
    let livePollTimer = null;
    let liveInFlight = false;

    function pruneSeen() {
        const now = Date.now();
        for (const [id, evictAt] of seenClickIds) {
            if (evictAt <= now) seenClickIds.delete(id);
        }
    }

    function setLiveButtonState() {
        const btn = document.getElementById('heatmap-live-toggle');
        if (!btn) return;
        btn.setAttribute('aria-pressed', liveEnabled ? 'true' : 'false');
        const dot = btn.querySelector('i');
        if (liveEnabled) {
            btn.style.background = 'rgba(34,197,94,0.18)';
            btn.style.borderColor = 'rgba(34,197,94,0.45)';
            btn.style.color = '#86efac';
            if (dot) { dot.style.color = '#22c55e'; dot.style.opacity = '1'; }
        } else {
            btn.style.background = 'var(--bg-glass-input)';
            btn.style.borderColor = '';
            btn.style.color = 'var(--text-secondary)';
            if (dot) { dot.style.color = ''; dot.style.opacity = '0.5'; }
        }
    }

    function clearLiveMarker(id) {
        const entry = liveMarkers.get(id);
        if (!entry) return;
        if (entry.timeoutId) clearTimeout(entry.timeoutId);
        const el = entry.marker.getElement();
        el.classList.add('fading');
        setTimeout(() => { try { entry.marker.remove(); } catch (e) {} }, 1500);
        liveMarkers.delete(id);
    }

    function clearAllLiveMarkers() {
        Array.from(liveMarkers.keys()).forEach(clearLiveMarker);
    }

    function addLivePin(point) {
        if (!map) return;
        // Skip if currently rendered OR previously rendered in this session.
        if (liveMarkers.has(point.id) || seenClickIds.has(point.id)) return;
        seenClickIds.set(point.id, Date.now() + SEEN_TTL_MS);
        const el = document.createElement('div');
        el.className = 'live-pin';
        const cc = (point.country_code || '').toUpperCase();
        const flag = (cc.length === 2 && /^[A-Z]{2}$/.test(cc))
            ? String.fromCodePoint(0x1F1E6 + cc.charCodeAt(0) - 65, 0x1F1E6 + cc.charCodeAt(1) - 65) + ' '
            : '';
        el.title = (flag + (point.city || 'Unknown city') + (cc ? ', ' + cc : '')).trim();
        const marker = new maplibregl.Marker({ element: el, anchor: 'center' })
            .setLngLat([point.lng, point.lat])
            .addTo(map);
        // Compute remaining TTL: clicks already a few seconds old should fade sooner.
        const ageMs = point.ts ? Math.max(0, Date.now() - point.ts * 1000) : 0;
        const ttl = Math.max(4000, LIVE_PIN_TTL_MS - ageMs);
        const timeoutId = setTimeout(() => clearLiveMarker(point.id), ttl);
        liveMarkers.set(point.id, { marker, removeAt: Date.now() + ttl, timeoutId });
    }

    function updateLiveMeta(uniqueVisitors) {
        const pill = document.getElementById('heatmap-live-meta');
        const text = document.getElementById('heatmap-live-meta-text');
        if (!pill || !text) return;
        if (!liveEnabled) {
            pill.style.display = 'none';
            return;
        }
        pill.style.display = 'inline-flex';
        const n = uniqueVisitors || 0;
        text.textContent = n + ' live visitor' + (n === 1 ? '' : 's') + ' right now';
    }

    // Cursor of the latest click id we've ever rendered. Lets the server
    // resume the stream without replaying clicks on reconnect, and lets the
    // polling fallback skip already-seen rows.
    let liveLastId = 0;
    let liveEventSource = null;
    let liveUsingSse = false;

    function handleLivePayload(data) {
        if (!liveEnabled || !data) return;
        const points = data.points || [];
        points.forEach(p => {
            addLivePin(p);
            if (typeof p.id === 'number' && p.id > liveLastId) liveLastId = p.id;
        });
        const meta = data.meta || {};
        if (typeof meta.last_id === 'number' && meta.last_id > liveLastId) {
            liveLastId = meta.last_id;
        }
        updateLiveMeta(meta.unique_visitors || 0);
    }

    function pollLive() {
        if (!liveEnabled || liveInFlight || !map) return;
        liveInFlight = true;
        pruneSeen();
        fetch(heatmapLiveUrl, { headers: { 'Accept': 'application/json' } })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(data => handleLivePayload(data))
            .catch(err => { console.warn('Live heatmap poll failed', err); })
            .finally(() => { liveInFlight = false; });
    }

    function startPollingFallback() {
        liveUsingSse = false;
        pollLive();
        if (livePollTimer) clearInterval(livePollTimer);
        livePollTimer = setInterval(pollLive, LIVE_POLL_MS);
    }

    // Track repeated SSE errors so we can give up and fall back to polling
    // even after the stream opened successfully at least once (e.g. a proxy
    // starts dropping long-held connections mid-session).
    const SSE_FAILURE_WINDOW_MS = 60000;
    const SSE_FAILURE_THRESHOLD = 3;
    let sseErrorTimestamps = [];

    function startSse() {
        if (typeof window.EventSource === 'undefined') {
            startPollingFallback();
            return;
        }
        let opened = false;
        try {
            const url = heatmapLiveStreamUrl
                + (heatmapLiveStreamUrl.indexOf('?') === -1 ? '?' : '&')
                + 'lastId=' + encodeURIComponent(liveLastId);
            liveEventSource = new EventSource(url, { withCredentials: true });
        } catch (e) {
            startPollingFallback();
            return;
        }
        const onData = (ev) => {
            try { handleLivePayload(JSON.parse(ev.data)); } catch (e) {}
        };
        liveEventSource.addEventListener('snapshot', (ev) => {
            opened = true;
            sseErrorTimestamps = []; // healthy stream resets the failure budget
            onData(ev);
        });
        liveEventSource.addEventListener('clicks', onData);
        liveEventSource.addEventListener('bye', (ev) => {
            // Server closed the stream cleanly after its max duration; the
            // browser will auto-reconnect, but force a fresh URL with the
            // updated cursor so no window.lastId is missed.
            try { liveEventSource && liveEventSource.close(); } catch (e) {}
            liveEventSource = null;
            if (liveEnabled) startSse();
        });
        liveEventSource.onopen = () => { opened = true; liveUsingSse = true; };
        liveEventSource.onerror = () => {
            // If we never got a successful open on this attempt, the endpoint
            // is probably unreachable (proxy strips SSE, auth failed, etc).
            // Fall back to polling instead of letting EventSource spin on
            // reconnects.
            if (!opened) {
                try { liveEventSource && liveEventSource.close(); } catch (e) {}
                liveEventSource = null;
                if (liveEnabled) startPollingFallback();
                return;
            }
            // Previously opened but failing now: track the rate of errors and
            // downgrade to polling if it crosses the threshold in our window.
            const now = Date.now();
            sseErrorTimestamps.push(now);
            sseErrorTimestamps = sseErrorTimestamps.filter(t => now - t <= SSE_FAILURE_WINDOW_MS);
            if (sseErrorTimestamps.length >= SSE_FAILURE_THRESHOLD) {
                try { liveEventSource && liveEventSource.close(); } catch (e) {}
                liveEventSource = null;
                sseErrorTimestamps = [];
                if (liveEnabled) startPollingFallback();
            }
            // Otherwise: let EventSource keep attempting its built-in reconnect.
        };
    }

    function startLive() {
        liveEnabled = true;
        setLiveButtonState();
        updateLiveMeta(0);
        startSse();
    }

    function stopLive() {
        liveEnabled = false;
        setLiveButtonState();
        if (livePollTimer) { clearInterval(livePollTimer); livePollTimer = null; }
        if (liveEventSource) { try { liveEventSource.close(); } catch (e) {} liveEventSource = null; }
        liveUsingSse = false;
        clearAllLiveMarkers();
        updateLiveMeta(0);
    }

    function toggleLive() {
        if (liveEnabled) stopLive(); else startLive();
    }
    function buildHeatmapCanvas() {
        if (!map) return null;
        // Force a synchronous render so the WebGL drawing buffer is fresh.
        try { map.triggerRepaint(); } catch (e) {}
        const src = map.getCanvas();
        const w = src.width, h = src.height;
        if (!w || !h) return null;

        const out = document.createElement('canvas');
        out.width = w; out.height = h;
        const ctx = out.getContext('2d');

        // Background fill matching current basemap so transparent areas read clean.
        ctx.fillStyle = currentStyle === 'light' ? '#f8fafc' : '#0b1220';
        ctx.fillRect(0, 0, w, h);
        ctx.drawImage(src, 0, 0, w, h);

        // Branded overlay (bottom-left) with 1INME wordmark + period label.
        const dpr = Math.max(1, w / src.clientWidth || 1);
        const padX = 22 * dpr, padY = 18 * dpr;
        const boxX = 18 * dpr, boxY = h - 18 * dpr;
        const titleSize = 26 * dpr, subSize = 14 * dpr;

        ctx.font = `800 ${titleSize}px Inter, system-ui, -apple-system, sans-serif`;
        const title = '1INME';
        const sub = (heatmapPeriod || '').toString();
        const slug = (heatmapLinkSlug || '').toString();
        const tWidth = ctx.measureText(title).width;
        ctx.font = `600 ${subSize}px Inter, system-ui, -apple-system, sans-serif`;
        const sWidth = Math.max(ctx.measureText(sub).width, slug ? ctx.measureText('/' + slug).width : 0);
        const boxW = Math.max(tWidth, sWidth) + padX * 2;
        const boxH = titleSize + subSize + (slug ? subSize + 6 * dpr : 0) + padY * 2 + 8 * dpr;

        // Rounded translucent panel.
        const r = 14 * dpr;
        const x = boxX, y = boxY - boxH;
        ctx.fillStyle = 'rgba(8, 12, 24, 0.72)';
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + boxW - r, y);
        ctx.quadraticCurveTo(x + boxW, y, x + boxW, y + r);
        ctx.lineTo(x + boxW, y + boxH - r);
        ctx.quadraticCurveTo(x + boxW, y + boxH, x + boxW - r, y + boxH);
        ctx.lineTo(x + r, y + boxH);
        ctx.quadraticCurveTo(x, y + boxH, x, y + boxH - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();
        ctx.fill();

        // Wordmark gradient.
        const grad = ctx.createLinearGradient(x + padX, 0, x + padX + tWidth, 0);
        grad.addColorStop(0, '#f97316');
        grad.addColorStop(1, '#ef4444');
        ctx.fillStyle = grad;
        ctx.font = `800 ${titleSize}px Inter, system-ui, -apple-system, sans-serif`;
        ctx.textBaseline = 'top';
        ctx.fillText(title, x + padX, y + padY);

        // Period label.
        ctx.fillStyle = 'rgba(248, 250, 252, 0.92)';
        ctx.font = `600 ${subSize}px Inter, system-ui, -apple-system, sans-serif`;
        ctx.fillText(sub, x + padX, y + padY + titleSize + 6 * dpr);

        if (slug) {
            ctx.fillStyle = 'rgba(148, 163, 184, 0.95)';
            ctx.font = `500 ${subSize}px Inter, system-ui, -apple-system, sans-serif`;
            ctx.fillText('/' + slug, x + padX, y + padY + titleSize + subSize + 12 * dpr);
        }

        return { canvas: out, slug: slug };
    }

    function heatmapFileName(slug) {
        const safeSlug = (slug || 'link').replace(/[^a-z0-9_-]+/gi, '-').toLowerCase();
        return `1inme-heatmap-${safeSlug}-${new Date().toISOString().slice(0,10)}.png`;
    }

    function canvasToBlob(canvas) {
        return new Promise((resolve, reject) => {
            try {
                if (canvas.toBlob) {
                    canvas.toBlob(b => b ? resolve(b) : reject(new Error('Empty blob')), 'image/png');
                } else {
                    const data = canvas.toDataURL('image/png');
                    fetch(data).then(r => r.blob()).then(resolve, reject);
                }
            } catch (e) { reject(e); }
        });
    }

    function downloadHeatmapPng() {
        const built = buildHeatmapCanvas();
        if (!built) return;
        let url;
        try { url = built.canvas.toDataURL('image/png'); }
        catch (e) { console.error('Heatmap export failed', e); alert('Could not export the map image.'); return; }
        const a = document.createElement('a');
        a.href = url;
        a.download = heatmapFileName(built.slug);
        document.body.appendChild(a);
        a.click();
        a.remove();
    }

    function showShareToast(msg, isError) {
        const toast = document.getElementById('heatmap-share-toast');
        if (!toast) return;
        toast.textContent = msg;
        toast.style.display = 'inline-flex';
        if (isError) {
            toast.style.background = 'rgba(239,68,68,0.15)';
            toast.style.borderColor = 'rgba(239,68,68,0.4)';
            toast.style.color = '#fca5a5';
        } else {
            toast.style.background = 'rgba(34,197,94,0.15)';
            toast.style.borderColor = 'rgba(34,197,94,0.4)';
            toast.style.color = '#86efac';
        }
        clearTimeout(showShareToast._t);
        showShareToast._t = setTimeout(() => { toast.style.display = 'none'; }, 3500);
    }

    async function copyImageBlobToClipboard(blob) {
        if (!navigator.clipboard || typeof ClipboardItem === 'undefined') {
            throw new Error('Clipboard image copy not supported');
        }
        await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
    }

    function shareCaption(slug) {
        const base = 'Check out where my link traffic is coming from on 1INME';
        return slug ? `${base} (/${slug})` : base;
    }

    async function shareHeatmap(action) {
        const built = buildHeatmapCanvas();
        if (!built) return;
        let blob;
        try { blob = await canvasToBlob(built.canvas); }
        catch (e) { console.error('Heatmap blob failed', e); showShareToast('Could not capture the map image.', true); return; }

        const fileName = heatmapFileName(built.slug);
        const caption = shareCaption(built.slug);
        const shareUrl = window.location.href;

        // If user invoked the primary Share button (action === 'native') and the
        // browser supports sharing files (Web Share API on mobile mostly), use it.
        if (action === 'native') {
            let nativeOk = false;
            if (navigator.canShare) {
                try {
                    const file = new File([blob], fileName, { type: 'image/png' });
                    if (navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file], title: '1INME Heatmap', text: caption });
                        return;
                    }
                } catch (e) {
                    if (e && e.name === 'AbortError') return;
                    console.warn('Native share failed, falling back', e);
                    nativeOk = false;
                }
            }
            // Native share unavailable or threw — fall back to the menu so the
            // user can pick copy / X / LinkedIn instead of silently doing nothing.
            const menu = document.getElementById('heatmap-share-menu');
            if (menu) menu.style.display = 'block';
            showShareToast('Sharing isn\u2019t available here — pick a destination below.', true);
            return;
        }

        // Desktop fallback: copy image to clipboard, then for X/LinkedIn open intent.
        let copied = false;
        try { await copyImageBlobToClipboard(blob); copied = true; }
        catch (e) { console.warn('Clipboard image copy failed', e); }

        if (action === 'copy') {
            showShareToast(copied ? 'Image copied to clipboard.' : 'Clipboard not available — try Download instead.', !copied);
            return;
        }
        if (action === 'x') {
            const intent = 'https://twitter.com/intent/tweet?text=' + encodeURIComponent(caption) + '&url=' + encodeURIComponent(shareUrl);
            window.open(intent, '_blank', 'noopener,noreferrer');
            showShareToast(copied ? 'Image copied — paste into your post.' : 'Opened X. Use Download to attach the image.', !copied);
            return;
        }
        if (action === 'linkedin') {
            const intent = 'https://www.linkedin.com/sharing/share-offsite/?url=' + encodeURIComponent(shareUrl);
            window.open(intent, '_blank', 'noopener,noreferrer');
            showShareToast(copied ? 'Image copied — paste into your post.' : 'Opened LinkedIn. Use Download to attach the image.', !copied);
            return;
        }
    }

    function deviceSupportsFileShare() {
        if (!navigator.canShare || typeof File === 'undefined') return false;
        try { return navigator.canShare({ files: [new File([new Blob()], 'x.png', { type: 'image/png' })] }); }
        catch (e) { return false; }
    }

    function setupShareButton() {
        const btn = document.getElementById('heatmap-share');
        const menu = document.getElementById('heatmap-share-menu');
        if (!btn || !menu) return;
        const closeMenu = () => { menu.style.display = 'none'; };
        btn.addEventListener('click', (ev) => {
            ev.stopPropagation();
            if (deviceSupportsFileShare()) {
                shareHeatmap('native');
                return;
            }
            menu.style.display = (menu.style.display === 'none' || !menu.style.display) ? 'block' : 'none';
        });
        menu.querySelectorAll('[data-share-action]').forEach(item => {
            item.addEventListener('click', (ev) => {
                ev.stopPropagation();
                closeMenu();
                shareHeatmap(item.getAttribute('data-share-action'));
            });
        });
        document.addEventListener('click', (ev) => {
            if (!menu.contains(ev.target) && ev.target !== btn) closeMenu();
        });
        document.addEventListener('keydown', (ev) => { if (ev.key === 'Escape') closeMenu(); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        currentStyle = document.documentElement.classList.contains('light-mode') ? 'light' : 'dark';
        setActiveStyleBtn();
        document.querySelectorAll('.heatmap-style-btn').forEach(btn => {
            btn.addEventListener('click', () => switchStyle(btn.dataset.style, true));
        });
        const liveBtn = document.getElementById('heatmap-live-toggle');
        if (liveBtn) liveBtn.addEventListener('click', toggleLive);
        // Pause polling when the tab is hidden to be a good citizen.
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (livePollTimer) { clearInterval(livePollTimer); livePollTimer = null; }
            } else if (liveEnabled && !livePollTimer) {
                pollLive();
                livePollTimer = setInterval(pollLive, LIVE_POLL_MS);
            }
        });
        const dlBtn = document.getElementById('heatmap-download');
        if (dlBtn) dlBtn.addEventListener('click', downloadHeatmapPng);
        setupShareButton();
        buildMap();
        new MutationObserver(syncMapToAppTheme)
            .observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
    });
})();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isLight = document.documentElement.classList.contains('light-mode');
    const gridColor = isLight ? 'rgba(0,0,0,0.06)' : 'rgba(255,255,255,0.05)';
    const tickColor = isLight ? 'rgba(15,23,42,0.75)' : 'rgba(255,255,255,0.7)';
    if (window.Chart) {
        Chart.defaults.color = tickColor;
        Chart.defaults.borderColor = gridColor;
        Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";
    }
    const palette = ['#7c3aed','#10b981','#3b82f6','#f59e0b','#ec4899','#06b6d4','#8b5cf6','#ef4444','#14b8a6','#eab308'];

    @if(!$clicksOverTime->isEmpty())
    (function(){
        const ctx = document.getElementById('clicksChart').getContext('2d');
        const g1 = ctx.createLinearGradient(0,0,0,300);
        g1.addColorStop(0, 'rgba(124,58,237,0.45)'); g1.addColorStop(1, 'rgba(124,58,237,0.02)');
        const g2 = ctx.createLinearGradient(0,0,0,300);
        g2.addColorStop(0, 'rgba(16,185,129,0.30)'); g2.addColorStop(1, 'rgba(16,185,129,0.02)');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @json($clicksOverTime->pluck('bucket')),
                datasets: [
                    { label: 'Total Clicks', data: @json($clicksOverTime->pluck('count')), borderColor: '#8b5cf6', backgroundColor: g1, tension: 0.4, fill: true, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: '#8b5cf6', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 },
                    { label: 'Unique IPs', data: @json($clicksOverTime->pluck('unique_count')), borderColor: '#34d399', backgroundColor: g2, tension: 0.4, fill: true, borderWidth: 2.5, pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: '#34d399', pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { labels: { color: tickColor, usePointStyle: true, padding: 16, font: { size: 11, weight: '600' } } },
                    tooltip: { backgroundColor: isLight ? 'rgba(255,255,255,0.98)' : 'rgba(20,15,40,0.95)', titleColor: tickColor, bodyColor: tickColor, borderColor: 'rgba(124,58,237,0.4)', borderWidth: 1, padding: 12, cornerRadius: 10, displayColors: true, boxPadding: 4 }
                },
                scales: {
                    x: { grid: { color: gridColor, drawTicks: false }, ticks: { color: tickColor, font: { size: 10 } }, border: { display: false } },
                    y: { grid: { color: gridColor, drawTicks: false }, ticks: { color: tickColor, font: { size: 10 } }, beginAtZero: true, border: { display: false } }
                }
            }
        });
    })();
    @endif

    function doughnut(id, labels, data) {
        const el = document.getElementById(id); if (!el) return;
        new Chart(el, {
            type: 'doughnut',
            data: { labels, datasets: [{ data, backgroundColor: palette, borderWidth: 0, hoverOffset: 8, spacing: 2 }] },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '65%',
                plugins: {
                    legend: { position: 'right', labels: { color: tickColor, font: { size: 11, weight: '500' }, usePointStyle: true, padding: 10, boxWidth: 8 } },
                    tooltip: { backgroundColor: isLight ? 'rgba(255,255,255,0.98)' : 'rgba(20,15,40,0.95)', titleColor: tickColor, bodyColor: tickColor, borderColor: 'rgba(124,58,237,0.4)', borderWidth: 1, padding: 10, cornerRadius: 10 }
                }
            }
        });
    }

    @if(!$browserStats->isEmpty())doughnut('browserChart', @json($browserStats->pluck('browser')), @json($browserStats->pluck('count')));@endif
    @if(!$osStats->isEmpty())doughnut('osChart', @json($osStats->pluck('os')), @json($osStats->pluck('count')));@endif
    @if(!$deviceStats->isEmpty())doughnut('deviceChart', @json($deviceStats->pluck('device_type')), @json($deviceStats->pluck('count')));@endif
    @if(!$sourceStats->isEmpty())doughnut('sourceChart', @json($sourceStats->pluck('source')->map(fn($s) => ['mobile_app' => 'Mobile app', 'web' => 'Web', 'unknown' => 'Unknown'][$s] ?? ucfirst(str_replace('_', ' ', $s)))), @json($sourceStats->pluck('count')));@endif
});
</script>

<script>
(function(){
    var container = document.getElementById('recent-clicks-container');
    if(!container) return;
    var endpoint = container.dataset.endpoint;
    container.addEventListener('click', function(e){
        var btn = e.target.closest('.rc-page-btn');
        if(!btn) return;
        e.preventDefault();
        var page = btn.getAttribute('data-rc-page');
        if(!page) return;
        var sep = endpoint.indexOf('?') === -1 ? '?' : '&';
        var url = endpoint + sep + 'page=' + encodeURIComponent(page);
        container.style.opacity = '0.5';
        container.style.pointerEvents = 'none';
        fetch(url, {headers: {'X-Requested-With':'XMLHttpRequest', 'Accept':'text/html'}, credentials:'same-origin'})
            .then(function(r){ return r.text(); })
            .then(function(html){
                container.innerHTML = html;
                container.style.opacity = '';
                container.style.pointerEvents = '';
                container.scrollIntoView({behavior:'smooth', block:'start'});
            })
            .catch(function(){
                container.style.opacity = '';
                container.style.pointerEvents = '';
                window.location.href = btn.getAttribute('href');
            });
    });
})();
</script>
@endpush
@endsection
