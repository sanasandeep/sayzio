<?php

namespace App\Modules\User\Support;

use App\Modules\User\Models\User;

/**
 * Canonical ordered stage list for the first-run onboarding wizard, shared by
 * the web onboarding page and the post-registration WhatsApp step so the
 * visible progress indicator (Welcome → Pick persona → Choose template →
 * Connect WhatsApp → Done) stays in lockstep across both surfaces.
 *
 * The WhatsApp stage is only surfaced when it will actually be shown next
 * (the user has no verified number yet and the one-time step hasn't fired),
 * so the "Step X of Y" count never promises a stage the user will skip. The
 * mobile setup flow mirrors the same ordered stages.
 */
class OnboardingSteps
{
    /**
     * @return array<int, array{key:string,label:string,optional?:bool}>
     */
    public static function forUser(User $user): array
    {
        $steps = [
            ['key' => 'welcome',  'label' => 'Welcome'],
            ['key' => 'persona',  'label' => 'Pick your persona'],
            ['key' => 'template', 'label' => 'Choose a template'],
        ];

        if (self::whatsappPending($user)) {
            $steps[] = ['key' => 'whatsapp', 'label' => 'Connect WhatsApp', 'optional' => true];
        }

        $steps[] = ['key' => 'done', 'label' => 'Done'];

        return $steps;
    }

    /** Zero-based index of a stage key within the given step list (0 if absent). */
    public static function indexOf(array $steps, string $key): int
    {
        foreach ($steps as $i => $step) {
            if (($step['key'] ?? null) === $key) {
                return $i;
            }
        }
        return 0;
    }

    /**
     * Whether the one-time WhatsApp connect stage is still pending for this
     * user (no verified number and never shown before). Mirrors the gate in
     * RedirectToOnboarding so the stepper and the redirect agree.
     */
    public static function whatsappPending(User $user): bool
    {
        $settings = $user->settings ?? [];

        return !$user->hasWhatsappNumber()
            && empty($settings['whatsapp_step_shown_at'] ?? null);
    }
}
