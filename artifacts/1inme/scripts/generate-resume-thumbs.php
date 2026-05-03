<?php
// One-off script: regenerate /public/img/resume-templates/<id>.svg from the
// registry metadata. Each thumbnail visualises the template's layout,
// header style, divider, accent placement and title treatment so the
// picker conveys real differences at a glance.
//
// Usage:  php scripts/generate-resume-thumbs.php
//
// (Lives under scripts/ so it isn't autoloaded; safe to run anytime.)

require __DIR__ . '/../vendor/autoload.php';

use App\Modules\User\Services\ResumeTemplateRegistry;

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$outDir = __DIR__ . '/../public/img/resume-templates';
if (!is_dir($outDir)) mkdir($outDir, 0775, true);

// Per-category palette so neighbouring templates feel cohesive but
// distinct between groups.
$palettes = [
    'professional' => ['bg' => '#ffffff', 'ink' => '#0f172a', 'accent' => '#1e3a8a', 'soft' => '#dbeafe', 'muted' => '#94a3b8'],
    'modern'       => ['bg' => '#ffffff', 'ink' => '#0f172a', 'accent' => '#0ea5e9', 'soft' => '#e0f2fe', 'muted' => '#94a3b8'],
    'creative'     => ['bg' => '#fffaf5', 'ink' => '#7c2d12', 'accent' => '#ea580c', 'soft' => '#fed7aa', 'muted' => '#a8a29e'],
    'academic'     => ['bg' => '#fdfdfb', 'ink' => '#1f2937', 'accent' => '#7f1d1d', 'soft' => '#fee2e2', 'muted' => '#94a3b8'],
    'technical'    => ['bg' => '#ffffff', 'ink' => '#111827', 'accent' => '#10b981', 'soft' => '#d1fae5', 'muted' => '#94a3b8'],
    'executive'    => ['bg' => '#ffffff', 'ink' => '#111827', 'accent' => '#1f2937', 'soft' => '#e5e7eb', 'muted' => '#9ca3af'],
    'portfolio'    => ['bg' => '#fdf4ff', 'ink' => '#581c87', 'accent' => '#a21caf', 'soft' => '#f5d0fe', 'muted' => '#a78bfa'],
    'minimal'      => ['bg' => '#ffffff', 'ink' => '#111827', 'accent' => '#374151', 'soft' => '#f3f4f6', 'muted' => '#9ca3af'],
];

function esc(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_XML1); }

function fontFamily(string $headings): string
{
    return match ($headings) {
        'serif'   => 'Georgia, serif',
        'display' => '"Plus Jakarta Sans", Inter, sans-serif',
        'mono'    => '"SFMono-Regular", "Menlo", monospace',
        default   => 'Inter, sans-serif',
    };
}

function rect(int $x, int $y, int $w, int $h, string $fill, string $rx = '0'): string
{
    return "<rect x=\"$x\" y=\"$y\" width=\"$w\" height=\"$h\" rx=\"$rx\" fill=\"$fill\"/>";
}
function line(int $x1, int $y1, int $x2, int $y2, string $stroke, string $sw = '1'): string
{
    return "<line x1=\"$x1\" y1=\"$y1\" x2=\"$x2\" y2=\"$y2\" stroke=\"$stroke\" stroke-width=\"$sw\"/>";
}
function txt(int $x, int $y, string $s, string $font, int $size, string $weight, string $fill, string $extra = ''): string
{
    return "<text x=\"$x\" y=\"$y\" font-family=\"$font\" font-size=\"$size\" font-weight=\"$weight\" fill=\"$fill\" $extra>".esc($s)."</text>";
}

