<?php
namespace App\Modules\User\Controllers;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\ContactDirectory;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ContactDirectoryController extends Controller
{
    private function directory(Link $link): ContactDirectory
    {
        abort_unless($link->user_id === workspace_owner_id(), 403);
        abort_unless($link->type === Link::TYPE_CONTACT_DIRECTORY, 404);
        return $link->contactDirectory()->firstOrCreate([], ['settings' => []]);
    }

    public function editor(Link $link)
    {
        $directory = $this->directory($link)->load(['categories', 'contacts']);
        return view('user.links.directory.editor', compact('link', 'directory'));
    }

    private function contactData(Request $request, ContactDirectory $directory): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'category_id' => ['nullable', 'integer', Rule::exists('directory_categories', 'id')->where('directory_id', $directory->id)],
            'is_active' => 'boolean', 'is_pinned' => 'boolean', 'sort_order' => 'required|integer|min:0|max:100000',
            'details' => 'nullable|array:role,description,phone,extension,alternate_phone,whatsapp,email,website,support_url,appointment_url,photo_url,location,message,opens,closes,days,holidays,closed_note,icon',
            'details.icon' => 'nullable|in:address-book,bell,building,headset,shield,briefcase,utensils,taxi',
            'details.role' => 'nullable|string|max:150', 'details.description' => 'nullable|string|max:1000',
            'details.phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]+$/'],
            'details.extension' => 'nullable|digits_between:1,10',
            'details.alternate_phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]+$/'],
            'details.whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]+$/'],
            'details.email' => 'nullable|email|max:255',
            'details.website' => 'nullable|url:http,https|max:2048', 'details.support_url' => 'nullable|url:http,https|max:2048',
            'details.appointment_url' => 'nullable|url:http,https|max:2048', 'details.photo_url' => 'nullable|url:http,https|max:2048',
            'details.location' => 'nullable|string|max:300', 'details.message' => 'nullable|string|max:1000',
            'details.opens' => 'nullable|date_format:H:i', 'details.closes' => 'nullable|date_format:H:i',
            'details.days' => ['nullable', 'string', 'regex:/^[0-6](,[0-6])*$/'],
            'details.holidays' => ['nullable', 'string', 'max:1000', 'regex:/^(\d{4}-\d{2}-\d{2})(,\s*\d{4}-\d{2}-\d{2})*$/'],
            'details.closed_note' => 'nullable|string|max:300',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_pinned'] = $request->boolean('is_pinned');
        return $data;
    }

    public function save(Request $request, Link $link, string $action)
    {
        $directory = $this->directory($link);
        if ($action === 'settings') {
            $settings = $request->validate([
                'intro' => 'nullable|string|max:1000', 'timezone' => 'required|timezone',
                'layout' => 'required|in:list,grid,compact',
                'background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'surface' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
                'font' => 'required|in:sans-serif,serif,monospace',
                'reference_label' => 'nullable|string|max:80', 'emergency_note' => 'nullable|string|max:500',
            ]);
            $directory->update(['settings' => $settings]);
        } elseif ($action === 'category') {
            $data = $request->validate(['name' => 'required|string|max:150', 'sort_order' => 'required|integer|min:0|max:100000', 'parent_id' => ['nullable', 'integer', Rule::exists('directory_categories', 'id')->where('directory_id', $directory->id)->whereNull('parent_id')]]);
            $category = $request->filled('id') ? $directory->categories()->findOrFail($request->input('id')) : $directory->categories()->make();
            abort_if($category->exists && (int) ($data['parent_id'] ?? 0) === $category->id, 422, 'A category cannot be its own parent.');
            abort_if($category->exists && !empty($data['parent_id']) && $directory->categories()->where('parent_id', $category->id)->exists(), 422, 'A category with subcategories must remain at the top level.');
            $category->fill($data)->save();
        } elseif ($action === 'contact') {
            $data = $this->contactData($request, $directory);
            $contact = $request->filled('id') ? $directory->contacts()->findOrFail($request->input('id')) : $directory->contacts()->make();
            $contact->fill($data)->save();
        } elseif ($action === 'delete-contact') {
            $directory->contacts()->findOrFail($request->input('id'))->delete();
        } elseif ($action === 'delete-category') {
            $directory->categories()->findOrFail($request->input('id'))->delete();
        } elseif ($action === 'starter') {
            $request->validate(['starter' => 'required|in:hotel,office,company']);
            $names = match ($request->input('starter')) {
                'hotel' => ['Reception', 'Housekeeping', 'Room service', 'Transport'],
                'office' => ['Front desk', 'IT help desk', 'Facilities', 'Security'],
                default => ['Customer support', 'Sales', 'Accounts', 'Human resources'],
            };
            DB::transaction(function () use ($directory, $names) {
                foreach ($names as $i => $name) {
                    $category = $directory->categories()->firstOrCreate(['name' => $name], ['sort_order' => $i]);
                    $directory->contacts()->firstOrCreate(['name' => $name, 'category_id' => $category->id], ['details' => ['description' => 'Add the department contact details before publishing.'], 'is_active' => false, 'sort_order' => $i]);
                }
            });
        } elseif ($action === 'import') {
            $request->validate(['file' => 'required|file|max:1024']);
            $handle = fopen($request->file('file')->getRealPath(), 'r');
            $columns = fgetcsv($handle);
            abort_unless($columns === ['name', 'category', 'phone', 'email', 'role', 'location'], 422, 'Use the six-column CSV format shown in the editor.');
            $rows = []; $seen = [];
            while (($row = fgetcsv($handle)) !== false) {
                abort_if(count($rows) >= 500 || count($row) !== 6, 422, 'Maximum 500 rows, each with six columns.');
                $row = array_map(fn ($value) => str_starts_with($value, "'") && preg_match('/^[=+@-]/', substr($value, 1)) ? substr($value, 1) : $value, $row);
                $v = validator(array_combine($columns, $row), [
                    'name' => 'required|string|max:150', 'category' => 'nullable|string|max:150',
                    'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]+$/'],
                    'email' => 'nullable|email|max:255', 'role' => 'nullable|string|max:150', 'location' => 'nullable|string|max:300',
                ])->validate();
                $key = mb_strtolower(trim($v['name']).'|'.trim($v['category']));
                if (!isset($seen[$key])) { $rows[] = $v; $seen[$key] = true; }
            }
            fclose($handle);
            DB::transaction(function () use ($directory, $rows) {
                foreach ($rows as $i => $row) {
                    $category = $row['category'] ? $directory->categories()->firstOrCreate(['name' => $row['category']]) : null;
                    $directory->contacts()->firstOrCreate(['name' => $row['name'], 'category_id' => $category?->id], [
                        'details' => array_intersect_key($row, array_flip(['phone', 'email', 'role', 'location'])),
                        'is_active' => false, 'sort_order' => $i,
                    ]);
                }
            });
        }
        return redirect()->route('user.links.directory.editor', $link)->with('success', 'Directory updated. Imported and starter contacts remain drafts until you publish them.');
    }

    public function export(Link $link)
    {
        $directory = $this->directory($link)->load(['categories', 'contacts']);
        return response()->streamDownload(function () use ($directory) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'category', 'phone', 'email', 'role', 'location']);
            foreach ($directory->contacts as $contact) {
                $d = $contact->details ?? [];
                $row = [$contact->name, $directory->categories->firstWhere('id', $contact->category_id)?->name ?? '', $d['phone'] ?? '', $d['email'] ?? '', $d['role'] ?? '', $d['location'] ?? ''];
                fputcsv($out, array_map(fn ($v) => preg_match('/^[=+@-]/', $v) ? "'".$v : $v, $row));
            }
            fclose($out);
        }, 'contacts.csv', ['Content-Type' => 'text/csv']);
    }
}
