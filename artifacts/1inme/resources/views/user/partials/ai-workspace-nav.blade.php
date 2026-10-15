@php
    $aiUser = auth()->user();
    $staffAvailable = $aiUser && (\App\Services\AI\AiPlanAccess::featureAllowed($aiUser,'ai_staff_billing') || \App\Services\AI\AiPlanAccess::featureAllowed($aiUser,'ai_staff_contacts') || \App\Services\AI\AiPlanAccess::featureAllowed($aiUser,'ai_staff_general'));
@endphp
<nav class="ai-workspace-nav" aria-label="AI workspace">
    @if($aiUser)<a href="{{ \App\Services\AI\AiPlanAccess::featureAllowed($aiUser,'ask_coach') ? route('user.ai.ask-coach.show') : route('user.ai.companion.show') }}" @if(request()->routeIs('user.ai.ask-coach.*','user.ai.companion.*')) aria-current="page" @endif><i class="fas fa-comment-dots"></i> Ask Zio</a>@endif
    @if($aiUser)<a href="{{ route('user.ai-personas.index') }}" @if(request()->routeIs('user.ai.ask-coach.*','user.ai.companion.*'))
<nav class="ai-workspace-nav ai-workspace-subnav" aria-label="Ask Zio modes">
    <a href="{{ route('user.ai.companion.show') }}" @if(request()->routeIs('user.ai.companion.*')) aria-current="page" @endif>General chat</a>
    @if($aiUser && \App\Services\AI\AiPlanAccess::featureAllowed($aiUser,'ask_coach'))<a href="{{ route('user.ai.ask-coach.show') }}" @if(request()->routeIs('user.ai.ask-coach.*')) aria-current="page" @endif>Business insights</a>@endif
    <button type="button" onclick="var button=document.getElementById('sa-launcher');if(button)button.click();">Page guidance</button>
</nav>
@endif
@if(request()->routeIs('user.ai-personas.*','user.ai.staff.*')) aria-current="page" @endif><i class="fas fa-user-astronaut"></i> Agents</a>@endif
    @if($aiUser)<a href="{{ route('user.ai-companions.index') }}" @if(request()->routeIs('user.ai-companions.*')) aria-current="page" @endif><i class="fas fa-comments"></i> Channels</a>@endif
</nav>
@if(request()->routeIs('user.ai-personas.*','user.ai.staff.*'))
<nav class="ai-workspace-nav ai-workspace-subnav" aria-label="Agent types">
    @if($aiUser)<a href="{{ route('user.ai-personas.index') }}" @if(request()->routeIs('user.ai-personas.*')) aria-current="page" @endif>Customer agents</a>@endif
    @if($staffAvailable)<a href="{{ route('user.ai.staff.index') }}" @if(request()->routeIs('user.ai.staff.*')) aria-current="page" @endif>Team assistants</a>@endif
</nav>
@endif
@once
<style>.ai-workspace-nav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px}.ai-workspace-nav a,.ai-workspace-nav button{display:flex;align-items:center;gap:8px;padding:10px 16px;border:1px solid var(--border-glass,#dbe2ef);border-radius:12px;font-size:13px;color:var(--text-muted,#9299aa);text-decoration:none}.ai-workspace-nav a[aria-current=page]{background:var(--accent,#3454ef);color:#fff;border-color:transparent}.ai-workspace-subnav a{padding:7px 13px;font-size:12px}.ai-workspace-subnav a[aria-current=page]{background:rgba(82,105,237,.1);color:var(--accent,#3454ef);border-color:rgba(82,105,237,.3)}</style>
@endonce
