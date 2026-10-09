<?php
namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\ListingCatalog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;

class ListingCatalogController extends Controller
{
    private function catalog(Link $link): ListingCatalog
    {
        abort_unless($link->user_id === workspace_owner_id(), 403);
        abort_unless($link->isListingCatalog(), 404);
        return $link->listingCatalog()->firstOrCreate([], ['settings'=>[]]);
    }

    public function editor(Link $link)
    {
        $catalog = $this->catalog($link)->load(['categories','entries']);
        $property = $link->type === Link::TYPE_REAL_ESTATE;
        return view('user.links.catalog.editor', compact('link','catalog','property'));
    }

    public function inquiries(Link $link)
    {
        $catalog = $this->catalog($link);
        $inquiries = $catalog->inquiries()->paginate(30);
        return view('user.links.catalog.inquiries', compact('link','inquiries'));
    }

    public function save(Request $request, Link $link, string $action)
    {
        $catalog = $this->catalog($link);
        $property = $link->type === Link::TYPE_REAL_ESTATE;
        if ($action === 'settings') {
            $data = $request->validate([
                'intro'=>'nullable|string|max:1000', 'business_name'=>'nullable|string|max:150',
                'currency'=>['required','regex:/^[A-Z]{3}$/'], 'timezone'=>'required|timezone',
                'layout'=>'required|in:grid,list', 'font'=>'required|in:sans-serif,serif,monospace',
                'background'=>['required','regex:/^#[0-9a-fA-F]{6}$/'], 'surface'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
                'text_color'=>['required','regex:/^#[0-9a-fA-F]{6}$/'], 'accent'=>['required','regex:/^#[0-9a-fA-F]{6}$/'],
                'hero_url'=>'nullable|url:http,https|max:2048', 'phone'=>['nullable','string','max:30','regex:/^\+?[0-9 ()-]+$/'],
                'email'=>'nullable|email|max:255', 'inquiries_enabled'=>'boolean',
            ]);
            $data['inquiries_enabled'] = $request->boolean('inquiries_enabled');
            $catalog->update(['settings'=>$data]);
        } elseif ($action === 'category') {
            $data = $request->validate(['name'=>'required|string|max:150','sort_order'=>'required|integer|min:0|max:100000']);
            $category = $request->filled('id') ? $catalog->categories()->findOrFail($request->input('id')) : $catalog->categories()->make();
            $category->fill($data)->save();
        } elseif ($action === 'delete-category') {
            $catalog->categories()->findOrFail($request->input('id'))->delete();
        } elseif ($action === 'delete-entry') {
            $catalog->entries()->findOrFail($request->input('id'))->delete();
        } elseif ($action === 'inquiry') {
            $data = $request->validate(['status'=>'required|in:new,contacted,closed']);
            $catalog->inquiries()->findOrFail($request->input('id'))->update($data);
            return redirect()->route('user.links.catalog.inquiries',$link)->with('success','Inquiry status updated.');
        } elseif ($action === 'starter') {
            $names = $property ? ['Apartments','Independent homes','Commercial properties'] : ['Professional skills','Technology','Creative courses'];
            foreach ($names as $i=>$name) $catalog->categories()->firstOrCreate(['name'=>$name],['sort_order'=>$i]);
        } elseif ($action === 'entry') {
            $baseKeys = 'description,price,price_note,photos,location,status,external_url';
            $specificKeys = $property ? 'purpose,property_type,bedrooms,bathrooms,area,area_unit,amenities' : 'mode,level,duration,instructor,instructor_bio,syllabus,prerequisites,certificate,batches';
            $rules = [
                'title'=>'required|string|max:200', 'category_id'=>['nullable','integer',Rule::exists('listing_categories','id')->where('catalog_id',$catalog->id)],
                'sort_order'=>'required|integer|min:0|max:100000','is_active'=>'boolean','is_featured'=>'boolean',
                'details'=>'required|array:'.$baseKeys.','.$specificKeys,
                'details.description'=>'nullable|string|max:5000','details.price'=>'nullable|numeric|min:0|max:999999999999',
                'details.price_note'=>'nullable|string|max:80','details.location'=>'nullable|string|max:300',
                'details.photos'=>'nullable|array|max:8','details.photos.*'=>'nullable|url:http,https|max:2048',
                'details.external_url'=>'nullable|url:http,https|max:2048',
                'details.status'=>'required|in:'.($property ? 'available,reserved,sold,rented' : 'enrolling,full,closed,coming_soon'),
            ];
            if ($property) {
                $rules += [
                    'details.purpose'=>'required|in:sale,rent', 'details.property_type'=>'required|in:apartment,house,land,commercial',
                    'details.bedrooms'=>'nullable|integer|min:0|max:100', 'details.bathrooms'=>'nullable|integer|min:0|max:100',
                    'details.area'=>'nullable|numeric|min:0|max:999999999','details.area_unit'=>'required|in:sq_ft,sq_m,acres',
                    'details.amenities'=>'nullable|string|max:2000',
                ];
            } else {
                $rules += [
                    'details.mode'=>'required|in:online,in_person,hybrid','details.level'=>'required|in:beginner,intermediate,advanced,all_levels',
                    'details.duration'=>'nullable|string|max:150','details.instructor'=>'nullable|string|max:150',
                    'details.instructor_bio'=>'nullable|string|max:2000','details.syllabus'=>'nullable|string|max:5000',
                    'details.prerequisites'=>'nullable|string|max:2000','details.certificate'=>'nullable|string|max:300',
                    'details.batches'=>'nullable|array|max:12',
                    'details.batches.*'=>'array:id,name,start_date,end_date,schedule,seats,status',
                    'details.batches.*.id'=>'nullable|uuid',
                    'details.batches.*.name'=>'nullable|string|max:150','details.batches.*.start_date'=>'nullable|date_format:Y-m-d',
                    'details.batches.*.end_date'=>'nullable|date_format:Y-m-d|after_or_equal:details.batches.*.start_date',
                    'details.batches.*.schedule'=>'nullable|string|max:300','details.batches.*.seats'=>'nullable|integer|min:0|max:100000',
                    'details.batches.*.status'=>'required|in:open,full,closed',
                ];
            }
            $data = $request->validate($rules);
            $data['is_active'] = $request->boolean('is_active');
            $data['is_featured'] = $request->boolean('is_featured');
            $data['details']['photos'] = array_values(array_filter($data['details']['photos'] ?? []));
            if (!$property) {
                $data['details']['batches'] = array_values(array_filter($data['details']['batches'] ?? [],fn($b)=>!empty($b['name'])));
                foreach ($data['details']['batches'] as &$batch) {
                    $batch['id'] = $batch['id'] ?? (string) \Illuminate\Support\Str::uuid();
                    if (!empty($batch['end_date']) && empty($batch['start_date'])) throw \Illuminate\Validation\ValidationException::withMessages(['details.batches'=>'A batch end date needs a start date.']);
                }
            }
            unset($batch);
            $entry = $request->filled('id') ? $catalog->entries()->findOrFail($request->input('id')) : $catalog->entries()->make();
            $entry->fill($data)->save();
        }
        return redirect()->route('user.links.catalog.editor',$link)->with('success','Catalog saved.');
    }
}
