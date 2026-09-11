@props([
    'method' => 'GET',
    'path'   => '/',
    'auth'   => 'true',          // 'true' | 'false' | 'optional'
    'id'     => null,
    'summary'=> '',
    'responseStatus' => '200 OK',
])

@php
    $methodClass = 'm-' . strtolower($method);
    $authBadge = match((string) $auth) {
        'false'    => ['Public',         'sky'],
        'optional' => ['Optional auth',  'fuchsia'],
        default    => ['Auth required',  'violet'],
    };
    $base = url('/api/v1');
@endphp

<article id="{{ $id }}" class="endpoint-card scroll-mt-20 bg-white/[0.03] border border-white/10 rounded-2xl overflow-hidden">

    {{-- An <h3>, not a <header>.

         This card had no heading at all. The method and the path -- the only
         thing that names the endpoint -- sat in a <span> and a <code>, so the
         API reference's outline went straight from the group's <h2> ("Links")
         to the <h4> inside the card ("Parameters"), skipping a level nine
         times on the page. That was the whole of /docs/api's heading defect.

         Naming the card fixes the skip and does something the skip was hiding:
         a screen reader can now move endpoint by endpoint through a 76-heading
         page. The accessible name reads "GET /links Auth required", which is
         how you would say it out loud.

         Every class is the one <header> carried, so the row is laid out
         exactly as before; `font-normal` is belt-and-braces in case Tailwind's
         preflight (which already resets heading size and weight to inherit)
         ever stops being loaded, since a bold heading would drag the <code>
         bold with it. --}}
    <h3 class="flex flex-wrap items-center gap-3 px-5 py-4 border-b border-white/5 font-normal">
        <span class="doc-method {{ $methodClass }} text-[11px] px-2.5 py-1 rounded">{{ strtoupper($method) }}</span>
        <code class="text-sm font-mono text-gray-100 break-all">{{ $path }}</code>
        <span class="text-[10px] uppercase tracking-wider px-2 py-0.5 rounded bg-{{ $authBadge[1] }}-500/10 text-{{ $authBadge[1] }}-300 border border-{{ $authBadge[1] }}-400/20 ml-auto">{{ $authBadge[0] }}</span>
        @if($id)
            <a href="#{{ $id }}" class="anchor-link text-gray-500 hover:text-blue-400 text-xs ml-1" aria-label="Anchor"><i class="fas fa-link"></i></a>
        @endif
    </h3>

    @if($summary)
        <p class="px-5 pt-4 text-sm text-gray-300">{{ $summary }}</p>
    @endif

    @isset($params)
        <div class="px-5 pt-4">
            <h4 class="text-xs uppercase tracking-wider text-gray-500 font-semibold mb-2">Parameters</h4>
            <div class="overflow-x-auto -mx-1">
                <table class="w-full text-sm text-left">
                    <thead class="text-[11px] uppercase text-gray-500 border-b border-white/10">
                        <tr><th class="py-1.5 px-2">Field</th><th class="py-1.5 px-2">Type</th><th class="py-1.5 px-2">Description</th></tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 align-top">{{ $params }}</tbody>
                </table>
            </div>
        </div>
    @endisset

    <div class="px-5 py-4 grid lg:grid-cols-2 gap-4">
        <div>
            <h4 class="text-xs uppercase tracking-wider text-gray-500 font-semibold mb-2">Request</h4>
            {{-- `{!! !!}`, because the slot is escaped again downstream.

                 x-doc-code ends in `{{ trim($slot) }}`, which escapes what it
                 is given. Echoing the slot with `{{ }}` here escapes it first,
                 so every quote in the sample went through twice and came out
                 as literal `&#039;` and `&quot;` on the page -- 32 of them on
                 /docs/api, in the curl commands and JSON bodies developers are
                 meant to copy. The Copy button handed over the same text.

                 The slot is Blade written in api-docs.blade.php, already
                 rendered by the time it arrives; passing it through raw lets
                 doc-code do the one escape the markup actually needs. --}}
            @isset($request)
                <x-doc-code lang="bash">{!! trim($request) !!}</x-doc-code>
            @else
                {{-- Raw here too, for the same reason and one I missed first time.

                     Every echo inside this slot is escaped again by x-doc-code,
                     so anything that escapes here comes out doubled. The
                     Authorization header carries double quotes, which is why
                     the fifteen endpoints that take this fallback -- the ones
                     with no request example of their own -- showed
                     `&quot;Authorization: Bearer YOUR_TOKEN&quot;` in the
                     command you are meant to copy.

                     They stayed broken after the first fix because the check
                     that declared it fixed looked only for `&amp;#039;`, the
                     single-quote form, and these are double quotes. The defect
                     was found with one pattern and confirmed gone with a
                     narrower one. --}}
                <x-doc-code lang="bash">curl {!! $base !!}{!! $path !!}{!! $auth === 'false' ? '' : ' \
  -H "Authorization: Bearer YOUR_TOKEN"' !!} \
  -H 'Accept: application/json'</x-doc-code>
            @endisset
        </div>
        <div>
            <h4 class="text-xs uppercase tracking-wider text-gray-500 font-semibold mb-2">Response <span class="text-gray-600 font-normal normal-case">— {{ $responseStatus }}</span></h4>
            @isset($response)
                <x-doc-code lang="{{ $response->attributes->get('lang') ?? 'json' }}">{!! trim($response) !!}</x-doc-code>
            @elseif(str_starts_with((string) $responseStatus, '204'))
                <div class="text-xs text-gray-500 italic px-1 py-3">No response body.</div>
            @else
                <x-doc-code lang="json">{ "data": { /* … */ } }</x-doc-code>
            @endisset
        </div>
    </div>
</article>
