<?php

namespace App\Modules\Common\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Common\Models\BlogCategory;
use App\Modules\Common\Models\BlogComment;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\BlogTag;
use App\Modules\Common\Models\NotificationBroadcast;
use App\Modules\Common\Services\BlogSettings;
use App\Modules\Common\Services\NotificationService;
use App\Modules\Common\Services\ViewerSession;
use App\Modules\User\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class BlogController extends Controller
{
    /**
     * Cache key for the default /blogs index page (no search, no tag
     * filter, page 1) — the page every anonymous visitor hits. Payload is
     * PLAIN ATTRIBUTE ARRAYS rehydrated on read (never serialized Eloquent
     * models — file cache turns those into __PHP_Incomplete_Class). With
     * production DB_PERSISTENT=false each query pays a ~3s SSL reconnect,
     * so the warm path must run zero queries. Invalidated immediately by
     * {@see BlogPost::flushPublicCaches()}, plus a 10-minute TTL safety net
     * for category/tag edits.
     */
    public const INDEX_CACHE_KEY = 'blogs:index:default:v1';

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tagSlug = trim((string) $request->query('tag', ''));
        $page = max(1, (int) $request->query('page', 1));

        // Warm cached path for the default view (covers ~all anonymous
        // traffic). Filtered/paginated views fall through to live queries.
        if ($q === '' && $tagSlug === '' && $page === 1) {
            $cached = $this->cachedDefaultIndex();
            if ($cached !== null) {
                return view('public.blogs.index', $cached + [
                    'activeTag' => null,
                    'q'         => '',
                    'settings'  => BlogSettings::all(),
                    'pageTitle' => 'Blog',
                    'pageMeta'  => BlogSettings::all()['hero_heading'] ?? 'Blog',
                ]);
            }
        }

        $query = BlogPost::published()->with(['category', 'author']);

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ilike', "%{$q}%")
                  ->orWhere('excerpt', 'ilike', "%{$q}%")
                  ->orWhere('body_html', 'ilike', "%{$q}%");
            });
        }

        // Tag filter via ?tag=slug — surfaces tag chips on the index page
        // without forcing a full /blogs/tag/{slug} navigation.
        $activeTag = null;
        if ($tagSlug !== '') {
            $activeTag = BlogTag::where('slug', $tagSlug)->first();
            if ($activeTag) {
                $tagId = $activeTag->id;
                $query->whereHas('tags', fn ($w) => $w->where('blog_tags.id', $tagId));
            }
        }

        $posts = $query->orderByDesc('published_at')->orderByDesc('id')->paginate(9)->withQueryString();
        $categories = BlogCategory::orderBy('sort_order')->orderBy('name')->get();
        $featured = BlogPost::published()->featured()->orderByDesc('published_at')->take(3)->get();
        // Most-used tags for the chip selector. Capped to keep the bar tidy.
        $popularTags = BlogTag::withCount(['posts' => fn ($w) => $w->where('status', 'published')])
            ->orderByDesc('posts_count')->orderBy('name')->take(20)->get()
            ->filter(fn ($t) => $t->posts_count > 0)->values();

        return view('public.blogs.index', [
            'posts'       => $posts,
            'categories'  => $categories,
            'featured'    => $featured,
            'popularTags' => $popularTags,
            'activeTag'   => $activeTag,
            'q'           => $q ?? '',
            'settings'    => BlogSettings::all(),
            'pageTitle'   => 'Blog',
            'pageMeta'    => BlogSettings::all()['hero_heading'] ?? 'Blog',
        ]);
    }

    /**
     * Build the cacheable default /blogs index payload from the DB as
     * plain attribute arrays. Shared by the request path
     * ({@see cachedDefaultIndex()}) and the scheduled marketing-cache
     * warmer (\App\Modules\Common\Support\MarketingPageCache), so both
     * always produce the same payload shape.
     */
    public static function buildDefaultIndexPayload(): array
    {
        $paginator = BlogPost::published()->with(['category', 'author'])
            ->orderByDesc('published_at')->orderByDesc('id')->paginate(9);
        $categories = BlogCategory::orderBy('sort_order')->orderBy('name')->get();
        $featured = BlogPost::published()->featured()->orderByDesc('published_at')->take(3)->get();
        $popularTags = BlogTag::withCount(['posts' => fn ($w) => $w->where('status', 'published')])
            ->orderByDesc('posts_count')->orderBy('name')->take(20)->get()
            ->filter(fn ($t) => $t->posts_count > 0)->values();

        return [
            'posts' => $paginator->getCollection()->map(fn (BlogPost $p) => [
                'post'     => $p->getAttributes(),
                'category' => $p->category?->getAttributes(),
                'author'   => $p->author?->getAttributes(),
            ])->all(),
            'total'       => $paginator->total(),
            'categories'  => $categories->map(fn ($c) => $c->getAttributes())->all(),
            'featured'    => $featured->map(fn ($p) => $p->getAttributes())->all(),
            // posts_count from withCount() lives in the attributes,
            // so it survives the round-trip.
            'popularTags' => $popularTags->map(fn ($t) => $t->getAttributes())->all(),
        ];
    }

    /**
     * Returns the fully-rehydrated view data for the default index page
     * from cache (building it on miss), or null when the cache layer is
     * unavailable — callers then fall through to the live-query path.
     */
    private function cachedDefaultIndex(): ?array
    {
        try {
            $payload = \Illuminate\Support\Facades\Cache::remember(
                self::INDEX_CACHE_KEY,
                600,
                fn () => self::buildDefaultIndexPayload()
            );
        } catch (\Throwable $e) {
            return null;
        }

        $posts = collect($payload['posts'])->map(function (array $row) {
            $post = BlogPost::query()->hydrate([$row['post']])->first();
            $post->setRelation('category', !empty($row['category'])
                ? BlogCategory::query()->hydrate([$row['category']])->first()
                : null);
            $post->setRelation('author', !empty($row['author'])
                ? Admin::query()->hydrate([$row['author']])->first()
                : null);
            return $post;
        });

        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $posts,
            (int) $payload['total'],
            9,
            1,
            ['path' => route('site.blogs.index'), 'pageName' => 'page']
        );

        return [
            'posts'       => $paginator,
            'categories'  => BlogCategory::hydrate($payload['categories']),
            'featured'    => BlogPost::hydrate($payload['featured']),
            'popularTags' => BlogTag::hydrate($payload['popularTags']),
        ];
    }

    public function category(string $slug)
    {
        $category = BlogCategory::where('slug', $slug)->firstOrFail();
        $posts = BlogPost::published()->with(['category', 'author'])
            ->where('category_id', $category->id)
            ->orderByDesc('published_at')
            ->paginate(9)->withQueryString();

        return view('public.blogs.category', [
            'category'   => $category,
            'posts'      => $posts,
            'categories' => BlogCategory::orderBy('sort_order')->orderBy('name')->get(),
            'settings'   => BlogSettings::all(),
            'pageTitle'  => $category->name . ' — Blog',
            'pageMeta'   => $category->description ?: ('Articles in ' . $category->name),
        ]);
    }

    public function tag(string $slug)
    {
        $tag = BlogTag::where('slug', $slug)->firstOrFail();
        $posts = BlogPost::published()->with(['category', 'author'])
            ->whereHas('tags', fn ($q) => $q->where('blog_tags.id', $tag->id))
            ->orderByDesc('published_at')
            ->paginate(9)->withQueryString();

        return view('public.blogs.tag', [
            'tag'        => $tag,
            'posts'      => $posts,
            'categories' => BlogCategory::orderBy('sort_order')->orderBy('name')->get(),
            'settings'   => BlogSettings::all(),
            'pageTitle'  => '#' . $tag->name . ' — Blog',
            'pageMeta'   => 'Posts tagged ' . $tag->name,
        ]);
    }

    public function show(string $slug)
    {
        $post = BlogPost::published()->with(['category', 'author', 'tags'])
            ->where('slug', $slug)->firstOrFail();

        // Best-effort view counter (non-blocking, ignore errors).
        try { $post->increment('view_count'); } catch (\Throwable $e) {}

        $tocBody = $post->tocAndBody();

        $related = BlogPost::published()
            ->where('id', '!=', $post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->orderByDesc('published_at')
            ->take(3)->get();

        $prev = BlogPost::published()
            ->where('published_at', '<', $post->published_at ?? $post->created_at)
            ->orderByDesc('published_at')->first();
        $next = BlogPost::published()
            ->where('published_at', '>', $post->published_at ?? $post->created_at)
            ->orderBy('published_at')->first();

        $settings = BlogSettings::all();

        $comments = BlogComment::where('post_id', $post->id)
            ->where('status', 'approved')
            ->whereNull('parent_id')
            ->with(['replies' => fn ($q) => $q->where('status', 'approved')->orderBy('created_at')])
            ->orderBy('created_at')
            ->paginate($settings['comments_per_page']);

        return view('public.blogs.show', [
            'post'        => $post,
            'bodyHtml'    => $tocBody['html'],
            'toc'         => $tocBody['toc'],
            'related'     => $related,
            'prevPost'    => $prev,
            'nextPost'    => $next,
            'comments'    => $comments,
            'settings'    => $settings,
            'commenter'   => $this->currentCommenter(),
            'pageTitle'   => $post->meta_title ?: $post->title,
            'pageMeta'    => $post->meta_description ?: $post->excerpt,
        ]);
    }

    /**
     * Public JSON feed of published posts, consumed by the standalone
     * marketing site (1inme.com) so its /blog list stays in sync with the
     * database-driven blog here. CORS-open + no auth: this is public content.
     */
    public function feed(Request $request)
    {
        $limit = min(50, max(1, (int) $request->query('limit', 30)));

        $posts = BlogPost::published()->with(['category', 'author'])
            ->orderByDesc('published_at')->orderByDesc('id')
            ->take($limit)->get();

        $data = $posts->map(fn (BlogPost $p) => $this->feedItem($p))->values();

        return response()->json(['data' => $data], 200, $this->corsHeaders());
    }

    /**
     * Public JSON for a single published post (full body), consumed by the
     * marketing site's /blog/:slug page.
     */
    public function feedShow(string $slug)
    {
        $post = BlogPost::published()->with(['category', 'author'])
            ->where('slug', $slug)->first();

        if (!$post) {
            return response()->json([
                'error' => ['message' => 'Post not found', 'code' => 'not_found'],
            ], 404, $this->corsHeaders());
        }

        $item = $this->feedItem($post);
        $item['bodyHtml']        = (string) $post->body_html;
        $item['metaTitle']       = $post->meta_title ?: $post->title;
        $item['metaDescription'] = $post->meta_description ?: $post->excerpt;

        return response()->json(['data' => $item], 200, $this->corsHeaders());
    }

    /**
     * Shared shape for a blog post in the public JSON feed. Field names are
     * camelCase to match the marketing site's BlogPost interface.
     */
    private function feedItem(BlogPost $p): array
    {
        $date = $p->published_at ?? $p->created_at;

        return [
            'slug'        => (string) $p->slug,
            'title'       => (string) $p->title,
            'excerpt'     => (string) ($p->excerpt ?? ''),
            'date'        => $date ? $date->toDateString() : null,
            'readingTime' => max(1, (int) $p->reading_time_min) . ' min read',
            'author'      => (string) ($p->author?->name ?? 'The Sayzio Team'),
            'category'    => (string) ($p->category?->name ?? 'General'),
            'coverImage'  => $this->absoluteUrl($p->cover_image),
        ];
    }

    /**
     * Turn a possibly-relative stored asset path (e.g. "/storage/...") into an
     * absolute URL. The marketing site is a different origin, so relative paths
     * would otherwise resolve against the marketing domain and 404. Absolute
     * http(s) URLs (S3/CloudFront) are returned untouched.
     */
    private function absoluteUrl(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        return url($path);
    }

    private function corsHeaders(): array
    {
        return [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, X-Requested-With',
            'Access-Control-Max-Age'       => '600',
            'Cache-Control'                => 'public, max-age=300',
        ];
    }

    public function feedPreflight()
    {
        return response('', 204, $this->corsHeaders());
    }

    public function rss()
    {
        $body = \Illuminate\Support\Facades\Cache::remember('blog.rss.xml', 600, function () {
            $posts = BlogPost::published()->with('author')
                ->orderByDesc('published_at')
                ->take(50)->get();
            return view('public.blogs.rss', ['posts' => $posts])->render();
        });
        return response($body, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }

    public function sitemap()
    {
        // Cached for 10 minutes; invalidated by BlogPost::flushPublicCaches()
        // on publish/update/schedule so the sitemap stays in sync.
        $body = \Illuminate\Support\Facades\Cache::remember('blog.sitemap.xml', 600, function () {
            $posts = BlogPost::published()->orderByDesc('published_at')->get();
            $categories = BlogCategory::orderBy('name')->get();
            $tags = BlogTag::orderBy('name')->get();
            return view('public.blogs.sitemap', [
                'posts' => $posts, 'categories' => $categories, 'tags' => $tags,
            ])->render();
        });
        return response($body, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function postComment(Request $request, string $slug)
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();
        $settings = BlogSettings::all();

        if (!$post->allow_comments || $settings['approval_mode'] === BlogSettings::APPROVAL_CLOSED) {
            return back()->with('error', 'Comments are closed on this post.');
        }

        $commenter = $this->currentCommenter();
        if ($commenter['type'] === null) {
            return back()->with('error', 'Please sign in to leave a comment.');
        }
        // Viewer-session commenters only allowed when the setting is enabled.
        if ($commenter['type'] === 'viewer' && empty($settings['allow_guest_viewer_comments'])) {
            return back()->with('error', 'Comments are limited to signed-in members.');
        }
        // Viewer commenters must have an email when the setting requires it.
        if ($commenter['type'] === 'viewer' && !empty($settings['require_email']) && empty($commenter['email'])) {
            return back()->with('error', 'An email is required to comment. Please add one to your viewer profile.');
        }

        $rules = [
            'body'      => 'required|string|min:2|max:4000',
            'parent_id' => 'nullable|integer|exists:blog_comments,id',
        ];
        if ($commenter['type'] === 'viewer' && $settings['require_email']) {
            // viewer accounts already have an email; guest path not enabled.
        }
        $data = $request->validate($rules);

        // Only staff can reply (parent_id set). Block reader -> reader replies.
        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            return back()->with('error', 'Only staff can reply to comments.');
        }

        // Decide initial moderation status.
        $status = 'pending';
        if ($settings['approval_mode'] === BlogSettings::APPROVAL_AUTO) {
            $status = 'approved';
        } elseif ($settings['approval_mode'] === BlogSettings::APPROVAL_RETURNING) {
            $hasApproved = BlogComment::where('author_type', $commenter['type'])
                ->where('author_id', $commenter['id'])
                ->where('status', 'approved')->exists();
            $status = $hasApproved ? 'approved' : 'pending';
        }

        $body = trim((string) $data['body']);
        if ($settings['spam_filter'] && $this->looksSpammy($body)) {
            $status = 'spam';
        }

        $comment = BlogComment::create([
            'post_id'       => $post->id,
            'parent_id'     => null,
            'author_type'   => $commenter['type'],
            'author_id'     => $commenter['id'],
            'author_name'   => $commenter['name'],
            'author_email'  => $commenter['email'],
            'author_avatar' => $commenter['avatar'],
            'body'          => $body,
            'status'        => $status,
            'ip_address'    => substr((string) $request->ip(), 0, 64),
            'user_agent'    => substr((string) $request->userAgent(), 0, 250),
        ]);

        // Notify staff with moderate permission of the new pending comment.
        if ($status === 'pending') {
            $this->notifyModerators($post, $comment);
        }

        $msg = $status === 'approved'
            ? 'Comment posted.'
            : ($status === 'spam' ? 'Your comment was flagged for review.' : 'Thanks — your comment is awaiting moderation.');

        return back()->with('success', $msg)->with('blog_comment_state', $status);
    }

    /**
     * Resolve the active commenter from either dashboard auth or viewer
     * session. Returns null type when no commenter is authenticated.
     */
    private function currentCommenter(): array
    {
        $u = Auth::user();
        if ($u) {
            return [
                'type'   => 'user',
                'id'     => (int) $u->id,
                'name'   => (string) ($u->name ?? $u->email ?? 'User'),
                'email'  => (string) ($u->email ?? ''),
                'avatar' => method_exists($u, 'getAvatarUrl') ? $u->getAvatarUrl() : null,
            ];
        }
        $v = ViewerSession::user();
        if ($v) {
            return [
                'type'   => 'viewer',
                'id'     => (int) $v->id,
                'name'   => (string) ($v->name ?? 'Reader'),
                'email'  => (string) ($v->email ?? ''),
                'avatar' => $v->avatar ?? null,
            ];
        }
        return ['type' => null, 'id' => null, 'name' => null, 'email' => null, 'avatar' => null];
    }

    private function looksSpammy(string $body): bool
    {
        $lower = strtolower($body);
        $links = preg_match_all('#https?://#', $lower);
        if ($links >= 4) return true;
        $needles = ['viagra', 'casino', 'porn', 'crypto giveaway'];
        foreach ($needles as $n) {
            if (strpos($lower, $n) !== false) return true;
        }
        return false;
    }

    private function notifyModerators(BlogPost $post, BlogComment $comment): void
    {
        try {
            $count = 0;
            foreach (Admin::with('role.permissions')->get() as $admin) {
                if ($admin->hasPermission('blogs.comments.moderate')) $count++;
            }
            NotificationBroadcast::create([
                'admin_id'         => null,
                'target_kind'      => 'permission',
                'target_value'     => 'blogs.comments.moderate',
                'type'             => 'blog_comment_pending',
                'subject'          => 'New blog comment awaiting approval',
                'body'             => 'On "' . $post->title . '" by ' . ($comment->author_name ?: 'a reader'),
                'target_url'       => route('admin.blogs.comments.index', ['status' => 'pending']),
                'recipients_count' => $count,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Blog comment moderator notification failed', ['error' => $e->getMessage()]);
        }
    }
}
