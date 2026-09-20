<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\BgCatalogEntry;
use App\Modules\User\Support\BgPresetCatalog;
use App\Modules\User\Support\CatalogOverrides;
use App\Modules\User\Support\GradientCatalog;
use App\Modules\User\Support\MeshGradientCatalog;
use App\Modules\User\Support\PatternCatalog;
use App\Modules\User\Support\TilesBgCatalog;
use App\Modules\User\Support\TornStyleCatalog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin CRUD over the seven compiled-in background catalogs.
 *
 * /admin/bg-templates already managed the 525 DB-backed templates. This
 * manages the other 485 looks, which lived in PHP constants and therefore
 * needed a deploy to change -- so "add a tiles palette" or "that gradient
 * is ugly, hide it" was an engineering ticket.
 *
 * The constants stay. Nothing is migrated out of them; a row here adds a
 * look, replaces one, or hides one, and DELETING the row restores the
 * shipped default. That is what makes editing a shipped look safe: the
 * original is still in the code, one click away.
 *
 * Hiding removes a look from the picker and NOT from the renderer. Pages
 * that already chose it keep rendering it, which is the difference
 * between retiring a look and breaking every page that used it.
 */
class BgCatalogEntryController extends Controller
{
    /** Where a look comes from, per kind. */
    private const KIND_ROUTE = '[a-z_]+';

    public function index(Request $request, string $kind = 'preset')
    {
        $kind = $this->kind($kind);
        $q    = trim((string) $request->get('q', ''));

        $rows    = CatalogOverrides::for($kind);
        $shipped = $this->shippedKeys($kind);
        $items   = [];

        foreach ($this->merged($kind) as $key => $entry) {
            if ($q !== '' && ! str_contains(mb_strtolower($key.' '.$entry['label']), mb_strtolower($q))) {
                continue;
            }
            $row = $rows[$key] ?? null;
            $items[] = [
                'key'       => $key,
                'label'     => $entry['label'],
                'origin'    => $row === null
                    ? 'shipped'
                    : (isset($shipped[$key]) ? 'override' : 'custom'),
                'is_active' => $row === null ? true : $row['is_active'],
                'swatch'    => $this->swatchCss($kind, $key, $entry),
            ];
        }

        return view('admin.bg-catalog.index', [
            'kind'      => $kind,
            'kinds'     => BgCatalogEntry::KINDS,
            'counts'    => $this->counts(),
            'items'     => $items,
            'q'         => $q,
            'customCount' => count($rows),
        ]);
    }

    public function create(string $kind)
    {
        $kind = $this->kind($kind);

        return view('admin.bg-catalog.edit', [
            'kind'      => $kind,
            'kinds'     => BgCatalogEntry::KINDS,
            'entryKey'  => '',
            'label'     => '',
            'form'      => $this->blankForm($kind),
            'origin'    => 'new',
            'isActive'  => true,
            'sortOrder' => 0,
            'styles'    => TornStyleCatalog::styles(),
        ]);
    }

    /**
     * Edit any look -- shipped or not.
     *
     * A shipped look has no row yet, so the form is filled from the
     * constant. Saving creates the row that overrides it; deleting that row
     * puts the constant back.
     */
    public function edit(string $kind, string $key)
    {
        $kind  = $this->kind($kind);
        $entry = $this->merged($kind)[$key] ?? abort(404);
        $row   = CatalogOverrides::for($kind)[$key] ?? null;

        return view('admin.bg-catalog.edit', [
            'kind'      => $kind,
            'kinds'     => BgCatalogEntry::KINDS,
            'entryKey'  => $key,
            'label'     => $entry['label'],
            'form'      => $this->formFrom($kind, $entry),
            'origin'    => $row === null ? 'shipped' : (isset($this->shippedKeys($kind)[$key]) ? 'override' : 'custom'),
            'isActive'  => $row === null ? true : $row['is_active'],
            'sortOrder' => $row === null ? 0 : $row['sort_order'],
            'styles'    => TornStyleCatalog::styles(),
        ]);
    }

    public function store(Request $request, string $kind)
    {
        $kind = $this->kind($kind);

        $key = (string) $request->input('entry_key', '');
        $request->validate([
            'entry_key' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9_\-]*$/'],
        ], [], ['entry_key' => 'key']);

        if (isset($this->merged($kind)[$key])) {
            throw ValidationException::withMessages([
                'entry_key' => "A {$kind} look with the key \"{$key}\" already exists — edit that one instead.",
            ]);
        }

        $this->save($request, $kind, $key);

