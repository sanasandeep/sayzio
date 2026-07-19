<?php

namespace Database\Seeders;

use App\Modules\Admin\Models\Admin;
use App\Modules\Admin\Models\Role;
use App\Modules\Common\Models\BlogCategory;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\BlogTag;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeds 25+ marketing-oriented blog posts so the public blog has rich
 * content to link into landing pages from day one. Fully idempotent:
 * matches by slug and updates instead of duplicating, so it can be re-run
 * any time without creating noise.
 *
 * Posts use the existing BlogPost / BlogCategory / BlogTag models and
 * remain editable from the admin blog UI like any hand-authored post.
 *
 * Guarded by the SEED_BLOG_POSTS env var (defaults true). Set
 * SEED_BLOG_POSTS=false in environments where you do not want the
 * marketing posts seeded — e.g. an isolated test database, or a
 * customer-facing instance that already has its own editorial content.
 */
class MarketingBlogPostsSeeder extends Seeder
{
    public function run(): void
    {
        // Production-style guard: opt out via env without touching code.
        // Defaults to ON so a fresh `php artisan db:seed` includes the
        // marketing content and the public blog isn't empty.
        if (filter_var(env('SEED_BLOG_POSTS', true), FILTER_VALIDATE_BOOLEAN) === false) {
            $this->command?->info('MarketingBlogPostsSeeder skipped (SEED_BLOG_POSTS=false).');
            return;
        }

        $author = $this->resolveAuthor();
        $categories = $this->ensureCategories();
        $tagCache = [];
        $defs = $this->posts();

        $extras = $this->extraSections();
        $faqs = $this->faqSections();

        foreach ($defs as $i => $def) {
            $cat = $categories[$def['category']] ?? null;

            $sections = $def['sections'];
            if (isset($extras[$def['slug']])) {
                foreach ($extras[$def['slug']] as $extra) {
                    $sections[] = $extra;
                }
            }
            if (isset($faqs[$def['slug']])) {
                $sections[] = $faqs[$def['slug']];
            }
            $sections[] = $this->practiceSection($def);

            $bodyHtml = $this->renderBody($def['intro'], $sections, $def['outro'] ?? null);
            $excerpt = $def['excerpt'];

            $publishedAt = Carbon::now()
                ->subDays((count($defs) - $i) * 4 + 2)
                ->setTime(9 + ($i % 8), ($i * 7) % 60);

            $post = BlogPost::updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'title'             => $def['title'],
                    'excerpt'           => $excerpt,
                    'body_html'         => $bodyHtml,
                    'cover_image'       => $def['cover_image'] ?? $this->brandedCover($def['slug']),
                    'category_id'       => $cat?->id,
                    'author_id'         => $author?->id,
                    'status'            => 'published',
                    'published_at'      => $publishedAt,
                    'scheduled_at'      => null,
                    'meta_title'        => $def['meta_title'] ?? $def['title'],
                    'meta_description'  => $def['meta_description'] ?? $excerpt,
                    'is_featured_home'  => $i < 3,
                    'featured_slot'     => $i === 0 ? 'hero' : ($i < 3 ? 'carousel' : null),
                    'allow_comments'    => true,
                ]
            );

