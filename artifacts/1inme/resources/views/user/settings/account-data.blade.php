@extends('user.layouts.settings')
@section('title','Your data & account')
@section('settings-content')
<div x-data="accountData()" x-init="load()" class="space-y-5 max-w-3xl">
<h1 class="text-2xl font-bold">Your data &amp; account</h1>
<p>Download your data, request deletion of selected personal data, or request permanent account deletion. Requests go to the existing privacy review queue. Approved deletions have a seven-day waiting period. Deleting an account does not cancel an Apple or Google subscription; manage that subscription in the store.</p>
<p role="status" x-text="message"></p>
<form @submit.prevent="submit()" class="card-premium p-5 space-y-4">
<label class="block">Request type<select class="theme-input w-full" x-model="type"><option value="export">Download my data</option><option value="data_deletion">Delete selected data, keep my account</option><option value="deletion">Delete my account permanently</option></select></label>
<div x-show="type==='data_deletion'" class="space-y-2"><p>Applies to your personal workspace only. Team workspace data, invoices, purchases and account records are retained.</p><template x-for="scope in ['links','contacts','notifications']"><label class="block"><input type="checkbox" :value="scope" x-model="scopes"> <span x-text="scope"></span></label></template></div>
<label class="block">Additional details (optional)<textarea class="theme-input w-full" maxlength="2000" x-model="reason"></textarea></label>
<label x-show="type!=='export'" class="block">Type <strong x-text="type==='deletion'?'DELETE ACCOUNT':'DELETE DATA'"></strong><input class="theme-input w-full" x-model="confirmation" autocomplete="off"></label>
<button class="btn-primary" :disabled="busy" type="submit">Submit request</button>
</form>
<h2 class="font-bold">Your requests</h2>
<template x-for="item in items" :key="item.id"><div class="card-premium p-4"><strong x-text="item.label"></strong><span class="ml-3" x-text="item.status"></span><p x-show="item.scheduled_at" x-text="'Scheduled: '+item.scheduled_at"></p><a x-show="item.can_download" :href="downloadBase+'/'+item.id+'/download'" class="underline">Download archive</a></div></template>
<p x-show="!items.length">No requests yet.</p>
</div>
<script>
function accountData(){return {type:'export',scopes:[],reason:'',confirmation:'',items:[],message:'',busy:false,downloadBase:@js(route('user.account-data.index')),
async load(){try {const r=await fetch(@js(route('user.account-data.index')),{headers:{Accept:'application/json'}});if(!r.ok)throw new Error('Unable to load requests.');this.items=(await r.json()).data.items;}catch(e){this.message=e.message;}},
async submit(){this.busy=true;this.message='';try{const r=await fetch(@js(route('user.account-data.store')),{method:'POST',headers:{'Content-Type':'application/json',Accept:'application/json','X-CSRF-TOKEN':@js(csrf_token())},body:JSON.stringify({type:this.type,reason:this.reason,confirmation:this.confirmation,data_scopes:this.type==='data_deletion'?this.scopes:undefined})});const j=await r.json();if(!r.ok)throw new Error(j.message||'Unable to submit request.');this.message='Request submitted for privacy review.';this.confirmation='';await this.load();}catch(e){this.message=e.message;}finally{this.busy=false;}}
};}
</script>
@endsection
