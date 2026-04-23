<?php

namespace App\Modules\Admin\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Common\Models\BlogCategory;
use App\Modules\Common\Models\BlogPost;
use App\Modules\Common\Models\BlogTag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PostController extends Controller
{
    public function index(Request $request)
    {
        $query = BlogPost::query()->with(['category', 'author']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($cat = $request->query('category')) {
            $query->where('category_id', $cat);
        }
        if ($author = $request->query('author')) {
            $query->where('author_id', $author);
        }
        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'ilike', "%{$q}%")->orWhere('slug', 'ilike', "%{$q}%");
            });
        }
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to . ' 23:59:59');
        }

        $posts = $query->orderByDesc('id')->paginate(20)->withQueryString();
        $categories = BlogCategory::orderBy('name')->get();
        $authors = Admin::orderBy('name')->get(['id', 'name']);

        $counts = [
            'all'       => BlogPost::count(),
            'draft'     => BlogPost::where('status', 'draft')->count(),
            'scheduled' => BlogPost::where('status', 'scheduled')->count(),
            'published' => BlogPost::where('status', 'published')->count(),
            'archived'  => BlogPost::where('status', 'archived')->count(),
        ];

        return view('admin.blogs.posts.index', compact('posts', 'categories', 'authors', 'counts'));
    }

    public function create()
    {
        $post = new BlogPost(['status' => 'draft', 'allow_comments' => true]);
        return view('admin.blogs.posts.form', [
            'post'       => $post,
            'categories' => BlogCategory::orderBy('name')->get(),
            'tags'       => BlogTag::orderBy('name')->get(),
            'authors'    => $this->eligibleAuthors(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $admin = Auth::guard('admin')->user();
        $this->authorizePublishStatus($admin, $data['status']);

        if (empty($data['slug'])) {
            $data['slug'] = BlogPost::uniqueSlug($data['title']);
        } else {
            $data['slug'] = BlogPost::uniqueSlug($data['slug']);
        }
        $data['author_id'] = $data['author_id'] ?? ($admin?->id);
        $data = $this->normalizeStatusTimestamps($data);

        $post = BlogPost::create($data);
        $this->syncTags($post, $request);

        return redirect()->route('admin.blogs.posts.edit', $post)->with('success', 'Post saved.');
    }

    public function edit(BlogPost $post)
    {
        return view('admin.blogs.posts.form', [
            'post'       => $post,
            'categories' => BlogCategory::orderBy('name')->get(),
            'tags'       => BlogTag::orderBy('name')->get(),
            'authors'    => $this->eligibleAuthors(),
        ]);
    }

    /**
     * Admins eligible to be listed as a post author: super-admins or
     * any admin holding the `blogs.manage` permission. Falls back to all
     * admins if permission resolution can't be done.
     */
    private function eligibleAuthors()
    {
        return Admin::orderBy('name')->get(['id', 'name'])->filter(function ($a) {
            try {
                return $a->isSuperAdmin() || $a->hasPermission('blogs.manage');
            } catch (\Throwable $e) {
                return true;
            }
        })->values();
    }

    public function update(Request $request, BlogPost $post)
    {
        $data = $this->validateData($request, $post);
        $admin = Auth::guard('admin')->user();
        $this->authorizePublishStatus($admin, $data['status']);

        if (empty($data['slug'])) {
            $data['slug'] = BlogPost::uniqueSlug($data['title'], $post->id);
        } elseif ($data['slug'] !== $post->slug) {
            $data['slug'] = BlogPost::uniqueSlug($data['slug'], $post->id);
        }
        $data = $this->normalizeStatusTimestamps($data, $post);

        $post->update($data);
        $this->syncTags($post, $request);

        return redirect()->route('admin.blogs.posts.edit', $post)->with('success', 'Post updated.');
    }

    public function destroy(BlogPost $post)
    {
        $post->delete();
        return redirect()->route('admin.blogs.posts.index')->with('success', 'Post deleted.');
    }

    /**
     * Upload an image (cover or inline) to the public disk under blogs/.
     * Returns JSON { url, path } so the editor can insert it.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,gif,webp,svg|max:5120',
        ]);
        $path = $request->file('file')->store('blogs', 'public');
        return response()->json([
            'url'  => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:publish,unpublish,archive,delete',
            'ids'    => 'required|array',
            'ids.*'  => 'integer|exists:blog_posts,id',
        ]);
        $posts = BlogPost::whereIn('id', $data['ids'])->get();
        $admin = Auth::guard('admin')->user();

        foreach ($posts as $post) {
            switch ($data['action']) {
                case 'publish':
                    if ($admin->hasPermission('blogs.publish') || $admin->isSuperAdmin()) {
                        $post->update(['status' => 'published', 'published_at' => $post->published_at ?: now()]);
                    }
                    break;
                case 'unpublish':
                    $post->update(['status' => 'draft']);
                    break;
                case 'archive':
                    $post->update(['status' => 'archived']);
                    break;
                case 'delete':
                    $post->delete();
                    break;
            }
        }
        return back()->with('success', 'Bulk action applied.');
    }

    private function validateData(Request $request, ?BlogPost $post = null): array
    {
        $rules = [
            'title'             => 'required|string|max:255',
            'slug'              => 'nullable|string|max:255|regex:/^[a-z0-9-]*$/i',
            'excerpt'           => 'nullable|string|max:1000',
            'body_html'         => 'nullable|string|max:200000',
            'cover_image'       => 'nullable|string|max:500',
            'category_id'       => 'nullable|exists:blog_categories,id',
            'author_id'         => 'nullable|exists:admins,id',
            'status'            => 'required|in:draft,scheduled,published,archived',
            'scheduled_at'      => 'nullable|date',
            'published_at'      => 'nullable|date',
            'meta_title'        => 'nullable|string|max:255',
            'meta_description'  => 'nullable|string|max:500',
            'og_image'          => 'nullable|string|max:500',
            'canonical_url'     => 'nullable|string|max:500',
            'is_featured_home'  => 'nullable|boolean',
            'featured_slot'     => 'nullable|in:hero,carousel',
            'allow_comments'    => 'nullable|boolean',
        ];
        $data = $request->validate($rules);
        $data['is_featured_home'] = (bool) ($data['is_featured_home'] ?? false);
        $data['allow_comments']   = (bool) ($data['allow_comments']   ?? false);
        return $data;
    }

    private function authorizePublishStatus(?Admin $admin, string $status): void
    {
        if (in_array($status, ['published', 'scheduled'], true)) {
            if (!$admin || (!$admin->isSuperAdmin() && !$admin->hasPermission('blogs.publish'))) {
                abort(403, 'You do not have permission to publish posts.');
            }
        }
    }

    private function normalizeStatusTimestamps(array $data, ?BlogPost $post = null): array
    {
        if ($data['status'] === 'published') {
            if (empty($data['published_at'])) {
                $data['published_at'] = $post && $post->published_at ? $post->published_at : now();
            }
            $data['scheduled_at'] = null;
        } elseif ($data['status'] === 'scheduled') {
            if (empty($data['scheduled_at']) || strtotime($data['scheduled_at']) <= time()) {
                // Falling back: if no future time provided, push it 5 minutes out
                // so the publisher picks it up promptly without breaking the flow.
                $data['scheduled_at'] = now()->addMinutes(5);
            }
        } elseif ($data['status'] === 'draft') {
            // Keep existing timestamps but unset scheduled.
            $data['scheduled_at'] = null;
        }
        return $data;
    }

    private function syncTags(BlogPost $post, Request $request): void
    {
        $raw = (string) $request->input('tags_input', '');
        if (trim($raw) === '') { $post->tags()->sync([]); return; }
        $names = array_filter(array_map('trim', preg_split('/,/', $raw)));
        $ids = [];
        foreach ($names as $name) {
            if ($name === '') continue;
            $tag = BlogTag::firstOrCreate(
                ['slug' => Str::slug($name) ?: 'tag-' . Str::random(4)],
                ['name' => $name]
            );
            $ids[] = $tag->id;
        }
        $post->tags()->sync(array_unique($ids));
    }
}
