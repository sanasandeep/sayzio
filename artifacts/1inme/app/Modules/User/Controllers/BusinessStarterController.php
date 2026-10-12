<?php
namespace App\Modules\User\Controllers;

use App\Modules\User\Models\{Link, ServiceBooking, RestaurantMenu};
use App\Modules\User\Services\BusinessStarterCatalog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class BusinessStarterController extends Controller
{
    public function store(Request $request, Link $link)
    {
        abort_unless((int) $link->user_id === (int) workspace_owner_id(), 403);
        $data = $request->validate(['starter'=>'required|string|max:32']);
        $starter = collect(BusinessStarterCatalog::forType($link->type))->firstWhere('key', $data['starter']);
        abort_unless($starter, 422, 'Choose a starter for this business type.');
        $route = DB::transaction(function () use ($link, $starter) {
            // Serialize repeated imports for this link. Existing content is never replaced.
            Link::whereKey($link->id)->lockForUpdate()->firstOrFail();
            if ($link->isAppointmentPage()) {
                $config = ServiceBooking::firstOrCreate(['link_id'=>$link->id], ['user_id'=>$link->user_id,'mode'=>'booking','currency'=>'USD','timezone'=>\App\Support\PlatformTimezone::platformDefault()]);
                $relation = 'services'; $route = 'user.links.service-booking.editor';
            } elseif ($link->type === Link::TYPE_RESTAURANT_MENU) {
                $config = RestaurantMenu::firstOrCreate(['link_id'=>$link->id], ['user_id'=>$link->user_id,'mode'=>'display','currency'=>'USD']);
                $relation = 'items'; $route = 'user.links.restaurant.editor';
            } elseif ($link->type === Link::TYPE_CONTACT_DIRECTORY) {
                $config = $link->contactDirectory()->firstOrCreate([], ['settings'=>[]]);
                $relation = 'contacts'; $route = 'user.links.directory.editor';
            } else {
                abort_unless($link->isListingCatalog(), 404);
                $config = $link->listingCatalog()->firstOrCreate([], ['settings'=>[]]);
                $relation = 'entries'; $route = 'user.links.catalog.editor';
            }
            foreach ($starter['items'] as $i=>$item) {
                $category = $config->categories()->firstOrCreate(['name'=>$item['category']], ['sort_order'=>$config->categories()->count()]);
                $values = ['is_active'=>false,'sort_order'=>$config->{$relation}()->count()];
                if ($relation === 'services' || $relation === 'items') {
                    $values += ['description'=>$item['description'],'price'=>$item['price'],'currency'=>$config->currency];
                    if ($relation === 'services') $values['duration_minutes'] = $item['duration'];
                } else {
                    $details = ['description'=>$item['description']];
                    if ($relation === 'entries') {
                        $details += $link->type === Link::TYPE_REAL_ESTATE
                            ? ['status'=>'available','purpose'=>'sale','property_type'=>'apartment','area_unit'=>'sq_ft']
                            : ['status'=>'coming_soon','mode'=>'online','level'=>'beginner','price'=>$item['price'],'batches'=>[]];
                    }
                    $values['details'] = $details;
                }
                $config->{$relation}()->firstOrCreate(['category_id'=>$category->id, $relation === 'entries' ? 'title' : 'name'=>$item['name']], $values);
            }
            return $route;
        });
        return redirect()->route($route, $link)->with('success', 'Starter added as drafts. Review prices and details, then publish each item.');
    }
}
