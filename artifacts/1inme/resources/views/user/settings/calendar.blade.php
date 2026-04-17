@extends('layouts.user')

@section('title', 'Calendar Sync')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h1 class="h3 mb-1 fw-bold">Calendar Sync</h1>
            <p class="text-muted mb-0">Connect Google, Microsoft, or any CalDAV calendar to mirror events as Event links and push back changes.</p>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Connect a calendar</h5>
                    <p class="text-muted small mb-3">Choose a provider to start the secure sign-in flow.</p>

                    <a href="{{ route('user.calendar.connect', 'google') }}" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fab fa-google me-2"></i> Google Calendar
                    </a>
                    <a href="{{ route('user.calendar.connect', 'microsoft') }}" class="btn btn-outline-primary w-100 mb-2">
                        <i class="fab fa-microsoft me-2"></i> Microsoft 365 / Outlook
                        <span class="badge bg-warning text-dark ms-1">Beta</span>
                    </a>
                    <a href="{{ route('user.calendar.connect', 'caldav') }}" class="btn btn-outline-primary w-100">
                        <i class="fas fa-server me-2"></i> CalDAV (Apple iCloud, Fastmail…)
                        <span class="badge bg-warning text-dark ms-1">Beta</span>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Connected accounts</h5>
                    @if($accounts->isEmpty())
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-calendar-alt fa-2x mb-2 opacity-50"></i>
                            <p class="mb-0">No calendars connected yet.</p>
                        </div>
                    @else
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="text-muted small">
                                    <tr>
                                        <th>Provider</th>
                                        <th>Account</th>
                                        <th>Last synced</th>
                                        <th>Sync</th>
                                        <th class="text-end">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @foreach($accounts as $a)
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark text-capitalize">{{ $a->provider }}</span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold">{{ $a->display_name ?: $a->external_account_id }}</div>
                                            <div class="text-muted small">{{ $a->external_account_id }}</div>
                                        </td>
                                        <td class="text-muted small">
                                            {{ $a->last_synced_at ? $a->last_synced_at->diffForHumans() : 'Never' }}
                                        </td>
                                        <td>
                                            <form method="POST" action="{{ route('user.calendar.update', $a) }}" class="d-flex flex-column gap-1">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="mirror_enabled" value="0">
                                                <input type="hidden" name="push_enabled" value="0">
                                                <label class="form-check form-switch m-0 small">
                                                    <input type="checkbox" class="form-check-input" name="mirror_enabled" value="1"
                                                           {{ $a->mirror_enabled ? 'checked' : '' }} onchange="this.form.submit()">
                                                    <span class="text-muted">Mirror in</span>
                                                </label>
                                                <label class="form-check form-switch m-0 small">
                                                    <input type="checkbox" class="form-check-input" name="push_enabled" value="1"
                                                           {{ $a->push_enabled ? 'checked' : '' }} onchange="this.form.submit()">
                                                    <span class="text-muted">Push out</span>
                                                </label>
                                            </form>
                                        </td>
                                        <td class="text-end">
                                            <form method="POST" action="{{ route('user.calendar.sync', $a) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-secondary"><i class="fas fa-sync"></i> Sync now</button>
                                            </form>
                                            <form method="POST" action="{{ route('user.calendar.destroy', $a) }}" class="d-inline"
                                                  onsubmit="return confirm('Disconnect this calendar? Mirrored events will remain but will no longer update.')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="fas fa-unlink"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
