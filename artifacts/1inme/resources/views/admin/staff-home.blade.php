@extends('admin.layouts.app')
@section('title', 'Staff workspace')
@section('page-title', 'Staff workspace')
@section('content')
<div class="glass rounded-2xl border border-white/10 p-6 max-w-3xl space-y-4">
    <h1 class="text-xl font-semibold">Your permitted tools</h1>
    <p class="ak-muted">Customer identity is restricted for your role. Open a tool below to see the fields and actions you are permitted to use.</p>
    @if(auth('admin')->user()?->hasPermission('users.view'))<a class="inline-block rounded-xl border p-3" href="{{ route('admin.users.index') }}">Accounts</a>@endif
    @if(auth('admin')->user()?->hasAnyPermission(['finance.invoices.totals', 'finance.coins.totals', 'finance.invoices.view', 'finance.coins.view', ...\App\Modules\Admin\Support\StaffPermissionCatalog::aiSlugs()]))<a class="inline-block rounded-xl border p-3" href="{{ route('admin.finance.index') }}">Financial reports</a>@endif
</div>
@endsection
