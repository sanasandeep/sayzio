{{--
    Leads list partial. Rendered both by the full index page and
    the AJAX filter/search/pagination requests (see LeadController::index).
    Expects: $leads (paginator of assoc arrays from LeadAggregator), $sourceLabels.
--}}
@if($leads->count())
<div class="glass rounded-2xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="enhanced-table w-full text-sm">
            <thead>
                <tr>
                    <th class="w-8">
                        <input type="checkbox" id="leads-select-all" class="rounded">
                    </th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Source</th>
                    <th>Context</th>
                    <th>Captured</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($leads as $lead)
                <tr data-lead-row data-source-type="{{ $lead['source_type'] }}" data-source-id="{{ $lead['source_id'] }}">
                    <td>
                        <input type="checkbox" class="lead-checkbox rounded" value="{{ $lead['source_type'] }}:{{ $lead['source_id'] }}">
                    </td>
                    <td>
                        <span class="font-medium" style="color: var(--text-primary);">{{ $lead['name'] ?: '—' }}</span>
                    </td>
                    <td>
                        <div class="flex flex-col text-xs" style="color: var(--text-muted);">
                            @if($lead['email'])<span><i class="fas fa-envelope text-[10px] mr-1"></i>{{ $lead['email'] }}</span>@endif
                            @if($lead['phone'])<span><i class="fas fa-phone text-[10px] mr-1"></i>{{ $lead['phone'] }}</span>@endif
                            @unless($lead['email'] || $lead['phone'])<span>-</span>@endunless
                        </div>
                    </td>
                    <td>
                        <span class="px-2 py-1 rounded-lg text-[11px] font-medium" style="background: var(--color-primary-soft, rgba(37,99,235,0.10)); color: var(--color-primary-500, #3b82f6); border: 1px solid var(--color-primary-soft, rgba(37,99,235,0.20));">
                            {{ $lead['source_label'] }}
                        </span>
                    </td>
                    <td class="text-xs" style="color: var(--text-muted);">{{ $lead['context'] ?: '—' }}</td>
                    <td class="text-xs" style="color: var(--text-faint);">{{ optional($lead['created_at'])->diffForHumans() ?? '—' }}</td>
                    <td class="text-right whitespace-nowrap">
                        <button type="button" class="btn-ghost text-xs py-1.5 px-2.5" data-lead-action="approve"
                                data-source-type="{{ $lead['source_type'] }}" data-source-id="{{ $lead['source_id'] }}">
                            <i class="fas fa-check text-[10px]"></i> Approve
                        </button>
                        <button type="button" class="btn-ghost text-xs py-1.5 px-2.5" data-lead-action="dismiss"
                                data-source-type="{{ $lead['source_type'] }}" data-source-id="{{ $lead['source_id'] }}">
                            <i class="fas fa-xmark text-[10px]"></i> Dismiss
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($leads->hasPages())
    <div class="px-4 py-3 border-t" style="border-color: var(--border-subtle);">
        {{ $leads->onEachSide(1)->links() }}
    </div>
    @endif
</div>
@else
<div class="glass rounded-2xl p-10 text-center">
    <i class="fas fa-user-plus text-3xl mb-3" style="color: var(--text-faint);"></i>
    <p class="font-medium" style="color: var(--text-primary);">No pending leads</p>
    <p class="text-sm mt-1" style="color: var(--text-muted);">
        New RSVPs, form submissions, orders, bookings, reviews and event interest will show up here for review.
    </p>
</div>
@endif
