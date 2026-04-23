@extends('admin.layouts.app')
@section('title', $post->exists ? 'Edit post' : 'New post')

@push('styles')
<style>
    .rte-btn { display:inline-flex; align-items:center; justify-content:center; min-width:1.75rem; height:1.75rem; padding:0 .4rem; font-size:12px; color:rgba(255,255,255,.7); background:transparent; border-radius:.375rem; border:1px solid transparent; }
    .rte-btn:hover { background:rgba(255,255,255,.08); color:#fff; }
    .rte-content { min-height: 320px; padding: 12px 14px; border:1px solid rgba(255,255,255,.1); border-radius:.5rem; background:rgba(255,255,255,.04); color:#fff; }
    .rte-content:focus { outline:none; border-color:rgba(139,92,246,.6); }
    .rte-content :is(h2,h3,h4) { font-weight:600; color:#fff; margin:.6em 0 .25em; }
    .rte-content h2 { font-size:1.35rem; }
    .rte-content h3 { font-size:1.1rem; }
    .rte-content p { margin:.4em 0; }
    .rte-content ul { list-style:disc; padding-left:1.25rem; margin:.4em 0; }
    .rte-content ol { list-style:decimal; padding-left:1.25rem; margin:.4em 0; }
    .rte-content blockquote { border-left:3px solid rgba(139,92,246,.5); padding-left:.75rem; color:rgba(255,255,255,.8); margin:.5em 0; }
    .rte-content a { color:#c4b5fd; text-decoration:underline; }
    .rte-content img { max-width:100%; border-radius:.5rem; }
    .rte-content:empty::before { content: attr(data-placeholder); color:rgba(255,255,255,.35); }
</style>
@endpush

@push('scripts')
<script>
function blogForm(initialBody) {
    return {
        body: initialBody || '',
        editor: null,
        mount(el) {
            this.editor = el;
            el.setAttribute('data-placeholder', 'Write the article…');
            el.innerHTML = this.body;
            el.addEventListener('input', () => { this.body = el.innerHTML; });
            el.addEventListener('paste', (e) => {
                e.preventDefault();
                const text = (e.clipboardData || window.clipboardData).getData('text/plain');
                document.execCommand('insertText', false, text);
            });
        },
        exec(cmd, value=null) { this.editor && this.editor.focus(); try { document.execCommand(cmd, false, value); } catch(e){} this.body = this.editor.innerHTML; },
        block(tag) { this.exec('formatBlock', tag.toUpperCase()); },
        link() {
            const url = window.prompt('URL'); if (!url) return;
            this.exec('createLink', url);
        },
        image() {
            const choice = window.prompt('Paste an image URL — or leave blank to upload from your computer.');
            if (choice && choice.trim() !== '') { this.exec('insertImage', choice.trim()); return; }
            const input = document.createElement('input');
            input.type = 'file'; input.accept = 'image/*';
            input.onchange = async () => {
                if (!input.files || !input.files[0]) return;
                const fd = new FormData(); fd.append('file', input.files[0]);
                const res = await fetch('{{ route('admin.blogs.posts.upload') }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                    body: fd,
                });
                if (!res.ok) { alert('Upload failed.'); return; }
                const data = await res.json();
                this.exec('insertImage', data.url);
            };
            input.click();
        },
        code() {
            const snippet = window.prompt('Paste your code snippet'); if (snippet === null) return;
            const html = '<pre><code>' + snippet.replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])) + '</code></pre><p><br></p>';
            this.exec('insertHTML', html);
        },
        embed() {
            const url = window.prompt('Paste a YouTube, Vimeo or generic embed URL'); if (!url) return;
            let src = url.trim();
            const yt = src.match(/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/|shorts\/))([\w-]{6,})/i);
            if (yt) { src = 'https://www.youtube.com/embed/' + yt[1]; }
            const vm = src.match(/vimeo\.com\/(\d+)/);
            if (vm) { src = 'https://player.vimeo.com/video/' + vm[1]; }
            const html = '<div class="embed-wrap"><iframe src="' + src.replace(/"/g, '&quot;') + '" frameborder="0" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen></iframe></div><p><br></p>';
            this.exec('insertHTML', html);
        },
    };
}
window.blogUploadCover = async function(input) {
    const f = input.files && input.files[0]; if (!f) return;
    const fd = new FormData(); fd.append('file', f);
    const res = await fetch('{{ route('admin.blogs.posts.upload') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: fd,
    });
    if (!res.ok) { alert('Cover upload failed.'); return; }
    const data = await res.json();
    const target = document.querySelector('input[name="cover_image"]');
    if (target) { target.value = data.url; target.dispatchEvent(new Event('input', { bubbles: true })); }
};
</script>
@endpush

