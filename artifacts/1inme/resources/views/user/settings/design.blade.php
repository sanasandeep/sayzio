@extends('user.layouts.settings')
@section('title', 'Design')
@section('settings-content')
<div x-data="{prefs:window.sayzioNavigation.read(),save(){window.sayzioNavigation.save(this.prefs)},setTheme(value){document.documentElement.classList.toggle('light-mode',value==='light');try{localStorage.setItem('1inme_theme',value);document.cookie='1inme_theme='+value+'; path=/; max-age=31536000; SameSite=Lax'}catch(e){}window.dispatchEvent(new CustomEvent('1inme:theme-change',{detail:{theme:value}}))}}" class="space-y-6">
    <div><h1 class="text-2xl font-bold">Design</h1><p class="mt-2" style="color:var(--text-muted)">Choose your page theme and navigation style. Changes apply immediately and are saved for this account on this browser.</p></div>
    <section class="glass rounded-2xl p-6"><h2 class="font-semibold mb-4">Page theme</h2><div class="flex gap-3"><button type="button" class="settings-tab" @click="setTheme('light')">☀ Light</button><button type="button" class="settings-tab" @click="setTheme('dark')">☾ Dark</button></div></section>
    <div class="grid md:grid-cols-2 gap-6">
    @foreach(['sidebar'=>'Sidebar','topbar'=>'Top bar'] as $area=>$label)
    <section class="glass rounded-2xl p-6">
        <h2 class="font-semibold mb-4">{{ $label }}</h2>
        <div class="grid grid-cols-2 gap-3">
            @foreach([''=>'Admin default','theme'=>'Page theme','light'=>'Light','dark'=>'Dark','gradient-light'=>'Light gradient','gradient-medium'=>'Medium gradient','gradient-dark'=>'Dark gradient'] as $value=>$text)
            <button type="button" @click="prefs.{{ $area }}='{{ $value }}';save()" :aria-pressed="(prefs.{{ $area }} || '') === '{{ $value }}'" class="design-choice" :class="{'selected':(prefs.{{ $area }} || '') === '{{ $value }}'}">
                <span class="design-swatch design-swatch-{{ $value ?: 'default' }}" aria-hidden="true"></span><span>{{ $text }}</span>
            </button>
            @endforeach
        </div>
        @if($area==='topbar')
        <label class="block mt-5">Logo area<select class="block w-full mt-2 rounded-lg p-3" style="background:var(--bg-card);border:1px solid var(--border-strong)" :value="typeof prefs.logo==='boolean' ? String(prefs.logo) : ''" @change="if($event.target.value===''){delete prefs.logo}else{prefs.logo=$event.target.value==='true'}save()"><option value="">Admin default</option><option value="false">Use sidebar style</option><option value="true">Include in top bar style</option></select></label>
        @endif
    </section>
    @endforeach
    </div>
    <button type="button" class="settings-tab" @click="window.sayzioNavigation.reset();prefs={}">Reset navigation to admin defaults</button>
</div>
<style>
.design-choice {padding:12px;border:1px solid var(--border-strong);border-radius:14px;text-align:left;color:var(--text-primary);background:var(--bg-card);font-size:14px;}
.design-choice.selected {border-color:var(--accent);box-shadow:0 0 0 1px var(--accent);}
.design-swatch {display:block;height:44px;border-radius:8px;margin-bottom:10px;border:1px solid #80808030;background:linear-gradient(90deg,#fff 50%,#111827 50%);}
.design-swatch-light {background:#fff}.design-swatch-dark {background:#111827}
.design-swatch-gradient-light {background:linear-gradient(135deg,#edf4ff,#f0eaff,#e4f6f5)}
.design-swatch-gradient-medium {background:linear-gradient(135deg,#4f46b5,#6751bb,#296d88)}
.design-swatch-gradient-dark {background:linear-gradient(135deg,#111c38,#2b1d48,#12383c)}
</style>
@endsection
