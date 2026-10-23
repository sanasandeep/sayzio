@if($req->additional_info || count($req->supporting_urls ?? []))
<div class="card-premium p-6">
    <h3 class="text-sm font-bold mb-3" style="color:var(--text-primary)">Supporting links &amp; additional information</h3>
    @if($req->additional_info)<p class="text-sm whitespace-pre-line mb-4" style="color:var(--text-secondary)">{{ $req->additional_info }}</p>@endif
    <ul class="space-y-2 text-sm">
        @foreach($req->supporting_urls ?? [] as $evidenceUrl)
        <li><a href="{{ $evidenceUrl }}" target="_blank" rel="noopener noreferrer" class="break-all underline" style="color:var(--accent)">{{ $evidenceUrl }}</a></li>
        @endforeach
    </ul>
</div>
@endif
