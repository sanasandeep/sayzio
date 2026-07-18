---
name: Blade @json() breaks on multi-line nested-closure args
description: Blade's @json/@directive argument parser truncates complex multi-line expressions with nested closures/[]/{}
---

`@json(<expr>)` compiles to `<?php echo json_encode(<expr>) ?>` but Blade extracts
`<expr>` with a balanced-parenthesis matcher that does NOT understand `[` array
literals, `{ }` closure bodies, or multi-line nesting. A complex argument like a
multi-line `collect(...)->map(function(){ return [...]; })->flatten()` gets
silently TRUNCATED mid-expression, producing an unbalanced-bracket PHP parse error
(`Unclosed '[' ... does not match ')'`) → the page 500s.

**Why:** the directive arg is regex-captured, not tokenized; nested `[`/`{` throw
off the paren count. Symptom is a parse error in the COMPILED view, not the source.

**How to apply:** keep `@json(...)` (and other Blade directives) arguments to a
SINGLE simple variable — build the array in the controller and pass it in, e.g.
`@json($templatesFlat)`. Never inline a multi-line closure/collection pipeline as
a directive argument.

**How to verify a blade compiles** (catches this class of bug without booting a
request):
```php
$compiled = app('blade.compiler')->compileString(file_get_contents($viewPath));
file_put_contents('/tmp/c.php', '<?php ?>'.$compiled);
// then: php -l /tmp/c.php
```
