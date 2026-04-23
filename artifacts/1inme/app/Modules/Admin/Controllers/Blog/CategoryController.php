<?php

namespace App\Modules\Admin\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Modules\Common\Models\BlogCategory;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = BlogCategory::orderBy('sort_order')->orderBy('name')->withCount('posts')->get();
        return view('admin.blogs.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);
        $data['slug'] = BlogCategory::uniqueSlug($data['slug'] ?: $data['name']);
        BlogCategory::create($data);
        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, BlogCategory $category)
    {
        $data = $this->validateData($request);
        $data['slug'] = $data['slug']
            ? BlogCategory::uniqueSlug($data['slug'], $category->id)
            : $category->slug;
        $category->update($data);
        return back()->with('success', 'Category updated.');
    }

    public function destroy(BlogCategory $category)
    {
        $category->delete();
        return back()->with('success', 'Category deleted.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'name'        => 'required|string|max:120',
            'slug'        => 'nullable|string|max:120|regex:/^[a-z0-9-]*$/i',
            'color'       => 'nullable|string|max:32',
            'cover_image' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'sort_order'  => 'nullable|integer|min:0|max:9999',
        ]);
    }
}
