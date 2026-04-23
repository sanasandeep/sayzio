<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BladeViewsLintTest extends TestCase
{
    /**
     * Compile every Blade view in resources/views and assert php -l passes
     * on the compiled output. Catches the recurring traps:
     *  - `@directive` tokens stuck to a word char (e.g. `KB@endif`)
     *  - Alpine attributes named like Blade directives (`@error=`, `@checked=`)
     *  - Inline expressions in `@json(...)` that exceed Blade's parser depth
     */
    public function test_all_blade_views_compile_and_lint_clean(): void
    {
        $exit = Artisan::call('view:lint');

        $this->assertSame(
            0,
            $exit,
            "view:lint reported broken Blade view(s):\n".Artisan::output(),
        );
    }
}
