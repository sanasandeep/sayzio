@extends('admin.layouts.app')
@section('title', 'Blog posts')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Blog posts</h1>
            <p class="text-sm text-white/50 mt-1">Manage everything published under /blogs.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.blogs.categories.index') }}" class="px-3 py-2 text-sm bg-white/5 hover:bg-white/10 rounded-lg text-white/80"><i class="fas fa-folder mr-1"></i>Categories</a>
            <a href="{{ route('admin.blogs.tags.index') }}" class="px-3 py-2 text-sm bg-white/5 hover:bg-white/10 rounded-lg text-white/80"><i class="fas fa-hashtag mr-1"></i>Tags</a>
            <a href="{{ route('admin.blogs.authors.index') }}" class="px-3 py-2 text-sm bg-white/5 hover:bg-white/10 rounded-lg text-white/80"><i class="fas fa-user-pen mr-1"></i>Authors</a>
            <a href="{{ route('admin.blogs.comments.index') }}" class="px-3 py-2 text-sm bg-white/5 hover:bg-white/10 rounded-lg text-white/80"><i class="fas fa-comments mr-1"></i>Comments</a>
            <a href="{{ route('admin.blogs.settings.edit') }}" class="px-3 py-2 text-sm bg-white/5 hover:bg-white/10 rounded-lg text-white/80"><i class="fas fa-cog mr-1"></i>Settings</a>
            @if(auth('admin')->user()->hasPermission('blogs.manage'))
                <a href="{{ route('admin.blogs.posts.create') }}" class="px-4 py-2 text-sm bg-blue-600 hover:bg-blue-700 rounded-lg text-white"><i class="fas fa-plus mr-1"></i>New post</a>
            @endif
        </div>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>@endif

    <div class="flex flex-wrap gap-2 text-xs">
        @foreach(['all'=>'All','draft'=>'Drafts','scheduled'=>'Scheduled','published'=>'Published','archived'=>'Archived'] as $key=>$label)
            @php $active = ($key==='all' && !request('status')) || request('status')===$key; @endphp
            <a href="{{ route('admin.blogs.posts.index', $key==='all' ? [] : ['status'=>$key]) }}" class="px-3 py-1.5 rounded-full {{ $active ? 'bg-blue-600 text-white' : 'bg-white/5 text-white/70 hover:bg-white/10' }}">{{ $label }} <span class="opacity-60">({{ $counts[$key] ?? 0 }})</span></a>
        @endforeach
    </div>

    <form method="GET" class="glass rounded-xl p-4 grid sm:grid-cols-5 gap-3">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search title/slug…" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
        <select name="category" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            <option value="">All categories</option>
            @foreach($categories as $c) <option value="{{ $c->id }}" @selected(request('category')==$c->id)>{{ $c->name }}</option>@endforeach
        </select>
        <select name="author" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
            <option value="">All authors</option>
            @foreach($authors as $a) <option value="{{ $a->id }}" @selected(request('author')==$a->id)>{{ $a->name }}</option>@endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-sm text-white">
        <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm text-white">Filter</button>
    </form>

    <form method="POST" action="{{ route('admin.blogs.posts.bulk') }}" class="glass rounded-xl overflow-hidden">
        @csrf
        <div class="p-3 flex items-center gap-2 border-b border-white/10">
            <select name="action" class="px-3 py-1.5 bg-white/5 border border-white/10 rounded text-xs text-white">
                <option value="publish">Publish</option>
                <option value="unpublish">Unpublish</option>
                <option value="archive">Archive</option>
                <option value="delete">Delete</option>
            </select>
            <button class="px-3 py-1.5 bg-white/10 hover:bg-white/15 rounded text-xs text-white">Apply</button>
        </div>
        <table class="w-full text-sm">
            <thead class="text-[10px] uppercase tracking-wider text-white/50 border-b border-white/10">
                <tr><th class="p-3"><input type="checkbox" onchange="document.querySelectorAll('input[name=\'ids[]\']').forEach(c=>c.checked=this.checked)"></th><th class="p-3 text-left">Title</th><th class="p-3 text-left">Category</th><th class="p-3 text-left">Status</th><th class="p-3 text-left">Author</th><th class="p-3 text-left">Date</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($posts as $p)
                    <tr class="border-b border-white/5 hover:bg-white/[0.02]">
                        <td class="p-3"><input type="checkbox" name="ids[]" value="{{ $p->id }}"></td>
                        <td class="p-3">
                            <a href="{{ route('admin.blogs.posts.edit', $p) }}" class="text-white hover:text-blue-300 font-medium">{{ $p->title }}</a>
                            <div class="text-[11px] text-white/40">/{{ $p->slug }}</div>
                        </td>
                        <td class="p-3 text-white/70">{{ optional($p->category)->name ?: '—' }}</td>
                        <td class="p-3"><span class="px-2 py-0.5 rounded text-[10px] uppercase tracking-wider {{ ['draft'=>'bg-white/10 text-white/70','scheduled'=>'bg-amber-500/20 text-amber-300','published'=>'bg-emerald-500/20 text-emerald-300','archived'=>'bg-white/5 text-white/40'][$p->status] }}">{{ $p->status }}</span></td>
                        <td class="p-3 text-white/70">{{ optional($p->author)->name ?: '—' }}</td>
                        <td class="p-3 text-white/60 text-xs">{{ optional($p->published_at ?: $p->scheduled_at ?: $p->created_at)->format('M j, Y H:i') }}</td>
                        <td class="p-3 text-right">
                            <a href="{{ route('site.blogs.show', $p->slug) }}" target="_blank" class="text-white/60 hover:text-white text-xs"><i class="fas fa-external-link-alt"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-8 text-center text-white/50">No posts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </form>

    <div>{{ $posts->links() }}</div>
</div>
@endsection
