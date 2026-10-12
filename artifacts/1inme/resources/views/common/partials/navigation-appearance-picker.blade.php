<div x-data="{ open:false, prefs:window.sayzioNavigation.read(), save(){window.sayzioNavigation.save(this.prefs)} }" @keydown.escape.window="open=false">
    <button type="button" class="header-icon-btn" @click="open=!open" :aria-expanded="open" aria-label="Navigation appearance" title="Navigation appearance"><i class="fas fa-palette"></i></button>
    <template x-teleport="body">
        <section class="navigation-picker" x-show="open" x-cloak @click.outside="open=false" aria-label="Navigation appearance">
            <div class="flex justify-between items-center"><h2 class="font-semibold">Navigation appearance</h2><button type="button" @click="open=false" aria-label="Close appearance"><i class="fas fa-times"></i></button></div>
            <p class="text-sm mt-2" style="color:var(--text-muted)">Keep your page theme and style each bar separately. Saved for this account on this browser.</p>
            @foreach(['sidebar' => 'Sidebar', 'topbar' => 'Top bar'] as $area => $label)
            <label>{{ $label }}
                <select x-model="prefs.{{ $area }}" @change="save()">
                    <option value="">Follow admin default</option><option value="theme">Follow page theme</option><option value="light">Light</option><option value="dark">Dark</option><option value="color">Color</option>
                </select>
            </label>
            <label x-show="prefs.{{ $area }} === 'color'">{{ $label }} color
                <input type="color" :value="prefs.{{ $area }}Color || '#4338ca'" @input="prefs.{{ $area }}Color=$event.target.value;save()" class="block w-full h-10 mt-2">
            </label>
            @endforeach
            <button type="button" class="text-sm" @click="window.sayzioNavigation.reset();prefs={}">Reset to admin defaults</button>
        </section>
    </template>
</div>
