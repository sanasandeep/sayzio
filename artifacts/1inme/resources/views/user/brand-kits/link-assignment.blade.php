@php
$identity=$link->settings['brand_identity']??[];
$availableKits=\App\Modules\User\Models\BrandKit::where('user_id',workspace_owner_id())->whereNull('config->archived_at')->get();
$assignedKit=$identity?\App\Modules\User\Models\BrandKit::where('user_id',workspace_owner_id())->find($identity['kit_id']??0):null;
@endphp
@include('user.brand-kits.styles')
<section class="bk-panel mb-6" id="brand-kit-assignment"><h2>Brand Kit</h2>
@if($assignedKit)<p class="bk-muted mb-4">Assigned: <strong>{{ $assignedKit->name }}</strong> · {{ ($identity['mode']??'follow')==='custom'?'Customized for this link':'Follow Brand Kit' }} · <a href="{{ route('user.brand-kits.edit',$assignedKit) }}">Edit shared Brand Kit</a></p>@elseif($identity)<p class="bk-muted mb-4">The previously assigned kit is unavailable. Current styling is retained.</p>@endif
@include('user.brand-kits.assignment-controls',['kits'=>$availableKits,'current'=>$identity,'dynamic'=>false])
</section>
