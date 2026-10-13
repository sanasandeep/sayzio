@isset($businessPerformance)
@php
 $bp=$businessPerformance; $profiles=\App\Modules\User\Services\IndustryPerformanceReport::PROFILES;
 $bpColors=['#3b82f6','#a376ef','#22b1a1']; $bpTones=['#123c85','#512b89','#075953'];
@endphp
<section class="business-report" id="business-performance" aria-labelledby="bp-title">
 <header class="bp-header"><div><span class="bp-eyebrow">BUSINESS PERFORMANCE · FREE · NO AI</span><h2 id="bp-title">{{ $bp['label'] }}</h2><p>{{ $bp['goal'] ?: 'Tell us the outcome this link should achieve.' }}</p></div><span class="bp-range">{{ implode(' – ', $bp['period']) }}</span></header>
 @if($bp['metrics'])
 <div class="bp-metrics">
 @foreach($bp['metrics'] as $metric)
 <article class="bp-stat" style="--ov-color:{{ $bpColors[$loop->index % 3] }};--ov-tone:{{ $bpTones[$loop->index % 3] }};">
  <h3>{{ $metric['label'] }}</h3>
  @include('user.partials.count-card-art',['kind'=>$metric['kind'],'artId'=>'business-stat-'.$loop->index])
  <strong>{{ $metric['value'] }}</strong><p>{{ $metric['note'] }}</p>
 </article>
 @endforeach
 </div>
 @endif
 <div class="bp-checks">
 @foreach($bp['checks'] as $check)
 <article class="bp-check bp-{{ $check['tone'] }}"><span class="bp-check-icon" aria-hidden="true">{{ $check['tone']==='warning'?'!':($check['tone']==='win'?'✓':'→') }}</span><div><h3>{{ $check['title'] }}</h3><p>{{ $check['detail'] }}</p></div></article>
 @endforeach
 </div>
 <details class="bp-context" @if(!$bp['profile'] || empty($bp['context']['industry'])) open @endif>
  <summary>Business context <span>Make this report specific to you</span></summary>
  <form action="{{ route('user.links.performance-business.update',$link) }}" method="POST">
   @csrf
   @foreach(request()->only(['from','to','period','source','country','device']) as $key=>$value)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endforeach
   <div class="bp-fields">
    <label>Industry profile
     @if(\App\Modules\User\Services\IndustryPerformanceReport::typeProfile($link))
     <input type="hidden" name="profile" value="{{ $bp['profile'] }}"><input class="theme-input" value="{{ $bp['label'] }}" readonly>
     @else
     <select name="profile" class="theme-input" required><option value="">Choose your industry</option>@foreach($profiles as $key=>$profile)<option value="{{ $key }}" @selected($key===$bp['profile'])>{{ $profile['label'] }}</option>@endforeach</select>
     @endif
    </label>
    <label>Business specialty / operating model<input class="theme-input" name="industry" required maxlength="200" value="{{ old('industry',$bp['context']['industry']??'') }}" placeholder="{{ $profiles[$bp['profile']]['prompt']??'Industry and how your business works' }}"></label>
    <label>Who is this for?<input class="theme-input" name="audience" required maxlength="200" value="{{ old('audience',$bp['context']['audience']??'') }}" placeholder="Your target audience"></label>
    <label>Primary goal<input class="theme-input" name="goal" required maxlength="200" value="{{ old('goal',$bp['context']['goal']??$bp['goal']) }}" placeholder="The action you want visitors to complete"></label>
   </div><button class="bp-save" type="submit">Save business context</button>
  </form>
 </details>
 <footer class="bp-footer">Based on recorded activity and explicit rules. No AI calls or coins used. Business outcomes include all sources; offline and external outcomes are unknown.@if($bp['limited']) <strong>Limited traffic sample — no growth score or market-demand claims.</strong>@endif</footer>
</section>
<style>
.business-report{border:1px solid var(--border-glass);border-radius:24px;padding:22px;background:var(--bg-card);margin:24px 0;box-shadow:var(--card-shadow)}.bp-header{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-bottom:18px}.bp-eyebrow{color:var(--accent);font-size:10px;font-weight:800;letter-spacing:.1em}.bp-header h2{font-size:24px;font-weight:750;margin:8px 0 4px}.bp-header p,.bp-range{font-size:12px;color:var(--text-muted)}.bp-metrics{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.bp-stat{position:relative;overflow:hidden;padding:18px;border-radius:20px;background:radial-gradient(ellipse at 100% 0%,var(--ov-color),transparent 85%),var(--ov-tone);color:#fff;box-shadow:0 8px 20px color-mix(in srgb,var(--ov-tone) 15%,transparent);border:1px solid #ffffff30;min-height:140px}.bp-stat h3{font-size:13px;font-weight:600;max-width:calc(100% - 65px)}.bp-stat strong{display:block;margin-top:22px;font-size:34px;line-height:1.1;color:#fff;position:relative}.bp-stat p{font-size:11px;color:#e7edff;margin-top:8px;position:relative}.bp-stat .ov-count-art{position:absolute;right:12px;top:20px;width:68px;height:58px;animation:bp-float 5s ease-in-out infinite}.bp-stat:nth-child(2) .ov-count-art{animation-delay:-1.5s}.bp-stat:nth-child(3) .ov-count-art{animation-delay:-3s}.bp-checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:18px 0}.bp-check{display:flex;gap:12px;padding:16px;border-radius:16px;border:1px solid color-mix(in srgb,var(--bp-tone) 25%,transparent);background:color-mix(in srgb,var(--bp-tone) 7%,var(--bg-card));--bp-tone:#6f7cf1}.bp-warning{--bp-tone:#e6a03c}.bp-win{--bp-tone:#20ac91}.bp-check-icon{flex-shrink:0;border-radius:10px;width:30px;height:30px;display:grid;place-items:center;color:var(--bp-tone);background:color-mix(in srgb,var(--bp-tone) 16%,transparent);font-weight:800}.bp-check h3{font-size:14px;font-weight:650}.bp-check p{font-size:12px;line-height:1.6;margin-top:6px;color:var(--text-muted)}.bp-context{border-top:1px solid var(--border-glass);padding-top:16px}.bp-context summary{font-size:13px;cursor:pointer;font-weight:650}.bp-context summary span{font-size:11px;color:var(--text-muted);font-weight:400;margin-left:8px}.bp-fields{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:18px 0}.bp-fields label{font-size:12px}.bp-fields input,.bp-fields select{display:block;width:100%;margin-top:6px}.bp-save{border:0;cursor:pointer;border-radius:12px;padding:10px 16px;color:white;background:linear-gradient(110deg,#3659ed,#7650e9);font-size:12px;font-weight:650}.bp-footer{font-size:11px;line-height:1.6;color:var(--text-muted);margin-top:18px}.bp-footer strong{font-weight:500;display:block;margin-top:4px}@keyframes bp-float{50%{transform:translateY(-5px) rotate(3deg)}}@media(max-width:700px){.bp-header{align-items:flex-start;flex-direction:column}.bp-metrics,.bp-checks,.bp-fields{grid-template-columns:1fr}.business-report{padding:16px}.bp-stat{min-height:120px}}@media(prefers-reduced-motion:reduce){.bp-stat .ov-count-art{animation:none}}
</style>
@endisset
