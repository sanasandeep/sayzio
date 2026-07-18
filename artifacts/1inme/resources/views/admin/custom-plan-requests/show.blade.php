@extends('admin.layouts.app')

@section('title', 'Custom Plan Request — ' . $customPlanRequest->name)

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.custom-plan-requests.index') }}" class="admin-btn-sm admin-btn-secondary">
            <i class="fas fa-arrow-left text-[10px]"></i> Back
        </a>
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-main)">Request from {{ $customPlanRequest->name }}</h1>
            <p class="text-xs mt-0.5" style="color:var(--text-muted)">{{ $customPlanRequest->email }} · {{ $customPlanRequest->created_at->format('M j, Y') }}</p>
        </div>
        @php
            $colorMap = ['new'=>'blue','reviewing'=>'amber','approved'=>'green','paid'=>'purple','declined'=>'red'];
            $clr = $colorMap[$customPlanRequest->status] ?? 'gray';
            $bgMap = ['blue'=>'rgba(59,130,246,0.1)','amber'=>'rgba(245,158,11,0.1)','green'=>'rgba(16,185,129,0.1)','purple'=>'rgba(139,92,246,0.1)','red'=>'rgba(239,68,68,0.1)','gray'=>'rgba(107,114,128,0.1)'];
            $txtMap = ['blue'=>'#60a5fa','amber'=>'#fbbf24','green'=>'#6ee7b7','purple'=>'#c4b5fd','red'=>'#f87171','gray'=>'#9ca3af'];
        @endphp
        <span class="ml-auto inline-flex items-center px-3 py-1 rounded-full text-xs font-bold"
              style="background:{{ $bgMap[$clr] }};color:{{ $txtMap[$clr] }}">
            {{ $customPlanRequest->statusLabel() }}
        </span>
    </div>

    @foreach(['success','error','info'] as $flash)
        @if(session($flash))
            <div class="mb-5 p-3.5 rounded-xl text-sm font-medium"
                 style="background:rgba({{ $flash === 'success' ? '16,185,129' : ($flash === 'error' ? '239,68,68' : '59,130,246') }},0.1);border:1px solid rgba({{ $flash === 'success' ? '16,185,129' : ($flash === 'error' ? '239,68,68' : '59,130,246') }},0.2);color:{{ $flash === 'success' ? '#6ee7b7' : ($flash === 'error' ? '#f87171' : '#60a5fa') }}">
                {{ session($flash) }}
            </div>
        @endif
    @endforeach

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left column: request details --}}
        <div class="lg:col-span-2 space-y-5">
            <div class="admin-card p-5">
                <h2 class="text-sm font-semibold mb-4" style="color:var(--text-main)">Request Details</h2>
                <dl class="space-y-3">
                    @if($customPlanRequest->company)
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Company</dt>
                        <dd class="text-sm" style="color:var(--text-main)">{{ $customPlanRequest->company }}</dd>
                    </div>
                    @endif
                    @if($customPlanRequest->expected_volume)
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Expected Volume</dt>
                        <dd class="text-sm" style="color:var(--text-main)">{{ $customPlanRequest->expected_volume }}</dd>
                    </div>
                    @endif
                    @if($customPlanRequest->budget)
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Budget</dt>
                        <dd class="text-sm" style="color:var(--text-main)">{{ $customPlanRequest->budget }}</dd>
                    </div>
                    @endif
                    @if($customPlanRequest->preferred_cycle)
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Preferred Billing</dt>
                        <dd class="text-sm capitalize" style="color:var(--text-main)">{{ $customPlanRequest->preferred_cycle }}</dd>
                    </div>
                    @endif
                    @if($customPlanRequest->requirements)
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0 mt-0.5" style="color:var(--text-faint)">Requirements</dt>
                        <dd class="text-sm leading-relaxed" style="color:var(--text-main)">{{ $customPlanRequest->requirements }}</dd>
                    </div>
                    @endif
                    @if($customPlanRequest->message)
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0 mt-0.5" style="color:var(--text-faint)">Message</dt>
                        <dd class="text-sm leading-relaxed" style="color:var(--text-main)">{{ $customPlanRequest->message }}</dd>
                    </div>
                    @endif
                    @if($customPlanRequest->user)
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Linked Account</dt>
                        <dd class="text-sm">
                            <a href="{{ route('admin.users.show', $customPlanRequest->user) }}" class="text-blue-400 hover:underline">
                                {{ $customPlanRequest->user->name }}
                            </a>
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>

            {{-- Approval outcome (when approved/paid) --}}
            @if($customPlanRequest->provisionedPlan)
            <div class="admin-card p-5" style="border-color:rgba(16,185,129,0.2);background:rgba(16,185,129,0.03);">
                <h2 class="text-sm font-semibold mb-3" style="color:#6ee7b7;">Provisioned Plan</h2>
                <dl class="space-y-2">
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Plan</dt>
                        <dd class="text-sm" style="color:var(--text-main)">{{ $customPlanRequest->provisionedPlan->name }}
                            <span class="text-xs ml-1" style="color:var(--text-faint)">(internal)</span>
                        </dd>
                    </div>
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Assigned to</dt>
                        <dd class="text-sm" style="color:var(--text-main)">{{ $customPlanRequest->assigned_email }}</dd>
                    </div>
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Billing Cycle</dt>
                        <dd class="text-sm capitalize" style="color:var(--text-main)">{{ $customPlanRequest->offer_cycle }}</dd>
                    </div>
                    @if($customPlanRequest->provisionedPlan->monthly_price > 0 || $customPlanRequest->provisionedPlan->annual_price > 0)
                    <div class="flex gap-4">
                        <dt class="text-xs font-medium w-32 shrink-0" style="color:var(--text-faint)">Price</dt>
                        <dd class="text-sm" style="color:var(--text-main)">
                            @if($customPlanRequest->offer_cycle === 'monthly')
                                ${{ number_format($customPlanRequest->provisionedPlan->monthly_price, 2) }}/month
                            @else
                                ${{ number_format($customPlanRequest->provisionedPlan->annual_price, 2) }}/year
                            @endif
                        </dd>
                    </div>
                    @endif
                </dl>
            </div>
            @endif

            {{-- Decline reason --}}
            @if($customPlanRequest->isDeclined() && $customPlanRequest->decline_reason)
            <div class="admin-card p-5" style="border-color:rgba(239,68,68,0.2);background:rgba(239,68,68,0.03);">
                <h2 class="text-sm font-semibold mb-2" style="color:#f87171;">Decline Reason</h2>
                <p class="text-sm leading-relaxed" style="color:var(--text-muted)">{{ $customPlanRequest->decline_reason }}</p>
            </div>
            @endif

            {{-- Approval Form --}}
            @if($customPlanRequest->isPending())
            <div class="admin-card p-5" x-data="{ basePlanId: '' }">
                <h2 class="text-sm font-semibold mb-4" style="color:var(--text-main)">
                    <i class="fas fa-gem text-green-400 mr-1.5"></i>Approve & Provision Custom Plan
                </h2>
                <form method="POST" action="{{ route('admin.custom-plan-requests.approve', $customPlanRequest) }}" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="admin-label">Start from existing plan <span class="font-normal opacity-60">(optional)</span></label>
                            <select name="base_plan_id" x-model="basePlanId" class="admin-input w-full text-sm">
                                <option value="">— Define from scratch —</option>
                                @foreach($plans as $plan)
                                    <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs" style="color:var(--text-faint)">Features will be copied from the base plan and can be overridden below.</p>
                        </div>
                        <div>
                            <label class="admin-label">Custom Plan Name <span class="text-red-400">*</span></label>
                            <input type="text" name="plan_name" required
                                   value="{{ old('plan_name', 'Custom — ' . $customPlanRequest->name) }}"
                                   class="admin-input w-full text-sm"
                                   placeholder="e.g. Enterprise Custom">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="admin-label">Currency <span class="text-red-400">*</span></label>
                            <select name="currency" class="admin-input w-full text-sm">
                                <option value="USD">USD ($)</option>
                                <option value="INR">INR (₹)</option>
                            </select>
                        </div>
                        <div>
                            <label class="admin-label">Monthly Price (minor units) <span class="text-xs opacity-60">e.g. 4900 = $49</span></label>
                            <input type="number" name="monthly_price" min="0" value="{{ old('monthly_price', 0) }}" class="admin-input w-full text-sm" placeholder="e.g. 4900">
                        </div>
                        <div>
                            <label class="admin-label">Annual Price (minor units) <span class="text-xs opacity-60">e.g. 49000 = $490</span></label>
                            <input type="number" name="annual_price" min="0" value="{{ old('annual_price', 0) }}" class="admin-input w-full text-sm" placeholder="e.g. 49000">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="admin-label">Billing Cycle for this Offer <span class="text-red-400">*</span></label>
                            <select name="offer_cycle" class="admin-input w-full text-sm">
                                <option value="monthly" @selected(($customPlanRequest->preferred_cycle ?? '') === 'monthly')>Monthly</option>
                                <option value="annual" @selected(($customPlanRequest->preferred_cycle ?? '') === 'annual')>Annual</option>
                            </select>
                        </div>
                        <div>
                            <label class="admin-label">Assign Offer to Email <span class="text-red-400">*</span></label>
                            <input type="email" name="assigned_email" required
                                   value="{{ old('assigned_email', $customPlanRequest->email) }}"
                                   class="admin-input w-full text-sm"
                                   placeholder="user@example.com">
                            <p class="mt-1 text-xs" style="color:var(--text-faint)">The user at this email will see a payment prompt.</p>
                        </div>
                    </div>

                    {{-- Feature overrides --}}
                    <details class="border rounded-xl" style="border-color:var(--border-subtle)">
                        <summary class="px-4 py-3 text-sm font-medium cursor-pointer" style="color:var(--text-main)">
                            <i class="fas fa-sliders-h mr-1.5 text-xs"></i>Override specific feature limits <span class="text-xs opacity-60">(optional)</span>
                        </summary>
                        <div class="px-4 pb-4 pt-2 grid grid-cols-2 sm:grid-cols-3 gap-3">
                            @foreach([
                                ['key'=>'max_links',            'label'=>'Max links (-1=unlimited)'],
                                ['key'=>'max_biolinks',         'label'=>'Max biolinks'],
                                ['key'=>'max_custom_domains',   'label'=>'Max custom domains'],
                                ['key'=>'storage_limit_mb',     'label'=>'Storage (MB)'],
                                ['key'=>'max_file_size_mb',     'label'=>'Max file size (MB)'],
                                ['key'=>'max_workspaces',       'label'=>'Max workspaces'],
                                ['key'=>'max_forms',            'label'=>'Max forms'],
                                ['key'=>'api_calls_monthly',    'label'=>'API calls/month'],
                                ['key'=>'stats_retention_days', 'label'=>'Stats retention (days)'],
                            ] as $feat)
                            <div>
                                <label class="admin-label text-[11px]">{{ $feat['label'] }}</label>
                                <input type="number" name="features[{{ $feat['key'] }}]" min="-1"
                                       value="{{ old('features.' . $feat['key']) }}"
                                       class="admin-input w-full text-xs" placeholder="inherit">
                            </div>
                            @endforeach
                            <div>
                                <label class="admin-label text-[11px]">Analytics tier</label>
                                <select name="features[analytics]" class="admin-input w-full text-xs">
                                    <option value="">inherit</option>
                                    <option value="basic">Basic</option>
                                    <option value="advanced">Advanced</option>
                                </select>
                            </div>
                        </div>
                    </details>

                    <div>
                        <label class="admin-label">Internal Notes</label>
                        <textarea name="admin_notes" rows="3" class="admin-input w-full text-sm resize-none"
                                  placeholder="Internal notes (not shown to the user)…">{{ old('admin_notes', $customPlanRequest->admin_notes) }}</textarea>
                    </div>

                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit" class="admin-btn admin-btn-primary">
                            <i class="fas fa-check mr-1.5"></i>Approve & Provision Plan
                        </button>
                    </div>
                </form>
            </div>
            @endif

            {{-- Mark reviewing --}}
            @if($customPlanRequest->status === 'new')
            <form method="POST" action="{{ route('admin.custom-plan-requests.reviewing', $customPlanRequest) }}">
                @csrf
                <button type="submit" class="admin-btn-sm admin-btn-secondary text-xs">
                    <i class="fas fa-eye mr-1"></i>Mark as Reviewing
                </button>
            </form>
            @endif
        </div>

        {{-- Right column: decline + notes --}}
        <div class="space-y-5">
            {{-- Decline --}}
            @if($customPlanRequest->isPending() || $customPlanRequest->isApproved())
            <div class="admin-card p-5">
                <h2 class="text-sm font-semibold mb-3" style="color:var(--text-main)">Decline Request</h2>
                <form method="POST" action="{{ route('admin.custom-plan-requests.decline', $customPlanRequest) }}">
                    @csrf
                    <textarea name="decline_reason" rows="3" class="admin-input w-full text-sm resize-none mb-3"
                              placeholder="Optional reason (will be emailed to the requester)…"></textarea>
                    <button type="submit"
                            onclick="return confirm('Decline this request and notify the requester?')"
                            class="w-full py-2 rounded-lg text-sm font-medium transition"
                            style="border:1px solid rgba(239,68,68,0.3);background:rgba(239,68,68,0.08);color:#f87171;">
                        <i class="fas fa-times mr-1.5"></i>Decline Request
                    </button>
                </form>
            </div>
            @endif

            {{-- Admin Notes (standalone update) --}}
            <div class="admin-card p-5">
                <h2 class="text-sm font-semibold mb-3" style="color:var(--text-main)">Internal Notes</h2>
                <form method="POST" action="{{ route('admin.custom-plan-requests.notes', $customPlanRequest) }}">
                    @csrf
                    @method('PATCH')
                    <textarea name="admin_notes" rows="4" class="admin-input w-full text-sm resize-none mb-3"
                              placeholder="Private notes for your team…">{{ $customPlanRequest->admin_notes }}</textarea>
                    <button type="submit" class="admin-btn-sm admin-btn-primary text-xs w-full">Save Notes</button>
                </form>
            </div>

            {{-- Meta --}}
            <div class="admin-card p-4 text-xs space-y-2" style="color:var(--text-faint)">
                <div><span class="font-medium">Submitted:</span> {{ $customPlanRequest->created_at->format('M j, Y H:i') }}</div>
                @if($customPlanRequest->handled_at)
                    <div><span class="font-medium">Handled:</span> {{ $customPlanRequest->handled_at->format('M j, Y H:i') }}</div>
                @endif
                @if($customPlanRequest->handledBy)
                    <div><span class="font-medium">By:</span> {{ $customPlanRequest->handledBy->name }}</div>
                @endif
                @if($customPlanRequest->invoice)
                    <div><span class="font-medium">Invoice:</span> {{ $customPlanRequest->invoice->number }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
