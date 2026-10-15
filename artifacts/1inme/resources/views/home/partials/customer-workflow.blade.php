@php
$workflowSteps = [
 ['customers','Customers','fa-address-card','Know who you’re helping.','Keep contact details, requirements and follow-ups together in your CRM.','New enquiry','Alex Morgan','Needs a new brand identity','Website enquiry','Follow up on the brief','CRM'],
 ['sales','Sales','fa-file-invoice-dollar','Turn an enquiry into agreed work.','Keep the agreed offer, invoice and payment information organized.','Sale agreed','Brand identity package','For Alex Morgan · Northstar Studio','Invoice paid','Prepare the project handover','Sales & billing'],
 ['delivery','Delivery','fa-list-check','Keep the work moving.','Connect plans, tasks and resources in a project so the next step is clear.','Project in progress','Alex’s brand identity','Design approved · Review next','Design complete','Collect feedback and deliver files','Projects & tasks'],
 ['support','Support','fa-comments','Keep the relationship going.','Organize customer conversations and follow-up work after delivery.','Customer follow-up','Alex Morgan','“Can we work on our website next?”','Brand project delivered','Discuss the next opportunity','Inbox & CRM'],
];
@endphp
<section id="customer-workflow" class="ch-section" aria-labelledby="ch-workflow-heading">
<div class="ch-heading"><span class="ch-eyebrow">AFTER THE FIRST CLICK</span><h2 id="ch-workflow-heading">Manage what happens next.</h2><p>Your page brings Alex in. Sayzio helps you organize the relationship that follows.</p></div>
<div class="ch-demo" data-home-tabs>
<div class="ch-tabs" role="tablist" aria-label="Customer workflow">@foreach($workflowSteps as $i=>$step)<button type="button" id="workflow-tab-{{ $step[0] }}" role="tab" aria-controls="workflow-panel-{{ $step[0] }}" aria-selected="{{ $i===0?'true':'false' }}" tabindex="{{ $i===0?'0':'-1' }}"><i class="fas {{ $step[2] }}" aria-hidden="true"></i>{{ $step[1] }}</button>@endforeach</div>
@foreach($workflowSteps as $i=>$step)
<div class="ch-panel" role="tabpanel" id="workflow-panel-{{ $step[0] }}" aria-labelledby="workflow-tab-{{ $step[0] }}" @if($i!==0) hidden @endif>
<div class="ch-panel-copy"><span class="ch-chip">{{ $step[10] }}</span><h3>{{ $step[3] }}</h3><p>{{ $step[4] }}</p><a href="{{ route('site.features') }}">Explore the features <span aria-hidden="true">→</span></a></div>
<div class="ch-product"><div class="ch-product-top"><span><i class="fas {{ $step[2] }}" aria-hidden="true"></i> Northstar Studio</span><small>Illustrative example</small></div><div class="ch-record"><span class="ch-status">{{ $step[5] }}</span><div class="ch-person"><span class="ch-avatar" aria-hidden="true">AM</span><div><h4>{{ $step[6] }}</h4><p>{{ $step[7] }}</p></div></div><div class="ch-record-line"><i class="fas fa-check-circle" aria-hidden="true"></i>{{ $step[8] }}</div><div class="ch-next"><small>NEXT STEP</small><strong>{{ $step[9] }}</strong></div></div><div class="ch-path" aria-label="Customer journey"><span>Enquiry</span><b aria-hidden="true">→</b><span>Customer</span><b aria-hidden="true">→</b><span>Next opportunity</span></div></div>
</div>@endforeach
<p class="ch-caption">One example across CRM, sales, projects and support. Connect records and review actions as needed.</p>
</div></section>