function renderHeader(array $tpl, array $pal, string $font, int $left, int $right): string
{
    $hs   = $tpl['style']['header_style'];
    $name = 'Jane Doe';
    $tag  = 'Senior ' . ucfirst($tpl['category']) . ' lead';
    $w    = $right - $left;
    $svg  = '';
    switch ($hs) {
        case 'banner':
            $svg .= rect(0, 0, 160, 40, $pal['accent']);
            $svg .= txt($left, 20, $name, $font, 12, '700', $pal['bg']);
            $svg .= txt($left, 30, $tag, $font, 5, '500', $pal['soft']);
            return $svg;
        case 'block':
            $svg .= rect(0, 0, 160, 32, $pal['soft']);
            $svg .= txt($left, 18, $name, $font, 12, '800', $pal['ink']);
            $svg .= txt($left, 27, $tag, $font, 5, '500', $pal['ink']);
            return $svg;
        case 'split':
            $svg .= rect(0, 0, 60, 44, $pal['accent']);
            $svg .= txt(30, 24, 'JD', $font, 14, '800', $pal['bg'], 'text-anchor="middle"');
            $svg .= txt(66, 22, $name, $font, 11, '700', $pal['ink']);
            $svg .= txt(66, 32, $tag, $font, 5, '500', $pal['muted']);
            return $svg;
        case 'monogram':
            $svg .= rect(0, 0, 160, 40, $pal['bg']);
            $svg .= "<circle cx=\"22\" cy=\"22\" r=\"12\" fill=\"{$pal['accent']}\"/>";
            $svg .= txt(22, 26, 'JD', $font, 9, '700', $pal['bg'], 'text-anchor="middle"');
            $svg .= txt(40, 22, $name, $font, 11, '700', $pal['ink']);
            $svg .= txt(40, 32, $tag, $font, 5, '500', $pal['muted']);
            return $svg;
        case 'centered':
            $svg .= txt(80, 22, $name, $font, 12, '700', $pal['ink'], 'text-anchor="middle"');
            $svg .= txt(80, 32, $tag, $font, 5, '500', $pal['muted'], 'text-anchor="middle"');
            $svg .= line(40, 38, 120, 38, $pal['accent'], '1.5');
            return $svg;
        case 'minimal':
            $svg .= txt($left, 22, $name, $font, 11, '700', $pal['ink']);
            $svg .= txt($left, 32, $tag, $font, 5, '500', $pal['muted']);
            return $svg;
        case 'underline':
            $svg .= txt($left, 22, $name, $font, 12, '800', $pal['ink']);
            $svg .= line($left, 26, $right, 26, $pal['ink'], '1.5');
            $svg .= txt($left, 36, $tag, $font, 5, '500', $pal['muted']);
            return $svg;
        case 'photo-left':
            $svg .= "<circle cx=\"22\" cy=\"22\" r=\"12\" fill=\"{$pal['soft']}\" stroke=\"{$pal['accent']}\" stroke-width=\"1\"/>";
            $svg .= txt(40, 22, $name, $font, 11, '700', $pal['ink']);
            $svg .= txt(40, 32, $tag, $font, 5, '500', $pal['muted']);
            return $svg;
        case 'sidebar-photo':
            // header just shows name; the sidebar block is rendered by layout.
            $svg .= txt($left, 22, $name, $font, 11, '700', $pal['ink']);
            $svg .= txt($left, 32, $tag, $font, 5, '500', $pal['muted']);
            return $svg;
        default: // 'rule'
            $svg .= txt($left, 22, $name, $font, 12, '800', $pal['ink']);
            $svg .= txt($left, 32, $tag, $font, 5, '500', $pal['muted']);
            $svg .= line($left, 38, $right, 38, $pal['ink'], '1.2');
            return $svg;
    }
}

function divider(int $x1, int $y, int $x2, array $pal, string $kind): string
{
    switch ($kind) {
        case 'none':       return '';
        case 'dot':
            $svg = '';
            for ($x = $x1; $x <= $x2 - 2; $x += 4) $svg .= "<circle cx=\"$x\" cy=\"$y\" r=\"0.7\" fill=\"{$pal['muted']}\"/>";
            return $svg;
        case 'accent-bar': return rect($x1, $y - 1, 26, 2, $pal['accent'], '1');
        case 'double':     return line($x1, $y - 1, $x2, $y - 1, $pal['ink'], '0.7') . line($x1, $y + 2, $x2, $y + 2, $pal['ink'], '0.7');
        default:           return line($x1, $y, $x2, $y, $pal['muted'], '0.6');
    }
}

function sectionTitle(int $x, int $y, string $label, array $pal, array $style, string $font): string
{
    $ts = $style['title_style'];
    $svg = '';
    switch ($ts) {
        case 'pill':
            $w = strlen($label) * 3 + 12;
            $svg .= rect($x, $y - 6, $w, 9, $pal['soft'], '4');
            $svg .= txt($x + 6, $y, $label, $font, 6, '700', $pal['accent']);
            break;
        case 'bracket':
            $svg .= txt($x, $y, '[ ' . $label . ' ]', $font, 6, '700', $pal['accent']);
            break;
        case 'numbered':
            static $n = 0; $n++;
            $svg .= txt($x, $y, sprintf('%02d  %s', $n, strtoupper($label)), $font, 6, '700', $pal['ink']);
            break;
        case 'bar':
            $svg .= rect($x, $y - 6, 3, 8, $pal['accent']);
            $svg .= txt($x + 7, $y, strtoupper($label), $font, 6, '700', $pal['ink']);
            break;
        case 'underline':
            $svg .= txt($x, $y, $label, $font, 6, '700', $pal['ink']);
            $svg .= line($x, $y + 2, $x + strlen($label) * 3 + 4, $y + 2, $pal['accent'], '1');
            break;
        case 'capitalized':
            $svg .= txt($x, $y, $label, $font, 6, '700', $pal['ink']);
            break;
        case 'plain':
            $svg .= txt($x, $y, $label, $font, 6, '600', $pal['ink']);
            break;
        default: // uppercase
            $svg .= txt($x, $y, strtoupper($label), $font, 6, '700', $pal['ink']);
    }
    return $svg;
}

