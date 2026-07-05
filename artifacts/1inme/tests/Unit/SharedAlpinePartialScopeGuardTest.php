<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SplFileInfo;

/**
 * Generalized guard against the "silent Alpine-scope breakage" class of bug.
 *
 * Several shared Blade partials are Alpine-driven: they READ a variable, method
 * or component from the SURROUNDING Alpine scope (via `:class`, `x-if`, `x-show`,
 * `x-text`, `x-model`, `@click`, …) but never DEFINE it themselves. Each one only
 * works when the including view wraps it in the right `x-data`. If a consumer
 * forgets that wrapper, the partial silently misbehaves with no PHP/JS error —
 * exactly the onboarding progress-bar bug that `OnboardingStepperScopeGuardTest`
 * already guards for the stepper partial.
 *
 * This test generalizes that idea: it maps every such shared partial to the
 * scope it requires, discovers every view that `@include`s it, and fails loudly
 * if any consumer does not provide that scope. Adding a new consumer that forgets
 * the wrapper — or refactoring a component so it no longer defines a required
 * var — now breaks CI instead of shipping a dead UI.
 *
 * NOTE: `user.onboarding._stepper` is intentionally NOT listed here; it is the
 * canonical instance and keeps its own richer guard in
 * `OnboardingStepperScopeGuardTest` to avoid two systems asserting one partial.
 *
 * A requirement is satisfied by the *presence* of a definition/reference anywhere
 * in the consumer file (matching the existing stepper guard's approach). This is
 * a deliberately coarse proxy for "the include lives inside the right x-data": it
 * catches the real-world regression (consumer forgot the scope entirely) without
 * trying to statically prove Alpine nesting, which Blade makes infeasible.
 */
class SharedAlpinePartialScopeGuardTest extends TestCase
{
    /**
     * Registry of at-risk shared partials → the scope they require.
     *
     * Each entry:
     *   - 'requires'  : map of <human label> => <list of alternative regexes>.
     *                   A consumer satisfies the label if it matches ANY of the
     *                   alternatives; it satisfies the partial only if EVERY
     *                   label is satisfied.
     *   - 'hint'      : shown in the failure message to point at a good example.
     *
     * @return array<string, array{requires: array<string, list<string>>, hint: string}>
     */
    private function registry(): array
    {
        return [
            // Reads `currency` (string) + `switchCurrency(c)` from the host page's
            // Alpine scope for the instant, no-reload currency switcher.
            'public.pricing._currency_badge' => [
                'requires' => [
                    'currency' => $this->varDefinition('currency'),
                    'switchCurrency' => $this->varDefinition('switchCurrency'),
                ],
                'hint' => 'define both in an ancestor x-data (see home/partials/pricing.blade.php and user/upgrade/show.blade.php).',
            ],

            // Reusable testimonial repeater: calls `resetTo(key, defaults)` and
            // mutates the model array named by the `$modelKey` include arg.
            'admin.marketing-settings.partials._testimonial-editor' => [
                'requires' => [
                    'resetTo' => $this->varDefinition('resetTo'),
                ],
                'hint' => 'wrap the include in the x-data that defines resetTo() and the model arrays (see admin/marketing-settings/index.blade.php).',
            ],

            // The admin sidebar reads the collapse state / helpers owned by the
            // admin layout shell.
            'admin.partials.sidebar' => [
                'requires' => [
                    'sidebarMode' => $this->varDefinition('sidebarMode'),
                    'setSidebar' => $this->varDefinition('setSidebar'),
                    'sidebarWidth' => $this->varDefinition('sidebarWidth'),
                ],
                'hint' => 'include it inside the admin shell x-data that defines sidebarMode/setSidebar/sidebarWidth (see admin/layouts/app.blade.php).',
            ],

            // Crop modal must live inside an `aboutPhotoUploader(...)` scope; it
            // reads/writes cropping, previewUrl, imgStyle, zoom, vpW, vpH, etc.
            'admin.site-pages.partials.about-crop-modal' => [
                'requires' => [
                    'aboutPhotoUploader scope' => $this->componentReference('aboutPhotoUploader'),
                ],
                'hint' => 'nest the include inside a <div x-data="aboutPhotoUploader(...)"> (see admin/site-pages/partials/about-editor.blade.php).',
            ],

            // QR type-specific form fields read `type` and `payload` from the
            // enclosing `qrBuilder()` scope.
            'user.qr-codes._type-forms' => [
                'requires' => [
                    'qrBuilder scope' => $this->componentReference('qrBuilder'),
                ],
                'hint' => 'include it inside the x-data="qrBuilder()" builder scope (see user/qr-codes/builder.blade.php).',
            ],

            // Resume modals are all wired to the parent `resumeEditor()` component.
            'user.resume.partials.import-modal' => [
                'requires' => [
                    'resumeEditor scope' => $this->componentReference('resumeEditor'),
                ],
                'hint' => 'include it inside the x-data="resumeEditor()" scope (see user/resume/editor.blade.php).',
            ],
            'user.resume.partials.tailor-modal' => [
                'requires' => [
                    'resumeEditor scope' => $this->componentReference('resumeEditor'),
                ],
                'hint' => 'include it inside the x-data="resumeEditor()" scope (see user/resume/editor.blade.php).',
            ],
            'user.resume.partials.cover-letter-modal' => [
                'requires' => [
                    'resumeEditor scope' => $this->componentReference('resumeEditor'),
                ],
                'hint' => 'include it inside the x-data="resumeEditor()" scope (see user/resume/editor.blade.php).',
            ],
        ];
    }

