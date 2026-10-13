@extends('user.layouts.app')
@section('title','Membership management')
@section('content')
@include('user.links.partials.editor-header',['activeMainTab'=>'members'])
<style>
.club-members {display:grid;gap:20px}.club-members section{background:var(--bg-card);border:1px solid var(--border-strong);border-radius:20px;padding:24px}.club-members h2{font-size:20px;font-weight:700;margin-bottom:12px}.club-members label{display:block;font-size:13px;margin-bottom:12px}.club-members input:not([type=checkbox]),.club-members select,.club-members textarea{display:block;width:100%;padding:10px;margin-top:6px;background:var(--bg-input,var(--bg-card));color:var(--text-primary);border:1px solid var(--border-strong);border-radius:10px}.club-members .fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.club-members p{color:var(--text-secondary);font-size:14px;margin-bottom:12px}.club-members .member{border-top:1px solid var(--border-strong);padding:16px 0;overflow-wrap:anywhere}.club-members .field-row{display:grid;grid-template-columns:1fr 1fr 1fr auto auto;gap:10px;align-items:center;margin-bottom:12px}.club-members .status{display:inline-block;padding:4px 10px;border-radius:30px;background:var(--bg-glass-hover);font-size:12px}.club-members .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}@media(max-width:640px){.club-members .fields,.club-members .field-row{grid-template-columns:1fr}.club-members section{padding:16px}}
</style>
<div class="club-members">
<section><h2>Members</h2><p>Add and manage members, approve applications and set membership expiry. Active members get a free Sayzio account or are linked to an existing account. Their account password and subscription are preserved.</p>
<form method="get"><label>Search members<input name="q" value="{{ request('q') }}" placeholder="Name, email or phone"></label><button class="btn-ghost">Search</button></form>
@forelse($members as $member)
<article class="member"><strong>{{ $member->name }}</strong> <span class="status">{{ $member->isExpired() && $member->status==='active' ? 'Expired' : ucfirst($member->status) }}</span><p>{{ $member->email }} · {{ $member->phone }}<br>Expires: {{ $member->expires_at?->format('d M Y') ?? 'No expiry' }} · {{ $member->account_id ? 'Sayzio account linked' : 'Awaiting approval' }}</p>
@foreach($settings['fields']??[] as $field)@if(isset($member->custom_fields[$field['key']]))<p>{{ $field['label'] }}: {{ $member->custom_fields[$field['key']] }}</p>@endif @endforeach
@canInWorkspace('links.edit')<a class="btn-ghost" href="{{ route('user.links.memberships',['link'=>$link,'edit'=>$member->id]) }}">Edit / approve / renew</a>@endcanInWorkspace</article>
@empty<p>No members yet. Add a member below or connect a Sayzio Form to collect applications.</p>@endforelse
{{ $members->links() }}</section>
@canInWorkspace('links.edit')
<section><h2>{{ $edit ? 'Edit member' : 'Add member' }}</h2>
<form method="post" action="{{ route('user.links.memberships.save',$link) }}">@csrf
@if($edit)<input type="hidden" name="id" value="{{ $edit->id }}">@endif
<div class="fields">
<label>Name *<input name="name" required maxlength="150" value="{{ old('name',$edit?->name) }}"></label>
<label>Email *<input type="email" name="email" required value="{{ old('email',$edit?->email) }}" @readonly($edit?->account_id)></label>
<label>Phone *<input name="phone" required maxlength="30" placeholder="Include country code" value="{{ old('phone',$edit?->phone) }}"></label>
<label>Membership expiry<input type="date" name="expires_at" value="{{ old('expires_at',$edit?->expires_at?->format('Y-m-d')) }}"><span>Leave empty for no expiry.</span></label>
<label>Status<select name="status">@foreach(['active','pending','cancelled'] as $status)<option value="{{ $status }}" @selected(old('status',$edit?->status??'active')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
@foreach($settings['fields']??[] as $field)
<label>{{ $field['label'] }} {{ $field['required'] ? '*' : '' }}
@if($field['type']==='textarea')<textarea name="custom_fields[{{ $field['key'] }}]" @required($field['required'])>{{ old('custom_fields.'.$field['key'],data_get($edit?->custom_fields,$field['key'])) }}</textarea>
@else<input type="{{ $field['type'] }}" name="custom_fields[{{ $field['key'] }}]" @required($field['required']) value="{{ old('custom_fields.'.$field['key'],data_get($edit?->custom_fields,$field['key'])) }}">@endif</label>
@endforeach
</div><p>Only the club record is edited here. New account holders can sign in through Sayzio’s email verification or password reset flow.</p><button class="btn-primary">Save member</button>@if($edit)<a class="btn-ghost" href="{{ route('user.links.memberships',$link) }}">Cancel</a>@endif
</form></section>
<section x-data="{ fields: @js(old('fields',$settings['fields']??[])).map(f => ({...f, required: f.required === true || f.required === 1 || f.required === '1'})), formId: @js((string)old('form_id',$settings['form_id']??'')) }"><h2>Membership form settings</h2><p>Name, phone and email are always required. Add custom fields, then connect a Sayzio Form. Submitted applications remain pending until you approve them. Invalid or incomplete submissions stay in Sayzio Forms for review.</p>
<form method="post" action="{{ route('user.links.memberships.settings',$link) }}">@csrf
<template x-for="(field,index) in fields" :key="index"><div class="field-row">
<label>Field key<input x-model="field.key" :name="'fields['+index+'][key]'" required pattern="[a-z][a-z0-9_]{0,39}" placeholder="membership_number"></label>
<label>Label<input x-model="field.label" :name="'fields['+index+'][label]'" required></label>
<label>Type<select x-model="field.type" :name="'fields['+index+'][type]'"><option value="text">Text</option><option value="textarea">Long text</option><option value="number">Number</option><option value="date">Date</option><option value="email">Email</option></select></label>
<label><input type="checkbox" x-model="field.required"> Required<input type="hidden" :name="'fields['+index+'][required]'" :value="field.required ? 1 : 0"></label>
<button type="button" class="btn-ghost" @click="fields.splice(index,1)">Remove</button></div></template>
<button type="button" class="btn-ghost" @click="fields.push({key:'',label:'',type:'text',required:false})" :disabled="fields.length>=20">Add custom field</button>
<label class="mt-4">Connected Sayzio Form<select name="form_id" x-model="formId"><option value="">No form connected</option>@foreach($forms as $form)<option value="{{ $form->id }}">{{ $form->title }}</option>@endforeach</select></label>
@foreach($forms as $form)<div x-show="formId === '{{ $form->id }}'" x-cloak><p><a class="btn-ghost" href="{{ $form->getPublicUrl() }}" target="_blank" rel="noopener">Open form</a> <a class="btn-ghost" href="{{ route('user.forms.builder',$form) }}">Edit Sayzio Form</a></p><div class="fields">
@foreach(['name'=>'Name','email'=>'Email','phone'=>'Phone',...collect($settings['fields']??[])->pluck('label','key')->all()] as $key=>$label)
<label>{{ $label }} maps to<select name="mapping[{{ $key }}]" :disabled="formId !== '{{ $form->id }}'"><option value="{{ $key }}">Same field key ({{ $key }})</option>@foreach($form->fields??[] as $f)<option value="{{ $f['id'] }}" @selected(($settings['mapping'][$key]??$key)===$f['id'])>{{ $f['label']??$f['id'] }} ({{ $f['id'] }})</option>@endforeach</select></label>
@endforeach</div></div>@endforeach
<div class="actions"><button class="btn-primary">Save form settings</button></div>
</form>
@canInWorkspace('inbox.create')<form method="post" action="{{ route('user.links.memberships.create-form',$link) }}" class="mt-4">@csrf<button class="btn-ghost">Create a Sayzio membership form</button><p class="mt-2">Save custom fields first. The new form includes those fields and a consent checkbox. You can add its URL or a Form block on your club page.</p></form>@endcanInWorkspace
</section>
@endcanInWorkspace
</div>
@endsection