@section('content')
@php $action = $post->exists ? route('admin.blogs.posts.update', $post) : route('admin.blogs.posts.store'); @endphp
<div class="max-w-6xl mx-auto" x-data="blogForm(@js(old('body_html', $post->body_html ?? '')))">
    <a href="{{ route('admin.blogs.posts.index') }}" class="text-xs text-violet-400 hover:underline"><i class="fas fa-arrow-left mr-1"></i>Back to all posts</a>

    <form method="POST" action="{{ $action }}" class="mt-4 grid lg:grid-cols-3 gap-6">
        @csrf
        @if($post->exists) @method('PUT') @endif

        <div class="lg:col-span-2 space-y-5">
            <div class="glass rounded-2xl p-6 space-y-4">
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Title</label>
                    <input type="text" name="title" required value="{{ old('title', $post->title) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white">
                    @error('title')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Slug <span class="text-white/40 normal-case">(optional — auto-generated)</span></label>
                    <input type="text" name="slug" value="{{ old('slug', $post->slug) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white font-mono text-sm">
                    @error('slug')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Excerpt</label>
                    <textarea name="excerpt" rows="2" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">{{ old('excerpt', $post->excerpt) }}</textarea>
                </div>
            </div>

            <div class="glass rounded-2xl p-6">
                <label class="block text-xs uppercase tracking-wider text-white/60 mb-2">Body</label>
                <div class="flex flex-wrap gap-1 mb-2">
                    <button type="button" class="rte-btn" @click="block('h2')">H2</button>
                    <button type="button" class="rte-btn" @click="block('h3')">H3</button>
                    <button type="button" class="rte-btn" @click="block('p')">¶</button>
                    <button type="button" class="rte-btn" @click="exec('bold')"><b>B</b></button>
                    <button type="button" class="rte-btn" @click="exec('italic')"><i>I</i></button>
                    <button type="button" class="rte-btn" @click="exec('insertUnorderedList')"><i class="fas fa-list-ul"></i></button>
                    <button type="button" class="rte-btn" @click="exec('insertOrderedList')"><i class="fas fa-list-ol"></i></button>
                    <button type="button" class="rte-btn" @click="block('blockquote')"><i class="fas fa-quote-right"></i></button>
                    <button type="button" class="rte-btn" @click="link()"><i class="fas fa-link"></i></button>
                    <button type="button" class="rte-btn" @click="image()" title="Insert image (URL or upload)"><i class="fas fa-image"></i></button>
                    <button type="button" class="rte-btn" @click="code()" title="Insert code block"><i class="fas fa-code"></i></button>
                    <button type="button" class="rte-btn" @click="embed()" title="Embed YouTube/Vimeo"><i class="fas fa-video"></i></button>
                    <button type="button" class="rte-btn" @click="exec('removeFormat')"><i class="fas fa-eraser"></i></button>
                </div>
                <div class="rte-content" contenteditable="true" x-init="mount($el)"></div>
                <textarea name="body_html" class="hidden" x-model="body"></textarea>
                @error('body_html')<p class="mt-1 text-xs text-red-400">{{ $message }}</p>@enderror
            </div>

            <div class="glass rounded-2xl p-6 space-y-4">
                <h3 class="text-sm font-semibold text-white">SEO</h3>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Meta title</label>
                    <input type="text" name="meta_title" value="{{ old('meta_title', $post->meta_title) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Meta description</label>
                    <textarea name="meta_description" rows="2" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">{{ old('meta_description', $post->meta_description) }}</textarea>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">OG image URL</label>
                        <input type="text" name="og_image" value="{{ old('og_image', $post->og_image) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    </div>
                    <div>
                        <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Canonical URL</label>
                        <input type="text" name="canonical_url" value="{{ old('canonical_url', $post->canonical_url) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    </div>
                </div>
            </div>
        </div>

        <aside class="space-y-5">
            <div class="glass rounded-2xl p-5 space-y-3">
                <h3 class="text-sm font-semibold text-white">Publish</h3>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Status</label>
                    <select name="status" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                        @foreach(['draft'=>'Draft','scheduled'=>'Scheduled','published'=>'Published','archived'=>'Archived'] as $k=>$v)
                            <option value="{{ $k }}" @selected(old('status', $post->status ?? 'draft')===$k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Schedule for</label>
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at', optional($post->scheduled_at)->format('Y-m-d\TH:i')) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Published at</label>
                    <input type="datetime-local" name="published_at" value="{{ old('published_at', optional($post->published_at)->format('Y-m-d\TH:i')) }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                </div>
                <button class="w-full px-4 py-2 bg-violet-600 hover:bg-violet-700 rounded-lg text-sm font-medium text-white">{{ $post->exists ? 'Save changes' : 'Create post' }}</button>
                @if($post->exists)
                    <button form="delete-form" class="w-full px-4 py-2 bg-red-500/15 hover:bg-red-500/25 text-red-300 rounded-lg text-xs">Delete post</button>
                @endif
            </div>

            <div class="glass rounded-2xl p-5 space-y-3">
                <h3 class="text-sm font-semibold text-white">Organize</h3>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Category</label>
                    <select name="category_id" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                        <option value="">Uncategorized</option>
                        @foreach($categories as $c) <option value="{{ $c->id }}" @selected(old('category_id', $post->category_id)==$c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Tags <span class="text-white/40 normal-case">(comma separated)</span></label>
                    <input type="text" name="tags_input" value="{{ old('tags_input', $post->exists ? $post->tags->pluck('name')->implode(', ') : '') }}" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Author</label>
                    @php $authorId = old('author_id', $post->author_id ?? auth('admin')->id()); @endphp
                    <select name="author_id" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                        <option value="">— Unassigned —</option>
                        @foreach($authors as $a)
                            <option value="{{ $a->id }}" @selected((int)$authorId === (int)$a->id)>{{ $a->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Cover image</label>
                    <input type="text" name="cover_image" value="{{ old('cover_image', $post->cover_image) }}" placeholder="https://… or upload" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    <div class="mt-2 flex items-center gap-2">
                        <label class="px-3 py-1.5 text-xs rounded-md bg-white/10 hover:bg-white/15 text-white cursor-pointer">
                            <i class="fas fa-upload mr-1"></i> Upload
                            <input type="file" accept="image/*" class="hidden" onchange="window.blogUploadCover(this)">
                        </label>
                    </div>
                </div>
            </div>

            <div class="glass rounded-2xl p-5 space-y-3">
                <h3 class="text-sm font-semibold text-white">Discovery</h3>
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="is_featured_home" value="0">
                    <input type="checkbox" name="is_featured_home" value="1" @checked(old('is_featured_home', $post->is_featured_home)) class="rounded border-white/20 bg-white/5">
                    Feature on homepage
                </label>
                <div>
                    <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Featured slot</label>
                    <select name="featured_slot" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                        <option value="">— None —</option>
                        <option value="hero"     @selected(old('featured_slot', $post->featured_slot)==='hero')>Hero</option>
                        <option value="carousel" @selected(old('featured_slot', $post->featured_slot)==='carousel')>Carousel</option>
                    </select>
                </div>
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="allow_comments" value="0">
                    <input type="checkbox" name="allow_comments" value="1" @checked(old('allow_comments', $post->exists ? $post->allow_comments : true)) class="rounded border-white/20 bg-white/5">
                    Allow comments
                </label>
            </div>
        </aside>
    </form>

    @if($post->exists)
        <form id="delete-form" method="POST" action="{{ route('admin.blogs.posts.destroy', $post) }}" class="hidden" onsubmit="return confirm('Delete this post permanently?')">@csrf @method('DELETE')</form>
    @endif
</div>
@endsection
