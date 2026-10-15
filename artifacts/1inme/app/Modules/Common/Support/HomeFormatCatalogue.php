<?php

namespace App\Modules\Common\Support;

use App\Modules\User\Support\LinkTypeCategories;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Public creation formats come from the same enabled catalogue as Create Link. */
class HomeFormatCatalogue
{
    public static function items(array $marketing = []): array
    {
        $copy = collect($marketing)->keyBy(fn ($item) => Str::slug($item['name'] ?? ''));
        $rows = [];
        foreach (LinkTypeCategories::categories() as $category) {
            foreach ($category['types'] as $type) {
                $rows[] = $type;
            }
        }
        $rows[] = ['value'=>'qr','label'=>'QR Code','desc'=>'Create a customizable QR code and share its destination.','icon'=>'fa-qrcode','tint'=>'#0891b2'];
        $rows[] = ['value'=>'form','label'=>'Forms','desc'=>'Collect enquiries and responses through a standalone form.','icon'=>'fa-list-check','tint'=>'#ca8a04'];
        try {
            $demos = Cache::remember(\App\Modules\Common\Controllers\SitePageController::DEMOS_CACHE_KEY,300,fn()=>\App\Modules\Common\Controllers\SitePageController::buildDemosData());
            $demoLinks = (array)($demos['links'] ?? []);
        } catch (\Throwable $e) { $demoLinks = []; }

        return array_map(function ($type) use ($copy,$demoLinks) {
            $id=$type['value']; $slug=Str::slug($type['label']); $custom=$copy->get($slug,[]);
            $group=match($id) {
                'url','file','text','qr'=>'share',
                'restaurant_menu','store_menu','service_booking','salon_spa','calendar','ics','paid_page'=>'sell',
                'form','vcf','reviews','ai_chat','conversational','contact_directory'=>'connect',
                default=>'pages',
            };
            $art=match($id) {'url'=>'link','file'=>'file','qr'=>'qr','form'=>'form','calendar','ics','service_booking','salon_spa'=>'calendar','restaurant_menu','store_menu','paid_page'=>'store','ai_chat','conversational'=>'chat',default=>'page'};
            $color=$custom['color'] ?? $type['tint']; if(!preg_match('/^#[0-9a-f]{6}$/i',$color))$color='#5865ef';
            $create=match($id){'qr'=>route('user.qr-codes.create'),'form'=>route('user.forms.create'),default=>route('user.links.create',['type'=>$id])};
            return ['id'=>$id,'name'=>$type['label'],'slug'=>$slug,'desc'=>$custom['desc']??$type['desc'],'color'=>$color,'group'=>$group,'art'=>$art,'create'=>$create,'usage'=>LinkTypeUsage::forName($type['label']), 'demo'=>isset($demoLinks['demo-type-'.$slug]) ? url('/demo-type-'.$slug) : null];
        },$rows);
    }
}
