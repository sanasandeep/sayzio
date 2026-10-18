<?php

namespace Tests\Feature;

use App\Support\View\JsonDirective;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Blade's own @json splits its argument on every comma rather than on argument
 * boundaries, so any expression containing one loses the four escaping flags
 * the directive means to apply. 87 of the 526 @json uses in this codebase
 * carry a comma and 86 of those sit inside a <script>, so this is not a corner
 * case -- it is most of the inline JSON on the site.
 *
 * These guards are about the two things that go wrong: the flags, and the
 * expression itself surviving intact.
 */
class JsonDirectiveTest extends TestCase
{
    /** If the override is not registered, every other test here is vacuous. */
    public function test_the_override_is_registered(): void
    {
        $this->assertSame(
            '<?php echo json_encode($x, 15, 512) ?>',
            Blade::compileString('@json($x)'),
            'the custom @json directive is not registered, so Blade is still using its own',
        );
    }

    /** The whole point: a comma in the expression used to eat the flags. */
    public function test_a_comma_in_the_expression_keeps_the_escaping_flags(): void
    {
        foreach ([
            "route('x', \$link)",
            "old('body', \$value)",
            "['a' => 1, 'b' => 2]",
            "['a' => 1, 'b' => 2, 'c' => 3]",
        ] as $expression) {
            $this->assertSame(
                "<?php echo json_encode({$expression}, 15, 512) ?>",
                Blade::compileString("@json({$expression})"),
                "@json({$expression}) lost its escaping flags",
            );
        }
    }

    /** What the flags are for, rendered rather than argued about. */
    public function test_the_dangerous_characters_are_escaped_when_rendered(): void
    {
        $rendered = Blade::render("@json(['a' => \$v, 'b' => 2, 'c' => 3])", ['v' => "x</script>&y'z"]);

        foreach (['<', '&', "'"] as $char) {
            $this->assertStringNotContainsString($char, $rendered,
                "a raw {$char} reached the page, so the escaping flags were dropped again");
        }

        foreach (['\\u003C', '\\u0026', '\\u0027'] as $escape) {
            $this->assertStringContainsString($escape, $rendered,
                "the dangerous character did not come back as {$escape}");
        }
    }

    /** An explicit options or depth argument has to keep working. */
    public function test_explicit_arguments_are_still_honoured(): void
    {
        $this->assertSame('<?php echo json_encode($x, JSON_UNESCAPED_SLASHES, 512) ?>',
            Blade::compileString('@json($x, JSON_UNESCAPED_SLASHES)'));
        $this->assertSame('<?php echo json_encode($x, 0, 256) ?>',
            Blade::compileString('@json($x, 0, 256)'));
    }

    /** The splitter itself: commas inside strings, calls and literals are not boundaries. */
    public function test_only_top_level_commas_separate_arguments(): void
    {
        $cases = [
            '$payload'                                 => ['$payload'],
            "route('x', \$link)"                       => ["route('x', \$link)"],
            "['a'=>1,'b'=>2,'c'=>3]"                   => ["['a'=>1,'b'=>2,'c'=>3]"],
            '"a,b"'                                    => ['"a,b"'],
            "'a, b'"                                   => ["'a, b'"],
            "'it\\'s, fine'"                           => ["'it\\'s, fine'"],
            "['k' => \"a,b\", 'j' => fn(\$a,\$b) => \$a]" => ["['k' => \"a,b\", 'j' => fn(\$a,\$b) => \$a]"],
            '$x, JSON_PRETTY_PRINT, 256'               => ['$x', 'JSON_PRETTY_PRINT', '256'],
            "\$a, ['b' => 1, 'c' => 2]"                => ['$a', "['b' => 1, 'c' => 2]"],
        ];

        foreach ($cases as $expression => $expected) {
            $this->assertSame($expected, JsonDirective::arguments($expression),
                "split the wrong way: {$expression}");
        }
    }

    public function test_an_empty_expression_does_not_produce_broken_php(): void
    {
        $this->assertSame('<?php echo json_encode([], 15, 512) ?>', JsonDirective::compile(''));
        $this->assertSame('<?php echo json_encode([], 15, 512) ?>', JsonDirective::compile(null));
    }
}
