<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SplFileInfo;

/**
 * Guards against the silent onboarding progress-bar breakage class of bug.
 *
 * The shared stepper partial (`user.onboarding._stepper`) is Alpine-driven:
 * it READS a numeric `stepIndex` from the surrounding Alpine scope via
 * `:class`, `x-if` and `x-text`. If a view includes the partial without an
 * `x-data` wrapper that DEFINES `stepIndex`, the progress indicator silently
 * fails (wrong step highlighted, missing "Step X of Y") with no error — this
 * already slipped through once on the privacy step.
 *
 * This test discovers every Blade view that includes the partial and fails
 * loudly if any of them does not define `stepIndex` in Alpine scope, so a new
 * onboarding step that forgets the wrapper can't regress the bar unnoticed.
 */
class OnboardingStepperScopeGuardTest extends TestCase
{
    /** The partial (as referenced in `@include`-family directives) whose scope we guard. */
    private const STEPPER_VIEW = 'user.onboarding._stepper';

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
     * Does the source include the stepper partial via any @include-family
     * directive (@include, @includeWhen, @includeIf, @includeFirst, @includeUnless)?
     */
    private function includesStepper(string $source): bool
    {
        // Match the view name in single/double quotes, tolerating leading args
        // (e.g. the condition in @includeWhen) up to the view-name argument.
        $view = preg_quote(self::STEPPER_VIEW, '/');

        return (bool) preg_match(
            '/@include(?:When|If|First|Unless)?\s*\([^)]*[\'"]'.$view.'[\'"]/',
            $source,
        );
    }

    /**
     * Does the source DEFINE `stepIndex` in Alpine scope (as opposed to merely
     * reading it)? Accepts the three real-world shapes:
     *   - object-literal property:   x-data="{ stepIndex: 0 }"
     *   - Alpine component getter:    get stepIndex() { ... }
     *   - assignment / method:        stepIndex = 0   /   stepIndex() { ... }
     *
     * Deliberately does NOT treat reads (`stepIndex > 1`, `stepIndex === 0`,
     * `stepIndex + 1`) as definitions, so a view that only echoes the value
     * without providing it still fails the guard.
     */
    private function definesStepIndex(string $source): bool
    {
        $patterns = [
            '/\bstepIndex\s*:/',            // object-literal property
            '/\bget\s+stepIndex\s*\(/',     // getter
            '/\bstepIndex\s*=(?!=)/',       // assignment (single =, not == / ===)
            '/\bstepIndex\s*\([^)]*\)\s*\{/', // method definition
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $source)) {
                return true;
            }
        }

        return false;
    }

    public function test_stepper_partial_exists(): void
    {
        $path = $this->viewsPath().'/'.str_replace('.', '/', self::STEPPER_VIEW).'.blade.php';
        $this->assertFileExists(
            $path,
            'The onboarding stepper partial moved or was renamed; update '.self::STEPPER_VIEW.' in this guard.',
        );
    }

    public function test_every_view_including_the_stepper_defines_stepIndex(): void
    {
        $offenders = [];
        $consumers = 0;

        foreach ($this->bladeFiles() as $file) {
            $source = file_get_contents($file);
            if ($source === false || ! $this->includesStepper($source)) {
                continue;
            }
            $consumers++;
            if (! $this->definesStepIndex($source)) {
                $offenders[] = $file;
            }
        }

        // The partial has real consumers today; if this drops to zero the
        // discovery regex has silently stopped matching and the guard is dead.
        $this->assertGreaterThan(
            0,
            $consumers,
            'No views were found including '.self::STEPPER_VIEW.'. The include-detection regex is likely broken, '
            .'which would let the guard pass vacuously.',
        );

        $this->assertSame(
            [],
            $offenders,
            "These view(s) include the onboarding stepper partial (".self::STEPPER_VIEW.") but do not define a "
            ."numeric `stepIndex` in Alpine scope, so the progress bar will silently break there:\n  - "
            .implode("\n  - ", $offenders)
            ."\nWrap the include in an `x-data` that provides `stepIndex` (see privacy.blade.php / whatsapp.blade.php) "
            ."or use a component like `onboarding()` that defines a `get stepIndex()` (see index.blade.php).",
        );
    }
}
