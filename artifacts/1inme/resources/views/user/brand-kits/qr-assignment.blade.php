@php $identity=$qrCode->design['brand_identity']??[];$availableKits=\App\Modules\User\Models\BrandKit::where('user_id',workspace_owner_id())->whereNull('config->archived_at')->get();$assignedKit=\App\Modules\User\Models\BrandKit::where('user_id',workspace_owner_id())->find($identity['kit_id']??0); @endphp
@include('user.brand-kits.styles')
<details class="bk-panel mb-6"><summary class="font-semibold cursor-pointer">Brand Kit · {{ $assignedKit?->name??'Not assigned' }}</summary><div class="mt-4">@if($assignedKit)<a href="{{ route('user.brand-kits.edit',$assignedKit) }}">Edit shared Brand Kit</a>@endif
@include('user.brand-kits.assignment-controls',['kits'=>$availableKits,'current'=>$identity,'targetType'=>'qr','dynamic'=>false,'assignmentRoute'=>route('user.qr-codes.brand-identity',$qrCode)])
</div></details>
