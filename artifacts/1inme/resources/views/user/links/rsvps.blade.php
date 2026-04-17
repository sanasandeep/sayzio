@extends('user.layouts.app')

@section('title', 'RSVPs — ' . $link->title)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <a href="{{ route('user.links.show', $link) }}" class="text-muted small text-decoration-none">
                <i class="fas fa-arrow-left me-1"></i> Back to event
            </a>
            <h1 class="h3 mb-0 fw-bold mt-2">RSVPs — {{ $link->title }}</h1>
        </div>
        <a href="{{ route('user.links.rsvps.export', $link) }}" class="btn btn-outline-primary">
            <i class="fas fa-download me-2"></i> Export CSV
        </a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <div class="row g-3 mb-4">
        @foreach([
            ['Going (incl. plus-ones)', $counts['yes'], 'success'],
            ['Maybe', $counts['maybe'], 'warning'],
            ['Can\'t make it', $counts['no'], 'secondary'],
            ['Total responses', $counts['total'], 'primary'],
        ] as [$label, $value, $color])
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="text-muted small">{{ $label }}</div>
                        <div class="h3 mb-0 text-{{ $color }}">{{ $value }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="text-muted small">
                        <tr>
                            <th class="ps-4">Guest</th>
                            <th>Contact</th>
                            <th>Response</th>
                            <th>Plus-ones</th>
                            <th>Source</th>
                            <th>Submitted</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rsvps as $r)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $r->name ?: '—' }}</div>
                                    @if($r->message)
                                        <div class="text-muted small fst-italic">"{{ \Illuminate\Support\Str::limit($r->message, 80) }}"</div>
                                    @endif
                                </td>
                                <td class="small">
                                    @if($r->email)<div><i class="far fa-envelope me-1 text-muted"></i>{{ $r->email }}</div>@endif
                                    @if($r->phone)<div><i class="fas fa-phone me-1 text-muted"></i>{{ $r->phone }}</div>@endif
                                </td>
                                <td>
                                    @php $colors = ['yes'=>'success','maybe'=>'warning','no'=>'secondary'][$r->response] ?? 'light'; @endphp
                                    <span class="badge bg-{{ $colors }}-subtle text-{{ $colors }} text-capitalize">
                                        {{ ['yes'=>'Going','maybe'=>'Maybe','no'=>'Not going'][$r->response] ?? $r->response }}
                                    </span>
                                </td>
                                <td>{{ $r->plus_ones }}</td>
                                <td class="small text-muted text-capitalize">{{ str_replace('_',' ', $r->source) }}</td>
                                <td class="small text-muted">{{ $r->created_at?->diffForHumans() }}</td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="{{ route('user.links.rsvps.destroy', [$link, $r]) }}" class="d-inline"
                                          onsubmit="return confirm('Remove this RSVP?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-5">
                                <i class="fas fa-inbox fa-2x mb-2 opacity-50"></i>
                                <p class="mb-0">No RSVPs yet. Share your event link to start collecting responses.</p>
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">{{ $rsvps->links() }}</div>
</div>
@endsection
