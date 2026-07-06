{{--
    Shared per-type notification icon, used by both the full notifications
    page and the header bell dropdown so the two surfaces never drift.
    Expects: $n (UserNotification), $d (array, usually $n->data ?? []).
    Optional: $size — Tailwind size classes, defaults to the index page's
    10x10 avatar. The header dropdown passes a smaller size.
--}}
@php
    $__iconSize = $size ?? 'w-10 h-10';
@endphp
@if($n->type === 'social_connection_broken')
    <div class="{{ $__iconSize }} rounded-full flex items-center justify-center flex-shrink-0"
         style="background: rgba(239,68,68,0.12); color:#ef4444;">
        <i class="fas fa-triangle-exclamation"></i>
    </div>
@elseif($n->type === 'workspace_access_request')
    <div class="{{ $__iconSize }} rounded-full flex items-center justify-center flex-shrink-0"
         style="background: rgba(92,131,255,0.12); color:#3d6bff;">
        <i class="fas fa-user-shield"></i>
    </div>
@elseif($n->type === 'task_assigned')
    <div class="{{ $__iconSize }} rounded-full flex items-center justify-center flex-shrink-0"
         style="background: rgba(34,197,94,0.12); color:#16a34a;">
        <i class="fas fa-list-check"></i>
    </div>
@elseif($n->type === 'task_mention')
    <div class="{{ $__iconSize }} rounded-full flex items-center justify-center flex-shrink-0"
         style="background: rgba(59,130,246,0.12); color:#2563eb;">
        <i class="fas fa-at"></i>
    </div>
@elseif($n->type === 'task_due')
    <div class="{{ $__iconSize }} rounded-full flex items-center justify-center flex-shrink-0"
         style="background: rgba(234,179,8,0.12); color:#ca8a04;">
        <i class="fas fa-clock"></i>
    </div>
@elseif($n->type === 'task_overdue')
    <div class="{{ $__iconSize }} rounded-full flex items-center justify-center flex-shrink-0"
         style="background: rgba(239,68,68,0.12); color:#dc2626;">
        <i class="fas fa-fire"></i>
    </div>
@elseif($n->type === 'billing.subscription_update')
    <div class="{{ $__iconSize }} rounded-full flex items-center justify-center flex-shrink-0"
         style="background: rgba(59,130,246,0.12); color:#2563eb;">
        <i class="fas fa-credit-card"></i>
    </div>
@elseif($n->type === 'delivery_project.comment')
    <div class="{{ $__iconSize }} rounded-full flex items-center justify-center flex-shrink-0"
         style="background: rgba(92,131,255,0.12); color:#3d6bff;">
        <i class="fas fa-comment-dots"></i>
    </div>
@elseif(!empty($d['follower_avatar']) || !empty($d['creator_avatar']))
    <img src="{{ $d['follower_avatar'] ?? $d['creator_avatar'] }}" class="{{ $__iconSize }} rounded-full object-cover flex-shrink-0" alt=""/>
@else
    <div class="{{ $__iconSize }} rounded-full bg-gradient-to-br from-blue-500 to-fuchsia-500 text-white flex items-center justify-center font-bold flex-shrink-0">
        {{ strtoupper(substr($d['follower_name'] ?? $d['creator_name'] ?? '?', 0, 1)) }}
    </div>
@endif
