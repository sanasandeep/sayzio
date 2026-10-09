<?php
namespace App\Modules\Common\Controllers;
use App\Modules\User\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicCatalogInquiryController extends RedirectController
{
    public function submit(Request $request, string $alias)
    {
        $link = Link::resolveByAlias($alias,$request->getHost());
        abort_unless($link && $link->isListingCatalog() && $link->isAccessible(),404);
        if ($gate = $this->enforceVisibility($request,$link)) return $gate;
        abort_if($link->is_password_protected && !session("link_unlocked_{$link->id}"),403);
        $catalog = $link->listingCatalog;
        abort_unless($catalog && ($catalog->settings['inquiries_enabled'] ?? true),403);
        $data = $request->validate([
            'entry_id'=>'required|integer','name'=>'required|string|max:150','email'=>'required|email|max:255',
            'phone'=>['nullable','string','max:40','regex:/^\+?[0-9 ()-]+$/'], 'message'=>'nullable|string|max:3000',
            'preferred_date'=>'nullable|date_format:Y-m-d|after_or_equal:today','batch'=>'nullable|uuid',
            'consent'=>'accepted','company_website'=>'nullable|string|max:0',
        ]);
        $property = $link->type === Link::TYPE_REAL_ESTATE;
        DB::transaction(function () use ($catalog,$data,$property) {
            $entry = $catalog->entries()->where('is_active',true)->lockForUpdate()->findOrFail($data['entry_id']);
            abort_unless($entry->acceptsInquiries($property),422,'This listing is not accepting requests.');
            $batchName = null;
            if (!$property && isset($data['batch'])) {
                $batch = collect($entry->details['batches'] ?? [])->firstWhere('id',$data['batch']);
                abort_unless($batch && ($batch['status'] ?? '') === 'open' && (!isset($batch['seats']) || (int)$batch['seats'] > 0),422,'This batch is not accepting inquiries.');
                $batchName = $batch['name'].(!empty($batch['start_date']) ? ' ('.$batch['start_date'].')' : '');
            }
            $catalog->inquiries()->create([
                'entry_id'=>$entry->id,'entry_title'=>$entry->title,'name'=>$data['name'],'email'=>$data['email'],
                'phone'=>$data['phone'] ?? null,'message'=>$data['message'] ?? null,
                'preferred_date'=>$property ? ($data['preferred_date'] ?? null) : null,'batch'=>$batchName,
            ]);
        });
        return redirect($link->getShortUrl().'#listing-'.$data['entry_id'])->with('catalog_success','Your request has been sent. The provider will contact you; this is not a confirmed booking or enrollment.');
    }
}