            $tagIds = [];
            foreach ($def['tags'] as $name) {
                $slug = Str::slug($name);
                if (!isset($tagCache[$slug])) {
                    $tagCache[$slug] = BlogTag::firstOrCreate(
                        ['slug' => $slug],
                        ['name' => $name]
                    );
                }
                $tagIds[] = $tagCache[$slug]->id;
            }
            $post->tags()->sync(array_unique($tagIds));
        }
    }

    /**
     * Pick the most appropriate admin to attribute posts to. Prefers a
     * super-admin so the author always has publish permission; falls
     * back to the first admin if there isn't one. If the database has
     * no admin at all (running this seeder standalone on a fresh DB),
     * provisions a deterministic default super-admin so every seeded
     * post still has a non-null author and is editable from admin.
     */
    private function resolveAuthor(): Admin
    {
        $admin = Admin::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super-admin'))
            ->orderBy('id')
            ->first();

        if (!$admin) {
            $admin = Admin::orderBy('id')->first();
        }

        if ($admin) {
            return $admin;
        }

        // Standalone seeding: ensure a default super-admin role and admin
        // exist so blog posts always have an author. Idempotent — if the
        // role or admin already exists, the firstOrCreate is a no-op.
        $role = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full access to all admin features',
                'guard' => 'admin',
            ]
        );

        return Admin::firstOrCreate(
            ['email' => 'content@1inme.com'],
            [
                'name' => 'Sayzio Content',
                'password' => Hash::make(Str::random(40)),
                'role_id' => $role->id,
                'status' => 'active',
            ]
        );
    }

    /**
     * Ensure all categories used by the seed posts exist. Returns a
     * map keyed by the seed-side short key (e.g. `biolinks`) so the
     * post definitions can reference them without juggling IDs.
     *
     * @return array<string, BlogCategory>
     */
    private function ensureCategories(): array
    {
        $defs = [
            'biolinks'      => ['name' => 'Biolinks',          'color' => '#3d6bff', 'sort' => 1, 'desc' => 'Tips for building a high-converting link-in-bio page.'],
            'creator-growth' => ['name' => 'Creator Growth',    'color' => '#0ea5e9', 'sort' => 2, 'desc' => 'How creators grow their audience and turn it into income.'],
            'monetization'  => ['name' => 'Monetization',      'color' => '#16a34a', 'sort' => 3, 'desc' => 'Turning followers and visitors into customers and members.'],
            'analytics'     => ['name' => 'Analytics',         'color' => '#f59e0b', 'sort' => 4, 'desc' => 'Measuring what matters across links, pages and campaigns.'],
            'seo'           => ['name' => 'SEO for Creators',  'color' => '#ef4444', 'sort' => 5, 'desc' => 'Search-engine playbooks aimed at creators and small brands.'],
            'audience'      => ['name' => 'Audience Building', 'color' => '#ec4899', 'sort' => 6, 'desc' => 'Building a loyal audience across newsletter, social and beyond.'],
            'product'       => ['name' => 'Product & Updates', 'color' => '#64748b', 'sort' => 7, 'desc' => 'How Sayzio is evolving and how to get the most out of it.'],
        ];

        $out = [];
        foreach ($defs as $key => $d) {
            $slug = Str::slug($d['name']);
            // Branded category cover SVG generated alongside the post heroes
            // (storage/app/public/blogs/categories/{slug}.svg). Only set it
            // when the file actually exists so future custom categories
            // don't end up with broken image URLs.
            $coverRel = 'blogs/categories/' . $slug . '.svg';
            $cover = Storage::disk('public')->exists($coverRel)
                ? '/storage/' . $coverRel
                : null;

            $out[$key] = BlogCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name'        => $d['name'],
                    'color'       => $d['color'],
                    'sort_order'  => $d['sort'],
                    'description' => $d['desc'],
                    'cover_image' => $cover,
                ]
            );
        }
        return $out;
    }

    private function renderBody(string $intro, array $sections, ?string $outro = null): string
    {
        $parts = [];
        $parts[] = '<p class="lead">' . $intro . '</p>';
        foreach ($sections as $s) {
            $parts[] = '<h2>' . htmlspecialchars($s['heading']) . '</h2>';
            foreach ((array) ($s['paragraphs'] ?? []) as $p) {
                $parts[] = '<p>' . $p . '</p>';
            }
            if (!empty($s['list'])) {
                $tag = ($s['list_type'] ?? 'ul') === 'ol' ? 'ol' : 'ul';
                $parts[] = '<' . $tag . '>';
                foreach ($s['list'] as $li) {
                    $parts[] = '<li>' . $li . '</li>';
                }
                $parts[] = '</' . $tag . '>';
            }
            if (!empty($s['quote'])) {
                $parts[] = '<blockquote><p>' . $s['quote'] . '</p></blockquote>';
            }
        }
        if ($outro) {
            $parts[] = '<h2>Wrapping up</h2>';
            $parts[] = '<p>' . $outro . '</p>';
        }
        return implode("\n", $parts);
    }

    /**
     * Returns the URL for a branded, on-brand hero image that lives in the
     * public storage disk (storage/app/public/blogs/posts/{slug}.svg, served
     * via the /storage symlink). Each of the seeded posts has a matching
     * SVG generated up front so the public blog renders branded artwork
     * instead of generic placeholder photos. If for some reason the file
     * is missing (e.g. someone added a post but didn't regenerate the
     * artwork), we fall back to a generic branded cover (the Biolinks
     * category SVG) so the page never renders a broken image.
     */
    private function brandedCover(string $slug): string
    {
        $rel = 'blogs/posts/' . $slug . '.svg';
        if (Storage::disk('public')->exists($rel)) {
            return '/storage/' . $rel;
        }
        return '/storage/blogs/categories/biolinks.svg';
    }

    /**
     * Extra closing sections appended to each post by slug. Keeps the
     * inline post catalog readable while letting every published post
     * land comfortably in the 600–1,000 word target. Each entry is a
     * normal section ([heading, paragraphs[], list?[]]).
     *
     * @return array<string, array<int, array{heading:string,paragraphs?:array,list?:array,list_type?:string}>>
     */
    private function extraSections(): array
    {
        return [
            'anatomy-of-a-high-converting-link-in-bio-page' => [
                ['heading' => 'A 30-minute audit you can run today', 'paragraphs' => [
                    'Open your page on a phone you have never visited it from. Time how long it takes you to (a) understand what you do and (b) find the primary action. If either takes more than five seconds, the page has work to do, usually in the tagline or the order of the top three blocks.',
                    'Then open the analytics for the last 30 days, sort blocks by tap rate descending, and ask of every block in the bottom half: "would I miss this if it disappeared tomorrow?" If the honest answer is no, delete it. Pages with fewer, sharper blocks consistently outperform busier pages with the same traffic.',
                ]],
            ],
            '15-link-in-bio-mistakes-that-kill-conversions' => [
                ['heading' => 'How to prioritise the fixes', 'paragraphs' => [
                    'You don\'t have to fix all fifteen this week. Start with the three mistakes that affect the largest number of visitors; usually the buried CTA, the generic button labels, and the missing proof, because each of those is in the path of every single visitor. The bottom-of-page CTA, image weight, and UTM hygiene can wait until next week.',
                    'A useful rule: every fix you make should be measurable in your Sayzio analytics within two weeks. If you can\'t imagine how you\'d see the impact, the fix is probably not worth the time.',
                ]],
            ],
            'designing-a-biolink-that-looks-like-your-brand' => [
                ['heading' => 'The thirty-minute brand pass', 'paragraphs' => [
                    'Block thirty minutes this week. Open your biolink on one screen and your most-used Instagram post on another. Walk through the page block by block and ask: "does this feel like the same brand?" Where the answer is no, change either the biolink or the post, but make them match.',
                    'Most creators discover that their biolink is the weakest brand surface they own, simply because they spend more time on individual posts than on the page that everything links to. A single afternoon of brand alignment can lift conversion by double digits with no copy changes.',
                ]],
            ],
            'how-to-ab-test-your-biolink' => [
                ['heading' => 'Documenting your tests', 'paragraphs' => [
                    'Keep a single text file or note with one line per test: what you changed, when, the before number, the after number, and the verdict. After a year you\'ll have a personal playbook of what works on your audience specifically, far more useful than any generic "best practice" article.',
                    'Reviewing the log every quarter also stops you from accidentally re-running tests you already ran (a more common trap than you\'d think) and helps you spot patterns: maybe verb-led labels always win on your page, or photos always beat illustrations.',
                ]],
            ],
            'smart-links-101' => [
                ['heading' => 'Three campaigns to try smart links on first', 'list_type' => 'ol', 'list' => [
                    'For your next product or content launch, route mobile vs desktop visitors to format-appropriate landing pages.',
                    'A pinned biolink "latest" block: keep the URL stable and just edit the destinations behind it as you publish new things.',
                    'Cross-promotion partner links: give each partner a unique smart link with their own UTM so you can attribute and reciprocate cleanly.',
                ]],
                ['heading' => 'When NOT to use a smart link', 'paragraphs' => [
                    'Don\'t use a smart link when a plain link would do. Stable destinations like your homepage, your podcast feed, or your booking page should be plain short links, easier to debug, easier to share, and they show up correctly in link previews on every platform.',
                ]],
            ],
            'from-zero-to-1000-real-followers-90-day-playbook' => [
                ['heading' => 'What "engaged" actually means', 'paragraphs' => [
                    'A thousand "engaged" followers is not a thousand random follows from a giveaway. It is a thousand people who would notice if you stopped posting for two weeks. The cleanest single proxy for this is the ratio of comments to followers: at the engagement level we are aiming for, you should be getting at least one comment per fifty followers on average.',
                    'If you are growing fast but your engagement ratio is dropping, the new followers are the wrong followers. Slow down, narrow the niche, and let quality compound rather than chasing a vanity number.',
                ]],
            ],
            'why-niching-down-grows-you-faster' => [
                ['heading' => 'How narrow is too narrow?', 'paragraphs' => [
                    'A useful test: can you imagine a hundred people who would describe themselves with the exact phrase your niche targets? "Yoga for runners with tight hips" passes that test; "yoga for left-handed Capricorn runners" probably doesn\'t. The first earns a steady audience over time; the second runs out of people in month three.',
                    'If you\'re unsure, err narrow. Niches expand on their own once you have an audience. They don\'t contract gracefully if you start broad.',
                ]],
            ],
            'comments-section-as-marketing-channel' => [
                ['heading' => 'A weekly review habit', 'paragraphs' => [
                    'Every Friday, scroll back through the week\'s comments and bookmark the three best questions you got. They become Monday\'s post topics. This single habit eliminates the "what should I post about?" anxiety that consumes most creators\' weekend, and it guarantees the content lands because the audience asked for it directly.',
                ]],
            ],
            'repurposing-one-idea-into-ten-pieces' => [
                ['heading' => 'When to retire an idea', 'paragraphs' => [
                    'After ten outputs from a single source asset, the idea is usually exhausted from your audience\'s perspective even if you still have more to say. Retire it. Move on to the next source asset. The discipline of stopping is as important as the discipline of repurposing; beating a dead horse trains the algorithm to deprioritise your account.',
                ]],
            ],
            'selling-digital-products-from-your-biolink' => [
                ['heading' => 'A weekly pitch slot', 'paragraphs' => [
                    'Pick one day a week (Friday is popular) where one of your social posts is allowed to be an explicit pitch for the product. The other six days are pure value, behind-the-scenes, or community engagement. This rhythm trains your audience that you sell something without ever feeling like a constant sales pitch, and the Friday post earns disproportionate clicks because it stands out from the rest of the week.',
                ]],
            ],
            'pricing-your-first-digital-product' => [
                ['heading' => 'Refunds are not failure', 'paragraphs' => [
                    'A refund rate under 5% is a sign your pricing and pitch are aligned. A refund rate of zero usually means your audience is small or your pricing is too cheap. A refund rate above 10% means the pitch is overpromising, the product is underdelivering, or both. Treat the refund rate as a feedback signal, not as a personal verdict.',
                    'Make the refund process easy and unconfrontational. Easy refunds raise trust and almost never get abused at creator scale. Friction-laden refund processes generate angry tweets that cost more than the refunds they prevent.',
                ]],
            ],
            'recurring-revenue-for-creators' => [
                ['heading' => 'The first 100 members', 'paragraphs' => [
                    'The first 100 members are the hardest. After that, the social proof of having a real community starts to do the selling for you, and word-of-mouth kicks in. Most creators give up at member 30 because growth feels glacial. The maths reward patience here more than almost anywhere else in a creator business.',
                    'Lifetime memberships, sold sparingly, can be a useful early-cash injection, but only if you have a clear, sustainable cost structure for delivering forever. Most creators regret lifetime tiers within 18 months.',
                ]],
            ],
            'sponsorships-101-first-brand-deal' => [
                ['heading' => 'Build a tiny media kit', 'paragraphs' => [
                    'A one-page PDF with your audience size, demographics, top recent posts, and three short testimonials from past partners (even unpaid ones) closes more deals than a glossy ten-page kit. Brands skim, so make the skim productive. Update it once a quarter so the numbers stay current.',
                ]],
            ],
            'five-numbers-creators-watch-weekly' => [
                ['heading' => 'A Friday ten-minute ritual', 'paragraphs' => [
                    'Block ten minutes every Friday afternoon. Open the dashboard, glance at the five tiles, write a single sentence in your notes about what changed and why you think it changed. After a quarter, those thirteen sentences are a real story about how the business is moving, much more useful than any chart.',
                    'Resist the urge to look daily. Daily checking trains anxiety; weekly checking trains pattern recognition.',
                ]],
            ],
            'utm-tags-without-tears' => [
                ['heading' => 'A naming cheat sheet', 'paragraphs' => [
                    'Pin a small note in your dashboard with the exact source/medium values you use. "instagram-bio", "instagram-story", "youtube-description", "newsletter-issue", "podcast-shownotes". Whenever you create a new short link, copy from the cheat sheet rather than typing fresh, since typos are the single biggest cause of analytics chaos for solo creators.',
                ]],
            ],
            'reading-your-biolink-heatmap' => [
                ['heading' => 'A monthly trim', 'paragraphs' => [
                    'On the first of every month, identify the lowest-performing block on your page and either rewrite or delete it. This single recurring habit keeps your page lean and ensures you are always experimenting with the part that needs help most. It also avoids the slow accumulation of dead weight that quietly degrades every other block on the page.',
                ]],
            ],
            'seo-basics-for-creators-who-hate-seo' => [
                ['heading' => 'A monthly SEO routine', 'paragraphs' => [
                    'Once a month, open Google Search Console and look at two reports: Performance (which queries are bringing visitors) and Coverage (any pages Google could not index). Fix the indexing errors first, they cost you nothing. Then look at the queries that are nearly ranking (positions 8–15) and lightly improve those posts; they are the closest to first-page traffic and the highest-leverage edits you can make.',
                ]],
            ],
            'long-tail-keywords-secret-weapon' => [
                ['heading' => 'A simple monthly cadence', 'paragraphs' => [
                    'Aim for two long-tail posts per month. Twenty-four posts a year, each targeting a specific phrase, is enough to build a meaningful organic channel within eighteen months. The compounding only kicks in if you keep going past the point where it feels pointless; month four is usually when most creators give up, just before the early posts start ranking.',
                ]],
            ],
            'internal-linking-for-creators' => [
                ['heading' => 'Treat your archive as live', 'paragraphs' => [
                    'Old posts are not done. Once a quarter, pick five high-traffic posts from your archive and add fresh internal links to anything you have published since. Updating the publish date is optional, but adding new links is one of the highest-ROI things you can do for an existing post, as it brings the post back into Google\'s freshness signals and helps the new pages get discovered faster.',
                ]],
            ],
            'building-email-list-from-zero' => [
                ['heading' => 'A four-week sprint plan', 'list_type' => 'ol', 'list' => [
                    'Week 1: build the lead magnet (5-page PDF or single-email mini-course is enough).',
                    'Week 2: rewrite your biolink so newsletter signup is the primary block.',
                    'Week 3: write and schedule a 4-email welcome sequence.',
                    'Week 4: post about the lead magnet across every channel and pin the CTA on every post.',
                ]],
            ],
            'newsletter-cadence-weekly-biweekly' => [
                ['heading' => 'When to change cadence', 'paragraphs' => [
                    'Don\'t change cadence in response to one bad month. Open rates fluctuate seasonally, with launches, and with deliverability quirks. Only consider changing cadence after six months of clear, consistent signal, and even then, ship a single explicit email announcing the change so subscribers don\'t feel ghosted.',
                ]],
            ],
            'cross-promotions-growth-channel' => [
                ['heading' => 'A reusable swap pitch', 'paragraphs' => [
                    'Save a short, friendly pitch you can adapt to each new partner. "Hi X, I love your work on Y. I have Z subscribers in [niche], you have a similar-sized list in [adjacent niche], would you be up for a one-issue cross-promotion next month? Happy to go first." Three sentences is enough; longer pitches actively lower reply rates.',
                ]],
            ],
            'referral-programs-that-get-used' => [
                ['heading' => 'Re-promote it quarterly', 'paragraphs' => [
                    'Most referral programs die from neglect: the creator launches it once, then never mentions it again. Add a single "did you know we have a referral program?" mention to your newsletter once a quarter, with the unique link surfaced for that specific subscriber. Re-promotion produces more referrals than the original launch in almost every case.',
                ]],
            ],
            'scheduling-posts-show-up-consistently' => [
                ['heading' => 'A simple rescue plan', 'paragraphs' => [
                    'If you fall behind and the queue empties out, do not try to refill the buffer in one heroic weekend. That guarantees burnout. Instead, schedule one post a day for the next week, then start rebuilding the buffer one extra post at a time. Slow recovery beats heroic recovery every time, because the heroic version usually breaks again within a fortnight.',
                ]],
            ],
            'social-proof-on-a-biolink' => [
                ['heading' => 'How to ask for testimonials', 'paragraphs' => [
                    'The single best testimonial-asking template: "Would you mind sharing in one or two sentences what changed for you since [working with me / starting the course / joining]? Specific numbers or moments are more useful than general praise, and feel free to be honest." This phrasing produces specific, credible quotes far more often than a generic "could you write a testimonial?" request.',
                ]],
            ],
            'welcome-sequences-first-seven-days' => [
                ['heading' => 'Measuring whether it works', 'paragraphs' => [
                    'Track two numbers: the open rate of email five (the pitch) and the unsubscribe rate across the seven-day window. A pitch open rate above 35% means the early emails warmed subscribers up well. An unsubscribe rate over 10% in week one means the lead magnet attracted the wrong audience or the welcome cadence was too aggressive; adjust accordingly.',
                ]],
            ],
            'whats-new-in-1inme-smarter-biolinks-cleaner-analytics' => [
                ['heading' => 'How to stay in the loop', 'paragraphs' => [
                    'We post a roundup like this every six weeks or so. Subscribe to the Sayzio newsletter from the footer of any page, and follow @1inme on your social platform of choice; the most-discussed updates usually start as a single post asking what creators want to see next. Your reply genuinely makes its way into the roadmap.',
                ]],
            ],
        ];
    }

    /**
     * A short, practical wrap-up appended to every post. Tailored
     * lightly to the post category so it doesn't read as boilerplate
     * across the catalog.
     */
    private function practiceSection(array $def): array
    {
        $cat = $def['category'];
        $title = $def['title'];
        $intros = [
            'biolinks'        => 'Biolink wins compound. Even one of the steps below, applied this week, will quietly push your conversion rate above where it was when you started reading.',
            'creator-growth'  => 'Growth is built from small, repeatable habits more than from any single insight. Pick one habit below, schedule it, and revisit it in thirty days.',
            'monetization'    => 'Monetisation is rarely the limiting factor: execution is. Pick the smallest viable change you can ship this week and let the data tell you whether to push harder.',
            'analytics'       => 'Analytics only pay back when they change behaviour. Block twenty minutes this week to act on one of the steps below; otherwise the dashboard is decoration.',
            'seo'             => 'Search-engine work compounds slowly and then suddenly. The single most important thing is consistency: pick one habit and run it for at least six months before judging the result.',
            'audience'        => 'Audience growth at small scale is mostly about removing friction and showing up reliably. Pick the lowest-friction step below and start it this week.',
            'product'         => 'Product updates are most useful when you actually try them. Spend ten minutes in the dashboard this week to confirm the workflow improvements land for your specific use case.',
        ];
        $intro = $intros[$cat] ?? $intros['creator-growth'];

        return [
            'heading' => 'Putting it into practice this week',
            'paragraphs' => [
                $intro,
                'A practical way to make the ideas above stick: pick a single sentence from this post that you disagree with or feel unsure about, write it down, and run a small test against it on your own page or audience over the next two weeks. The act of choosing one specific thing to verify forces real engagement with the material, and the resulting data is yours, specific to your audience, and far more valuable than any general best-practice article (including this one).',
                'If a particular section of "' . $title . '" landed for you, share it with one other creator in your network. Most of the creators we have helped get unstuck moved fastest when they had one peer working through the same playbook on the same timeline. Accountability is the cheapest growth hack there is, and it costs nothing but a single message.',
            ],
        ];
    }

    /**
     * One additional Q&A-style section per slug. Adds another ~150
     * words of practical content per post and helps each piece rank
     * for "people also ask" snippets in search.
     *
     * @return array<string, array{heading:string,paragraphs:array}>
     */
    private function faqSections(): array
    {
        $mk = function (array $qa): array {
            $paragraphs = [];
            foreach ($qa as $q => $a) {
                $paragraphs[] = '<strong>' . $q . '</strong> ' . $a;
            }
            return ['heading' => 'Common questions readers ask', 'paragraphs' => $paragraphs];
        };

        return [
            'anatomy-of-a-high-converting-link-in-bio-page' => $mk([
                'How many blocks should I have on my biolink?' => 'Aim for between five and seven. Below five and the page feels under-built; above seven and conversion to your primary action drops sharply because the eye has too many places to go.',
                'Should the primary CTA be a button or a form?' => 'A button is simpler and converts better in most cases. Inline forms only outperform when the offer is something like a one-field email signup with a strong, immediate reward like an instant download.',
                'Do I need a separate biolink for each platform?' => 'Usually no. One well-built page beats three rushed ones. The exception is if your audiences on different platforms want completely different things, for example, a B2B LinkedIn audience and a creative Instagram audience.',
            ]),
            '15-link-in-bio-mistakes-that-kill-conversions' => $mk([
                'How often should I audit my biolink?' => 'Once a month is enough for most creators. Anything more frequent and you\'ll be reacting to noise; anything less and you\'ll let problems compound for too long before noticing them.',
                'Is it bad to have many social icons?' => 'It is when they distract from your primary action. Five or six icons in a single row at the bottom of the page is fine; a wall of social icons above the CTA is not.',
                'What\'s the single most important fix to make first?' => 'Verb-led button labels. They cost nothing, take five minutes, and consistently produce the largest single-change improvement in tap-through rates we measure.',
            ]),
            'designing-a-biolink-that-looks-like-your-brand' => $mk([
                'Do I need custom fonts?' => 'No. The default fonts in Sayzio\'s themes look great. Custom fonts are a tax on page load and rarely move the needle on conversion. Spend the energy on accent colour and photo selection instead.',
                'How important is dark mode?' => 'Match whichever mode dominates your existing brand surfaces. Inconsistency between channels is more jarring than picking the "wrong" mode.',
                'Should I show my logo on the biolink?' => 'Only if your audience already recognises it. Otherwise a clean photo of you outperforms any logo, because faces build trust faster than marks.',
            ]),
            'how-to-ab-test-your-biolink' => $mk([
                'How much traffic do I need to A/B test usefully?' => 'Roughly 30 taps per day on the block you\'re testing is a reasonable floor. Below that, the noise is bigger than any signal you\'re likely to detect within a fortnight.',
                'Can I test two changes at once?' => 'Avoid it unless you have hundreds of taps a day. With small traffic you cannot reliably attribute the result to either change, and you\'ll end up second-guessing both.',
                'What\'s a "good" tap-through rate?' => 'Highly niche-dependent, but for the primary CTA on a creator biolink, 8–15% is a healthy range. Below 5% suggests the offer or wording isn\'t landing.',
            ]),
            'smart-links-101' => $mk([
                'Do smart links hurt SEO?' => 'No. Smart links are server-side redirects that search engines handle correctly. The destination pages keep their own SEO; only the routing layer is dynamic.',
                'Will smart links break link previews on social?' => 'Mostly no. The preview is generated from the destination page that the platform\'s scraper hits. As long as your fallback destination has good open-graph tags, previews look fine.',
                'How many destinations is too many in one smart link?' => 'Past about five rules, the link becomes hard to maintain and easy to misconfigure. If you need more than that, it\'s usually time for a small landing page that lets visitors choose.',
            ]),
            'from-zero-to-1000-real-followers-90-day-playbook' => $mk([
                'What if I miss a week?' => 'Don\'t skip the next one to "make up". Just resume the cadence. The audience does not remember individual missed posts; they remember whether you reliably show up over months.',
                'Is 90 days realistic without paid ads?' => 'For most niches, yes, assuming roughly five hours a week of genuine effort and a niche narrow enough for the algorithm to place you. Very saturated niches may take twice as long.',
                'Should I buy followers to get past the cold-start?' => 'No. Bought followers tank engagement metrics, which the algorithm reads as a signal to deprioritise your account. The shortcut costs more than it saves.',
            ]),
            'why-niching-down-grows-you-faster' => $mk([
                'What if I get bored of my niche?' => 'Niches are starting points, not life sentences. Once you have an audience, you can broaden carefully; most successful creators end up significantly wider than where they started.',
                'How do I know if my niche is too crowded?' => 'A useful test: if the top ten search results are dominated by sites that have been at it for ten years and have huge backing, you\'ll struggle to rank organically. Pick a slightly more specific angle.',
                'Should I tell my niche on every post?' => 'Bake it into your bio and biolink, not every individual post. Posts can wander a little without confusing the algorithm, as long as the front door is consistent.',
            ]),
            'comments-section-as-marketing-channel' => $mk([
                'Should I reply to negative comments?' => 'Reply once, briefly, factually. Don\'t engage further. Long debates in the comments hurt the post\'s engagement signal far more than the original negative comment did.',
                'Do pinned comments hurt reach?' => 'No, they help. Pinned comments increase total comment count and dwell time, both of which feed positive engagement signals.',
                'How long should replies be?' => 'Short. A two-sentence reply that feels personal beats a paragraph that feels rehearsed. Aim for the energy of a quick text to a friend.',
            ]),
            'repurposing-one-idea-into-ten-pieces' => $mk([
                'Won\'t my audience notice they\'re seeing the same idea?' => 'Almost no one follows you on every platform, and even those who do don\'t pattern-match across formats. The reach overlap that creators worry about is much smaller than they assume.',
                'How many source assets per month?' => 'Two is plenty if you atomise each one well. One a week is the upper end for most creators; more than that and quality starts to suffer.',
                'Can I repurpose other people\'s ideas?' => 'You can ride a trend, but the strongest atomised content always traces back to your own thinking. Pure curation is a fragile content strategy.',
            ]),
            'selling-digital-products-from-your-biolink' => $mk([
                'Will my audience be annoyed if I start selling?' => 'A small handful will unsubscribe. The vast majority either won\'t notice or will be glad to support you. The unsubscribers were not going to buy from you anyway.',
                'How often is "too often" to mention the product?' => 'Once a week as a primary CTA, plus woven naturally into other content, is a sustainable balance. Daily explicit pitches usually backfire.',
                'Should I run discounts?' => 'Sparingly. Frequent discounts train your audience to wait for the next one. Reserve discounts for genuine occasions like a launch, an anniversary, or a holiday.',
            ]),
            'pricing-your-first-digital-product' => $mk([
                'What about Purchasing Power Parity (PPP) discounts?' => 'A simple per-region discount (often 30–60% off for lower-income regions) can meaningfully expand your customer base without cannibalising full-price sales.',
                'How long should my launch window be?' => 'Five to seven days for a first launch. Longer windows lose urgency; shorter windows leave money on the table from people who needed a few days to decide.',
                'Should I offer payment plans?' => 'For products over $200, yes. Payment plans typically lift conversion by 15–25% with manageable default rates if handled by a real payment provider.',
            ]),
            'recurring-revenue-for-creators' => $mk([
                'Should I let people pause their subscription?' => 'Yes. Pause options dramatically reduce churn; many subscribers who would otherwise cancel will pause and return. The friction cost is small; the retention gain is large.',
                'What about a free trial?' => 'Free trials work best when the product has an obvious, immediate "aha" moment. They work poorly when the value is slow-build. Choose based on your product, not based on what feels generous.',
                'How do I price the annual plan?' => 'Roughly 10× the monthly price (so two months free) is the conventional ratio. It\'s generous enough to convert and small enough not to cannibalise monthly revenue.',
            ]),
            'sponsorships-101-first-brand-deal' => $mk([
                'How much should I charge for my first deal?' => 'Aim for the equivalent of $20–40 per thousand engaged followers, adjusted for niche. Tech, finance, and B2B niches command higher rates; lifestyle niches usually less.',
                'Should I work for free for "exposure"?' => 'Almost never. Brands with budget pay; brands without budget pay in unkept promises. The exception is a high-prestige collaboration that genuinely opens doors.',
                'Do I need an agent?' => 'Not until you have multiple deals a month and the admin is genuinely costing you creative time. Below that, agents take a cut without adding much.',
            ]),
            'five-numbers-creators-watch-weekly' => $mk([
                'What if a metric goes flat for one week?' => 'Ignore it. One week is noise. Four consecutive weeks of flat is signal: that\'s the threshold for considering a real change.',
                'Should I share metrics publicly?' => 'Some creators do, transparently. It can build community and trust, but be ready for the audience to react to month-on-month dips. Most creators benefit from sharing yearly summaries instead.',
                'What\'s a healthy bio-tap-to-newsletter rate?' => 'Roughly 10–20% of bio taps converting into newsletter signups is a strong page. Below 5% suggests either the offer or the lead magnet needs work.',
            ]),
            'utm-tags-without-tears' => $mk([
                'Do UTMs make my links ugly?' => 'Yes, that\'s why short links exist. The short link hides the UTM payload behind a clean URL while preserving the tags through the redirect.',
                'Will UTMs interfere with link previews?' => 'No. The preview is generated from the destination page; the URL parameters do not change the og-image or og-title.',
                'Should I add UTMs to internal links on my own site?' => 'No. Internal-link UTMs overwrite the original campaign source and make every visitor look like they came from your own site. UTMs belong on links into your site, not within it.',
            ]),
            'reading-your-biolink-heatmap' => $mk([
                'How many taps before the heatmap is meaningful?' => 'Roughly 200 total taps per month. Below that, individual visitor behaviour skews the picture too much for confident decisions.',
                'Why does my hottest block sometimes have low conversion?' => 'Curiosity taps. The block label is intriguing but the destination disappoints; investigate the destination page before you change anything else.',
                'Should I look at a per-day heatmap?' => 'Generally no. Daily heatmaps are noisy. Stick to monthly views for decisions; daily views for spotting outright errors.',
            ]),
            'seo-basics-for-creators-who-hate-seo' => $mk([
                'How long until I see results from SEO?' => 'Three to six months for the first signs of organic traffic, twelve to eighteen months for it to become a meaningful channel. Patience is the unfair advantage.',
                'Do I need to write 2,000-word posts?' => 'No. The right length is whatever fully answers the reader\'s question. Forced length hurts more than it helps because readers bounce.',
                'Should I update old posts?' => 'Yes, periodically. Refreshing a high-performing post once or twice a year keeps it competitive and signals freshness to search engines.',
            ]),
            'long-tail-keywords-secret-weapon' => $mk([
                'What\'s the smallest search volume worth targeting?' => 'Anything above 50 monthly searches with clear buyer intent is fair game. Below that, only target if the keyword is part of a topical cluster you\'re building.',
                'Should I target zero-volume keywords?' => 'Sometimes. If the question is genuinely something your audience asks, ranking for it can drive high-intent traffic that volume estimators undercount.',
                'How do I avoid keyword cannibalisation?' => 'One post per primary keyword, internal-linked to related posts. Don\'t write three slightly-different posts on the same exact phrase; they compete with each other in search.',
            ]),
            'internal-linking-for-creators' => $mk([
                'How many internal links per post?' => 'Five to ten is a healthy range for an 800-word post. Too few wastes the opportunity; too many feels stuffed and dilutes the link equity each one passes.',
                'Should the anchor text be exact-match keywords?' => 'No, natural phrasing performs better and looks less manipulated. Exact-match anchors are a holdover from older SEO eras.',
                'Do nofollow internal links count?' => 'They count for navigation but not for SEO authority. Default to follow for internal links unless you have a specific reason not to.',
            ]),
            'building-email-list-from-zero' => $mk([
                'Which email provider should I use?' => 'Pick something cheap or free with good deliverability. The provider matters far less than your habits; most creator newsletters succeed or fail on cadence and content, not infrastructure.',
                'Should I use a double opt-in?' => 'Yes. Double opt-in protects deliverability long-term, even though it shaves a small percentage off raw signups. The trade is worth it.',
                'How do I avoid spam folders?' => 'Use a real domain (not Gmail) for sends, warm up new sending domains slowly, and never email a list you didn\'t earn.',
            ]),
            'newsletter-cadence-weekly-biweekly' => $mk([
                'What\'s the best day to send?' => 'Tuesday and Thursday mornings perform marginally better on average, but consistency matters more than day. Pick a day, keep it.',
                'Is it OK to skip a week?' => 'Once or twice a year, yes; your readers are humans too. Make it a pattern and engagement decays.',
                'Should I send a "new subscriber" different content?' => 'Yes, see our welcome sequences post. New subscribers should get a tailored four-email onboarding before joining the regular cadence.',
            ]),
            'cross-promotions-growth-channel' => $mk([
                'How do I find good partners?' => 'Look at who your subscribers also recommend in replies, who shows up in adjacent newsletters\' "loved this" sections, and who you genuinely already read. Authentic taste filters partners better than any tool.',
                'What\'s a fair conversion rate from a swap?' => 'A 1–3% click-to-subscribe rate is healthy. Below 1% suggests the audiences don\'t overlap; above 3% suggests you\'ve found a great pairing; do it again.',
                'Can I swap with much larger creators?' => 'Yes, but expect to bring something other than reciprocal reach to the table: exclusive content, a referral perk, or a willingness to introduce them to other creators in your network.',
            ]),
            'referral-programs-that-get-used' => $mk([
                'How do I prevent people referring themselves?' => 'Block signups from the same IP address, the same payment method, or with email aliases of the original address. Most referral platforms support this out of the box; build it in if you\'re rolling your own.',
                'Should the reward be cash or credit?' => 'Credit (toward your own product) almost always converts better and costs you less per referral. Cash rewards attract referral spammers.',
                'How long should referrals be tracked?' => 'A 30- to 60-day attribution window covers nearly all real referrals without rewarding ancient cookies.',
            ]),
            'scheduling-posts-show-up-consistently' => $mk([
                'Will scheduled posts hurt my reach?' => 'Modern algorithms don\'t penalise scheduled vs manual posts. The myth comes from older platform behaviour that no longer applies.',
                'How far in advance is safe to schedule?' => 'Two weeks is the sweet spot. Beyond that, references age and you risk shipping something that no longer feels current.',
                'What if a scheduled post lands during breaking news?' => 'Pause the queue and review. A non-urgent post landing during a serious news beat reads as tone-deaf and is one of the few unforced errors scheduling can cause.',
            ]),
            'social-proof-on-a-biolink' => $mk([
                'Can I use anonymous testimonials?' => 'Only if you have to (sensitive industries like health). For most creator niches, named-and-photographed testimonials convert dramatically better and are worth the extra effort to collect.',
                'How recent should testimonials be?' => 'Within the last twelve months ideally. Older testimonials read as stale and make readers wonder why you don\'t have newer ones.',
                'Is a star rating useful?' => 'Only if you have a meaningful sample size and a real platform behind it (Trustpilot, App Store). A self-published star rating reads as decorative.',
            ]),
            'welcome-sequences-first-seven-days' => $mk([
                'Should I personalise the emails by source?' => 'Once you have multiple sign-up sources, yes, even a single line referencing where they joined ("thanks for subscribing from the SEO post") raises engagement.',
                'How long should each email be?' => '150–400 words. Long enough to deliver value, short enough that subscribers actually finish them.',
                'When does the regular newsletter start?' => 'Either day eight or the next regular send date, whichever is later. Avoid stacking a regular send on top of a welcome email on the same day.',
            ]),
            'whats-new-in-1inme-smarter-biolinks-cleaner-analytics' => $mk([
                'Where can I see all changes?' => 'The full changelog lives in the dashboard footer. We summarise the highlights here every six weeks; the changelog is updated in real time.',
                'How do I request a feature?' => 'Reply to any Sayzio newsletter or message us through the in-app chat. The most-requested features get prioritised in the roadmap discussion every month.',
                'Will any of these changes break existing pages?' => 'No, we treat backwards compatibility seriously. Existing biolinks, short links, and analytics keep working exactly as they did.',
            ]),
        ];
    }

    /**
     * The actual post catalog. 26+ posts spread across 6+ marketing
     * categories with rich, ~600–1,000 word bodies, headings, and lists.
     */
    private function posts(): array
    {
        return [
            [
                'title' => 'The Anatomy of a High-Converting Link-in-Bio Page',
                'slug'  => 'anatomy-of-a-high-converting-link-in-bio-page',
                'category' => 'biolinks',
                'tags' => ['biolinks', 'conversion', 'design'],
                'excerpt' => 'A walkthrough of the building blocks that turn a tap on your bio link into followers, sales, and signups.',
                'meta_description' => 'The seven blocks every creator biolink needs, and how to order them so visitors actually take the action you want.',
                'intro' => 'Most link-in-bio pages get a tap and lose the visitor inside three seconds. The pages that don\'t, the ones that quietly turn followers into customers, almost always share the same skeleton. After reviewing thousands of creator pages on Sayzio, we keep seeing the same handful of building blocks in the same order. Here is what that skeleton looks like, why each piece exists, and how to assemble it on your own page in an afternoon.',
                'sections' => [
                    ['heading' => 'Start with a one-line promise', 'paragraphs' => [
                        'Your bio name and tagline are the first thing a visitor reads, and the only thing many of them will read at all. Make the tagline a promise, not a job title. "Helping new parents sleep through the night" beats "Sleep coach" every time, because it tells the visitor what they get if they stay on the page. Job titles describe you; promises describe what changes for them, and that is the only frame visitors care about in the first three seconds of a visit.',
                        'Keep it under twelve words and avoid emojis as a crutch. Emojis look festive on Instagram or TikTok where they have to compete with motion and music, but on a quiet landing page they read as noise and make the whole page feel less serious. The exception is a single, well-chosen emoji acting as a wayfinding cue (a microphone for podcasters, a paintbrush for artists). One is interesting; four is decoration.',
                        'Test your promise by reading it to a friend who does not know what you do. If they can describe your audience and your offer back to you in one sentence, the promise is working. If they can\'t, rewrite it.',
                    ]],
                    ['heading' => 'Lead with the highest-intent action', 'paragraphs' => [
                        'The first button below your name should be the one action you most want a visitor to take. Not the prettiest one. Not the newest one. Not the one you launched last week and are still excited about. The most valuable one. For most creators that means either "Book a call", "Join my newsletter", or "Buy my latest". Anchor that link visually: make it bigger than the others, give it a contrasting colour, and put it above every social grid.',
                        'Visitors are subconsciously scanning for the path of least resistance. If the first button is also the most prominent, they take it. If you bury the most valuable action three blocks down because it felt rude to put it first, most visitors will tap a social link instead and you will lose them to whichever feed they land in.',
                    ], 'list' => [
                        'Coach or consultant: a booking link to a discovery call.',
                        'Course creator: a free lead magnet that funnels into the paid course.',
                        'Musician: the streaming smartlink for the latest release.',
                        'E-commerce: the bestseller, not the catalog.',
                        'Newsletter writer: signup form with a clear benefit, not just "subscribe".',
                    ]],
                    ['heading' => 'Add proof, then more links', 'paragraphs' => [
                        'A short proof block (a single testimonial, a press logo strip, a meaningful follower or student count) placed right under the primary CTA does more for conversion than any extra link. Visitors evaluate strangers in fractions of a second, and proof is shorthand for "other people trusted this person and got value back". Even one well-chosen quote from a real customer with a real photo will lift conversion measurably.',
                        'Only after the proof should you add the wider menu of secondary links: socials, recent content, podcast episodes, contact form. These are the long tail of your page. They matter, but they are not what you are optimising for, so they belong below the fold.',
                    ]],
                    ['heading' => 'Track everything from day one', 'paragraphs' => [
                        'Sayzio counts taps on every block, so you can see which links earn their place and which deserve to be retired. Review it monthly. The biggest gains usually come from removing links, not adding them; every extra block lowers the conversion rate of every other block on the page by giving visitors more places to look.',
                        'Set yourself a soft cap of seven blocks. Anything past seven needs to either earn its keep in the analytics or get cut. Discipline beats decoration.',
                    ]],
                ],
                'outro' => 'Treat your biolink like a one-screen landing page, not a directory. Every block should earn its scroll, the primary CTA should be impossible to miss, and the page should look exactly like your brand looks everywhere else. Get those three things right and you have already beaten 90% of creator pages in the wild.',
            ],
            [
                'title' => '15 Link-in-Bio Mistakes That Quietly Kill Conversions',
                'slug'  => '15-link-in-bio-mistakes-that-kill-conversions',
                'category' => 'biolinks',
                'tags' => ['biolinks', 'conversion', 'creator-tips'],
                'excerpt' => 'The unforced errors we see on creator biolinks every week, and the quick fixes that recover the lost taps.',
                'intro' => 'After reviewing thousands of creator biolinks on Sayzio, the same handful of mistakes show up again and again. None of them are catastrophic on their own, but stacked together they can halve the conversion rate of an otherwise great page. The good news: every one of them is fixable in minutes, with no design skill, no copywriter, and no rebuild. Read through, find the three or four that apply to you, and ship the fixes the same afternoon.',
                'sections' => [
                    ['heading' => 'The fifteen most common offenders', 'list_type' => 'ol', 'list' => [
                        'Burying the most important link under a wall of social icons that visitors tap reflexively.',
                        'Using stock photos that don\'t match the rest of the brand and signal "template page".',
                        'Generic button labels like "Click here" instead of outcome labels like "Book your free call".',
                        'No proof anywhere: no testimonial, no screenshot, no follower or student count.',
                        'Tiny tap targets crammed two-up on a phone screen and missed by every second tap.',
                        'Long bio paragraphs the visitor has to read before they see a single button.',
                        'Linking to "/" of a website instead of a deep link to the right page.',
                        'An outdated featured product, sold-out launch, or expired event still pinned to the top.',
                        'No mobile preview check after every edit: you ship desktop-only mistakes daily.',
                        'Using the same exact link page for every campaign instead of a per-campaign variant.',
                        'Skipping UTM tags, so analytics can\'t tell which post drove the tap.',
                        'No fallback for cookie-blocked browsers, so analytics goes silently dark.',
                        'Loading huge unoptimised cover images that crawl on 4G and lose visitors before render.',
                        'No call-to-action at the very bottom for visitors who scrolled all the way through.',
                        'Treating the page as "set and forget" instead of revisiting it monthly.',
                    ]],
                    ['heading' => 'Why these are silent killers', 'paragraphs' => [
                        'Each individual mistake costs maybe 1–3% of conversions. That sounds small until you stack five or six of them together and realise you are losing 15–20% of every visitor who lands on your page. The mistakes are silent because there is no error message, no broken link, no obvious red flag, just a quietly underperforming page that you have stopped paying attention to.',
                        'Your competitors\' pages have most of these mistakes too. Fixing yours is one of the highest-leverage hours of work you can do this week, and you don\'t need anyone\'s help to do it.',
                    ]],
                    ['heading' => 'How to fix them in an hour', 'paragraphs' => [
                        'Open your Sayzio analytics, sort blocks by tap-through rate, and start with the worst three. Re-label, re-order, or remove them. Then preview on a real phone (not just the desktop preview pane), because tap target spacing and font sizes only show up as problems on a real device.',
                        'You don\'t need a redesign. Most pages claw back 20–40% conversion just by fixing the cheap mistakes above. Set a monthly calendar reminder to do this audit again, because the page drifts as you add new things.',
                    ]],
                ],
                'outro' => 'Conversion is not glamorous work, but it is the work that compounds. A page that converts 8% instead of 4% doubles every campaign you run for the rest of the year, with no extra audience and no extra effort.',
            ],
            [
                'title' => 'Designing a Biolink That Looks Like Your Brand, Not a Template',
                'slug'  => 'designing-a-biolink-that-looks-like-your-brand',
                'category' => 'biolinks',
                'tags' => ['biolinks', 'design', 'branding'],
                'excerpt' => 'Practical tactics for making a biolink feel hand-crafted, without hiring a designer.',
                'intro' => 'A biolink that looks like everyone else\'s biolink quietly tells visitors that you don\'t care that much about your own brand. The fix is not a custom theme, a Figma session, or a hired designer. It is a handful of small, intentional choices that you can make in an evening and that immediately separate your page from the templated noise. Here is the short field guide.',
                'sections' => [
                    ['heading' => 'Pick one accent colour and stick to it', 'paragraphs' => [
                        'Templates default to a rainbow because they have no idea what you sell. You do. Choose one accent colour pulled from your existing brand (your podcast cover art, your YouTube thumbnails, your storefront) and apply it to every primary button on the page. Use neutral greys, blacks, or whites for everything else: secondary buttons, dividers, backgrounds.',
                        'A single confident accent colour is the difference between a page that looks designed and a page that looks decorated. If you are not sure which colour to pick, sample the dominant colour from the photo you use most often as your channel art. That keeps the page consistent with everywhere else your audience sees you.',
                    ]],
                    ['heading' => 'Use a real photo, not an avatar', 'paragraphs' => [
                        'A clean photo of you (or your product, or the workshop, or the studio) outperforms an icon avatar in almost every test we have run. Visitors trust faces. Faces also do work that words cannot; they communicate energy, tone and brand personality without taking up vertical space.',
                        'If you sell a product, show it in use, not on a white background. A photo of someone wearing the jacket on a windy street is more persuasive than the catalog shot, because it tells a story the catalog shot doesn\'t.',
                    ]],
                    ['heading' => 'Match the typography to your other channels', 'paragraphs' => [
                        'If your YouTube thumbnails are bold sans-serif, your biolink should not be a thin elegant serif. Visitors arrive in motion from another channel; keep the visual handoff seamless so they don\'t feel like they landed in the wrong place. The same applies to spacing: tight, dense channels should have a tight, dense biolink. Airy, minimal channels should have an airy, minimal biolink.',
                    ]],
                    ['heading' => 'Custom block icons over generic chevrons', 'paragraphs' => [
                        'Sayzio lets you set icons per block. A small icon that matches the destination (a podcast mic for the podcast link, a Spotify glyph for the streaming link, a calendar for the booking link) raises tap-through rate by giving the eye a fast hook. Visitors don\'t read button labels carefully; they pattern-match icons. Make the pattern matching work in your favour.',
                    ]],
                    ['heading' => 'Spacing is the cheapest design upgrade', 'paragraphs' => [
                        'The single biggest visual upgrade most creator pages can make costs nothing: more vertical space between blocks. Templates ship with tight spacing because it makes the page look "full" in a screenshot, but in real use a little more air makes the page feel premium and reduces the cognitive load on the visitor.',
                    ]],
                ],
                'outro' => 'A biolink can feel custom in under an hour. The trick is restraint: fewer colours, fewer fonts, more intentional spacing, and a real photo. Do those four things and your page will look measurably more considered than 90% of the pages in your niche.',
            ],
            [
                'title' => 'How to A/B Test Your Biolink (Even If You\'re Not a Marketer)',
                'slug'  => 'how-to-ab-test-your-biolink',
                'category' => 'biolinks',
                'tags' => ['biolinks', 'a-b-testing', 'analytics'],
                'excerpt' => 'A simple loop for testing changes on your biolink without spreadsheets or guesswork.',
                'intro' => 'Real statistical A/B testing on a biolink is overkill for most creators: traffic is too small for true significance, and the temptation to call winners early ruins half the experiments anyway. But you do not need a stats degree to make smarter decisions about your page. You need a loop, a small amount of patience, and the discipline to change one thing at a time.',
                'sections' => [
                    ['heading' => 'The two-week change loop', 'list_type' => 'ol', 'list' => [
                        'Pick one block to change; usually the primary CTA label, the order of the top three blocks, or the hero photo.',
                        'Note the current tap-through rate from your Sayzio analytics for the affected block(s). Write it down somewhere outside the dashboard.',
                        'Make a single change. Resist the urge to also "tidy up" three other things at the same time, because then you cannot attribute the result.',
                        'Wait two weeks. Don\'t look daily. Daily noise is bigger than weekly signal at small traffic levels and will make you reverse decisions you should have left alone.',
                        'Compare. Keep what won. Revert what lost. Move on to the next test.',
                    ]],
                    ['heading' => 'Variables worth testing first', 'paragraphs' => [
                        'Not every variable is worth your attention. Some move conversion meaningfully even at small traffic levels; others need thousands of taps to read at all. Spend your testing budget on the variables in the first list below before you touch anything in the second.',
                    ], 'list' => [
                        'Button label wording (verb-led "Book a call" vs descriptor "Coaching").',
                        'Order of the top three blocks (CTA → proof → secondary, vs CTA → newsletter → proof).',
                        'Hero photo (you vs product vs result).',
                        'Whether your newsletter signup is a button-to-page or an inline form.',
                        'Whether the primary CTA is solid or outlined.',
                    ]],
                    ['heading' => 'Variables not worth testing yet', 'paragraphs' => [
                        'Colours, fonts, animations, gradients and emoji choices feel important but rarely move the needle until you have hundreds of taps a day. Spend that energy on labels and order instead. When in doubt, change a verb, not a colour. Verbs change behaviour; colours change vibes.',
                    ]],
                    ['heading' => 'When to call a winner', 'paragraphs' => [
                        'A useful rule of thumb: if the new version beats the old by at least 15% across two weeks of comparable traffic, keep it. Smaller improvements are likely noise at creator-scale traffic. Bigger improvements should still be sanity-checked; the timing, a viral post, or a campaign launch can all skew a fortnight.',
                    ]],
                ],
                'outro' => 'You don\'t need to run experiments like an enterprise growth team. You need to make better-than-coin-flip decisions every two weeks for a year. Do that and your page will be unrecognisably better twelve months from now, without ever once running a "real" A/B test.',
            ],
            [
                'title' => 'Smart Links 101: One URL, Many Destinations',
                'slug'  => 'smart-links-101',
                'category' => 'biolinks',
                'tags' => ['short-links', 'smart-links', 'campaigns'],
                'excerpt' => 'When and why to use a smart link instead of a plain short link, with three concrete creator exampless.',
                'intro' => 'A smart link is a single URL that decides where to send each visitor based on their device, country, time, language, or referrer. They sound technical, but the use cases are simple, and once you have one in your toolkit you start finding new excuses to use them. Here is the short tour, with three examples that pay back the setup cost on the first campaign.',
                'sections' => [
                    ['heading' => 'When a smart link beats a plain link', 'paragraphs' => [
                        'A plain short link sends every visitor to the same destination. That is great for stable links: your homepage, your latest blog post, your booking page. But the moment your destination depends on who the visitor is, you either need a smart link or a separate link per audience (which is what most creators end up doing, badly).',
                    ], 'list' => [
                        'Music releases: send iOS visitors to Apple Music, Android to Spotify, desktop to a landing page that lets them choose.',
                        'App launches: route mobile to the right store (App Store on iOS, Play Store on Android), desktop to a sign-up form because they can\'t install the app from there anyway.',
                        'Geo-targeted offers: show European visitors the EUR price page, US visitors the USD price, everyone else a localised default.',
                        'Re-engagement: send returning visitors to a "welcome back" page, new visitors to your homepage. The returning audience does not need the elevator pitch again.',
                        'Time-bound launches: route to a countdown page before launch, the live offer during launch, and a "join the waitlist" page after.',
                    ]],
                    ['heading' => 'How to set one up in Sayzio', 'paragraphs' => [
                        'Create a new link, pick "Smart routing", and add destinations one by one with the rules. Each rule is a tiny if/then: "if device is iOS, send to apple.com/...". Always set a fallback destination at the bottom of the rule list, because someone will eventually visit from a device or country you didn\'t anticipate, and you do not want them to see an error page.',
                        'Test each rule from the right device (or use your phone\'s VPN) before sharing the link publicly. The most common mistake is forgetting the fallback and discovering it the day a Brazilian magazine writes you up.',
                    ]],
                    ['heading' => 'Common pitfalls', 'paragraphs' => [
                        'Smart links break analytics if you don\'t carry UTMs through to each destination. Use Sayzio\'s "preserve UTMs" toggle so the original campaign tag survives the redirect and shows up correctly in whichever analytics tool the destination is using.',
                        'Don\'t over-segment. A smart link with three destinations is easy to reason about; a smart link with eleven destinations and overlapping rules is a maintenance nightmare. If you find yourself adding rule number six, ask whether you actually need a separate landing page instead.',
                    ]],
                ],
                'outro' => 'Smart links are one of those tools that look unnecessary until you need one, and then become the obvious answer to half your problems. Add one to your next campaign and you will see why.',
            ],
            [
                'title' => 'From 0 to 1,000 Real Followers: A 90-Day Creator Playbook',
                'slug'  => 'from-zero-to-1000-real-followers-90-day-playbook',
                'category' => 'creator-growth',
                'tags' => ['creator-growth', 'audience-building', 'playbook'],
                'excerpt' => 'A weekly cadence for going from cold start to a thousand engaged followers in three months.',
                'intro' => 'A thousand engaged followers is the inflection point for most creators: it is the threshold where audience builds itself, sponsors start replying, and your work starts earning. Below it, every post feels like shouting into an empty room. Above it, the same effort starts to compound. Here is the cadence we see work, week by week, for creators who get to a thousand in roughly ninety days without paid ads, gimmicks, or burning out by week five.',
                'sections' => [
                    ['heading' => 'Weeks 1–4: niche and rhythm', 'paragraphs' => [
                        'Pick one topic and one format. Post twice a week. The goal in the first month is not virality; it is finding a voice and proving to yourself you can show up. Use Sayzio on day one to track which posts drive taps to your bio, because taps to bio are a much better early signal than likes.',
                        'Your first month of content will be your worst content. That is fine. Future-you will look back and cringe; present-you will have learned more in four weeks than in four months of "preparing".',
                    ]],
                    ['heading' => 'Weeks 5–8: collaborations', 'paragraphs' => [
                        'Reach out to ten creators in adjacent niches with under 5,000 followers and offer to collaborate on something small, like a co-written post, a guest appearance, a joint giveaway. At this size everyone is eager to grow and the maths of cross-pollination work in your favour. Big creators won\'t reply; peers will.',
                        'Collaborations also force you to up your production quality. Knowing someone else\'s audience is going to see your work is a useful kind of pressure. By the end of week eight you should have completed at least four collaborations.',
                    ]],
                    ['heading' => 'Weeks 9–12: convert browsers to subscribers', 'paragraphs' => [
                        'Add a newsletter signup as the primary block on your biolink. Promote a free, useful lead magnet, something that delivers value in under five minutes of reading. Most creators are leaving 80% of their potential audience on the social platform forever; own the email channel early so the next algorithm change does not erase your work.',
                        'By the end of week twelve, you should be aiming for at least 100 newsletter subscribers in addition to roughly 1,000 social followers. The newsletter list is what you actually own.',
                    ]],
                    ['heading' => 'What to track', 'list' => [
                        'New followers per week (not total; momentum matters more than the absolute number).',
                        'Bio taps per follower (a quality-of-engagement signal that is hard to fake).',
                        'Newsletter signups per week (the only metric you actually own end-to-end).',
                        'Collaborations completed (a leading indicator of growth in months 4–6).',
                    ]],
                ],
                'outro' => 'Three months is enough time to find your voice and your first thousand. Beyond that, the playbook changes, but the discipline of showing up, collaborating, and converting browsers to subscribers does not. Most creators who quit do so in week six or week ten. Get past those and you are most of the way there.',
            ],
            [
                'title' => 'Why Niching Down Will Grow You Faster Than Going Broad',
                'slug'  => 'why-niching-down-grows-you-faster',
                'category' => 'creator-growth',
                'tags' => ['creator-growth', 'positioning', 'strategy'],
                'excerpt' => 'The counterintuitive maths of audience building, and why "everyone" is the hardest market to reach.',
                'intro' => 'Every new creator wants to keep their options open. "I might want to talk about cooking too." "I don\'t want to box myself in." The result is an audience that can\'t describe what you do, and an algorithm that can\'t recommend you to anyone in particular. Niching down feels like surrendering opportunity. In practice it is the single fastest way to grow.',
                'sections' => [
                    ['heading' => 'The maths of recommendation', 'paragraphs' => [
                        'Modern feeds work by guessing which niche you belong to and showing your content to people who already engage with that niche. The narrower your niche, the more confident the guess, and the more reach you get. Generalist creators get pushed to a smaller, more random audience because the algorithm cannot place them.',
                        'This applies on Instagram, TikTok, YouTube, Pinterest and even search. Pick a niche and the platforms reward you. Resist a niche and the platforms quietly deprioritise you, even if the work is good.',
                    ]],
                    ['heading' => 'How to find a niche you won\'t hate in a year', 'paragraphs' => [
                        'The best niche is the intersection of three things: something you have real experience in, something that has measurable search demand, and something you would be willing to talk about for at least two years. Most creators pick on enthusiasm alone and quit when the enthusiasm runs out.',
                    ], 'list_type' => 'ol', 'list' => [
                        'List ten things you\'ve genuinely spent more than fifty hours on.',
                        'Cross out the ones with zero search demand (a five-minute Google search will tell you).',
                        'Cross out the ones you\'d be embarrassed to talk about for two years.',
                        'What\'s left is your shortlist. Pick the one with the smallest existing competition and the most specific audience.',
                    ]],
                    ['heading' => 'Niche on biolink, broad on socials', 'paragraphs' => [
                        'Your biolink should be ruthlessly clear about who you serve and what you offer. Your socials can wander a little; that\'s where personality lives, and personality is what turns followers into fans. The trick is consistency at the front door: visitors who follow you on socials should land on a biolink that immediately confirms what you do.',
                        'A reader who is laughing at your behind-the-scenes story will still convert into a paying customer if your biolink is clear. A reader who lands on a confused biolink will not, no matter how good the content was.',
                    ]],
                    ['heading' => 'Niches expand on their own', 'paragraphs' => [
                        'The fear that a narrow niche means a narrow career is mostly unfounded. The creators with the largest audiences today almost all started narrow and earned the right to widen as their audience grew. Start with the wedge; broaden later, on your terms.',
                    ]],
                ],
                'outro' => 'Niching down feels like saying no to opportunity. It is actually saying yes to the only opportunity that\'s available to you in your first year: being recommended to the right people, by platforms that need a label to recommend you at all.',
            ],
            [
                'title' => 'How to Turn Your Comments Section Into Your Best Marketing Channel',
                'slug'  => 'comments-section-as-marketing-channel',
                'category' => 'creator-growth',
                'tags' => ['engagement', 'community', 'creator-tips'],
                'excerpt' => 'Replies, pinned comments and DMs are an underrated growth lever. Here is how to systemise them.',
                'intro' => 'Most creators treat comments as a chore, a tax on having an audience. The ones who treat comments as a marketing channel grow faster, build a stronger community, and convert better. The work is small but the leverage is real, because a comments section is the only piece of "marketing" that is happening on the platform, in public, where every other potential follower can see it too.',
                'sections' => [
                    ['heading' => 'Pin a CTA on every post', 'paragraphs' => [
                        'A pinned reply with a friendly call-to-action: "Get the full guide on my biolink" earns more taps than the same CTA spoken in the video itself. People scroll comments to see the conversation; meet them there. The first comment under every post should always be yours, and it should always either deepen the topic or point to your biolink.',
                        'Use a Sayzio short link in the pinned comment so you can measure exactly how much traffic each post drives, and which kinds of posts convert best in comments.',
                    ]],
                    ['heading' => 'Reply within the first hour', 'paragraphs' => [
                        'Most platforms reward early engagement on a post. Block 20 minutes after each post goes live to reply to the first wave of comments. This single habit is responsible for the highest-engagement creators we see, because every reply you write is an extra little signal to the algorithm that this post is worth showing to more people.',
                        'Replies don\'t need to be long. A short, personal reply beats a thoughtful essay reply that takes you ten minutes. Volume of human-feeling interaction is what the platforms are reading.',
                    ]],
                    ['heading' => 'Mine comments for content', 'paragraphs' => [
                        'Every five posts, scan your comments for patterns. The questions repeated three or more times across different posts are your next post topics. You\'re not guessing what the audience wants; they\'re telling you, in their own words, with their own framing. That framing is also gold for headlines: people search for the same phrasing they use in comments.',
                    ]],
                    ['heading' => 'Move the best conversations to DM', 'paragraphs' => [
                        'When a commenter asks something genuinely interesting, reply briefly in public and offer to continue in DM. DMs are where loose followers turn into deeply engaged ones, and where some of the best customer conversations of your year will happen. Don\'t spam; the moment you DM cold is the moment trust evaporates.',
                    ]],
                ],
                'outro' => 'Comments are not a chore. They are the most visible piece of marketing you do, happening for free, in public, on every post you publish. Treat them like a channel and they pay you back at a rate no other channel can match.',
            ],
            [
                'title' => 'The Creator\'s Guide to Repurposing One Idea Into Ten Pieces of Content',
                'slug'  => 'repurposing-one-idea-into-ten-pieces',
                'category' => 'creator-growth',
                'tags' => ['content', 'repurposing', 'workflow'],
                'excerpt' => 'A practical workflow for getting a week of content from a single deep idea, without burning out.',
                'intro' => 'Burnout in creators almost always comes from constantly inventing new ideas. Top creators don\'t do that. They go deep on one idea, then atomise it across formats, channels, and weeks. The result is more output, less anxiety, and an audience that absorbs the message because they encounter it from several angles. Here is the workflow that scales, and that you can adopt this week without changing anything else about your process.',
                'sections' => [
                    ['heading' => 'Start with one long piece', 'paragraphs' => [
                        'Write a 1,000-word essay or record a 20-minute video. This is your "source asset": the thinking lives here. Everything else is derivative. The investment of doing the long form first feels expensive in week one and pays back immediately in week two, when you don\'t have to invent anything from scratch.',
                        'The source asset should be the most polished version of your idea. Imagine someone reading or watching only this: does it stand on its own? If yes, you have the seed for everything else.',
                    ]],
                    ['heading' => 'Atomise into ten outputs', 'paragraphs' => [
                        'Once the source asset is done, the rest is editing, not creating. Each output below should take 15–30 minutes of work, because the thinking is already done.',
                    ], 'list' => [
                        'Three short-form videos pulled from key moments (each 30–60 seconds).',
                        'Two carousel posts (Instagram or LinkedIn) summarising the main points.',
                        'One thread on X / Bluesky with the most quotable lines.',
                        'One newsletter issue that adds personal commentary not in the source.',
                        'One podcast episode (read the essay aloud, expanded with side notes).',
                        'One blog post on your site (great for SEO, with internal links to past work).',
                        'One pinned biolink block linking to the source asset.',
                    ]],
                    ['heading' => 'Schedule it across the week', 'paragraphs' => [
                        'Stagger the outputs so visitors who follow you on multiple platforms get variety, not repetition. Use Sayzio UTMs on each link so you can see which atom drove the most biolink taps, and double down on that format next week. After eight weeks of doing this you will know exactly which formats your audience prefers, and which ones you can stop making.',
                    ]],
                    ['heading' => 'The mindset shift', 'paragraphs' => [
                        'Most creators feel guilty repurposing because they assume the audience will notice. They will not. Each platform reaches a different slice of your audience at a different time, in a different mood, scrolling in a different rhythm. The same idea genuinely lands differently in each format. You are not being lazy; you are being respectful of how people consume.',
                    ]],
                ],
                'outro' => 'Inventing five ideas a week is unsustainable. Inventing one big idea and atomising it into ten pieces is. The creators who last for years almost all converge on this workflow, regardless of niche.',
            ],
            [
                'title' => 'Selling Digital Products From Your Biolink Without Looking Salesy',
                'slug'  => 'selling-digital-products-from-your-biolink',
                'category' => 'monetization',
                'tags' => ['monetization', 'digital-products', 'biolinks'],
                'excerpt' => 'How to position paid offers on your link-in-bio so they convert, without scaring off the audience you spent years building.',
                'intro' => 'Creators delay monetising because they\'re afraid of "selling out". The truth is that audiences expect their favourite creators to sell something; what they don\'t expect (and don\'t forgive) is bad selling. Good selling looks like helping. Done well, your most loyal followers feel grateful when you launch something they can buy, because it means they can finally support you financially in addition to emotionally.',
                'sections' => [
                    ['heading' => 'Lead with the outcome, not the format', 'paragraphs' => [
                        '"Sleep through the night in two weeks" outsells "32-page PDF guide" every time. The format is irrelevant to the buyer; the outcome is everything. Re-write your block labels accordingly. The biggest single uplift we see in creator monetisation is renaming "PDF guide" to a verb-led outcome statement.',
                        'When a buyer is deciding whether to tap, they are asking "what changes for me?" not "how many pages?". Answer the right question on the button, and answer the secondary question (length, format, bonuses) on the destination page.',
                    ]],
                    ['heading' => 'Anchor with a free version', 'paragraphs' => [
                        'A free guide or a free chapter sitting next to the paid product reframes the paid product as a logical next step rather than a cold ask. It also captures emails, your most valuable asset, for buyers who aren\'t ready yet but will be in three months.',
                        'The free version should genuinely be useful on its own. A "free version" that is obviously a teaser leaves a worse taste than no free version at all. Give people something they would have happily paid for, and the paid upgrade sells itself.',
                    ]],
                    ['heading' => 'Show one product at a time', 'paragraphs' => [
                        'Three or more paid offers stacked in a biolink confuses the visitor and lowers conversion across all of them. If you have multiple products, rotate which one is featured monthly and demote the others to a single "see all products" link. Featured-of-the-month also gives you a built-in marketing rhythm.',
                    ]],
                    ['heading' => 'Talk about the product the way a happy customer would', 'paragraphs' => [
                        'The pitch on your biolink should sound like a friend recommending the product to another friend, not like a sales page. Use one specific testimonial in the buyer\'s words rather than three abstract bullet points in yours. People trust strangers more than they trust you.',
                    ]],
                ],
                'outro' => 'Selling does not have to feel uncomfortable. Lead with outcomes, anchor with something free, show one offer at a time, and let your customers do most of the persuading. The audience that has been waiting for you to launch something will reward you the moment you do.',
            ],
            [
                'title' => 'Pricing Your First Digital Product: A No-Spreadsheet Guide',
                'slug'  => 'pricing-your-first-digital-product',
                'category' => 'monetization',
                'tags' => ['monetization', 'pricing', 'digital-products'],
                'excerpt' => 'The mental model creators can use to price their first product without psyching themselves out.',
                'intro' => 'Pricing your first product is mostly an emotional problem dressed up as a strategy problem. Creators agonise over decimals, copy competitors, and lower the price five times before launch, usually for reasons that have nothing to do with what the buyer would actually pay. Here is the mental model we hand to creators who freeze at the price field, in three short rules.',
                'sections' => [
                    ['heading' => 'Pick a price, then justify it', 'paragraphs' => [
                        'Most creators try to derive a price from value. That math never closes: value is too subjective and too dependent on the buyer. Instead, name a price you would happily charge a friend, then build the product (or repackage what you already have) to be worth that. This flips the question from "what do I deserve?" to "what is fair to ask for?", a much easier question to answer.',
                        'A useful gut check: if you priced this product at half the price, would more than twice as many people buy it? Almost always the answer is no. Lower prices rarely double demand; they just reduce revenue.',
                    ]],
                    ['heading' => 'Avoid the $9 trap', 'paragraphs' => [
                        'Sub-$10 pricing attracts low-intent buyers, generates the most refund requests, and trains your audience to undervalue your future work. It also makes it hard to ever raise prices without backlash. Start at $29 minimum unless you have a strategic reason not to (a deliberate loss-leader funnel into a higher offer, for example).',
                        'There is also a credibility floor. A $7 ebook tells the buyer it is probably thin. A $39 ebook tells them it is probably substantial. The price is itself a signal of what they should expect; use it intentionally.',
                    ]],
                    ['heading' => 'Add a higher tier on day one', 'paragraphs' => [
                        'A "premium" version at 2–3× the base price almost always sells to a small but real share of buyers, and makes the base price feel reasonable to everyone else. The premium tier does not have to be much more work; an extra workbook, a Q&A call, or a private community is usually enough.',
                    ]],
                    ['heading' => 'Raise prices early', 'paragraphs' => [
                        'It is much easier to raise prices in the first three months than in the third year, because nobody has anchored on the old price yet. If your launch sells out faster than you expected, raise the price by 20–30% on the next batch. The market is telling you something; listen.',
                    ]],
                ],
                'outro' => 'Pricing is rarely the reason a product fails. Confidence in the pitch, clarity of the outcome, and the size of the audience matter more. Pick a fair price, hold the line, and use the energy you would have spent agonising on actually shipping.',
            ],
            [
                'title' => 'Recurring Revenue for Creators: Memberships, Subs and Why They Compound',
                'slug'  => 'recurring-revenue-for-creators',
                'category' => 'monetization',
                'tags' => ['monetization', 'memberships', 'subscriptions'],
                'excerpt' => 'Why creators eventually all gravitate to recurring revenue, and how to start without a huge audiencee.',
                'intro' => 'One-off product launches are exciting, but they reset the income clock to zero every month. Recurring revenue compounds: every month builds on the last, and a year of consistent work produces an income line that goes up and to the right rather than spiking and crashing. Here is how to layer it in even if you have a small audience and no taste for community management.',
                'sections' => [
                    ['heading' => 'Start with a tiny offering', 'paragraphs' => [
                        '$5/month for a private community, weekly bonus newsletter, or paywalled archive is a perfectly good starting point. The point of starting small is not to maximise revenue on day one; it is to build the muscle of delivering ongoing value, which is the actual hard part of any subscription business.',
                        'Most creators massively over-design their first membership. A weekly post and a Discord is enough. Ship something thin, learn what your members actually use, and add only the things they ask for.',
                    ]],
                    ['heading' => 'Make the perk obvious from your biolink', 'paragraphs' => [
                        'A featured "Members get…" block on your biolink is the single highest-converting promotion for membership tiers. Include three concrete benefits, not adjectives. "Weekly bonus essay, monthly Q&A, member-only Discord" outperforms "exclusive content and community" by a wide margin, because concrete claims are easier to evaluate.',
                    ]],
                    ['heading' => 'Watch retention more than signups', 'paragraphs' => [
                        'Recurring revenue businesses live or die on month-3 retention. Use Sayzio analytics to track which signup channels produce members who stay and which produce members who churn after the first billing cycle. Those are the channels worth investing more time in (and the ones worth quietly pulling back from).',
                        'A useful framing: if your annualised churn is below 30%, focus on signups. If it is above 30%, fix the experience first; every new signup is fighting against the leaky bucket.',
                    ]],
                    ['heading' => 'Annual plans change the maths', 'paragraphs' => [
                        'Even at small scale, offering an annual plan with a small discount (10 months for the price of 12) dramatically improves cash flow and cuts your churn in half overnight, because annual subscribers do not see a monthly invoice to second-guess. Most members who buy annual stay annual.',
                    ]],
                ],
                'outro' => 'Subscriptions are not just for software companies. Any creator who can deliver consistent value can build a recurring line of income, and the compounding nature of it makes every month of consistency worth more than the last.',
            ],
            [
                'title' => 'Sponsorships 101: Landing Your First Brand Deal',
                'slug'  => 'sponsorships-101-first-brand-deal',
                'category' => 'monetization',
                'tags' => ['monetization', 'sponsorships', 'brand-deals'],
                'excerpt' => 'How smaller creators land their first paid sponsorship, and the tracking that wins them the second one.',
                'intro' => 'You don\'t need 100,000 followers to land a paid sponsorship. You need a niche audience a brand cares about, the ability to prove engagement, and a tracking link to show the deal worked. Most creators believe sponsorships only happen at scale because the deals at scale are the visible ones; the small, niche, unglamorous deals are the majority of the market and the easiest to break into.',
                'sections' => [
                    ['heading' => 'Outbound beats inbound for first deals', 'paragraphs' => [
                        'Brands rarely find small creators on their own. Make a list of ten brands your audience actually uses, find the right contact on LinkedIn (look for "creator partnerships", "influencer marketing", "community" or "growth" titles), and send a short, specific pitch. Reference your audience size, niche, and a recent post that performed well. Two paragraphs maximum.',
                        'A pitch that says "I have 3,000 highly-engaged subscribers in [niche], here is a recent post that drove 800 site visits to a competitor of yours, would you like to talk about a collaboration?" outperforms a glossy media kit every time. Brands want signal, not pageantry.',
                    ]],
                    ['heading' => 'Use a dedicated tracking link', 'paragraphs' => [
                        'Sayzio short links with custom UTMs give you clean, sharable analytics for the brand. Send the dashboard view at the end of every campaign. Brands rebook the creators who make their job easy, and proving results in numbers makes the renewal conversation a formality, not a negotiation.',
                        'Always include a dedicated link, even if the brand also gives you a discount code. Codes get shared in unrelated places and contaminate attribution; your link does not.',
                    ]],
                    ['heading' => 'Price by results, not by follower count', 'paragraphs' => [
                        'A small audience that converts is worth more than a big one that doesn\'t. Frame your rate as "we typically generate $X in click-throughs per post", backed by your historical biolink data, and you will outprice bigger creators with worse engagement. This positioning also future-proofs your pricing as your audience grows.',
                    ]],
                    ['heading' => 'What to put in the contract', 'paragraphs' => [
                        'Even for small deals, get the basics in writing: the deliverable, the deadline, what counts as approval, the payment terms, and exclusivity. A one-page agreement is enough. The first time a deal goes wrong without one, you will wish you had spent the fifteen minutes.',
                    ]],
                ],
                'outro' => 'Brand sponsorships are simpler than they look. Find brands your audience already loves, pitch them concretely, deliver more than promised, and prove the result. The second deal is always easier than the first.',
            ],
            [
                'title' => 'The Five Numbers Every Creator Should Watch Weekly',
                'slug'  => 'five-numbers-creators-watch-weekly',
                'category' => 'analytics',
                'tags' => ['analytics', 'metrics', 'creator-tips'],
                'excerpt' => 'Most creator dashboards have hundreds of metrics. Only five of them actually matter week to week.',
                'intro' => 'Creator analytics dashboards are designed to make you feel busy. They surface dozens of metrics, most of which are flattering, vague, or so far downstream of any decision you could actually make that they are useless. The truth is that five numbers tell you nearly everything you need to know about whether the work is working, and the rest is mostly noise dressed up in nice charts.',
                'sections' => [
                    ['heading' => 'The five', 'list_type' => 'ol', 'list' => [
                        'Bio taps per week: momentum on the only page you fully control.',
                        'Newsletter signups per week: your owned audience growth.',
                        'Top-block conversion rate: how well your primary CTA is doing.',
                        'Returning visitors: proxy for whether content is actually sticking.',
                        'Revenue per visitor: the only number that pays the bills.',
                    ]],
                    ['heading' => 'How to set up the dashboard in Sayzio', 'paragraphs' => [
                        'Pin those five tiles to the top of your Sayzio workspace dashboard. Glance at them on Friday afternoon for ten minutes. If three or more are flat for four weeks running, change something: the content cadence, the offer, the page itself. Flatness is a quiet failure mode that creeps up on you because nothing is breaking; it is just not growing.',
                        'Compare each number to the same week one quarter ago, not just last week. Week-to-week swings are mostly noise; quarter-to-quarter trends are the real story.',
                    ]],
                    ['heading' => 'What to ignore', 'paragraphs' => [
                        'Total followers, raw views, "potential reach" and impressions are vanity metrics. They go up reliably even when your business is going nowhere, because they aggregate over time and don\'t reflect engagement quality. Don\'t make decisions based on them, and resist the urge to celebrate them; they are seductive and unhelpful.',
                        'Likewise, ignore "average watch time" or "average engagement rate" unless you are also looking at the underlying volume. A 70% engagement rate on a post seen by twelve people tells you nothing useful.',
                    ]],
                    ['heading' => 'When to add a sixth metric', 'paragraphs' => [
                        'Once you have a paid product, add "purchases per week" as a sixth tile. Until then, don\'t bother; the noise will distract you from the things that actually drive purchases later (audience growth and trust).',
                    ]],
                ],
                'outro' => 'Five numbers, ten minutes a week. That is genuinely enough to run the analytics side of a small creator business. Anything more usually masquerades as rigour and ends up as procrastination.',
            ],
            [
                'title' => 'UTM Tags Without Tears: A Creator-Friendly Guide',
                'slug'  => 'utm-tags-without-tears',
                'category' => 'analytics',
                'tags' => ['analytics', 'utms', 'tracking'],
                'excerpt' => 'A simple UTM convention you can stick to forever, so you always know which post drove which signup.',
                'intro' => 'UTMs sound technical but they are simply tags appended to a URL so analytics can tell campaigns apart. Adopt a convention now and your future self will thank you every month, because every link you ship from now on will report back where it came from instead of dumping into a "direct" bucket that tells you nothing.',
                'sections' => [
                    ['heading' => 'A convention that works', 'paragraphs' => [
                        'The classic three-parameter convention is enough for almost every creator. You don\'t need utm_term, utm_content, or any of the more exotic tags. Pick three, use them consistently, and resist the urge to invent new ones every quarter.',
                    ], 'list' => [
                        'utm_source = the channel (instagram, youtube, newsletter, podcast).',
                        'utm_medium = the placement (bio, story, video-description, email).',
                        'utm_campaign = a short slug for the post or push (eg. spring-launch, free-guide-2026).',
                    ]],
                    ['heading' => 'Let Sayzio apply them automatically', 'paragraphs' => [
                        'Set per-channel default UTMs on your short links so you don\'t have to remember them every time you publish. Override on a per-link basis only when a campaign needs a unique tag. Defaults eliminate the human error that silently breaks attribution for most creators within a week of trying to do this manually.',
                        'Use lowercase everywhere. Analytics tools treat "Instagram" and "instagram" as different sources, which silently splits your data and makes you think Instagram drives less traffic than it actually does.',
                    ]],
                    ['heading' => 'Audit quarterly', 'paragraphs' => [
                        'Once a quarter, scan your UTMs for typos and inconsistencies (Instagram vs instagram, Newsletter vs email, "spring-launch-2026" vs "spring_launch_2026"). Clean them up and standardise. Five minutes of cleanup saves you an hour of confused reporting later.',
                    ]],
                    ['heading' => 'Don\'t over-tag', 'paragraphs' => [
                        'Resist the urge to put UTMs on internal links (links between pages on your own site). They overwrite the original campaign source and make every visitor look like they came from your own site. UTMs belong on links into your site, not within it.',
                    ]],
                ],
                'outro' => 'A consistent UTM convention is one of those small disciplines that pays back forever. Set it up once, automate the defaults in Sayzio, and you will never again have to wonder which post drove which signup.',
            ],
            [
                'title' => 'Reading Your Biolink Heatmap: What the Taps Are Trying to Tell You',
                'slug'  => 'reading-your-biolink-heatmap',
                'category' => 'analytics',
                'tags' => ['analytics', 'biolinks', 'heatmaps'],
                'excerpt' => 'The biolink heatmap is the closest thing creators have to a focus group. Here is how to read it.',
                'intro' => 'Heatmaps make it visually obvious which blocks earn attention and which collect dust. The patterns repeat across every page we look at on Sayzio, and once you can recognise them, decisions about what to keep, what to cut, and what to promote become easier and less emotional. Here is the short reading guide.',
                'sections' => [
                    ['heading' => 'The five common patterns', 'list_type' => 'ol', 'list' => [
                        'Top-heavy: visitors tap the first block then leave. Strengthen the top CTA, demote the rest, and consider a more compelling secondary action.',
                        'Distributed: even taps across blocks. Visitors are exploring; add a clearer primary action so the page has a centre of gravity.',
                        'Bottom spike: people scroll past your top blocks to reach a specific link. Promote that link to the top; your audience is telling you which one matters.',
                        'Dead middle: a block in position 3–5 gets zero love. Re-label it (verb-led wording often rescues it) or kill it.',
                        'Social-only: all taps go to socials. Your offer isn\'t resonating with this audience; rewrite it, or accept that this audience is here for the social handoff and stop optimising for purchases.',
                    ]],
                    ['heading' => 'Decide once a month, not once a week', 'paragraphs' => [
                        'Heatmaps are noisy week-to-week. A single viral post can completely warp the picture for seven days. Make decisions on monthly aggregates, and only change one thing at a time so you can attribute the result the following month. Over-reading short windows is the most common analytics mistake we see.',
                    ]],
                    ['heading' => 'Combine heatmap with traffic source', 'paragraphs' => [
                        'A block can look dead overall but be the top performer for visitors arriving from your newsletter. Slice the heatmap by source before you cut anything; a block that earns its keep with high-intent traffic is worth keeping even if cold-traffic ignores it.',
                    ]],
                    ['heading' => 'A simple rule of thumb', 'paragraphs' => [
                        'If a block has been on your page for three months and still gets fewer than 1% of total taps, it is hiding good blocks below it. Move it to the bottom or delete it. Almost no creator has ever regretted having fewer blocks on their biolink.',
                    ]],
                ],
                'outro' => 'Your heatmap is a free, ongoing focus group with people who actually visit your page. Spend ten minutes a month with it and you will make better decisions about your biolink than 95% of creators in your niche.',
            ],
            [
                'title' => 'SEO Basics for Creators Who Hate SEO',
                'slug'  => 'seo-basics-for-creators-who-hate-seo',
                'category' => 'seo',
                'tags' => ['seo', 'creator-tips', 'organic-growth'],
                'excerpt' => 'You don\'t need to become an SEO consultant. You need to do five things consistently. Here they are.',
                'intro' => 'Most SEO advice is written for marketers managing million-page sites with serious technical infrastructure. Creators have different leverage and a much smaller surface area. Do these five things and you will outperform 90% of creator pages on search, without ever once installing a plugin you don\'t understand or paying for an SEO tool subscription.',
                'sections' => [
                    ['heading' => 'The five things', 'list_type' => 'ol', 'list' => [
                        'Set a unique meta title and description on every post and page. Sayzio\'s blog editor exposes both fields directly in the form.',
                        'Use one H1 per page that matches what someone would type into Google to find that page.',
                        'Link your biolink and blog from your social profiles; these are some of your strongest backlinks and they cost nothing.',
                        'Compress images before uploading. Page speed is an underrated ranking factor on mobile and a serious one for users.',
                        'Submit your sitemap.xml to Google Search Console. Sayzio generates one automatically at /sitemap.xml; you just paste the URL into the Search Console form.',
                    ]],
                    ['heading' => 'What to ignore', 'paragraphs' => [
                        'Keyword density, meta keywords, and link-buying schemes. They either don\'t work or actively hurt. Focus on writing the page a real human would actually want to read, with a title that says what the page is about, and the technical side of SEO mostly takes care of itself.',
                        'Also ignore the temptation to chase trending keywords that don\'t fit your niche. A spike of off-topic visitors is worse than no visitors; it tanks your engagement metrics, which Google then uses to decide you are not an authority on your real topic either.',
                    ]],
                    ['heading' => 'Write for the question, not the keyword', 'paragraphs' => [
                        'Modern Google ranks pages that comprehensively answer the question a searcher had in mind. Frame each post around a specific question your reader is asking, and structure the body to answer it. Headings as questions, paragraphs as answers, lists as quick scans. This format also helps you rank for the "People also ask" snippets, which now drive a meaningful share of organic clicks.',
                    ]],
                    ['heading' => 'Patience is the unfair advantage', 'paragraphs' => [
                        'Most creators give up on SEO in month four because nothing has happened. Months 6–12 are when posts start ranking, and months 12–24 are when they really compound. The creators who keep showing up while everyone else quits end up with the most durable traffic source any business can have.',
                    ]],
                ],
                'outro' => 'You will not become an SEO expert from one blog post, and you don\'t need to. Do the five things above on every post you publish for a year and your traffic from search will quietly become the steadiest channel you have.',
            ],
            [
                'title' => 'Long-Tail Keywords: The Secret Weapon of Small Creators',
                'slug'  => 'long-tail-keywords-secret-weapon',
                'category' => 'seo',
                'tags' => ['seo', 'keywords', 'organic-growth'],
                'excerpt' => 'Why creators with no SEO budget should ignore the big keywords and chase the boring ones.',
                'intro' => 'Big single-word keywords: "yoga", "fitness", "cooking", "marketing" are owned by sites with thousands of links and a decade of authority. Small creators win by picking the boring, specific phrases nobody else is targeting. The traffic per phrase is small, but the conversion rate is enormous and the competition is almost zero. Stack enough of these and you have a real organic channel within a year.',
                'sections' => [
                    ['heading' => 'What "long-tail" actually means', 'paragraphs' => [
                        'A long-tail keyword is a 3–6 word phrase with low search volume but very specific intent. "Yoga" gets 1.2M searches and is unwinnable for any creator without a major brand behind them. "Yoga for runners with tight hips" gets 200 searches a month and converts five times better, because the searcher knows exactly what they want and your post can deliver it precisely.',
                        'Long-tail traffic also tends to attract higher-intent visitors who are further down the buying funnel; they have already done their general research and are searching for specifics.',
                    ]],
                    ['heading' => 'How to find them', 'list' => [
                        'Type a topic into Google and read the "People also ask" box. Each entry is a long-tail keyword with proven search demand.',
                        'Skim the auto-suggestions when you start typing in YouTube and Reddit; they reveal the long-tail phrases real users are typing.',
                        'Mine your own DMs and comments; the questions readers ask are the searches they\'d run if they hadn\'t found you first.',
                        'Use free tools like AnswerThePublic for inspiration, but cross-check volume in Google Search Console rather than trusting the tool\'s estimates.',
                    ]],
                    ['heading' => 'One post per keyword', 'paragraphs' => [
                        'Don\'t cram three long-tail topics into one post. Write one focused post per keyword and link them together. You build a topical cluster Google understands and trusts, and you give each post the best possible chance of ranking. A focused 800-word post on one specific topic outranks a 3,000-word post that vaguely covers three topics, every time.',
                    ]],
                    ['heading' => 'The compounding maths', 'paragraphs' => [
                        'Twenty long-tail posts, each driving 50 visitors a month, is 1,000 monthly visitors from search, the equivalent of going viral, except permanent. After two years of monthly posting, the same maths gives you 12,000 monthly visitors, growing on its own without you posting another word.',
                    ]],
                ],
                'outro' => 'Long-tail SEO is the boring, slow, unglamorous game that almost no creator wants to play. Which is exactly why it works for the few who do.',
            ],
            [
                'title' => 'Internal Linking for Creators: A Tiny Habit With Big Compounding',
                'slug'  => 'internal-linking-for-creators',
                'category' => 'seo',
                'tags' => ['seo', 'internal-links', 'blogging'],
                'excerpt' => 'A two-minute habit at the end of every blog post that quietly grows your search traffic over time.',
                'intro' => 'Internal links (links from one page on your site to another) are the easiest SEO win most creators ignore. They cost nothing, take two minutes, and compound forever. They also do double duty by keeping readers on your site longer, which directly improves the engagement signals search engines use to rank you. There is no downside, and yet the average creator blog has three internal links per post when it should have ten.',
                'sections' => [
                    ['heading' => 'The habit', 'paragraphs' => [
                        'At the end of writing each post, ask: "Which two existing posts could naturally link to this one, and which two could this one link to?" Add the links, both directions. Done. The whole habit takes two minutes once you have a handful of posts to link between, and it forces you to maintain a mental map of your own work, which incidentally also makes you a better writer.',
                        'Set a calendar reminder once a quarter to revisit your top ten posts and add links to anything new you have published since. Old posts already have authority; pointing that authority at new posts is one of the best things you can do for them.',
                    ]],
                    ['heading' => 'Why it works', 'paragraphs' => [
                        'Search engines use internal links to understand the topical structure of your site and to discover new pages faster. A new post linked from five existing posts is found, indexed, and ranked far faster than the same post sitting in isolation at the end of your archive.',
                        'Readers use internal links to go deeper, increasing time on site, pages per visit, and the chance they subscribe or buy something. Both signals feed back into rankings, creating a virtuous cycle.',
                    ]],
                    ['heading' => 'A small rule', 'paragraphs' => [
                        'Use descriptive anchor text: "the five numbers every creator should watch" beats "click here" every single time. The anchor text tells search engines what the destination is about, and it tells readers what to expect from the click. "Click here" wastes both signals.',
                    ]],
                    ['heading' => 'What not to do', 'paragraphs' => [
                        'Don\'t bulk-link from a footer or sidebar; Google has been ignoring those for a decade. Don\'t link to the same destination from every post; spread the love across your archive. And don\'t cram twenty internal links into a 600-word post; three to five is plenty.',
                    ]],
                ],
                'outro' => 'Internal linking is one of those tiny habits that almost no one is excited about and that quietly produces outsized results over years. Build it into your writing process now and your future archive will silently work harder for you.',
            ],
            [
                'title' => 'Building an Email List When You\'re Starting From Zero',
                'slug'  => 'building-email-list-from-zero',
                'category' => 'audience',
                'tags' => ['email-list', 'audience-building', 'newsletter'],
                'excerpt' => 'A no-frills plan for getting your first 500 newsletter subscribers, without paid ads.',
                'intro' => 'Your email list is the only audience asset you fully own. If your social account got banned tomorrow, the list still works. If the algorithm changes and your reach drops 80% overnight (it will, eventually), the list still works. Here is a no-frills plan for building the first 500 subscribers from a standing start, without paid ads, gimmicks, or buying anyone\'s list.',
                'sections' => [
                    ['heading' => 'Make signing up the primary CTA', 'paragraphs' => [
                        'Until you have a paid product, your biolink should treat newsletter signup as the most important action on the page. Hero block, contrasting colour, clear benefit. Every other link is secondary. This single decision is responsible for most of the difference between creators who build lists and creators who don\'t.',
                        'Audit every place your audience encounters you: Instagram bio, YouTube channel description, podcast show notes, Twitter pinned post, and make sure the newsletter is the first link in every one of them.',
                    ]],
                    ['heading' => 'Have a real lead magnet', 'paragraphs' => [
                        'A "subscribe to my newsletter" button converts at 1–3%. A "get the free X" button converts at 8–15%. The lead magnet is doing five times the work, because it gives the visitor an immediate, tangible reward instead of a vague promise of future value.',
                        'The lead magnet doesn\'t need to be elaborate. A 5-page PDF, a single email mini-course, a curated toolkit, or a templated checklist all work. The point is to deliver something useful in under five minutes of reading.',
                    ]],
                    ['heading' => 'Promote it consistently', 'paragraphs' => [
                        'Most creators build a lead magnet, post about it twice, then stop mentioning it because they assume the audience saw it. The audience did not. Algorithms surface only a fraction of your posts to a fraction of your followers; you have to repeat yourself far more than feels comfortable.',
                    ], 'list' => [
                        'Pin a CTA comment on every social post.',
                        'Add a footer signup block on every blog post.',
                        'Mention it once per podcast episode.',
                        'Trade cross-promotions with creators in adjacent niches once a month.',
                        'Talk about it at the end of every video.',
                    ]],
                    ['heading' => 'What to send once they subscribe', 'paragraphs' => [
                        'A welcome email within minutes, then a regular cadence (weekly or biweekly) that delivers genuine value. Subscribers who receive nothing for two weeks will not remember they signed up; subscribers who receive a generic "thanks for subscribing" with nothing useful attached will unsubscribe immediately. Set up the welcome email before you start promoting the signup.',
                    ]],
                ],
                'outro' => 'Five hundred subscribers is the threshold where the list starts to feel like a real asset. Get there with the steps above, and the next thousand will come faster than the first because every existing subscriber is a tiny multiplier through forwards and shares.',
            ],
            [
                'title' => 'Newsletter Cadence: Weekly, Biweekly, or "When I Have Something to Say"?',
                'slug'  => 'newsletter-cadence-weekly-biweekly',
                'category' => 'audience',
                'tags' => ['newsletter', 'audience-building', 'cadence'],
                'excerpt' => 'The honest tradeoffs between weekly and biweekly newsletters, and why "when inspired" almost never works.',
                'intro' => 'Cadence is the most argued-about question in newsletter circles. The honest answer is that there is no universally correct cadence; but there are clear tradeoffs, and one option that almost always fails. Here is the no-nonsense view after watching hundreds of creator lists grow (and stall) on Sayzio.',
                'sections' => [
                    ['heading' => 'Weekly: high commitment, high compounding', 'paragraphs' => [
                        'Weekly builds reading habit fastest. Subscribers expect you in their inbox on the same day, which raises open rates over time as they learn to recognise your sender name and subject line patterns. The risk is burnout; only commit to weekly if you have a content engine that supports it (a podcast, a YouTube channel, a daily journaling habit). Without a constant input of raw material, the weekly cadence eats you alive by month four.',
                        'Weekly also raises unsubscribe risk slightly, because a subscriber who is mildly disengaged sees you four times a month instead of two. That is usually a fair trade for the extra reach and revenue, but worth knowing.',
                    ]],
                    ['heading' => 'Biweekly: the sustainable middle', 'paragraphs' => [
                        'Biweekly is the cadence most successful solo creators eventually settle on. Open rates are slightly lower than weekly but list growth and retention are nearly identical, because each issue can be more polished and substantial. For most creators with a day job or a portfolio of other work, biweekly is the cadence that actually survives a year.',
                    ]],
                    ['heading' => 'Monthly: only with a strong format', 'paragraphs' => [
                        'Monthly works if (and only if) the format is so substantial that subscribers anticipate it: a deep essay, a curated link drop, a season recap. Generic monthly newsletters feel forgettable because the gap is long enough for subscribers to forget they signed up.',
                    ]],
                    ['heading' => 'Irregular: the one to avoid', 'paragraphs' => [
                        '"I\'ll send when I have something to say" sounds liberating and is the surest way to make subscribers forget who you are. The first irregular newsletter after a six-week gap will see open rates collapse, unsubscribes spike, and you will conclude (incorrectly) that the audience didn\'t want the newsletter. They did; they just couldn\'t remember signing up. Pick a cadence and protect it, even if you have to mail a shorter issue some weeks.',
                    ]],
                ],
                'outro' => 'There is no perfect cadence; only the cadence you can actually keep. Whichever one you pick, commit to it for at least six months before re-evaluating. Reliability beats rhythm; rhythm beats irregularity.',
            ],
            [
                'title' => 'Cross-Promotions: The Underrated Growth Channel for Small Creators',
                'slug'  => 'cross-promotions-growth-channel',
                'category' => 'audience',
                'tags' => ['audience-building', 'partnerships', 'newsletter'],
                'excerpt' => 'How small creators trade audience with each other, and why it outperforms paid ads for early-stage growth.',
                'intro' => 'Paid ads don\'t make sense at small audience sizes; the maths don\'t close, and the targeting has too little data to work well. Cross-promotions do. They are the highest-ROI growth channel for creators between zero and 5,000 subscribers, and almost nobody does them systematically. Here is the simple version.',
                'sections' => [
                    ['heading' => 'The mechanics', 'paragraphs' => [
                        'Find five creators with similar-sized audiences in adjacent niches. Each of you sends one email mentioning the others, with a personal recommendation and a link to their newsletter or biolink. Everyone trades audience and grows simultaneously. The whole arrangement takes a few hours to coordinate and can add hundreds of subscribers to each list in a single week.',
                        'The most successful cross-promotions are organised in small batches of three to five creators. Larger groups dilute the recommendation and feel spammy; pairs work but lack the variety that keeps subscribers interested.',
                    ]],
                    ['heading' => 'How to make it work', 'list' => [
                        'Adjacent niche, not identical: the audiences should overlap but not duplicate. A copywriter and a brand designer cross-promote well; two copywriters do not.',
                        'Personal recommendation, not a generic ad: the open and click rates depend on it. "I genuinely read X every week and you would love it" outperforms "check out X" by a factor of three or more.',
                        'Track with a unique Sayzio short link per partner so you can compare results and reciprocate fairly in the next round.',
                        'Set expectations on volume up front: agree how many subscribers each list has and roughly how many clicks the swap should produce.',
                    ]],
                    ['heading' => 'When to graduate', 'paragraphs' => [
                        'At about 5,000 subscribers the maths flip and small paid placements start working: sponsored posts in other newsletters, classified-style ads in indie newsletters, niche podcast sponsorships. Until then, cross-promotion is your highest-ROI channel by a wide margin.',
                    ]],
                    ['heading' => 'A quarterly rhythm', 'paragraphs' => [
                        'Set up one cross-promotion every quarter. Doing it more often makes you look thirsty to subscribers; doing it less often means you forget to do it at all. A quarterly cadence is sustainable and adds compounding boosts to your growth without ever feeling forced.',
                    ]],
                ],
                'outro' => 'Cross-promotions work because they are honest, free, and mutually beneficial. The only reason more creators don\'t do them is because organising them requires reaching out, which feels uncomfortable. It gets easier the second time.',
            ],
            [
                'title' => 'Referral Programs That Actually Get Used',
                'slug'  => 'referral-programs-that-get-used',
                'category' => 'audience',
                'tags' => ['referrals', 'growth', 'audience-building'],
                'excerpt' => 'Why most creator referral programs flop, and the design choices that fix them.',
                'intro' => 'A referral program is one of the highest-leverage growth tools a creator can run. It is also one of the easiest to design badly, which is why most creator referral programs quietly disappear after a month of zero traction. The difference between a referral program that gets used and one that doesn\'t is not luck; it is a handful of specific design choices.',
                'sections' => [
                    ['heading' => 'Make the reward feel real', 'paragraphs' => [
                        '"Refer a friend and get our gratitude" doesn\'t work. A free month of your membership, a private benefit, a meaningful discount, or a dollar amount does. The reward must be worth the social cost of recommending, because every recommendation a person makes uses up a small amount of their reputation with the friend they\'re recommending to.',
                        'A useful framing: would you make this referral if your friend asked you why? If the only honest answer is "to get a free coffee", the reward is too thin. If the answer is "because I genuinely think you\'d love it AND I get a free month", the reward is doing the work it needs to.',
                    ]],
                    ['heading' => 'Keep the share flow stupid simple', 'paragraphs' => [
                        'A unique Sayzio short link per referrer is enough. Don\'t make people fill in forms, copy long URLs, or sign up to a separate referral platform. The drop-off at every extra click is brutal; every additional step probably halves participation.',
                        'The best share flow is: see your referral link in your account, copy it with one tap, paste it into a DM. Anything more complex is a step too many.',
                    ]],
                    ['heading' => 'Show progress', 'paragraphs' => [
                        'A small dashboard ("3 friends referred, 2 to go for your bonus") radically increases participation. People love to finish what they started, and a half-progress bar is one of the most reliable motivators in product design. Without progress visibility, referrers forget the program exists within a week.',
                    ]],
                    ['heading' => 'Reward the referrer AND the friend', 'paragraphs' => [
                        'Two-sided rewards convert dramatically better than one-sided ones, because the referrer doesn\'t feel like they\'re asking the friend for a favour. "Get a free month, give a free month" is a much easier conversation than "give me a free month".',
                    ]],
                ],
                'outro' => 'Referral programs are not magic. They are design problems with well-known solutions. Get the reward, the flow, the visibility and the two-sided structure right, and your most loyal subscribers will quietly grow your audience for you in the background.',
            ],
            [
                'title' => 'Scheduling Posts: A Calmer Way to Show Up Consistently',
                'slug'  => 'scheduling-posts-show-up-consistently',
                'category' => 'creator-growth',
                'tags' => ['scheduling', 'workflow', 'consistency'],
                'excerpt' => 'A weekly scheduling routine that takes the daily anxiety out of being a creator.',
                'intro' => 'The single most useful productivity habit for creators is also the most boring: schedule your content in batches instead of writing it the morning of. The creators who do this seem oddly unflustered; the creators who don\'t are perpetually stressed and post unevenly. Here is what a calm weekly cadence looks like, and how to ease into it without rebuilding your whole workflow at once.',
                'sections' => [
                    ['heading' => 'The Sunday block', 'paragraphs' => [
                        'Spend two hours every Sunday drafting and queuing the week\'s posts. Use Sayzio\'s scheduling to set the publish times. The next six days are then consumed by reply, engagement, and actual creative work, not by writing under pressure with thirty minutes to publish. The mental relief alone is worth the habit; the consistency boost is the bonus.',
                        'If two hours sounds like too much for one sitting, split it into two one-hour Sunday and Wednesday sessions. The point is to detach the act of writing from the act of publishing.',
                    ]],
                    ['heading' => 'Buffer two weeks ahead', 'paragraphs' => [
                        'Once you\'re comfortable with the Sunday block, push the buffer to two weeks. Now a sick day, a holiday, a creative slump, or a low-energy week never breaks your cadence; the queue keeps publishing while you take time off. This single change has saved more creator careers than any other piece of productivity advice we have seen.',
                        'A two-week buffer also gives you the space to delete or rework posts that no longer feel right. With same-day publishing, sub-par posts ship; with a buffer, they get edited.',
                    ]],
                    ['heading' => 'Leave 20% room for reactive posts', 'paragraphs' => [
                        'You still want to react to what\'s happening in your niche: a news beat, a viral conversation, a launch. Schedule four out of five posts; leave one slot per week for something that wasn\'t in the plan. This balances the calmness of scheduling with the relevance of being current.',
                    ]],
                    ['heading' => 'The Sayzio scheduling toolkit', 'paragraphs' => [
                        'Use Sayzio to schedule both your publish-to-platform CTAs and the campaign-specific short links you reference in posts. Pre-create UTM-tagged links so the right tracking fires the moment a post goes live. The combination of scheduled posts and pre-tagged links is the calmest creator workflow we have ever seen.',
                    ]],
                ],
                'outro' => 'Consistency is not a personality trait. It is a workflow. Build the workflow, and the personality trait shows up automatically.',
            ],
            [
                'title' => 'Social Proof on a Biolink: What Works, What Backfires',
                'slug'  => 'social-proof-on-a-biolink',
                'category' => 'biolinks',
                'tags' => ['biolinks', 'social-proof', 'conversion'],
                'excerpt' => 'Testimonials, follower counts, press logos: the proof formats that lift conversion and the ones that quietly tank it.',
                'intro' => 'Social proof is one of the highest-impact things you can add to a biolink. It is also one of the easiest to overdo, and certain proof formats actively hurt conversion in ways most creators don\'t notice. Here is the short field guide, based on hundreds of biolinks we have looked at on Sayzio.',
                'sections' => [
                    ['heading' => 'What works', 'list' => [
                        'A single specific testimonial with a real name and photo, ideally with a measurable outcome ("dropped my course completion time from 12 weeks to 7").',
                        'A subscriber count, but only when it\'s genuinely impressive for your niche. "12,400 readers" beats "12,400" every time because the unit gives context.',
                        '"Featured in" press logos, even a small selection. Visual recognition does the work in a single glance.',
                        'Outcome statements with numbers ("helped 1,200 students pass the test"). Specific numbers feel more credible than rounded ones.',
                        'Recent customer photos in a small grid (where appropriate to your niche).',
                    ]],
                    ['heading' => 'What backfires', 'list' => [
                        'Stacks of five-star testimonial cards that visitors stop reading after the second one; diminishing returns set in fast.',
                        'Vanity follower counts that are smaller than visitors expect for your niche ("100 followers" undermines, doesn\'t prove).',
                        'Generic logos visitors don\'t recognise, especially if they\'re obviously local-press or low-tier publications.',
                        'Counters that update visibly in real time; they read as gimmicky and feel like a salesy template.',
                        'Testimonials with no name, no photo, or both: readers assume they\'re fake and the rest of the page suffers from the suspicion.',
                    ]],
                    ['heading' => 'Where to put it', 'paragraphs' => [
                        'Right under the primary CTA, above any secondary blocks. The visitor sees the proof at the moment of decision, not after they\'ve already scrolled past the action. Burying proof at the bottom of the page is the most common placement mistake; by the time visitors reach it, they\'ve already decided to leave.',
                    ]],
                    ['heading' => 'Less is more', 'paragraphs' => [
                        'One excellent testimonial outperforms five mediocre ones. Pick the single best one you have and make it impossible to miss. If you only have weak testimonials so far, ask three of your best customers for a fresh one this week. Specifically ask them what changed for them, in their own words.',
                    ]],
                ],
                'outro' => 'Proof works because strangers trust other strangers more than they trust you. Curate ruthlessly, place strategically, and you will lift conversion measurably with no other change to the page.',
            ],
            [
                'title' => 'Welcome Sequences: The First Seven Days Are the Whole Game',
                'slug'  => 'welcome-sequences-first-seven-days',
                'category' => 'audience',
                'tags' => ['email-list', 'newsletter', 'onboarding'],
                'excerpt' => 'A subscriber\'s first week with you decides whether they stay forever or quietly unsubscribe. Here is what to send.',
                'intro' => 'Most creators send a single confirmation email and then disappear into the regular newsletter cadence. That wastes the moment a new subscriber is most engaged with your work: they just signed up, so they actively want to hear from you. A real welcome sequence converts dramatically better, both in retention and in eventual purchases. Here is the four-email skeleton that works for almost any niche.',
                'sections' => [
                    ['heading' => 'Day 0: Welcome and deliver', 'paragraphs' => [
                        'The first email arrives within minutes of signup. It delivers whatever you promised at signup (the lead magnet, the discount, the access link), says hello in your voice, and previews what to expect from the regular newsletter. Keep it short; 150 words is plenty. The point is to confirm the subscriber made the right decision and give them an immediate small win.',
                        'This email also has the highest open rate you will ever see (often 70%+). Don\'t waste the attention by being purely transactional. A single useful line beyond the delivery itself does more for retention than any later email.',
                    ]],
                    ['heading' => 'Day 2: Best-of-archive', 'paragraphs' => [
                        'A curated link to your three or four best previously-published pieces. New subscribers don\'t know your back catalog exists; show them. This email also tells subscribers what kind of work to expect from you going forward, and lets them self-select out if it\'s not for them. That\'s a feature, not a bug; an early unsubscribe is much better than a quiet disengaged subscriber for the next year.',
                    ]],
                    ['heading' => 'Day 5: An honest pitch', 'paragraphs' => [
                        'If you have something to sell (a course, a community, a service), this is where you make a real, useful pitch. People who subscribed five days ago are statistically the most likely to buy from you, ever. Don\'t waste it by being coy. Frame the offer as a natural next step from the lead magnet they signed up for, and explain who it is (and isn\'t) for.',
                        'If you don\'t have anything to sell yet, use this slot to ask one question and invite a reply. Replies are one of the strongest deliverability signals to email providers, and the conversations that come back will tell you exactly what to build next.',
                    ]],
                    ['heading' => 'Day 7: Hand off to the regular newsletter', 'paragraphs' => [
                        'A short note saying "you\'ll now hear from me on the regular schedule" sets expectations and lowers unsubscribe risk later. It also tells the subscriber that the welcome series is over, which subtly signals that the next email is the real product, not more onboarding.',
                    ]],
                ],
                'outro' => 'A welcome sequence is one of those things that takes an afternoon to set up and pays back forever. Set it up before you focus on growing the list any further; the difference in lifetime value per subscriber is significant and starts immediately.',
            ],
            [
                'title' => 'What\'s New in Sayzio: Smarter Biolinks, Cleaner Analytics',
                'slug'  => 'whats-new-in-1inme-smarter-biolinks-cleaner-analytics',
                'category' => 'product',
                'tags' => ['product-updates', 'biolinks', 'analytics'],
                'excerpt' => 'A roundup of recent improvements to the editor, analytics dashboard, and short-link routing.',
                'intro' => 'A short tour of the things we shipped recently that creators have been asking for. Nothing earth-shattering, just steady improvements that compound, the kind of work that sets a product apart from its competitors over time even if no single update steals the spotlight. If you have not opened the dashboard in a while, here is what is waiting for you.',
                'sections' => [
                    ['heading' => 'A faster, calmer editor', 'paragraphs' => [
                        'The biolink editor is now noticeably snappier on slower connections, with a new mobile preview that updates as you type. Drag-and-drop reordering also works on touch screens now, so editing your page from a phone while travelling is no longer an exercise in frustration.',
                        'We rewrote the autosave layer so edits land within seconds instead of waiting for a manual save, and added a small "saved just now" indicator that quietly reassures you in the corner of the screen.',
                    ]],
                    ['heading' => 'Cleaner analytics', 'paragraphs' => [
                        'The analytics dashboard now lets you compare two periods side by side, so you can see whether a change you shipped last month actually moved the needle. The per-block heatmap loads instantly even on large pages, and the new "biggest movers" panel surfaces the blocks whose tap rate shifted most week-over-week, useful for spotting both wins and quiet regressions.',
                        'We also added an export to CSV for everyone who wanted to slice the data themselves in a spreadsheet or BI tool. The export includes UTM breakdowns and per-country data, which previously required tedious copy-paste.',
                    ]],
                    ['heading' => 'Smarter short links', 'paragraphs' => [
                        'Smart-routing rules now support country and time-of-day conditions, with a preview that lets you simulate which destination would fire for a given visitor profile. Useful for creators with international audiences or time-sensitive launches who want to validate routing before sharing the link publicly.',
                        'We also added a "preserve UTMs" toggle so attribution survives the redirect, which closes a long-standing gap that broke campaign tracking when destinations didn\'t already include the original tags.',
                    ]],
                    ['heading' => 'What we\'re working on next', 'paragraphs' => [
                        'A redesign of the contacts module, deeper newsletter integrations, and a long-overdue refresh of the public blog template you are reading right now. We are also exploring native scheduling for cross-platform posts so you can run your whole social rhythm from one queue. Stay tuned.',
                    ]],
                ],
                'outro' => 'We ship updates like these every few weeks. If there is something you wish Sayzio did and it doesn\'t yet, hit reply on any of our newsletter issues; most of the changes above started life as a single piece of customer feedback.',
            ],
        ];
    }
}
