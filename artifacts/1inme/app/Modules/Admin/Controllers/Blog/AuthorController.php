<?php

namespace App\Modules\Admin\Controllers\Blog;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\Admin;
use App\Modules\Common\Models\BlogPost;
use Illuminate\Support\Facades\DB;

class AuthorController extends Controller
{
    public function index()
    {
        $counts = BlogPost::select('author_id', DB::raw('count(*) as total'),
                DB::raw("sum(case when status='published' then 1 else 0 end) as published"),
                DB::raw("sum(case when status='draft' then 1 else 0 end) as drafts"),
                DB::raw("sum(case when status='scheduled' then 1 else 0 end) as scheduled"))
            ->whereNotNull('author_id')
            ->groupBy('author_id')
            ->get()->keyBy('author_id');

        $authors = Admin::with('role')
            ->whereIn('id', $counts->keys()->all() ?: [0])
            ->orderBy('name')
            ->get();

        return view('admin.blogs.authors.index', compact('authors', 'counts'));
    }
}
