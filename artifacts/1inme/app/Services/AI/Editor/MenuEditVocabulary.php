<?php

namespace App\Services\AI\Editor;

use App\Modules\User\Support\MenuItemMarks;
use App\Modules\User\Support\MenuPresentation;
use App\Modules\User\Support\MenuSectionNav;

/**
 * What "Modify with AI" is allowed to change, and how it is described to
 * the model.
 *
 * Sana, 2026-10-05: "it should not be modify whole.. it should able to
 * update as per instructions... it should able to change backgroud, add and
 * modify items, add stickers or any blocks or update price or anything
 * features available".
 *
 * ---- Why the previous shape was wrong ----------------------------------
 *
 * The builder replaces the catalogue. Every category and every item is
 * deleted and the model's answer is written in their place. #200 made that
 * survivable by sending the current menu back to the model and telling it
 * that anything omitted is deleted -- but survivable is not the same as
 * right. "Make the dosas fifty rupees" still cost a full re-generation of
 * eighty dishes, still re-wrote descriptions nobody asked about, and still
 * put the whole menu on the line against one model's willingness to copy
 * seventy-nine rows back verbatim.
 *
 * So the model no longer returns a menu. It returns a LIST OF CHANGES, and
 * each one is applied to the rows that are already there. Anything it does
 * not name is not touched, because nothing touches it.
 *
 * ---- "anything features available" -------------------------------------
 *
 * That phrase is the part that decides the design. A hand-written list of
 * what the AI may change is a list that is wrong the week after somebody
 * adds a sixth colour -- which is the exact failure this codebase has hit
 * over and over: a control that exists and does nothing, a capability that
 * exists and no screen offers it.
 *
 * So APPEARANCE_SOURCES below is not a list of keys. It is a list of the
 * CATALOGUES the editor already renders its own controls from. Add a
 * seventh colour to MenuPresentation::COLOURS and the AI can set it the
 * same afternoon, with no edit to this file; a test asserts exactly that
 * rather than trusting it.
 */
class MenuEditVocabulary
{
    /**
     * Appearance settings that live on the MENU row (`menu.settings`), as
     * {catalogue of allowed values} => {settings key}.
     *
     * Each entry names a const on MenuPresentation. Nothing here is typed
     * out twice: the allowed values ARE the editor's options, because they
     * are read from the same constant the editor's <select> loops over.
     *
     * @var array<string, string>  settings key => MenuPresentation constant name
     */
    public const CHOICE_SOURCES = [
        'layout'        => [MenuPresentation::class, 'LAYOUTS'],
        'divider'       => [MenuPresentation::class, 'DIVIDERS'],
        'heading_style' => [MenuPresentation::class, 'HEADINGS'],
        'price_style'   => [MenuPresentation::class, 'PRICES'],
        // Sana, 2026-10-05: "Section jumping". Added here and the AI can
        // set it the same afternoon, because both halves read the
        // catalogue -- which is the whole point of this file.
        'section_nav'    => [MenuSectionNav::class, 'NAVS'],
        'section_marker' => [MenuSectionNav::class, 'MARKERS'],
    ];

    /**
     * Appearance settings that live on the LINK row
     * (`link.settings.biolink`), with the value shape the AI may send.
     *
     * The background is here rather than in the menu settings because that
     * is where the shared Appearance screen puts it -- and "change
     * backgroud" was the first thing he asked for, so this is the half that
     * had to exist for the sentence to be true.
     *
     * Deliberately NOT the whole PageBackgroundInput surface: uploads,
     * slideshows and video are files, and a model cannot hand us a file. A
     * flat colour and a gradient are the two a creator can describe in
     * words, which is what this feature takes.
     *
     * @var array<string, string>
     */
    public const PAGE_KEYS = [
        'background_color'    => 'hex',
        'background_gradient' => 'css-gradient',
        'font_family'         => 'font',
        'font_color'          => 'hex',
    ];