        return redirect()
            ->route('admin.bg-catalog.edit', [$kind, $key])
            ->with('success', "Created \"{$request->input('label')}\".");
    }

    public function update(Request $request, string $kind, string $key)
    {
        $kind = $this->kind($kind);
        abort_unless(isset($this->merged($kind)[$key]), 404);

        $this->save($request, $kind, $key);

        return redirect()
            ->route('admin.bg-catalog.edit', [$kind, $key])
            ->with('success', 'Saved. It is live in the picker now.');
    }

    /**
     * Hide or show a look.
     *
     * A shipped look has no row, so hiding one CREATES a row carrying the
     * shipped payload unchanged -- the look is not edited, only withdrawn
     * from the picker, and showing it again is the same row flipped back.
     */
    public function toggle(string $kind, string $key)
    {
        $kind  = $this->kind($kind);
        $entry = $this->merged($kind)[$key] ?? abort(404);

        $row = BgCatalogEntry::firstOrNew(['kind' => $kind, 'entry_key' => $key]);
        if (! $row->exists) {
            $row->fill([
                'label'      => $entry['label'],
                'payload'    => $this->payloadFrom($kind, $entry),
                'sort_order' => 0,
            ]);
        }
        $row->is_active = ! ($row->exists ? (bool) $row->is_active : true);
        $row->save();

        return back()->with(
            'success',
            $entry['label'].' is now '.($row->is_active ? 'visible in' : 'hidden from').' the picker.'
        );
    }

    /**
     * Delete the row: a custom look disappears, an overridden shipped look
     * goes back to the version in the code.
     */
    public function destroy(string $kind, string $key)
    {
        $kind = $this->kind($kind);
        $row  = BgCatalogEntry::where('kind', $kind)->where('entry_key', $key)->first();
        abort_if($row === null, 404);

        $wasShipped = isset($this->shippedKeys($kind)[$key]);
        $label      = $row->label;
        $row->delete();

        return redirect()
            ->route('admin.bg-catalog.index', $kind)
            ->with('success', $wasShipped
                ? "\"{$label}\" is back to the version that ships with the code."
                : "\"{$label}\" deleted.");
    }

    // ===== Saving =====

    private function save(Request $request, string $kind, string $key): void
    {
        $data = $request->validate([
            'label'      => ['required', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer'],
        ]);

        BgCatalogEntry::updateOrCreate(
            ['kind' => $kind, 'entry_key' => $key],
            [
                'label'      => $data['label'],
                'payload'    => $this->validatePayload($request, $kind),
                'is_active'  => $request->boolean('is_active'),
                'sort_order' => (int) ($data['sort_order'] ?? 0),
            ]
        );
    }

    /**
     * Per-kind payload validation.
     *
     * The list-shaped fields (stops, blobs, tiles, sheets) are entered one
     * per line rather than as a repeater, because that is what they are:
     * lines of data. Each line is parsed and validated here, and a bad line
     * is a validation error naming the line number, not a silently dropped
     * entry.
     */
    private function validatePayload(Request $request, string $kind): array
    {
        return match ($kind) {
            'preset' => [
                'group'    => $this->inList($request, 'group', array_keys(BgPresetCatalog::GROUPS)),
                'css'      => $this->css($request, 'css'),
                'paper'    => $this->hexOrNull($request, 'paper'),
                'backdrop' => $this->css($request, 'backdrop', required: false),
            ],
            'gradient' => [
                'category' => $this->inList($request, 'category', array_keys(GradientCatalog::CATEGORIES)),
                'type'     => $this->inList($request, 'type', ['linear', 'radial', 'conic']),
                'angle'    => $this->intBetween($request, 'angle', 0, 360),
                'stops'    => $this->stops($request),
            ],
            'mesh' => [
                'base'  => $this->hex($request, 'base'),
                'blobs' => $this->blobs($request),
            ],
            'pattern' => [
                'css'    => $this->css($request, 'css'),
                'colors' => $this->hexList($request, 'colors'),
            ],
            'tiles' => [
                'colors' => $this->hexList($request, 'colors'),
                'tiles'  => $this->tiles($request),
            ],
            'torn' => [
                'style'    => $this->inList($request, 'style', array_keys(TornStyleCatalog::styles())),
                'paper'    => $this->hex($request, 'paper'),
                'backdrop' => $this->hexList($request, 'backdrop', exactly: 2),
            ],
            'torn_style' => [
                'sheets' => $this->sheets($request),
            ],
            default => [],
        };
    }

    // ===== Field parsers =====

    private function inList(Request $request, string $field, array $allowed): string
    {
        $value = (string) $request->input($field, '');
        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages([$field => "Choose one of: ".implode(', ', $allowed).'.']);
        }

        return $value;
    }

    private function intBetween(Request $request, string $field, int $min, int $max): int
    {
        $value = (int) $request->input($field, $min);
        if ($value < $min || $value > $max) {
            throw ValidationException::withMessages([$field => "Must be between {$min} and {$max}."]);
        }

        return $value;
    }

    /**
     * A CSS value an admin typed, bound for a `<style>` block or an inline
     * style attribute on every page that picks this look.
     *
     * Not one shipped look contains `<`, `{` or `}` -- these are
     * declarations and gradient values, not rules -- so refusing those three
     * characters costs nothing and closes the only ways out of the context
     * the value is written into.
     */
    private function css(Request $request, string $field, bool $required = true): string
    {
        $value = trim((string) $request->input($field, ''));

        if ($value === '') {
            if ($required) {
                throw ValidationException::withMessages([$field => 'This is required.']);
            }

            return '';
        }
        if (mb_strlen($value) > 4000) {
            throw ValidationException::withMessages([$field => 'Too long (4,000 characters max).']);
        }
        if (preg_match('/[<{}]/', $value)) {
            throw ValidationException::withMessages([
                $field => 'CSS here is a declaration or a value, so it cannot contain <, { or }.',
            ]);
        }

        return $value;
    }

    private function hex(Request $request, string $field): string
    {
        $value = trim((string) $request->input($field, ''));
        if (! preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            throw ValidationException::withMessages([$field => 'Must be a 6-digit hex colour, e.g. #1a0533.']);
        }

        return strtolower($value);
    }

    private function hexOrNull(Request $request, string $field): ?string
    {
        return trim((string) $request->input($field, '')) === '' ? null : $this->hex($request, $field);
    }

    /** @return list<string> */
    private function hexList(Request $request, string $field, ?int $exactly = null): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', trim((string) $request->input($field, ''))) ?: [] as $raw) {
            if ($raw === '') {
                continue;
            }
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $raw)) {
                throw ValidationException::withMessages([$field => "\"{$raw}\" is not a 6-digit hex colour."]);
            }
            $out[] = strtolower($raw);
        }
        if ($exactly !== null && count($out) !== $exactly) {
            throw ValidationException::withMessages([$field => "Give exactly {$exactly} colours."]);
        }
        if ($exactly === null && count($out) > 8) {
            throw ValidationException::withMessages([$field => 'Eight colours is the most that is useful here.']);
        }

        return $out;
    }

    /** @return list<string> non-empty trimmed lines */
    private function lines(Request $request, string $field): array
    {
        return array_values(array_filter(array_map(
            'trim',
            preg_split('/\R/', (string) $request->input($field, '')) ?: []
        ), fn ($l) => $l !== ''));
    }

    /** `#hex 0` per line. @return list<array{color: string, pos: int}> */
    private function stops(Request $request): array
    {
        $out = [];
        foreach ($this->lines($request, 'stops') as $n => $line) {
            $parts = preg_split('/\s+/', $line);
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $parts[0] ?? '')) {
                throw ValidationException::withMessages(['stops' => 'Line '.($n + 1).': expected a 6-digit hex colour.']);
            }
            $pos = (int) ($parts[1] ?? 0);
            if ($pos < 0 || $pos > 100) {
                throw ValidationException::withMessages(['stops' => 'Line '.($n + 1).': the position must be 0-100.']);
            }
            $out[] = ['color' => strtolower($parts[0]), 'pos' => $pos];
        }
        if (count($out) < 2) {
            throw ValidationException::withMessages(['stops' => 'A gradient needs at least two stops; one colour is a colour.']);
        }

        return $out;
    }

    /** `#hex x y spread` per line. @return list<array{0:string,1:int,2:int,3:int}> */
    private function blobs(Request $request): array
    {
        $out = [];
        foreach ($this->lines($request, 'blobs') as $n => $line) {
            $p = preg_split('/\s+/', $line);
            if (count($p) !== 4 || ! preg_match('/^#[0-9a-fA-F]{6}$/', $p[0])) {
                throw ValidationException::withMessages([
                    'blobs' => 'Line '.($n + 1).': expected "#hex x y spread", e.g. "#22d3ee 15 20 55".',
                ]);
            }
            foreach ([1, 2, 3] as $i) {
                if (! is_numeric($p[$i]) || (int) $p[$i] < 0 || (int) $p[$i] > 200) {
                    throw ValidationException::withMessages(['blobs' => 'Line '.($n + 1).': x, y and spread are 0-200.']);
                }
            }
            $out[] = [strtolower($p[0]), (int) $p[1], (int) $p[2], (int) $p[3]];
        }
        if ($out === []) {
            throw ValidationException::withMessages(['blobs' => 'A mesh with no blobs is just its base colour.']);
        }

        return $out;
    }

    /** One CSS gradient per line. @return list<string> */
    private function tiles(Request $request): array
    {
        $out = [];
        foreach ($this->lines($request, 'tiles') as $n => $line) {
            if (preg_match('/[<{}]/', $line)) {
                throw ValidationException::withMessages(['tiles' => 'Line '.($n + 1).': <, { and } are not allowed here.']);
            }
            if (mb_strlen($line) > 500) {
                throw ValidationException::withMessages(['tiles' => 'Line '.($n + 1).' is too long.']);
            }
            $out[] = $line;
        }
        if ($out === []) {
            throw ValidationException::withMessages(['tiles' => 'A palette needs at least one tile gradient.']);
        }
        if (count($out) > 24) {
            throw ValidationException::withMessages(['tiles' => 'The grid is 24 tiles, so more than 24 gradients never show.']);
        }

        return $out;
    }

    /** `polygon(...) | shade` per line. @return list<array{clip: string, shade: float}> */
    private function sheets(Request $request): array
    {
        $out = [];
        foreach ($this->lines($request, 'sheets') as $n => $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            $clip  = $parts[0];
            if (preg_match('/[<{}]/', $clip) || ! str_starts_with($clip, 'polygon(')) {
                throw ValidationException::withMessages([
                    'sheets' => 'Line '.($n + 1).': expected a clip path, e.g. "polygon(0% 0%, 100% 0%, …) | 1.0".',
                ]);
            }
            $shade = isset($parts[1]) && $parts[1] !== '' ? (float) $parts[1] : 1.0;
            if ($shade <= 0 || $shade > 1) {
                throw ValidationException::withMessages(['sheets' => 'Line '.($n + 1).': the shade is above 0 and at most 1.']);
            }
            $out[] = ['clip' => $clip, 'shade' => $shade];
        }
        if ($out === []) {
            throw ValidationException::withMessages(['sheets' => 'A tear shape with no sheets clips nothing and renders a blank page.']);
        }

        return $out;
    }

    // ===== Reading the merged library =====

    private function kind(string $kind): string
    {
        abort_unless(isset(BgCatalogEntry::KINDS[$kind]), 404);

        return $kind;
    }

    /**
     * Which keys of a kind ship with the code.
     *
     * Only the KEYS matter here -- this decides whether a row is adding a
     * look or overriding one, and whether deleting it leaves nothing behind
     * or restores a default.
     *
     * @return array<string, true>
     */
    private function shippedKeys(string $kind): array
    {
        $keys = match ($kind) {
            'preset'     => array_keys(BgPresetCatalog::shipped()),
            'gradient'   => array_column(GradientCatalog::shipped(), 'id'),
            'mesh'       => array_keys(MeshGradientCatalog::shipped()),
            'pattern'    => array_keys(PatternCatalog::shipped()),
            'tiles'      => array_keys(TilesBgCatalog::shipped()),
            'torn'       => array_keys(TornStyleCatalog::shippedPresets()),
            'torn_style' => array_keys(TornStyleCatalog::shippedStyles()),
            default      => [],
        };

        return array_fill_keys($keys, true);
    }

    /** Shipped + admin, keyed, in the order the picker sees them. */
    private function merged(string $kind): array
    {
        return match ($kind) {
            'preset'     => BgPresetCatalog::all(),
            // Gradients call their display name `name`; every other catalog
            // calls it `label`. One screen renders all seven, so the odd one
            // out gets an alias here rather than a special case everywhere.
            'gradient'   => array_map(
                fn ($g) => $g + ['label' => $g['name']],
                array_column(GradientCatalog::all(), null, 'id')
            ),
            'mesh'       => MeshGradientCatalog::all(),
            'pattern'    => PatternCatalog::all(),
            'tiles'      => TilesBgCatalog::palettes(),
            'torn'       => TornStyleCatalog::presets(),
            'torn_style' => TornStyleCatalog::allStyles(),
            default      => [],
        };
    }

    /** @return array<string, int> kind => how many looks it holds */
    private function counts(): array
    {
        $out = [];
        foreach (array_keys(BgCatalogEntry::KINDS) as $kind) {
            $out[$kind] = count($this->merged($kind));
        }

        return $out;
    }

    /** How the index paints one swatch. */
    private function swatchCss(string $kind, string $key, array $entry): string
    {
        return match ($kind) {
            'preset'     => $entry['css'] ?? '',
            'gradient'   => 'background: '.GradientCatalog::toCss($entry),
            'mesh'       => (string) MeshGradientCatalog::css($key),
            'pattern'    => (string) PatternCatalog::css($key),
            'tiles'      => 'background: '.($entry['tiles'][0] ?? '#111'),
            'torn'       => 'background: linear-gradient(135deg, '
                                .implode(', ', array_slice((array) $entry['backdrop'], 0, 2) ?: ['#333', '#111']).')',
            'torn_style' => 'background: '.\App\Modules\User\Support\PageBackground::DEFAULT_PAPER,
            default      => '',
        };
    }

    // ===== The form =====

    /** Turn a merged entry into the line-based form fields. */
    private function formFrom(string $kind, array $entry): array
    {
        return match ($kind) {
            'preset' => [
                'group'    => $entry['group'] ?? 'abstract',
                'css'      => $entry['css'] ?? '',
                'paper'    => $entry['paper'] ?? '',
                'backdrop' => $entry['backdrop'] ?? '',
            ],
            'gradient' => [
                'category' => $entry['category'] ?? 'featured',
                'type'     => $entry['type'] ?? 'linear',
                'angle'    => $entry['angle'] ?? 135,
                'stops'    => implode("\n", array_map(fn ($s) => $s['color'].' '.$s['pos'], $entry['stops'] ?? [])),
            ],
            'mesh' => [
                'base'  => $entry['base'] ?? '#0a0612',
                'blobs' => implode("\n", array_map(fn ($b) => implode(' ', $b), $entry['blobs'] ?? [])),
            ],
            'pattern' => [
                'css'    => $entry['css'] ?? '',
                'colors' => implode(', ', $entry['colors'] ?? []),
            ],
            'tiles' => [
                'colors' => implode(', ', $entry['colors'] ?? []),
                'tiles'  => implode("\n", $entry['tiles'] ?? []),
            ],
            'torn' => [
                'style'    => $entry['style'] ?? TornStyleCatalog::DEFAULT,
                'paper'    => $entry['paper'] ?? '#cfe0e6',
                'backdrop' => implode(', ', (array) ($entry['backdrop'] ?? [])),
            ],
            'torn_style' => [
                'sheets' => implode("\n", array_map(
                    fn ($s) => $s['clip'].' | '.$s['shade'],
                    $entry['sheets'] ?? []
                )),
            ],
            default => [],
        };
    }

    private function blankForm(string $kind): array
    {
        return $this->formFrom($kind, match ($kind) {
            'preset'     => ['group' => 'abstract', 'css' => ''],
            'gradient'   => ['category' => 'featured', 'type' => 'linear', 'angle' => 135, 'stops' => []],
            'mesh'       => ['base' => '#0a0612', 'blobs' => []],
            'pattern'    => ['css' => '', 'colors' => []],
            'tiles'      => ['colors' => [], 'tiles' => []],
            'torn'       => ['style' => TornStyleCatalog::DEFAULT, 'paper' => '#cfe0e6', 'backdrop' => []],
            'torn_style' => ['sheets' => []],
            default      => [],
        });
    }

    /** A merged entry back into a stored payload -- used when hiding a shipped look. */
    private function payloadFrom(string $kind, array $entry): array
    {
        return match ($kind) {
            'preset' => array_filter([
                'group'    => $entry['group'] ?? 'abstract',
                'css'      => $entry['css'] ?? '',
                'paper'    => $entry['paper'] ?? null,
                'backdrop' => $entry['backdrop'] ?? null,
            ], fn ($v) => $v !== null),
            'gradient' => [
                'category' => $entry['category'] ?? 'featured',
                'type'     => $entry['type'] ?? 'linear',
                'angle'    => (int) ($entry['angle'] ?? 135),
                'stops'    => $entry['stops'] ?? [],
            ],
            'mesh'       => ['base' => $entry['base'] ?? '#0a0612', 'blobs' => $entry['blobs'] ?? []],
            'pattern'    => ['css' => $entry['css'] ?? '', 'colors' => $entry['colors'] ?? []],
            'tiles'      => ['colors' => $entry['colors'] ?? [], 'tiles' => $entry['tiles'] ?? []],
            'torn'       => [
                'style'    => $entry['style'] ?? TornStyleCatalog::DEFAULT,
                'paper'    => $entry['paper'] ?? '#cfe0e6',
                'backdrop' => array_values((array) ($entry['backdrop'] ?? [])),
            ],
            'torn_style' => ['sheets' => $entry['sheets'] ?? []],
            default      => [],
        };
    }
}
