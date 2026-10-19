@php
/*
 * "AI with your context" -- shown as context actually feeding something.
 *
 * The scene used to be a chip reading "Your selected context", a prompt
 * bubble and an answer card stacked vertically. It asserted the idea and
 * showed none of it: nothing on screen connected the context to the
 * result, and the two inputs the heading names -- Brand Kits and AI Minds
 * -- never appeared at all.
 *
 * Now they do. Two input cards on the left carry real contents (the brand's
 * swatches and type, the knowledge selected), two curves converge from them
 * into one answer on the right, and the answer carries the same swatches
 * back so you can see what it was built from. A Y, deliberately -- the
 * workflow section above runs as a horizontal rail, and two identical
 * rectangles back to back was half of what made this page feel flat.
 *
 * `feeds` is [label, detail, kind] where kind picks what the card draws:
 * swatches, chips or lines.
 */
$aiExamples = [
 ['create','Create','fa-wand-magic-sparkles','Turn your brief into a draft.','Use your Brand Kit and selected AI Minds to prepare pages and campaign materials you can review and edit.','Brand Kit + AI Minds','Draft a page for our new design service.','Page draft ready for review','Your brand style, service details and call to action, brought together.',
   [['Brand Kit','Northstar','swatches'],['AI Minds','Services, tone','chips']]],
 ['assist','Assist','fa-comment-dots','Give your AI the right context.','Connect knowledge to an Agent and choose where visitors can reach it through Channels.','AI Minds + Agents + Channels','What does your design service include?','Answer with your business knowledge','Help visitors understand your offering and find their next step.',
   [['AI Minds','Service list, FAQs','chips'],['Channels','Page, chat, WhatsApp','lines']]],
 ['organize','Organize','fa-list-check','Move from an idea to next steps.','Use Planner for plans and AI Staff for supported work. Review suggestions before applying changes.','Planner + AI Staff','Help me organize this launch.','A plan you can work from','Review tasks, prepare materials in Studio and connect them to your project.',
   [['Planner','Launch plan','lines'],['AI Staff','Studio, tasks','chips']]],
];
$aiSwatches = ['#5845d8','#8668ef','#2fb8c6','#f0a93c','#e4617f'];
@endphp
<section id="connected-ai" class="ch-section" aria-labelledby="ch-ai-heading"><div class="ch-heading"><span class="ch-eyebrow">AI WITH YOUR CONTEXT</span><h2 id="ch-ai-heading">Your knowledge. Your brand.<br>AI that helps you work.</h2><p><strong>Brand Kits</strong> set the identity. <strong>AI Minds</strong> supply the knowledge. You choose how to use them.</p><p class="ch-muted"><strong>Zio</strong> is the assistant behind it: on your pages Zio answers visitors and captures leads, and in the app Zio helps you work. <strong>Coins</strong> are the pay-as-you-go balance AI generation draws on &mdash; one balance you top up, spent only when you need more than your plan includes.</p></div>
<div class="ch-demo ch-converge" data-home-tabs><div class="ch-tabs" role="tablist" aria-label="Ways AI can help">@foreach($aiExamples as $i=>$example)<button type="button" id="ai-tab-{{ $example[0] }}" role="tab" aria-controls="ai-panel-{{ $example[0] }}" aria-selected="{{ $i===0?'true':'false' }}" tabindex="{{ $i===0?'0':'-1' }}"><i class="fas {{ $example[2] }}" aria-hidden="true"></i>{{ $example[1] }}</button>@endforeach</div>
@foreach($aiExamples as $i=>$example)
<div class="ch-panel" role="tabpanel" id="ai-panel-{{ $example[0] }}" aria-labelledby="ai-tab-{{ $example[0] }}" @if($i!==0) hidden @endif>
<div class="ch-panel-copy"><span class="ch-chip">{{ $example[5] }}</span><h3>{{ $example[3] }}</h3><p>{{ $example[4] }}</p><small class="ch-muted">AI is optional. Review the coin estimate before generation.</small></div>

<div class="ch-merge">
<div class="ch-feeds">
@foreach($example[9] as $feed)
<article class="ch-feed"><span class="ch-feed-name">{{ $feed[0] }}</span><span class="ch-feed-detail">{{ $feed[1] }}</span>
@if($feed[2]==='swatches')<span class="ch-sw-row" aria-hidden="true">@foreach($aiSwatches as $sw)<i style="background:{{ $sw }}"></i>@endforeach<b>Aa</b></span>
@elseif($feed[2]==='chips')<span class="ch-feed-chips" aria-hidden="true"><i></i><i></i><i></i></span>
@else<span class="ch-feed-lines" aria-hidden="true"><i></i><i></i></span>@endif
</article>
@endforeach
</div>

{{-- The two curves. Dashed stroke with a flowing offset, plus a brighter
     short segment travelling each one -- all CSS, so the reduced-motion
     block below genuinely stops it. SMIL would not have. --}}
<svg class="ch-merge-art" viewBox="0 0 120 190" preserveAspectRatio="none" aria-hidden="true" focusable="false">
  <path class="ch-wire" d="M2 44 C52 44, 60 95, 118 95"/>
  <path class="ch-wire" d="M2 146 C52 146, 60 95, 118 95"/>
  <path class="ch-wire ch-wire-run" d="M2 44 C52 44, 60 95, 118 95"/>
  <path class="ch-wire ch-wire-run ch-wire-run--b" d="M2 146 C52 146, 60 95, 118 95"/>
</svg>

<article class="ch-out"><span class="ch-out-top"><span class="ch-spark" aria-hidden="true">✦</span>{{ $example[7] }}</span>
<p>{{ $example[8] }}</p>
<span class="ch-sw-row ch-sw-row--sm" aria-hidden="true">@foreach($aiSwatches as $sw)<i style="background:{{ $sw }}"></i>@endforeach</span>
<span class="ch-status">Review → Edit → Use</span></article>
</div>
</div>@endforeach
</div></section>