    /** Operations that change what is ON the page. */
    public const CONTENT_OPS = [
        'item.update',
        'item.add',
        'item.remove',
        'category.add',
        'category.update',
        'category.remove',
    ];

    /**
     * Every appearance key the AI may set, with how its value is checked.
     *
     * Generated, never listed. @return array<string, array{kind: string, values?: array<int, string>, label: string}>
     */
    public static function appearanceKeys(): array
    {
        $keys = [];

        // Colours: the editor's own catalogue, labels included, so the
        // model is told "price_color -- Prices" rather than guessing from
        // the key name.
        foreach (MenuPresentation::COLOURS as $key => $meta) {
            $keys[$key] = [
                'kind'  => 'hex',
                'label' => $meta['label'],
                'scope' => 'menu',
            ];
        }

        // Choice settings: allowed values are the catalogue's own keys.
        foreach (self::CHOICE_SOURCES as $key => [$class, $constant]) {
            $catalogue = constant($class.'::'.$constant);
            $keys[$key] = [
                'kind'   => 'choice',
                'values' => array_keys($catalogue),
                'label'  => self::humanise($key),
                'scope'  => 'menu',
            ];
        }

        foreach (self::PAGE_KEYS as $key => $kind) {
            $keys[$key] = [
                'kind'  => $kind,
                'label' => self::humanise($key),
                'scope' => 'page',
            ];
        }

        return $keys;
    }

    /**
     * The marks a dish can wear, from the catalogue the picker reads.
     *
     * Sana, 2026-10-05: "i told to update all items marks with veg, non
     * veg and others also.... but it modified with description. how?"
     *
     * Because `marks` was not in this vocabulary. The model was asked for
     * a change it had no operation for, and did the nearest thing it
     * could: wrote "Non-Veg" into the DESCRIPTION, where it showed up on
     * the page looking almost right.
     *
     * That is the exact failure this class was written to prevent, and it
     * happened because only the APPEARANCE half was generated from a
     * catalogue -- the item fields were hand-listed, which is the mistake
     * the comment at the top of this file is about. So marks are read from
     * MenuItemMarks, the same rows the editor's own picker loops over, and
     * a test walks that table rather than a copy of it.
     *
     * @return array<int, array{key: string, label: string, graded: bool, max: int}>
     */
    public static function marks(): array
    {
        return MenuItemMarks::pickable()->map(fn ($m) => [
            'key'    => $m->key,
            'label'  => $m->label,
            'graded' => (bool) $m->is_graded,
            'max'    => (int) ($m->max_grade ?: 1),
        ])->all();
    }

    /**
     * What this page can be told to change, in the creator's words.
     *
     * Generated from the same catalogues the prompt and the applier use, so
     * the screen cannot promise a change the AI will not make, or stay
     * quiet about one it will. Every other version of this list in this
     * codebase has been hand-written, and every hand-written one has gone
     * out of date.
     *
     * @return array<int, string>
     */
    public static function abilities(string $itemNoun = 'item'): array
    {
        $colours = count(MenuPresentation::COLOURS);

        $choices = [];
        foreach (self::CHOICE_SOURCES as $key => [$class, $constant]) {
            $choices[] = strtolower(self::humanise($key)).' ('
                .count(constant($class.'::'.$constant)).' to pick from)';
        }

        return [
            'Prices, names and descriptions on any '.$itemNoun,
            'The marks on a dish — '.(count(self::marks()) > 0
                ? implode(', ', array_slice(array_column(self::marks(), 'label'), 0, 4))
                    .(count(self::marks()) > 4 ? ' and '.(count(self::marks()) - 4).' more' : '')
                : 'once you have set some up'),
            'Add or remove an '.$itemNoun.', or a whole section',
            'Hide an '.$itemNoun.' or a section without deleting it',
            'Mark something sold out, or back in',
            'The page background colour, gradient, text colour and font',
            $colours.' separate colours on the page itself — headings, '.$itemNoun.' names, descriptions, prices, dividers',
            'The '.implode(', the ', $choices),
        ];
    }

