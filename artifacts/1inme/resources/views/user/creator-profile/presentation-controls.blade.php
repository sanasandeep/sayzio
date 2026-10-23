@php
    $profileDesign = \App\Modules\User\Support\CreatorProfilePresentation::forUser($user);
@endphp
<fieldset x-show="editorTab === 'design' || editorTab === 'all'" class="cp-editor-panel">
    <legend>Profile layout</legend>
    <p class="cp-editor-hint">Start with the composition. Every layout works with your photo, content and links.</p>
    <div class="cp-choice-grid">
        @foreach(['classic'=>['Classic','Left portrait · rounded cards'],'centered'=>['Spotlight','Centered portrait · soft cards'],'editorial'=>['Editorial','Right portrait · crisp cards'],'minimal'=>['Compact','Inline portrait · clean cards']] as $key=>$layout)
        <label class="cp-choice">
            <input type="radio" name="presentation[layout]" value="{{ $key }}" @checked(old('presentation.layout',$profileDesign['layout']) === $key)>
            <span class="cp-choice-body"><span class="cp-layout-demo cp-demo-{{ $key }}" aria-hidden="true"><i></i><b></b><em></em></span><strong>{{ $layout[0] }}</strong><small>{{ $layout[1] }}</small></span>
        </label>
        @endforeach
    </div>
</fieldset>
<fieldset x-show="editorTab === 'design' || editorTab === 'all'" class="cp-editor-panel">
    <legend>Gradient &amp; appearance</legend>
    <p class="cp-editor-hint">Choose a gradient, then a light or dark card palette. Your cover image and custom accent still take priority.</p>
    <div class="cp-choice-grid">
        @foreach(['paper'=>['Aurora','#8daff3,#c4a3ee'],'warm'=>['Sunset','#e7ab7d,#e8a8bd'],'mint'=>['Lagoon','#78bea9,#9dbfe8'],'midnight'=>['Twilight','#7984c9,#ad86bc']] as $key=>$theme)
        <label class="cp-choice"><input type="radio" name="presentation[theme]" value="{{ $key }}" @checked(old('presentation.theme',$profileDesign['theme']) === $key)><span class="cp-choice-body"><span class="cp-gradient-swatch" style="background:linear-gradient(135deg,{{ $theme[1] }})" aria-hidden="true"></span><strong>{{ $theme[0] }}</strong></span></label>
        @endforeach
    </div>
    <div class="cp-mode-choices mt-4">
        @foreach(['light'=>'Light','dark'=>'Dark'] as $key=>$label)
        <label class="cp-choice"><input type="radio" name="presentation[mode]" value="{{ $key }}" @checked(old('presentation.mode',$profileDesign['mode']) === $key)><span class="cp-choice-body"><strong>{{ $label }}</strong><small>{{ $key === 'light' ? 'Bright cards, soft gradient' : 'Quiet cards, subtle gradient' }}</small></span></label>
        @endforeach
    </div>
</fieldset>