    /**
     * Regexes that count as DEFINING an Alpine scalar/method `$name` (as opposed
     * to merely reading it). Mirrors OnboardingStepperScopeGuardTest::definesStepIndex.
     *
     * @return list<string>
     */
    private function varDefinition(string $name): array
    {
        $n = preg_quote($name, '/');

        return [
            '/\b'.$n.'\s*:/',               // object-literal property: `name: ...`
            '/\bget\s+'.$n.'\s*\(/',       // getter: `get name() { ... }`
            '/\b'.$n.'\s*=(?![=>])/',      // assignment: `name = ...` (not == / === / => arrow)
            '/\b'.$n.'\s*\([^)]*\)\s*\{/', // method: `name(...) { ... }`
        ];
    }

    /**
     * Regexes that count as the consumer PROVIDING an Alpine component scope
     * `$name` — either by mounting it as an x-data or defining the factory.
     *
     * @return list<string>
     */
    private function componentReference(string $name): array
    {
        $n = preg_quote($name, '/');

        return [
            '/x-data\s*=\s*["\'][^"\']*\b'.$n.'\s*\(/', // x-data="name(...)"
            '/\bfunction\s+'.$n.'\s*\(/',               // function name(...) { ... }
            '/\b'.$n.'\s*\([^)]*\)\s*\{/',              // name(...) { ... } (Alpine.data)
        ];
    }

    private function viewsPath(): string
    {
        return dirname(__DIR__, 2).'/resources/views';
    }

    /** @return list<string> absolute paths to every *.blade.php under resources/views */
    private function bladeFiles(): array
    {
        $root = $this->viewsPath();
        $this->assertDirectoryExists($root, "resources/views not found at {$root}");

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );

        $files = [];
        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * Does the source include $view via any @include-family directive
     * (@include, @includeWhen, @includeIf, @includeFirst, @includeUnless)?
     * Requires the literal `@include(` so plain string mentions in comments
     * (e.g. sibling modals referencing each other) don't count as consumers.
     */
    private function includes(string $source, string $view): bool
    {
        $v = preg_quote($view, '/');

        return (bool) preg_match(
            '/@include(?:When|If|First|Unless)?\s*\([^)]*[\'"]'.$v.'[\'"]/',
            $source,
        );
    }

    /** @return list<string> labels of requirements the source fails to satisfy */
    private function unmetRequirements(string $source, array $requires): array
    {
        $unmet = [];
        foreach ($requires as $label => $alternatives) {
            $satisfied = false;
            foreach ($alternatives as $pattern) {
                if (preg_match($pattern, $source)) {
                    $satisfied = true;
                    break;
                }
            }
            if (! $satisfied) {
                $unmet[] = $label;
            }
        }

        return $unmet;
    }

    public function test_every_registered_partial_exists(): void
    {
        foreach (array_keys($this->registry()) as $view) {
            $path = $this->viewsPath().'/'.str_replace('.', '/', $view).'.blade.php';
            $this->assertFileExists(
                $path,
                "Registered shared partial [{$view}] moved or was renamed; update the registry in ".self::class.'.',
            );
        }
    }

    public function test_every_consumer_provides_the_required_alpine_scope(): void
    {
        $blades = $this->bladeFiles();
        $failures = [];

        foreach ($this->registry() as $view => $spec) {
            $consumers = 0;

            foreach ($blades as $file) {
                $source = file_get_contents($file);
                if ($source === false || ! $this->includes($source, $view)) {
                    continue;
                }
                $consumers++;

                $unmet = $this->unmetRequirements($source, $spec['requires']);
                if ($unmet !== []) {
                    $rel = str_replace($this->viewsPath().'/', '', $file);
                    $failures[] = sprintf(
                        "  - %s includes [%s] but does not provide: %s\n      Fix: %s",
                        $rel,
                        $view,
                        implode(', ', $unmet),
                        $spec['hint'],
                    );
                }
            }

            // If a partial has real consumers today but the discovery regex
            // finds none, the guard has silently gone dead — fail loudly.
            $this->assertGreaterThan(
                0,
                $consumers,
                "No views were found including [{$view}]. Either the partial is now unused (remove it from the "
                .'registry) or the @include-detection regex has broken (which would let this guard pass vacuously).',
            );
        }

        $this->assertSame(
            [],
            $failures,
            "These view(s) include a shared Alpine-driven partial without providing the scope it reads, so the "
            ."partial will silently break there:\n".implode("\n", $failures),
        );
    }
}
