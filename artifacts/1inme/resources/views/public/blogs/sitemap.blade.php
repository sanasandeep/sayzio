<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('site.blogs.index') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>
    @foreach($posts as $p)
    <url>
        <loc>{{ route('site.blogs.show', $p->slug) }}</loc>
        <lastmod>{{ optional($p->updated_at)->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.6</priority>
    </url>
    @endforeach
    @foreach($categories as $c)
    <url>
        <loc>{{ route('site.blogs.category', $c->slug) }}</loc>
        <changefreq>weekly</changefreq>
        <priority>0.4</priority>
    </url>
    @endforeach
    @foreach($tags as $t)
    <url>
        <loc>{{ route('site.blogs.tag', $t->slug) }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.3</priority>
    </url>
    @endforeach
</urlset>
