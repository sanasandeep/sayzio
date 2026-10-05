<?php

namespace App\Services\AI\Editor;

use App\Modules\User\Models\Link;
use App\Modules\User\Support\MenuPresentation;

/**
 * Applying a list of changes to a menu that already exists.
 *
 * Sana, 2026-10-05: "it should not be modify whole.. it should able to
 * update as per instructions".
 *
 * ---- The rule this class exists to keep -------------------------------
 *
 * A row that no operation names is never written to. Not re-saved with the
 * same values, not touched. That is the whole difference between this and
 * the builder, and it is why "modify" is now an honest word on the button:
 * if the model returns one operation, exactly one row changes, whatever
 * else it did or did not say.
 *
 * ---- Named, not guessed ------------------------------------------------
 *
 * Operations address rows by NAME, because a name is the only handle the
 * creator's instruction gives ("make the masala dosa sixty"). So the
 * matching rules matter more than they look:
 *
 *   - exact match first, case-insensitively and whitespace-trimmed;
 *   - then, only if there is exactly ONE, a contains-match;
 *   - two candidates is a SKIP, with the reason reported.
 *
 * Guessing between two dishes called "Chicken Curry" and "Chicken Curry
 * (Half)" is how somebody's price ends up on the wrong row and nobody
 * finds out until a customer argues at the counter. A skipped operation
 * that says "two items match" is a worse demo and a better product.
 *
 * ---- Every operation reports ------------------------------------------
 *
 * The caller gets back what was applied AND what was not, in the creator's
 * words. A modify tool that says "done" while silently dropping three of
 * five instructions is the thing that teaches people not to use it.
 */
class MenuEditPlan
{
    /** Ceiling on one plan, so a runaway answer cannot rewrite a catalogue. */
    public const MAX_OPERATIONS = 40;

    /**
     * @param  array  $operations  decoded "operations" array from the model
     * @param  array{
     *     category: class-string, item: class-string,
     *     sold_out: string, item_noun: string
     * } $schema
     * @return array{applied: array<int, string>, skipped: array<int, array{op: string, why: string}>}
     */
    public static function apply(array $operations, $menu, Link $link, array $schema): array
    {
        $applied = [];
        $skipped = [];

        // Collected and written ONCE at the end, because two settings
        // operations against the same JSON column would otherwise have the
        // second overwrite the first's read-modify-write.
        $menuSettings = (array) ($menu->settings ?? []);
        $pageSettings = (array) ($link->settings['biolink'] ?? []);
        $menuDirty = false;
        $pageDirty = false;

        foreach (array_slice($operations, 0, self::MAX_OPERATIONS) as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $op = is_string($raw['op'] ?? null) ? trim($raw['op']) : '';

            try {
                switch ($op) {
                    case 'appearance.set':
                        [$scope, $key, $value] = self::appearance($raw);
                        if ($scope === 'menu') {
                            $menuSettings[$key] = $value;
                            $menuDirty = true;
                        } else {
                            $pageSettings[$key] = $value;
                            $pageDirty = true;
                        }
                        $applied[] = self::appearanceLine($key, $value);
                        break;

                    case 'item.update':
                        $applied[] = self::itemUpdate($raw, $menu, $schema);
                        break;

                    case 'item.add':
                        $applied[] = self::itemAdd($raw, $menu, $schema);
                        break;

                    case 'item.remove':
                        $applied[] = self::itemRemove($raw, $menu, $schema);
                        break;

                    case 'category.add':
                        $applied[] = self::categoryAdd($raw, $menu, $schema);
                        break;

                    case 'category.update':
                        $applied[] = self::categoryUpdate($raw, $menu, $schema);
                        break;

                    case 'category.remove':
                        $applied[] = self::categoryRemove($raw, $menu, $schema);
                        break;

                    default:
                        throw new SkippedOperation('not an operation this page understands');
                }
            } catch (SkippedOperation $e) {
                $skipped[] = ['op' => $op ?: '(unnamed)', 'why' => $e->getMessage()];
            }
        }

        if ($menuDirty) {
            $menu->update(['settings' => $menuSettings]);
        }

        if ($pageDirty) {
            // The link's settings are one JSON blob with a 'biolink' key
            // the Appearance screen owns; everything else in it belongs to
            // other features and is carried through untouched.
            $all = (array) ($link->settings ?? []);
            $all['biolink'] = $pageSettings;
            $link->update(['settings' => $all]);
        }

        if (! $applied && ! $skipped) {
            throw new \RuntimeException('The AI did not return any changes to make. Your coins were refunded — please try again with a more specific instruction.');
        }

        return ['applied' => $applied, 'skipped' => $skipped];
    }

    // ---- appearance -----------------------------------------------------

