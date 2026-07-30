<?php

namespace Tests\Feature;

use App\Modules\User\Models\Domain;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\LinkAlias;
use App\Modules\User\Models\User;
use App\Modules\User\Support\AliasNamespace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins the per-domain alias uniqueness rules introduced alongside per-domain
 * resolution (see CrossDomainAliasResolutionTest for the resolution side):
 *
 *   - Within one platform domain an alias is unique CASE-INSENSITIVELY across
 *     BOTH links.alias and link_aliases.alias.
 *   - The SAME alias may exist independently on different platform domains.
 *   - NULL domain_id is equivalent to the DEFAULT platform domain
 *     (sayzio.app) namespace for uniqueness purposes.
 *   - The web availability endpoint (`POST /user/links/check-alias`) honours
 *     the requested domain_id.
 */
class PerDomainAliasUniquenessTest extends TestCase
{
    use RefreshDatabase;

    private const BRAND_PRIMARY   = 'sayzio.app';
    private const BRAND_SECONDARY = '1in.me';

    private function makeUser(): User
    {
        return User::factory()->create()->fresh();
    }

    private function globalBrandDomain(string $host, bool $primary = false): Domain
    {
        return Domain::updateOrCreate(
            ['domain' => $host],
            [
                'user_id'     => null,
                'type'        => 'custom',
                'is_verified' => true,
                'is_active'   => true,
                'is_primary'  => $primary,
            ]
        );
    }

    private function makeUrlLink(User $user, ?int $domainId, string $alias): Link
    {
        return Link::create([
            'user_id'   => $user->id,
            'domain_id' => $domainId,
            'type'      => 'url',
            'alias'     => $alias,
            'long_url'  => 'https://dest.example.com/' . $alias,
            'is_active' => true,
            'settings'  => ['open_in_app' => false],
        ]);
    }

    public function test_alias_taken_case_insensitively_within_a_domain_across_both_tables(): void
    {
        $user    = $this->makeUser();
        $sayzio  = $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $oneInMe = $this->globalBrandDomain(self::BRAND_SECONDARY);

        // Taken via links.alias on sayzio.app.
        $alias = 'uniq-' . Str::lower(Str::random(6));
        $this->makeUrlLink($user, $sayzio->id, $alias);

        $this->assertTrue(AliasNamespace::isTaken($alias, $sayzio->id));
        // Case-insensitive.
        $this->assertTrue(AliasNamespace::isTaken(Str::upper($alias), $sayzio->id));
        // Free on the other platform domain.
        $this->assertFalse(AliasNamespace::isTaken($alias, $oneInMe->id));

        // Taken via link_aliases.alias on 1in.me.
        $link  = $this->makeUrlLink($user, $oneInMe->id, 'base-' . Str::lower(Str::random(6)));
        $extra = 'extra-' . Str::lower(Str::random(6));
        LinkAlias::create([
            'link_id'   => $link->id,
            'user_id'   => $user->id,
            'domain_id' => $oneInMe->id,
            'alias'     => $extra,
        ]);

        $this->assertTrue(AliasNamespace::isTaken($extra, $oneInMe->id));
        $this->assertTrue(AliasNamespace::isTaken(Str::upper($extra), $oneInMe->id));
        $this->assertFalse(AliasNamespace::isTaken($extra, $sayzio->id));
    }

    public function test_null_domain_is_equivalent_to_the_default_platform_domain(): void
    {
        $user    = $this->makeUser();
        $sayzio  = $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $oneInMe = $this->globalBrandDomain(self::BRAND_SECONDARY);

        // A legacy NULL-domain link occupies the DEFAULT (sayzio.app) bucket.
        $alias = 'legacy-' . Str::lower(Str::random(6));
        $this->makeUrlLink($user, null, $alias);

        $this->assertTrue(AliasNamespace::isTaken($alias, null));
        $this->assertTrue(AliasNamespace::isTaken($alias, $sayzio->id));
        $this->assertFalse(AliasNamespace::isTaken($alias, $oneInMe->id));

        // And the reverse: a sayzio-bound alias blocks the NULL bucket too.
        $alias2 = 'bound-' . Str::lower(Str::random(6));
        $this->makeUrlLink($user, $sayzio->id, $alias2);
        $this->assertTrue(AliasNamespace::isTaken($alias2, null));
    }

    public function test_ignore_link_id_excludes_the_links_own_row(): void
    {
        $user   = $this->makeUser();
        $sayzio = $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);

        $alias = 'self-' . Str::lower(Str::random(6));
        $link  = $this->makeUrlLink($user, $sayzio->id, $alias);

        // A link keeping its own alias is not "taken" against itself…
        $this->assertFalse(AliasNamespace::isTaken($alias, $sayzio->id, $link->id));
        // …but is against everyone else.
        $this->assertTrue(AliasNamespace::isTaken($alias, $sayzio->id));
    }

    public function test_check_alias_endpoint_honours_the_requested_domain(): void
    {
        $owner  = $this->makeUser();
        $sayzio = $this->globalBrandDomain(self::BRAND_PRIMARY, primary: true);
        $oneInMe = $this->globalBrandDomain(self::BRAND_SECONDARY);

        $alias = 'chk-' . Str::lower(Str::random(6));
        $this->makeUrlLink($owner, $sayzio->id, $alias);

        $checker = $this->makeUser();
        $this->actingAs($checker);

        // Taken on sayzio.app (and via the NULL/default bucket)…
        $this->getJson(route('user.links.check-alias', [
            'alias' => $alias, 'domain_id' => $sayzio->id,
        ]))->assertOk()->assertJsonPath('available', false);
        $this->getJson(route('user.links.check-alias', [
            'alias' => $alias,
        ]))->assertOk()->assertJsonPath('available', false);

        // …but free on 1in.me.
        $this->getJson(route('user.links.check-alias', [
            'alias' => $alias, 'domain_id' => $oneInMe->id,
        ]))->assertOk()->assertJsonPath('available', true);
    }
}
