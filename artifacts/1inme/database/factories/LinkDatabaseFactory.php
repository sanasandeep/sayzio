<?php

namespace Database\Factories;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Factory for the canonical {@see Link} model
 * (`App\Modules\User\Models\Link`).
 *
 * Wired to the model via {@see Link::newFactory()} for exactly the reason
 * documented on {@see UserDatabaseFactory}: the model lives under a module
 * namespace, so Laravel's default resolver derives
 * `Database\Factories\Modules\User\Models\LinkFactory` -- a class that does not
 * exist. Without both halves (the `HasFactory` trait and the `newFactory()`
 * override) `Link::factory()` throws BadMethodCallException.
 *
 * Before this factory existed that exception fired in `setUp()`, erroring
 * every test in the classes that reach for it -- UpdatesPageTest,
 * ProfileShowcaseTest and GlobalSearchOverlayTest -- before a single assertion
 * ran, and taking the whole `php artisan test` job red with it.
 *
 * The default state is the least surprising link: an active, public short URL
 * owned by a fresh user. `alias` is unique because the column is, and is
 * lowercased since aliases are looked up case-sensitively on the public route.
 *
 * Every key below is a real `links` column, which
 * `scripts/check-factory-columns.php` enforces at CI time.
 *
 * @extends Factory<Link>
 */
class LinkDatabaseFactory extends Factory
{
    protected $model = Link::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'type'       => 'url',
            'alias'      => 'lnk'.Str::lower(Str::random(12)),
            'title'      => 'L '.Str::random(4),
            'long_url'   => 'https://example.com/'.Str::lower(Str::random(8)),
            'is_active'  => true,
            'visibility' => 'public',
        ];
    }
}
