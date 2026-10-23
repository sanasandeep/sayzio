<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Renaming a link's alias sends a request.
 *
 * It did not. Reported as "I tried to rename the custom alias, it's not
 * changing", and reproduced on the live page with fetch intercepted:
 * clicking Save produced ZERO requests and ZERO alerts. Calling save()
 * directly threw `ReferenceError: alias is not defined`.
 *
 * Alpine evaluates ATTRIBUTE expressions inside a `with(scope)`, which is why
 * `@click="save()"` and `x-model="alias"` resolve bare names. A method defined
 * in x-data is an ordinary function on the data object, so a bare `alias` in
 * its body is a free variable and there is no global by that name. The throw
 * happened while building the request body, before fetch was reached, and
 * Alpine catches what a handler throws -- so the button did nothing visible
 * at all. The worst shape of bug: no request, no error, no clue.
 *
 * Both handlers on this card had it. Renaming the primary alias and
 * rebinding an extra alias to another domain were equally dead.
 */
class TheAliasSaveActuallySendsSomethingTest extends TestCase
{
    private function card(): string
    {
        $path = resource_path('views/user/links/partials/aliases-card.blade.php');
        $this->assertFileExists($path);

        return file_get_contents($path);
    }

    /**
     * Every method body in this file reaches its own state through `this.`.
     *
     * Checked by extracting the x-data method bodies rather than by grepping
     * the file, so a bare name in an ATTRIBUTE expression -- where it is
     * correct, because Alpine scopes those -- cannot fail this.
     */
    public function test_the_handlers_reach_their_state_through_this(): void
    {
        $src = $this->card();
        $bare = [];

        foreach ($this->methodBodies($src) as $method => $body) {
            foreach (['alias', 'domainId', 'editing', 'savedAlias', 'savedDomainId', 'saved'] as $name) {
                // A use of the bare name that is not `this.name`, not
                // `.name`, not a key (`name:`), and not part of a hyphenated
                // word. That last one is not hypothetical: the route in this
                // handler is named `update-alias`, and without the hyphen in
                // the boundary the test failed on the FIXED file, pointing at
                // a URL string.
                $pattern = '/(?<![-.\w$])' . preg_quote($name, '/') . '(?![-\w$])(?!\s*:)/';
                if (! preg_match($pattern, $body)) {
                    continue;
                }
                $bare[] = "{$method}() uses bare `{$name}`";
            }
        }

        $this->assertSame([], $bare, sprintf(
            "These read or write component state as a free variable. In an x-data METHOD that\n"
            . "is a ReferenceError, and Alpine swallows it: the control does nothing at all,\n"
            . "with no request and no error on screen.\n\n%s",
            implode("\n", $bare)
        ));
    }

    /**
     * The control: the extractor really did find the handlers.
     *
     * Without this, a regex that matched no method bodies would make the test
     * above pass forever while the file went back to being broken.
     */
    public function test_the_extractor_finds_both_handlers(): void
    {
        $bodies = $this->methodBodies($this->card());

        $this->assertArrayHasKey('save', $bodies, 'the primary-alias handler was not found');
        $this->assertArrayHasKey('saveDomain', $bodies, 'the per-alias domain handler was not found');
        $this->assertStringContainsString('fetch(', $bodies['save'], 'save() no longer sends anything');
        $this->assertStringContainsString('fetch(', $bodies['saveDomain'], 'saveDomain() no longer sends anything');
    }

    /**
     * Success is decided by the status, not by the absence of an `errors` key.
     *
     * The old handler reloaded whenever the body had no `errors`, which is
     * every non-validation failure -- a 403 or a 500 carries a `message` and
     * nothing else. The page came back showing the OLD value with no message,
     * which looks exactly like the ReferenceError above and would have hidden
     * it even after that was fixed.
     */
    public function test_a_failed_save_is_not_read_as_a_success(): void
    {
        $src = $this->card();

        $this->assertDoesNotMatchRegularExpression(
            '/if\s*\(\s*d\.success\s*\|\|\s*!\s*d\.errors\s*\)/',
            $src,
            'the handler still treats any response without an errors key as a success'
        );
        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*r\.ok\s*\)/',
            $src,
            'the handler does not check the response status'
        );
    }

    /**
     * A failure says so. The silent reload is what made this take a bug
     * report to find rather than a glance.
     */
    public function test_a_failure_tells_the_person_something(): void
    {
        $src = $this->card();

        $this->assertMatchesRegularExpression(
            '/Could not save \(HTTP/',
            $src,
            'a failure with no message in the body now reports nothing at all'
        );
    }

    /**
     * Extract the body of each `name() { ... }` defined inside an x-data
     * attribute in this file.
     *
     * @return array<string,string>
     */
    private function methodBodies(string $src): array
    {
        $out = [];

        if (! preg_match_all('/([A-Za-z_$][\w$]*)\s*\(\s*\)\s*\{/', $src, $m, PREG_OFFSET_CAPTURE)) {
            return $out;
        }

        foreach ($m[1] as $i => [$name, $_]) {
            if (in_array($name, ['function', 'if', 'for', 'while', 'switch', 'catch'], true)) {
                continue;
            }

            $open = $m[0][$i][1] + strlen($m[0][$i][0]) - 1;
            $depth = 0;
            $end = $open;

            for ($j = $open; $j < strlen($src); $j++) {
                if ($src[$j] === '{') {
                    $depth++;
                } elseif ($src[$j] === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $end = $j;
                        break;
                    }
                }
            }

            $body = substr($src, $open, $end - $open + 1);

            // Only the two state-bearing handlers on this card matter here;
            // anything else that matched is not an x-data method.
            if (! str_contains($body, 'fetch(')) {
                continue;
            }

            // Comments out, and this is not fussiness: the first run of this
            // test failed on the FIXED file, because the comment explaining
            // the bug says "a bare `alias`" and the scanner cannot tell prose
            // from code. A test that reads its own explanation as a defect
            // fails for the one reason that has nothing to do with the code.
            $body = preg_replace('#/\*.*?\*/#s', '', $body);
            $body = preg_replace('#//[^\n]*#', '', $body);

            // String literals out too, for the same reason and with the
            // same history: the second run failed on the FIXED file
            // pointing at the words "Your alias was not changed" inside
            // an alert, and the run before that at the route name
            // `update-alias`. Prose is not code, whether it sits in a
            // comment, a message or a URL.
            $body = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", "''", $body);
            $body = preg_replace('/"(?:[^"\\\\]|\\\\.)*"/s', '""', $body);

            $out[$name] = $body;
        }

        return $out;
    }
}
