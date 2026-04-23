<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <title>{{ config('app.name', '1INME') }} — Blog</title>
    <link>{{ url('/blogs') }}</link>
    <atom:link href="{{ route('site.blogs.rss') }}" rel="self" type="application/rss+xml"/>
    <description>Latest posts from the {{ config('app.name', '1INME') }} blog.</description>
    <language>en-us</language>
    @foreach($posts as $post)
        <item>
            <title>{{ $post->title }}</title>
            <link>{{ route('site.blogs.show', $post->slug) }}</link>
            <guid isPermaLink="true">{{ route('site.blogs.show', $post->slug) }}</guid>
            <pubDate>{{ optional($post->published_at)->toRfc822String() }}</pubDate>
            @if($post->author) <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">{{ $post->author->name }}</dc:creator>@endif
            <description><![CDATA[{!! $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body_html), 280) !!}]]></description>
        </item>
    @endforeach
</channel>
</rss>
