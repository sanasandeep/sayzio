<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\Resume;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guards that /sitemap-resumes.xml and /sitemap-links.xml only ever list
 * pages an anonymous crawler can actually see. Each public renderer emits
 * its own `noindex` / lock gate (resume-public.blade.php's $allowIndex,
 * biolink.blade.php's password wall and robots meta); the sitemap builder
 * must mirror those gates exactly or it hands crawlers URLs the app itself
 * refuses to let them index.
 */
class UserContentSitemapExclusionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $handle): User
    {
        return User::create([
            'name'     => 'Sitemap Test User',
            'email'    => $handle . '@example.test',
            'password' => bcrypt('secret'),
            'handle'   => $handle,
        ]);
    }

    public function test_resumes_sitemap_excludes_noindex_resumes(): void
    {
        $indexable = $this->makeUser('sm-resume-ok-' . uniqid());
        $noindexed = $this->makeUser('sm-resume-noindex-' . uniqid());

        Resume::create([
            'user_id'        => $indexable->id,
            'is_public'      => true,
            'is_default'     => true,
            'visibility'     => 'public',
            'allow_indexing' => true,
        ]);

        Resume::create([
            'user_id'        => $noindexed->id,
            'is_public'      => true,
            'is_default'     => true,
            'visibility'     => 'public',
            'allow_indexing' => false,
        ]);

        $body = $this->get('/sitemap-resumes.xml')->getContent();

        $this->assertStringContainsString(url('/' . $indexable->handle . '/resume'), $body);
        $this->assertStringNotContainsString(url('/' . $noindexed->handle . '/resume'), $body);
    }

    public function test_links_sitemap_excludes_password_protected_biolinks(): void
    {
        $user = $this->makeUser('sm-link-pw-' . uniqid());

        $open = Link::create([
            'user_id'    => $user->id,
            'type'       => 'biolink',
            'alias'      => 'open' . bin2hex(random_bytes(3)),
            'title'      => 'Open biolink',
            'is_active'  => true,
            'visibility' => 'public',
        ]);

        $locked = Link::create([
            'user_id'                 => $user->id,
            'type'                    => 'biolink',
            'alias'                   => 'locked' . bin2hex(random_bytes(3)),
            'title'                   => 'Locked biolink',
            'is_active'               => true,
            'visibility'              => 'public',
            'is_password_protected'   => true,
            'password'                => bcrypt('secret'),
        ]);

        $body = $this->get('/sitemap-links.xml')->getContent();

        $this->assertStringContainsString(url('/' . $open->alias), $body);
        $this->assertStringNotContainsString(url('/' . $locked->alias), $body);
    }

    public function test_links_sitemap_excludes_noindex_biolinks(): void
    {
        $user = $this->makeUser('sm-link-noindex-' . uniqid());

        $open = Link::create([
            'user_id'    => $user->id,
            'type'       => 'biolink',
            'alias'      => 'openb' . bin2hex(random_bytes(3)),
            'title'      => 'Open biolink',
            'is_active'  => true,
            'visibility' => 'public',
        ]);

        $noindexed = Link::create([
            'user_id'    => $user->id,
            'type'       => 'biolink',
            'alias'      => 'noindexb' . bin2hex(random_bytes(3)),
            'title'      => 'Noindex biolink',
            'is_active'  => true,
            'visibility' => 'public',
            'settings'   => [
                'biolink' => [
                    'meta' => [
                        'robots' => 'noindex,nofollow',
                    ],
                ],
            ],
        ]);

        $body = $this->get('/sitemap-links.xml')->getContent();

        $this->assertStringContainsString(url('/' . $open->alias), $body);
        $this->assertStringNotContainsString(url('/' . $noindexed->alias), $body);
    }
}
