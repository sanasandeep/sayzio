@extends('user.layouts.app')
@section('title', 'Submissions · ' . $form->title)

@section('content')
<div class="max-w-7xl mx-auto">
    @include('user.partials.page-hero', [
        'title' => 'Submissions',
        'subtitle' => $form->title,
        'icon' => 'fa-inbox',
        'back' => route('user.forms.show', $form),
        'chips' => [
            ['icon' => 'fa-database text-pink-400', 'text' => number_format($form->total_submissions) . ' total'],
        ],
        'actions' => [
            ['label' => 'Export CSV', 'url' => route('user.forms.submissions.export', $form), 'icon' => 'fa-file-csv', 'class' => 'btn-ghost'],
        ],
    ])

    @include('user.forms._tabs')

    @if(session('success'))
    <div class="mb-6 px-4 py-3 rounded-xl text-sm font-medium" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.2); color: #10b981;">
        <i class="fas fa-check-circle mr-1.5"></i> {{ session('success') }}
    </div>
    @endif

    {{-- Filter pills --}}
    <div class="flex items-center gap-2 mb-5 flex-wrap">
        @foreach(['' => 'All', 'unread' => 'Unread', 'starred' => 'Starred', 'spam' => 'Spam'] as $val => $label)
            @php $active = (request('filter') ?? '') === $val; @endphp
            <a href="?filter={{ $val }}" class="text-xs px-3 py-1.5 rounded-full font-semibold" style="{{ $active ? 'background: linear-gradient(135deg,#8b5cf6,#6d28d9); color:white;' : 'background: var(--bg-glass-input); border: 1px solid var(--border-glass); color: var(--text-secondary);' }}">{{ $label }}</a>
        @endforeach
    </div>

    @if($submissions->isEmpty())
        <div class="card-premium p-12 text-center">
            <i class="fas fa-inbox text-4xl mb-3" style="color: var(--text-faint);"></i>
            <p class="text-sm" style="color: var(--text-muted);">No submissions match this filter yet.</p>
        </div>
    @else
        <div class="card-premium overflow-hidden">
            <div class="divide-y" style="border-color: var(--border-glass);">
                @foreach($submissions as $s)
                    @php $name = $s->data['name'] ?? $s->data['email'] ?? '#' . $s->id; @endphp
                    <div class="flex items-center gap-3 p-4 hover:bg-violet-500/5 transition-colors {{ !$s->is_read ? 'bg-violet-500/5' : '' }}">
                        <form method="POST" action="{{ route('user.forms.submissions.star', [$form, $s]) }}">@csrf
                            <button class="text-base {{ $s->is_starred ? 'text-amber-400' : '' }}" style="color: {{ $s->is_starred ? '' : 'var(--text-faint)' }};" title="Star">
                                <i class="fa{{ $s->is_starred ? 's' : 'r' }} fa-star"></i>
                            </button>
                        </form>
                        <a href="{{ route('user.forms.submissions.show', [$form, $s]) }}" class="flex items-center gap-3 flex-1 min-w-0">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-xs font-bold flex-shrink-0" style="background: linear-gradient(135deg, #8b5cf6, #ec4899); color: white;">
                                {{ strtoupper(substr($name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-{{ $s->is_read ? 'medium' : 'bold' }} truncate" style="color: var(--text-primary);">{{ $name }}</span>
                                    @unless($s->is_read)<span class="w-2 h-2 rounded-full bg-violet-500 flex-shrink-0"></span>@endunless
                                </div>
                                <div class="text-[11px] truncate" style="color: var(--text-faint);">
                                    @php $preview = collect($s->data)->reject(fn($v,$k) => in_array($k, ['name','email']) || is_array($v))->take(3)->map(fn($v,$k) => "$k: $v")->implode(' · '); @endphp
                                    {{ $preview ?: $s->data['email'] ?? 'No content' }}
                                </div>
                            </div>
                        </a>
                        <div class="text-[10px] text-right flex-shrink-0" style="color: var(--text-faint);">
                            {{ $s->created_at->diffForHumans() }}<br>
                            <span class="font-mono">{{ $s->ip ?? '' }}</span>
                        </div>
                        <form method="POST" action="{{ route('user.forms.submissions.destroy', [$form, $s]) }}" onsubmit="return confirm('Delete this submission?');">
                            @csrf @method('DELETE')
                            <button class="w-7 h-7 rounded-lg flex items-center justify-center text-[10px]" style="background: rgba(239,68,68,0.1); color: #f87171;"><i class="fas fa-trash"></i></button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mt-6">{{ $submissions->links() }}</div>
    @endif
</div>
@endsection