function bodyLines(int $x, int $y, int $w, array $pal, int $rows = 3): string
{
    $svg = '';
    for ($i = 0; $i < $rows; $i++) {
        $rw = $w - ($i % 2 ? 18 : 0);
        $svg .= rect($x, $y + $i * 5, $rw, 2, $i === 0 ? $pal['muted'] : $pal['soft'], '1');
    }
    return $svg;
}

function pills(int $x, int $y, array $pal, int $count = 4): string
{
    $svg = ''; $cx = $x;
    for ($i = 0; $i < $count; $i++) {
        $w = 14 + ($i % 3) * 6;
        $svg .= rect($cx, $y, $w, 5, $pal['soft'], '2');
        $cx += $w + 3;
    }
    return $svg;
}

foreach (ResumeTemplateRegistry::all() as $tpl) {
    $cat = $tpl['category'] ?? 'professional';
    $pal = $palettes[$cat] ?? $palettes['professional'];
    $st  = $tpl['style'];
    $font = fontFamily($st['headings']);
    $layout = $st['layout'];

    // Layout geometry
    $hasLeftRail  = ($st['accent'] ?? 'none') === 'left-rail';
    $hasRightRail = ($st['accent'] ?? 'none') === 'right-rail';
    $hasTopBar    = ($st['accent'] ?? 'none') === 'top-bar';
    $hasCorner    = ($st['accent'] ?? 'none') === 'corner';

    $left = 14 + ($hasLeftRail ? 4 : 0);
    $right = 146 - ($hasRightRail ? 4 : 0);

    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 200" width="160" height="200">';
    $svg .= rect(0, 0, 160, 200, $pal['bg']);

    if ($hasLeftRail)  $svg .= rect(0, 0, 4, 200, $pal['accent']);
    if ($hasRightRail) $svg .= rect(156, 0, 4, 200, $pal['accent']);
    if ($hasTopBar)    $svg .= rect(0, 0, 160, 3, $pal['accent']);
    if ($hasCorner)    $svg .= "<polygon points=\"160,0 130,0 160,30\" fill=\"{$pal['accent']}\"/>";

    // Header band
    $svg .= renderHeader($tpl, $pal, $font, $left, $right);
    $headerBottom = in_array($st['header_style'], ['banner', 'block', 'split'], true) ? 48 : 44;

    // Layout body
    if ($layout === 'sidebar' || $layout === 'sidebar-right' || $st['header_style'] === 'sidebar-photo' || $st['header_style'] === 'photo-left') {
        $sideW = 50;
        $sideX = $layout === 'sidebar-right' ? (160 - $sideW - 6) : 6;
        $mainX = $layout === 'sidebar-right' ? 12 : ($sideX + $sideW + 8);
        $mainW = 160 - $mainX - 14;
        // Sidebar
        $svg .= rect($sideX, $headerBottom + 4, $sideW, 200 - $headerBottom - 10, $pal['soft'], '4');
        if ($st['header_style'] === 'sidebar-photo') {
            $svg .= "<circle cx=\"" . ($sideX + $sideW / 2) . "\" cy=\"" . ($headerBottom + 22) . "\" r=\"12\" fill=\"{$pal['accent']}\"/>";
        }
        $sy = $headerBottom + ($st['header_style'] === 'sidebar-photo' ? 42 : 14);
        $svg .= sectionTitle($sideX + 4, $sy, 'Skills', $pal, $st, $font);
        $svg .= pills($sideX + 4, $sy + 6, $pal, 3);
        $svg .= sectionTitle($sideX + 4, $sy + 30, 'Contact', $pal, $st, $font);
        $svg .= bodyLines($sideX + 4, $sy + 36, $sideW - 8, $pal, 3);
        // Main
        $my = $headerBottom + 10;
        $svg .= sectionTitle($mainX, $my, 'Experience', $pal, $st, $font);
        $svg .= divider($mainX, $my + 4, $mainX + $mainW, $pal, $st['divider']);
        $svg .= bodyLines($mainX, $my + 8, $mainW, $pal, 4);
        $svg .= sectionTitle($mainX, $my + 40, 'Education', $pal, $st, $font);
        $svg .= divider($mainX, $my + 44, $mainX + $mainW, $pal, $st['divider']);
        $svg .= bodyLines($mainX, $my + 48, $mainW, $pal, 3);
    } elseif ($layout === 'two-col') {
        $colW = ($right - $left - 8) / 2;
        for ($c = 0; $c < 2; $c++) {
            $cx = (int) ($left + $c * ($colW + 8));
            $cy = $headerBottom + 8;
            $svg .= sectionTitle($cx, $cy, $c === 0 ? 'Experience' : 'Education', $pal, $st, $font);
            $svg .= divider($cx, $cy + 4, (int) ($cx + $colW), $pal, $st['divider']);
            $svg .= bodyLines($cx, $cy + 8, (int) $colW, $pal, 5);
            $cy2 = $cy + 50;
            $svg .= sectionTitle($cx, $cy2, $c === 0 ? 'Skills' : 'Awards', $pal, $st, $font);
            $svg .= divider($cx, $cy2 + 4, (int) ($cx + $colW), $pal, $st['divider']);
            if ($c === 0) {
                $svg .= pills($cx, $cy2 + 8, $pal, 3);
            } else {
                $svg .= bodyLines($cx, $cy2 + 8, (int) $colW, $pal, 3);
            }
        }
    } elseif ($layout === 'portfolio' || $layout === 'portfolio-grid') {
        $py = $headerBottom + 6;
        $svg .= sectionTitle($left, $py, 'Featured projects', $pal, $st, $font);
        $svg .= divider($left, $py + 4, $right, $pal, $st['divider']);
        // 3-up grid
        $gx = $left;
        $cellW = (int) (($right - $left - 8) / 3);
        for ($i = 0; $i < 3; $i++) {
            $svg .= rect((int) ($gx + $i * ($cellW + 4)), $py + 8, $cellW, $layout === 'portfolio-grid' ? 36 : 24, $pal['soft'], '3');
        }
        $py2 = $py + ($layout === 'portfolio-grid' ? 50 : 38);
        $svg .= sectionTitle($left, $py2, 'Experience', $pal, $st, $font);
        $svg .= divider($left, $py2 + 4, $right, $pal, $st['divider']);
        $svg .= bodyLines($left, $py2 + 8, $right - $left, $pal, 4);
        $svg .= sectionTitle($left, $py2 + 38, 'Skills', $pal, $st, $font);
        $svg .= pills($left, $py2 + 44, $pal, 4);
    } elseif ($layout === 'timeline') {
        $tx = $left + 6;
        $svg .= line($tx, $headerBottom + 8, $tx, 188, $pal['accent'], '1');
        $items = [['Senior Engineer', 'Acme'], ['Engineer', 'Globex'], ['Intern', 'Initech']];
        $y = $headerBottom + 12;
        foreach ($items as $row) {
            $svg .= "<circle cx=\"$tx\" cy=\"$y\" r=\"2\" fill=\"{$pal['accent']}\"/>";
            $svg .= txt($tx + 8, $y + 2, $row[0], $font, 6, '700', $pal['ink']);
            $svg .= txt($tx + 8, $y + 10, $row[1], $font, 5, '500', $pal['muted']);
            $y += 28;
        }
    } else {
        // single / compact
        $rowGap = $layout === 'compact' ? 24 : 30;
        $sections = ['Experience', 'Education', 'Skills'];
        $y = $headerBottom + 8;
        foreach ($sections as $s) {
            $svg .= sectionTitle($left, $y, $s, $pal, $st, $font);
            $svg .= divider($left, $y + 4, $right, $pal, $st['divider']);
            if ($s === 'Skills') $svg .= pills($left, $y + 8, $pal, 5);
            else                 $svg .= bodyLines($left, $y + 8, $right - $left, $pal, $layout === 'compact' ? 3 : 4);
            $y += $rowGap;
        }
    }

    $svg .= '</svg>' . "\n";

    file_put_contents($outDir . '/' . $tpl['id'] . '.svg', $svg);
}

echo "Wrote " . count(ResumeTemplateRegistry::all()) . " thumbnails to {$outDir}\n";