    /** "heading_style" => "Heading style". */
    private static function humanise(string $key): string
    {
        return ucfirst(str_replace('_', ' ', $key));
    }

    /**
     * The contract the model answers against.
     *
     * Written out from the catalogues above, so the prompt cannot describe
     * a different set of options from the one the applier accepts -- the
     * two halves of every "the AI can change X" claim this platform makes.
     */
    public static function prompt(string $noun = 'menu', string $itemNoun = 'item'): string
    {
        $lines = [];

        foreach (self::appearanceKeys() as $key => $meta) {
            $lines[] = match ($meta['kind']) {
                'choice'       => '  - "'.$key.'" ('.$meta['label'].'): one of '.implode(', ', $meta['values']),
                'hex'          => '  - "'.$key.'" ('.$meta['label'].'): a #rrggbb colour',
                'css-gradient' => '  - "'.$key.'" ('.$meta['label'].'): a CSS linear-gradient(...) value',
                'font'         => '  - "'.$key.'" ('.$meta['label'].'): a Google Font family name',
                default        => '  - "'.$key.'"',
            };
        }

        $appearance = implode("\n", $lines);

        // Generated from the same rows the editor's picker reads, so the
        // model is told about a mark the afternoon it is added -- and is
        // never left to approximate "mark these non-veg" with a change to
        // some other field.
        $markList = [];
        foreach (self::marks() as $m) {
            $markList[] = '  - "'.$m['key'].'" — '.$m['label']
                .($m['graded'] ? ' (graded 1 to '.$m['max'].', e.g. {"key":"'.$m['key'].'","grade":2})' : '');
        }
        $marks = $markList
            ? implode("\n", $markList)
            : '  (this menu has no marks set up — do not use the marks field)';

        return <<<PROMPT
You are editing a {$noun} that ALREADY EXISTS. You are not writing a new one.

Answer with ONE JSON object only — no prose, no markdown fences:

{ "operations": [ ... ] }

Each operation is one change. Emit ONLY the operations the instruction
actually asks for. Anything you do not name is left exactly as it is —
you do not need to repeat it, and you must not.

Content operations:
  {"op":"item.update","category":"<section name, optional>","{$itemNoun}":"<exact current name>",
   "name":"<new name, optional>","description":"<optional>","price":12.5,
   "sold_out":true|false,"hidden":true|false,"marks":["<key>", ...]}
  {"op":"item.add","category":"<section name>","name":"...","description":"<optional>","price":12.5,
   "marks":["<key>", ...]}
  {"op":"item.remove","category":"<optional>","{$itemNoun}":"<exact current name>"}
  {"op":"category.add","name":"...","description":"<optional>"}
  {"op":"category.update","category":"<exact current name>","name":"<new name, optional>",
   "description":"<optional>","hidden":true|false}
  {"op":"category.remove","category":"<exact current name>"}

Appearance operations — one key per operation:
  {"op":"appearance.set","key":"<key below>","value":"<value>"}

{$appearance}

Marks — the little badges on a dish (veg, non-veg, spicy and so on). Set
them with the "marks" field above, using these keys and NOTHING else:

{$marks}

Rules:
- A mark NEVER goes in "description". If somebody asks you to mark dishes
  veg or non-veg, use "marks". Writing it into the description puts the
  word in the wrong place on the page and leaves the badge unset.
- "marks" replaces that item's whole set, so list every mark it should
  end up with, not only the new one.
- Use the EXACT current names shown in the page content below when naming
  an existing {$itemNoun} or section. Do not rename something by guessing
  at a near-match.
- Only include the fields you are changing. A field you omit keeps its
  current value; a field you send as null is cleared.
- Never invent prices. If the instruction does not give a number, do not
  send a price.
- If the instruction asks for something that is not in the list above,
  leave it out rather than approximating it with a different change.
PROMPT;
    }
}
