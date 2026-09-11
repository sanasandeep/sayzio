<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The homepage only ever shows a domain Sana actually owns.
 *
 * It was showing several he does not. `1inme.co` seven times -- the old brand
 * spelling, never registered under `.co`. `sayzio.com` once, on a page served
 * from `sayzio.app`. `syz.io`, `studio.com`, `jane.co`, all invented, all
 * real domains that belong to somebody.
 *
 * The risk is not aesthetic. A visitor who reads `sayzio.com/pricing` in a
 * mock and types it in lands on a stranger's site, and the stranger gets to
 * decide what they find.
 *
 * Three kinds of domain are allowed here, and the distinction is the whole
 * test:
 *
 *   OWNED        sayzio.app, 1in.me, bizs.club, getbio.one -- ours to show
 *   PLACEHOLDER  yourbrand.com / .link -- the VISITOR's own domain in the
 *                bring-your-own-domain sections. Replacing these with an
 *                owned domain would invert what those sections say.
 *   RESERVED     example.com -- IANA keeps it unregistrable, which is what
 *                makes it safe for a person's email in a mock. A stranger's
 *                address should not carry our brand, and it must not carry a
 *                real company's either.
 *
 * Everything else is a third-party service we genuinely integrate with, named
 * explicitly below, or a mistake.
 */
class HomepageUsesOwnedDomainsTest extends TestCase
{
    /** Domains Sana owns. */
    private const OWNED = ['sayzio.app', '1in.me', 'bizs.club', 'getbio.one'];

    /** Stand-ins for the visitor's own domain, and IANA's reserved example. */
    private const PLACEHOLDERS = ['yourbrand.com', 'yourbrand.link', 'example.com'];

    /** Real services the page refers to on purpose. */
    private const THIRD_PARTY = ['stripe.com', 'googleapis.com', 'gstatic.com', 'cloudflare.com', 'github.com'];

    /** @return list<string> */
    private function homepageViews(): array
    {
        $found = [resource_path('views/home.blade.php')];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views/home'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    public function test_the_homepage_shows_no_domain_we_do_not_own(): void
    {
        $allowed = array_merge(self::OWNED, self::PLACEHOLDERS, self::THIRD_PARTY);
        $offenders = [];

        foreach ($this->homepageViews() as $path) {
            $short = str_replace(resource_path('views/'), '', $path);
            $source = (string) file_get_contents($path);

            // A Blade include path like `home.partials.share-visual` looks
            // exactly like a domain to a regex. Strip directives first.
            $source = preg_replace('/@[a-z]+\s*\([^)]*\)/i', '', $source) ?? $source;

            preg_match_all(
                '/\b([a-z0-9][a-z0-9-]*\.(?:app|me|club|one|com|io|co|link|net|org|shop|store))\b/i',
                $source,
                $hits,
                PREG_OFFSET_CAPTURE
            );

            foreach ($hits[1] as [$domain, $offset]) {
                $domain = strtolower($domain);

                if (in_array($domain, $allowed, true)) {
                    continue;
                }

                $line = substr_count(substr($source, 0, $offset), "\n") + 1;
                $offenders[] = "{$short}:{$line}  {$domain}";
            }
        }

        $this->assertSame([], $offenders, sprintf(
            "The homepage shows a domain Sana does not own.\n\n"
            . "A visitor who reads one of these in a mock and types it in lands on\n"
            . "somebody else's site.\n\n"
            . "Owned: %s\n"
            . "Placeholders for the visitor's own domain: %s\n"
            . "IANA-reserved, safe for a person's email in a mock: example.com\n\n%d found:\n  %s",
            implode(', ', self::OWNED),
            implode(', ', ['yourbrand.com', 'yourbrand.link']),
            count($offenders),
            implode("\n  ", $offenders)
        ));
    }

    /**
     * The scan has to be reading views that really do name domains.
     *
     * It passes against an empty set, and an empty set is what a path that
     * stopped resolving produces.
     */
    public function test_the_scan_finds_domains_to_judge(): void
    {
        $seen = 0;

        foreach ($this->homepageViews() as $path) {
            $seen += preg_match_all(
                '/\b(?:1in\.me|sayzio\.app|yourbrand\.com)\b/i',
                (string) file_get_contents($path)
            );
        }

        $this->assertGreaterThan(
            10,
            $seen,
            'the domain scan is finding almost nothing; it is no longer reading the homepage views'
        );
    }
}
