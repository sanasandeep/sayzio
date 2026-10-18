<?php

namespace App\Support\View;

/**
 * A @json that does not quietly drop its escaping flags.
 *
 * Illuminate\View\Compilers\Concerns\CompilesJson does this:
 *
 *     $parts   = explode(',', $this->stripParentheses($expression));
 *     $options = isset($parts[1]) ? trim($parts[1]) : $this->encodingOptions;
 *     $depth   = isset($parts[2]) ? trim($parts[2]) : 512;
 *     return "<?php echo json_encode($parts[0], $options, $depth) ?>";
 *
 * It splits on EVERY comma, not on argument boundaries. So the moment the
 * expression itself contains one -- @json(route('x', $link)), @json(old('body',
 * $v)), @json(['a' => 1, 'b' => 2]) -- the fragments after the first are read
 * as the options and depth arguments, and the four escaping flags the
 * directive means to apply are replaced by whatever those fragments reassemble
 * into. Measured against this codebase: 87 of 526 @json uses carry a comma,
 * and 86 of those sit inside a <script> tag.
 *
 * What that costs, precisely: `<`, `&` and `'` go out raw. PHP escapes `/` by
 * default, so a literal `</script>` still cannot appear and the obvious
 * break-out is not open -- this is a defence-in-depth loss rather than a hole.
 * It is a loss that 86 call sites are relying on without knowing.
 *
 * Past three commas the tail is dropped entirely and the compiled PHP no
 * longer parses, which is how folder-map.blade.php broke when a field was
 * added to a payload that happened to have exactly two commas until then.
 *
 * This splits on top-level commas only, respecting quotes, parentheses,
 * brackets and braces, so an explicit second or third argument still works
 * and an expression that merely contains commas is left whole.
 */
class JsonDirective
{
    /** The flags Blade's own @json intends to apply, and usually does not. */
    public const OPTIONS = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;

    public static function compile(?string $expression): string
    {
        $parts = self::arguments((string) $expression);

        $value   = $parts[0] ?? '[]';
        $options = $parts[1] ?? (string) self::OPTIONS;
        $depth   = $parts[2] ?? '512';

        return "<?php echo json_encode({$value}, {$options}, {$depth}) ?>";
    }

    /**
     * Split a PHP argument list on its top-level commas.
     *
     * @return list<string>
     */
    public static function arguments(string $expression): array
    {
        $parts   = [''];
        $depth   = 0;
        $quote   = null;
        $escaped = false;

        for ($i = 0, $len = strlen($expression); $i < $len; $i++) {
            $char = $expression[$i];

            if ($quote !== null) {
                $parts[count($parts) - 1] .= $char;
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                $parts[count($parts) - 1] .= $char;

                continue;
            }

            if ($char === '(' || $char === '[' || $char === '{') {
                $depth++;
            } elseif ($char === ')' || $char === ']' || $char === '}') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = '';

                continue;
            }

            $parts[count($parts) - 1] .= $char;
        }

        return array_values(array_filter(array_map('trim', $parts), fn ($part) => $part !== ''));
    }
}