    /** @return array{0: string, 1: string, 2: mixed} scope, key, clean value */
    private static function appearance(array $raw): array
    {
        $key  = is_string($raw['key'] ?? null) ? trim($raw['key']) : '';
        $spec = MenuEditVocabulary::appearanceKeys()[$key] ?? null;

        if ($spec === null) {
            throw new SkippedOperation('"'.($key ?: '?').'" is not a setting this page has');
        }

        $value = $raw['value'] ?? null;

        $clean = match ($spec['kind']) {
            'hex' => self::hex($value),
            'choice' => in_array((string) $value, $spec['values'], true) ? (string) $value : null,
            // A gradient is CSS, and CSS from a model is CSS from a
            // stranger. Only the one function this picker itself writes is
            // accepted, and only with colours and stops in it.
            'css-gradient' => self::gradient($value),
            'font' => self::font($value),
            default => null,
        };

        if ($clean === null) {
            throw new SkippedOperation('"'.(is_scalar($value) ? (string) $value : 'that').'" is not a value '.$key.' accepts');
        }

        return [$spec['scope'], $key, $clean];
    }

    private static function hex(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $v) ? $v : null;
    }

    private static function gradient(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        if ($v === '' || mb_strlen($v) > 500) {
            return null;
        }
        if (! preg_match('/^(linear|radial)-gradient\([^;{}<>]*\)$/i', $v)) {
            return null;
        }

        return $v;
    }

    private static function font(mixed $value): ?string
    {
        $v = is_string($value) ? trim($value) : '';
        if ($v === '') {
            return null;
        }
        // cleanFamily is what the renderer will run this through anyway.
        // Validating with the same function means a family that passes here
        // is a family the page actually draws -- rather than one that
        // validates, saves, and silently falls back to the system stack.
        $clean = MenuPresentation::cleanFamily($v);

        return $clean !== '' ? $clean : null;
    }

    private static function appearanceLine(string $key, mixed $value): string
    {
        $label = MenuEditVocabulary::appearanceKeys()[$key]['label'] ?? $key;

        return $label.' → '.(is_string($value) ? $value : json_encode($value));
    }

    // ---- content --------------------------------------------------------

    private static function itemUpdate(array $raw, $menu, array $schema): string
    {
        $item = self::findItem($raw, $menu, $schema);
        $changes = [];

        if (array_key_exists('name', $raw) && ($name = self::str($raw['name'], 160)) !== null) {
            $changes['name'] = $name;
        }
        if (array_key_exists('description', $raw)) {
            $changes['description'] = self::str($raw['description'], 500);
        }
        if (array_key_exists('price', $raw) && is_numeric($raw['price'])) {
            $changes['price'] = self::price($raw['price']);
        }
        if (array_key_exists('sold_out', $raw) && is_bool($raw['sold_out'])) {
            $changes[$schema['sold_out']] = $raw['sold_out'];
        }
        if (array_key_exists('hidden', $raw) && is_bool($raw['hidden'])) {
            $changes['is_active'] = ! $raw['hidden'];
        }

        if (! $changes) {
            throw new SkippedOperation('no change was given for "'.$item->name.'"');
        }

        $was = $item->name;
        $item->update($changes);

        return $was.': '.implode(', ', array_map(
            fn ($k, $v) => self::fieldLabel($k, $schema).' '.self::valueLabel($v),
            array_keys($changes),
            $changes
        ));
    }

    private static function itemAdd(array $raw, $menu, array $schema): string
    {
        $name = self::str($raw['name'] ?? null, 160);
        if ($name === null) {
            throw new SkippedOperation('a new '.$schema['item_noun'].' needs a name');
        }

        $category = self::findCategory($raw['category'] ?? null, $menu, $schema, required: true);

        $last = $schema['item']::where('menu_id', $menu->id)
            ->where('category_id', $category->id)
            ->max('sort_order');

        $schema['item']::create([
            'menu_id'     => $menu->id,
            'category_id' => $category->id,
            'name'        => $name,
            'description' => self::str($raw['description'] ?? null, 500),
            'price'       => self::price($raw['price'] ?? 0),
            'currency'    => $menu->currency,
            'sort_order'  => (int) $last + 1,
            'is_active'   => true,
        ]);

        return 'Added '.$name.' to '.$category->name;
    }

    private static function itemRemove(array $raw, $menu, array $schema): string
    {
        $item = self::findItem($raw, $menu, $schema);
        $name = $item->name;
        $item->delete();

        return 'Removed '.$name;
    }

    private static function categoryAdd(array $raw, $menu, array $schema): string
    {
        $name = self::str($raw['name'] ?? null, 120);
        if ($name === null) {
            throw new SkippedOperation('a new section needs a name');
        }

        $last = $schema['category']::where('menu_id', $menu->id)->max('sort_order');

        $schema['category']::create([
            'menu_id'     => $menu->id,
            'name'        => $name,
            'description' => self::str($raw['description'] ?? null, 500),
            'sort_order'  => (int) $last + 1,
            'is_active'   => true,
        ]);

        return 'Added section '.$name;
    }

    private static function categoryUpdate(array $raw, $menu, array $schema): string
    {
        $category = self::findCategory($raw['category'] ?? null, $menu, $schema, required: true);
        $changes = [];

        if (array_key_exists('name', $raw) && ($name = self::str($raw['name'], 120)) !== null) {
            $changes['name'] = $name;
        }
        if (array_key_exists('description', $raw)) {
            $changes['description'] = self::str($raw['description'], 500);
        }
        if (array_key_exists('hidden', $raw) && is_bool($raw['hidden'])) {
            $changes['is_active'] = ! $raw['hidden'];
        }

        if (! $changes) {
            throw new SkippedOperation('no change was given for "'.$category->name.'"');
        }

        $was = $category->name;
        $category->update($changes);

        return 'Section '.$was.': '.implode(', ', array_map(
            fn ($k, $v) => self::fieldLabel($k, $schema).' '.self::valueLabel($v),
            array_keys($changes),
            $changes
        ));
    }

    private static function categoryRemove(array $raw, $menu, array $schema): string
    {
        $category = self::findCategory($raw['category'] ?? null, $menu, $schema, required: true);
        $name = $category->name;

        // The items go with it. A section deleted out from under its
        // dishes leaves rows that belong to nothing and render nowhere --
        // invisible on the page and still counted in every total.
        $schema['item']::where('menu_id', $menu->id)->where('category_id', $category->id)->delete();
        $schema['category']::where('menu_id', $menu->id)->where('parent_id', $category->id)->delete();
        $category->delete();

        return 'Removed section '.$name.' and everything in it';
    }

    // ---- resolution -----------------------------------------------------

    /** The one row this operation names, or a skip saying why not. */
    private static function findItem(array $raw, $menu, array $schema)
    {
        $needle = self::str($raw[$schema['item_noun']] ?? $raw['item'] ?? $raw['name'] ?? null, 200);
        if ($needle === null) {
            throw new SkippedOperation('no '.$schema['item_noun'].' was named');
        }

        $query = $schema['item']::where('menu_id', $menu->id);

        // A category, when given, narrows the search before it disambiguates
        // it -- "the Chicken Curry in Mains" is a different row from the one
        // in Specials, and the model was told it may say so.
        if (($catName = self::str($raw['category'] ?? null, 200)) !== null) {
            $category = self::findCategory($catName, $menu, $schema, required: false);
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        return self::oneOf($query->get(), $needle, $schema['item_noun']);
    }

    private static function findCategory(mixed $name, $menu, array $schema, bool $required)
    {
        $needle = self::str($name, 200);
        if ($needle === null) {
            if ($required) {
                throw new SkippedOperation('no section was named');
            }

            return null;
        }

        return self::oneOf($schema['category']::where('menu_id', $menu->id)->get(), $needle, 'section');
    }

    /**
     * Exact first, then a single contains-match, never a choice between two.
     *
     * The ambiguity branch is the point of this method. A model asked to
     * change "the curry" on a menu with four curries will name one of them
     * loosely, and picking the first is how a price lands on the wrong
     * dish -- silently, because the report would say it worked.
     */
    private static function oneOf($rows, string $needle, string $noun)
    {
        $want = mb_strtolower(trim($needle));

        $exact = $rows->filter(fn ($r) => mb_strtolower(trim((string) $r->name)) === $want);
        if ($exact->count() === 1) {
            return $exact->first();
        }
        if ($exact->count() > 1) {
            throw new SkippedOperation($exact->count().' '.$noun.'s are called "'.$needle.'" — rename one, or change it by hand');
        }

        $loose = $rows->filter(fn ($r) => str_contains(mb_strtolower(trim((string) $r->name)), $want));
        if ($loose->count() === 1) {
            return $loose->first();
        }
        if ($loose->count() > 1) {
            throw new SkippedOperation('"'.$needle.'" matches '.$loose->count().' '.$noun.'s — say which one');
        }

        throw new SkippedOperation('no '.$noun.' called "'.$needle.'"');
    }

    // ---- small helpers --------------------------------------------------

    private static function str(mixed $value, int $max): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    private static function price(mixed $value): float
    {
        $n = is_numeric($value) ? (float) $value : 0.0;

        return max(0.0, min(9999999.0, round($n, 2)));
    }

    private static function fieldLabel(string $key, array $schema): string
    {
        return match ($key) {
            'name'        => 'renamed to',
            'description' => 'description',
            'price'       => 'price',
            'is_active'   => 'visibility',
            $schema['sold_out'] => 'stock',
            default       => $key,
        };
    }

    private static function valueLabel(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'on' : 'off';
        }

        return $value === null ? 'cleared' : (string) $value;
    }
}
