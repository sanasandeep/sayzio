{{ $creator }} just posted to their Insider feed:

{{ $title }}

{{ \Illuminate\Support\Str::limit(strip_tags($body), 500) }}

Read more: {{ $url }}
