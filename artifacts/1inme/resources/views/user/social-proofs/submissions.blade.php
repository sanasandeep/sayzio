@extends('user.layouts.app')
@section('title', 'Submissions: ' . $proof->name)

@section('content')
<div class="bz-scope mb-5 flex items-center justify-between flex-wrap gap-3">
    <div>
        <a href="{{ route('user.social-proofs.edit', $proof) }}" class="text-white/50 hover:text-white text-xs"><i class="fas fa-arrow-left mr-1"></i> Back to campaign</a>
        <h1 class="text-2xl font-bold text-white mt-1">Submissions</h1>
        <p class="text-white/40 text-xs mt-0.5">{{ $proof->name }}, data collected by collector &amp; feedback notifications.</p>
    </div>
    <a href="{{ route('user.social-proofs.submissions.csv', $proof) }}"
       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium">
        <i class="fas fa-file-csv mr-1.5"></i> Export CSV
    </a>
</div>

<div class="bz-scope bg-white/[.03] border border-white/[.08] rounded-2xl overflow-hidden">
    @if($submissions->isEmpty())
        <div class="text-center py-14 px-6">
            <p class="text-white/60 text-sm">No submissions yet.</p>
            <p class="text-white/35 text-xs mt-1">When visitors fill in one of this campaign's collector or feedback notifications, their responses will show up here.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-white/40 text-xs uppercase tracking-wider border-b border-white/[.08]">
                        <th class="px-4 py-3">Date</th>
                        <th class="px-4 py-3">Template</th>
                        <th class="px-4 py-3">Submission</th>
                        <th class="px-4 py-3">Page</th>
                        <th class="px-4 py-3">Spam</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($submissions as $s)
                    <tr class="border-b border-white/[.05] text-white/80">
                        <td class="px-4 py-3 whitespace-nowrap text-white/50 text-xs">{{ $s->created_at?->format('M j, Y H:i') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap"><span class="inline-flex px-2 py-0.5 rounded-full text-[11px] bg-blue-500/15 text-blue-300 border border-blue-500/25">{{ \App\Modules\User\Models\SocialProof::TYPES[$s->type] ?? $s->type }}</span></td>
                        <td class="px-4 py-3 max-w-md"><span class="break-words">{{ $s->valueSummary() ?: '—' }}</span></td>
                        <td class="px-4 py-3 max-w-[220px] truncate text-white/40 text-xs">{{ $s->page_url }}</td>
                        <td class="px-4 py-3 text-xs">{!! $s->is_spam ? '<span class="text-rose-300">spam</span>' : '<span class="text-white/30">—</span>' !!}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-3">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
