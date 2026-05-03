@extends('user.layouts.app')
@section('title', 'Bulk Create — Preview')

@section('content')
@php
    $totalRows = count($rows);
    $invalidCount = $totalRows - $validCount;
@endphp
<div class="max-w-6xl mx-auto">
    <div class="flex items-center gap-4 mb-6">
        <a href="{{ route('user.links.url.bulk') }}" class="text-white/30 hover:text-white transition-colors" title="Back"><i class="fas fa-arrow-left"></i></a>
        <div>
            <h1 class="text-2xl font-bold text-white">Preview your batch</h1>
            <p class="text-xs text-white/40 mt-0.5">
                {{ $totalRows }} row{{ $totalRows === 1 ? '' : 's' }} parsed —
                <span class="text-emerald-400">{{ $validCount }} valid</span>,
                <span class="text-red-400">{{ $invalidCount }} with issues</span>.
                Fix or skip flagged rows before creating.
            </p>
        </div>
    </div>

    @if(session('error'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-sm text-red-300">
            <i class="fas fa-exclamation-triangle mr-2"></i>{{ session('error') }}
        </div>
    @endif

    <form method="POST" action="{{ route('user.links.url.bulk.store') }}">
        @csrf

        {{-- Carry shared options forward as hidden fields. --}}
        @foreach($shared as $k => $v)
            @if(is_array($v))
                @foreach($v as $vv)
                    <input type="hidden" name="{{ $k }}[]" value="{{ $vv }}">
                @endforeach
            @elseif(is_bool($v))
                <input type="hidden" name="{{ $k }}" value="{{ $v ? 1 : 0 }}">
            @elseif($v !== null && $v !== '')
                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
            @endif
        @endforeach

        <div class="glass rounded-2xl p-4 mb-4 flex flex-wrap items-center gap-2 text-xs text-white/60">
            <span class="font-semibold text-white/80">Applies to all:</span>
            @if(!empty($shared['domain_id']))
                @php $d = $domains->firstWhere('id', $shared['domain_id']); @endphp
                @if($d)<span class="px-2 py-1 rounded-md bg-white/5 border border-white/10"><i class="fas fa-globe mr-1 text-violet-400"></i>{{ $d->domain }}</span>@endif
            @endif
            @if(!empty($shared['project_id']))
                @php $p = $projects->firstWhere('id', $shared['project_id']); @endphp
                @if($p)<span class="px-2 py-1 rounded-md bg-white/5 border border-white/10"><i class="fas fa-folder mr-1 text-violet-400"></i>{{ $p->name }}</span>@endif
            @endif
            <span class="px-2 py-1 rounded-md bg-white/5 border border-white/10">Redirect: {{ $shared['redirect_type'] }}</span>
            @if(!empty($shared['expires_at']))<span class="px-2 py-1 rounded-md bg-white/5 border border-white/10"><i class="fas fa-clock mr-1 text-amber-400"></i>Expires {{ $shared['expires_at'] }}</span>@endif
            @if(!empty($shared['password']))<span class="px-2 py-1 rounded-md bg-white/5 border border-white/10"><i class="fas fa-lock mr-1 text-violet-400"></i>Password protected</span>@endif
            @if(!empty($shared['utm_source']) || !empty($shared['utm_medium']) || !empty($shared['utm_campaign']))<span class="px-2 py-1 rounded-md bg-white/5 border border-white/10"><i class="fas fa-chart-bar mr-1 text-violet-400"></i>UTM tags</span>@endif
            @if(!empty($shared['pixel_ids']))<span class="px-2 py-1 rounded-md bg-white/5 border border-white/10"><i class="fas fa-bullseye mr-1 text-violet-400"></i>{{ count($shared['pixel_ids']) }} pixel(s)</span>@endif
            @if(!empty($shared['show_preview_page']))<span class="px-2 py-1 rounded-md bg-white/5 border border-white/10"><i class="fas fa-eye mr-1 text-violet-400"></i>Preview page on</span>@endif
        </div>

        <div class="glass rounded-2xl overflow-hidden mb-6">
            <table class="w-full text-sm">
                <thead class="bg-white/5 border-b border-white/10 text-[11px] uppercase tracking-wider text-white/40">
                    <tr>
                        <th class="px-3 py-2 text-left w-12">#</th>
                        <th class="px-3 py-2 text-left">Destination URL</th>
                        <th class="px-3 py-2 text-left w-48">Alias</th>
                        <th class="px-3 py-2 text-left w-48">Title</th>
                        <th class="px-3 py-2 text-left w-32">Status</th>
                        <th class="px-3 py-2 text-center w-16">Skip</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-white/5">
                    @foreach($rows as $i => $row)
                    @php $hasErr = !empty($row['errors']); @endphp
                    <tr class="{{ $hasErr ? 'bg-red-500/5' : '' }}">
                        <td class="px-3 py-2 text-white/40 align-top pt-3">{{ $i + 1 }}</td>
                        <td class="px-3 py-2 align-top">
                            <input type="url" name="rows[{{ $i }}][long_url]" value="{{ $row['long_url'] }}"
                                   class="w-full bg-white/5 border {{ $hasErr ? 'border-red-500/40' : 'border-white/10' }} rounded-lg px-2 py-1.5 text-xs text-white focus:ring-2 focus:ring-violet-500/40 outline-none font-mono">
                        </td>
                        <td class="px-3 py-2 align-top">
                            <input type="text" name="rows[{{ $i }}][alias]" value="{{ $row['alias'] ?: $row['final_alias'] }}"
                                   placeholder="auto"
                                   class="w-full bg-white/5 border {{ $hasErr ? 'border-red-500/40' : 'border-white/10' }} rounded-lg px-2 py-1.5 text-xs text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </td>
                        <td class="px-3 py-2 align-top">
                            <input type="text" name="rows[{{ $i }}][title]" value="{{ $row['title'] }}"
                                   placeholder="—"
                                   class="w-full bg-white/5 border border-white/10 rounded-lg px-2 py-1.5 text-xs text-white placeholder-white/20 focus:ring-2 focus:ring-violet-500/40 outline-none">
                        </td>
                        <td class="px-3 py-2 align-top">
                            @if($hasErr)
                                <span class="inline-flex items-center gap-1 text-[11px] text-red-300" title="{{ implode(' ', $row['errors']) }}">
                                    <i class="fas fa-exclamation-circle"></i> {{ $row['errors'][0] }}
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-[11px] text-emerald-300">
                                    <i class="fas fa-check-circle"></i> Ready
                                </span>
                            @endif
                        </td>
                        <td class="px-3 py-3 text-center align-top">
                            <input type="checkbox" name="rows[{{ $i }}][skip]" value="1" {{ $hasErr ? 'checked' : '' }}
                                   class="rounded bg-white/5 border-white/20 text-violet-600 focus:ring-violet-500/40">
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between gap-3">
            <a href="{{ route('user.links.url.bulk') }}" class="px-5 py-2.5 text-sm text-white/50 hover:text-white hover:bg-white/5 rounded-xl transition-all">
                <i class="fas fa-arrow-left mr-1"></i> Edit input
            </a>
            <button type="submit" class="bg-violet-600 hover:bg-violet-700 text-white px-6 py-2.5 rounded-xl text-sm font-medium transition-all hover:shadow-lg hover:shadow-violet-500/20"
                    onclick="return confirm('Create the unskipped links now?')">
                <i class="fas fa-check mr-1.5 text-xs"></i> Create {{ $validCount }} link{{ $validCount === 1 ? '' : 's' }}
            </button>
        </div>
    </form>
</div>
@endsection
