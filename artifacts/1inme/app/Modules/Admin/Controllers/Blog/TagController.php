<?php

namespace App\Modules\Admin\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\BlogTag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index()
    {
        $tags = BlogTag::orderBy('name')->withCount('posts')->paginate(50);
        return view('admin.blogs.tags.index', compact('tags'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => 'nullable|string|max:80|regex:/^[a-z0-9-]*$/i',
        ]);
        $data['slug'] = BlogTag::uniqueSlug($data['slug'] ?: $data['name']);
        BlogTag::create($data);
        return back()->with('success', 'Tag created.');
    }

    public function update(Request $request, BlogTag $tag)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'slug' => 'nullable|string|max:80|regex:/^[a-z0-9-]*$/i',
        ]);
        $data['slug'] = $data['slug']
            ? BlogTag::uniqueSlug($data['slug'], $tag->id)
            : $tag->slug;
        $tag->update($data);
        return back()->with('success', 'Tag updated.');
    }

    public function destroy(BlogTag $tag)
    {
        $tag->delete();
        return back()->with('success', 'Tag deleted.');
    }
}
