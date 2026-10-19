@php
/*
 * The customer journey, shown as a journey.
 *
 * This was four tabs, each showing one static card, with the actual story
 * -- Enquiry -> Customer -> Next opportunity -- printed underneath in 9px
 * grey. The sequence was the point and the design hid it: you saw one
 * state at a time and never the travel between them.
 *
 * So the tablist IS the rail now. One Alex Morgan record sits at the
 * active stop and moves along when you pick another, carrying its history
 * with it: the stage chips accumulate rather than swap, because that is
 * what a relationship does. By Support the record is still showing that
 * the enquiry came in, the invoice was paid and the work was delivered.
 *
 * The rail keeps its tablist semantics -- role, aria-controls,
 * aria-selected, roving tabindex -- so compact-home-script drives it and
 * keyboard users get the same arrow-key behaviour as before. Only the
 * paint changed.
 *
 * `chips` is cumulative on purpose; `done` marks the ones already behind
 * you at that stage.
 */
$workflowSteps = [
 ['customers','Customers','fa-address-card','Know who you’re helping.','Keep contact details, requirements and follow-ups together in your CRM.','New enquiry','Alex Morgan','Needs a new brand identity','Website enquiry','Follow up on the brief','CRM',
   [['Enquiry',false]]],
 ['sales','Sales','fa-file-invoice-dollar','Turn an enquiry into agreed work.','Keep the agreed offer, invoice and payment information organized.','Sale agreed','Brand identity package','For Alex Morgan · Northstar Studio','Invoice paid','Prepare the project handover','Sales & billing',
   [['Enquiry',true],['Paid',false]]],
 ['delivery','Delivery','fa-list-check','Keep the work moving.','Connect plans, tasks and resources in a project so the next step is clear.','Project in progress','Alex’s brand identity','Design approved · Review next','Design complete','Collect feedback and deliver files','Projects & tasks',
   [['Enquiry',true],['Paid',true],['In progress',false]]],
 ['support','Support','fa-comments','Keep the relationship going.','Organize customer conversations and follow-up work after delivery.','Customer follow-up','Alex Morgan','“Can we work on our website next?”','Brand project delivered','Discuss the next opportunity','Inbox & CRM',
   [['Enquiry',true],['Paid',true],['Delivered',true],['Next job',false]]],
];
@endphp
<section id="customer-workflow" class="ch-section" aria-labelledby="ch-workflow-heading">
<div class="ch-heading"><span class="ch-eyebrow">AFTER THE FIRST CLICK</span><h2 id="ch-workflow-heading">Manage what happens next.</h2><p>Your page brings Alex in. Sayzio helps you organize the relationship that follows.</p></div>
<div class="ch-demo ch-journey" data-home-tabs data-ch-journey>

{{-- The rail. One dashed line behind four stops, with a pulse running it
     left to right, so the row reads as a route rather than a button bar. --}}
<div class="ch-rail" role="tablist" aria-label="Customer workflow">
<svg class="ch-rail-art" viewBox="0 0 1000 24" preserveAspectRatio="none" aria-hidden="true" focusable="false">
  <line class="ch-rail-base" x1="8" y1="12" x2="992" y2="12"/>
  <line class="ch-rail-flow" x1="8" y1="12" x2="992" y2="12"/>
  <circle class="ch-rail-pulse" cy="12" r="4"/>
</svg>
@foreach($workflowSteps as $i=>$step)
<button type="button" class="ch-stop" id="workflow-tab-{{ $step[0] }}" role="tab" aria-controls="workflow-panel-{{ $step[0] }}" aria-selected="{{ $i===0?'true':'false' }}" tabindex="{{ $i===0?'0':'-1' }}">
<span class="ch-stop-dot" aria-hidden="true"><i class="fas {{ $step[2] }}"></i></span>
<span class="ch-stop-label">{{ $step[1] }}</span>
</button>
@endforeach
</div>

@foreach($workflowSteps as $i=>$step)
<div class="ch-panel" role="tabpanel" id="workflow-panel-{{ $step[0] }}" aria-labelledby="workflow-tab-{{ $step[0] }}" @if($i!==0) hidden @endif>
<div class="ch-panel-copy"><span class="ch-chip">{{ $step[10] }}</span><h3>{{ $step[3] }}</h3><p>{{ $step[4] }}</p><a href="{{ route('site.features') }}">Explore the features <span aria-hidden="true">→</span></a></div>
<div class="ch-product" data-ch-card><div class="ch-product-top"><span><i class="fas {{ $step[2] }}" aria-hidden="true"></i> Northstar Studio</span><small>Illustrative example</small></div>
<div class="ch-record"><span class="ch-status">{{ $step[5] }}</span>
<div class="ch-person"><span class="ch-avatar" aria-hidden="true">AM</span><div><h4>{{ $step[6] }}</h4><p>{{ $step[7] }}</p></div></div>
<div class="ch-record-line"><i class="fas fa-check-circle" aria-hidden="true"></i>{{ $step[8] }}</div>
{{-- The history the record is carrying by this stage. Earlier stages stay
     on the card, ticked; the newest one is the live chip. --}}
<div class="ch-trail" aria-label="Progress so far">@foreach($step[11] as $chip)<span class="ch-trail-chip {{ $chip[1] ? 'is-done' : 'is-now' }}">@if($chip[1])<i class="fas fa-check" aria-hidden="true"></i>@endif{{ $chip[0] }}</span>@endforeach</div>
<div class="ch-next"><small>NEXT STEP</small><strong>{{ $step[9] }}</strong></div></div></div>
</div>@endforeach
<p class="ch-caption">One example across CRM, sales, projects and support. Connect records and review actions as needed.</p>
</div></section>
