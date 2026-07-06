<?php

namespace Database\Factories;

use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory for the canonical {@see User} model
 * (`App\Modules\User\Models\User`).
 *
 * Wired to the model via {@see User::newFactory()} because the model lives
 * under a module namespace that Laravel's default factory-name resolver can't
 * map to this class.
 *
 * The default state mirrors the hand-rolled `makeUser()` helpers that several
 * feature tests grew independently (an active, onboarded user) and — via
 * {@see self::configure()} — provisions the user's personal workspace through
 * {@see User::ensureDefaultWorkspace()} after creation, exactly as those
 * helpers did. New tests can therefore call `User::factory()->create()` and
 * get the same fully-provisioned account without re-discovering the trap.
 *
 * @extends Factory<User>
 */
class UserDatabaseFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'              => 'U ' . Str::random(4),
            'email'             => 'u' . Str::random(8) . '@ex.com',
            'password'          => Hash::make('password'),
            'status'            => 'active',
            'email_verified_at' => now(),
            'onboarded_at'      => now(),
        ];
    }

    /**
     * Provision the personal workspace after the row is created, matching the
     * manual `makeUser()` helpers this factory replaces.
     *
     * Also drops the legacy `role` attribute before persisting: the `role`
     * column was removed from `users` (roles now live in the `user_roles`
     * pivot — see the `create_user_roles_pivot_seed_user_admin` migration).
     * Many `makeUser()` helpers still pass a cosmetic `role => 'user'`; the
     * old `User::create()` path silently discarded it via mass-assignment
     * protection (`role` is not `$fillable`), but a factory runs unguarded and
     * would otherwise force the dropped column into the INSERT. Stripping it in
     * `afterMaking` reproduces the original silent-drop before the row is saved.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user): void {
            if (array_key_exists('role', $user->getAttributes())) {
                $user->offsetUnset('role');
            }
        })->afterCreating(function (User $user): void {
            $user->ensureDefaultWorkspace();
        });
    }

    /** The user's email address should be unverified. */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** The user has not completed the onboarding wizard. */
    public function notOnboarded(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarded_at' => null,
        ]);
    }
}
