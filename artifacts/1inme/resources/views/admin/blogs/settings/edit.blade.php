@extends('admin.layouts.app')
@section('title', 'Blog settings')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-bold text-white">Blog settings</h1>
        <a href="{{ route('admin.blogs.posts.index') }}" class="text-xs text-blue-400 hover:underline">← Posts</a>
    </div>

    @if(session('success'))<div class="p-3 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-sm">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('admin.blogs.settings.update') }}" class="glass rounded-2xl p-6 space-y-6">
        @csrf

        <section class="space-y-4">
            <h2 class="text-sm font-semibold text-white">Hero (blog index)</h2>
            <div class="grid sm:grid-cols-2 gap-3">
                <input type="text" name="hero_eyebrow" value="{{ old('hero_eyebrow', $settings['hero_eyebrow']) }}" placeholder="Eyebrow" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                <input type="text" name="hero_heading" value="{{ old('hero_heading', $settings['hero_heading']) }}" placeholder="Heading" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
            </div>
            <textarea name="hero_subheading" rows="2" placeholder="Subheading" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">{{ old('hero_subheading', $settings['hero_subheading']) }}</textarea>
            <div class="grid sm:grid-cols-2 gap-3">
                <input type="text" name="hero_cta_label" value="{{ old('hero_cta_label', $settings['hero_cta_label']) }}" placeholder="CTA label (optional)" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                <input type="text" name="hero_cta_url" value="{{ old('hero_cta_url', $settings['hero_cta_url']) }}" placeholder="CTA URL (/path or https://)" class="px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
            </div>
            <input type="text" name="default_og_image" value="{{ old('default_og_image', $settings['default_og_image']) }}" placeholder="Default OG image URL" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-white">Comments</h2>
            <div>
                <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Approval mode</label>
                <select name="approval_mode" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
                    <option value="auto"     @selected($settings['approval_mode']==='auto')>Auto-approve all</option>
                    <option value="returning" @selected($settings['approval_mode']==='returning')>Auto-approve returning commenters</option>
                    <option value="manual"   @selected($settings['approval_mode']==='manual')>Manual approval required</option>
                    <option value="closed"   @selected($settings['approval_mode']==='closed')>Closed (no new comments)</option>
                </select>
            </div>
            <div class="grid sm:grid-cols-3 gap-3">
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="allow_guest_viewer_comments" value="0">
                    <input type="checkbox" name="allow_guest_viewer_comments" value="1" @checked($settings['allow_guest_viewer_comments']) class="rounded border-white/20 bg-white/5">
                    Allow viewer-session comments
                </label>
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="require_email" value="0">
                    <input type="checkbox" name="require_email" value="1" @checked($settings['require_email']) class="rounded border-white/20 bg-white/5">
                    Require email
                </label>
                <label class="flex items-center gap-2 text-sm text-white/80">
                    <input type="hidden" name="spam_filter" value="0">
                    <input type="checkbox" name="spam_filter" value="1" @checked($settings['spam_filter']) class="rounded border-white/20 bg-white/5">
                    Basic spam filter
                </label>
            </div>
            <div>
                <label class="block text-xs uppercase tracking-wider text-white/60 mb-1.5">Comments per page</label>
                <input type="number" name="comments_per_page" min="5" max="200" value="{{ $settings['comments_per_page'] }}" class="w-32 px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white text-sm">
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-white">Reply permissions</h2>
            <p class="text-xs text-white/50">Roles below are allowed to reply on the public site (in addition to the global <code>blogs.comments.reply</code> permission).</p>
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach($roles as $r)
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="checkbox" name="reply_role_slugs[]" value="{{ $r->slug }}" @checked(in_array($r->slug, $settings['reply_role_slugs'], true)) class="rounded border-white/20 bg-white/5">
                        {{ $r->name }} <span class="text-white/40 text-xs">({{ $r->slug }})</span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="space-y-3">
            <h2 class="text-sm font-semibold text-white">Marketing pages</h2>
            <p class="text-xs text-white/50">Pick which marketing pages should display the "Latest from blog" block.</p>
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach(['features'=>'Features','about'=>'About','how-it-works'=>'How it works','contact'=>'Contact','faqs'=>'FAQs'] as $slug=>$label)
                    <label class="flex items-center gap-2 text-sm text-white/80">
                        <input type="checkbox" name="cta_on_pages[]" value="{{ $slug }}" @checked(in_array($slug, $settings['cta_on_pages'], true)) class="rounded border-white/20 bg-white/5">
                        {{ $label }} <span class="text-white/40 text-xs">/{{ $slug }}</span>
                    </label>
                @endforeach
            </div>
        </section>

        <div class="pt-4 border-t border-white/10">
            <button class="px-5 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-sm font-medium text-white">Save settings</button>
        </div>
    </form>
</div>
@endsection
