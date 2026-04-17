<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\VcfData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VcfLinkController extends Controller
{
    /**
     * vCard 3.0 fields a contact can hold. Used by both the create and
     * edit forms to keep the available label dropdowns in sync.
     */
    private const EMAIL_LABELS  = ['Personal', 'Work', 'Other'];
    private const PHONE_LABELS  = ['Mobile', 'Work', 'Home', 'Main', 'Fax', 'Work Fax', 'Home Fax', 'Other'];
    private const URL_LABELS    = ['Website', 'Work', 'Personal', 'Blog', 'Portfolio', 'Other'];
    private const ADDR_LABELS   = ['Home', 'Work', 'Other'];
    private const SOCIAL_SERVICES = [
        'Twitter', 'X', 'LinkedIn', 'Facebook', 'Instagram', 'GitHub', 'YouTube', 'TikTok',
        'Threads', 'Mastodon', 'Bluesky', 'Pinterest', 'Snapchat', 'Reddit', 'Twitch', 'Discord',
        'WhatsApp', 'Telegram', 'Signal', 'Skype', 'Other',
    ];

    public function create(Request $request)
    {
        $projects = $request->user()->projects()->orderBy('name')->get();
        $prefillAlias = (string) $request->query('alias', '');
        $aliasLimits  = $request->user()->getAliasLengthLimits();

        return view('user.links.create-vcf', $this->formContext() + compact('projects', 'prefillAlias', 'aliasLimits'));
    }

    public function edit(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($link->type !== 'vcf', 404);

        $projects = $request->user()->projects()->orderBy('name')->get();
        $aliasLimits = $request->user()->getAliasLengthLimits();
        $vcf = $link->vcfData ?: new VcfData();

        return view('user.links.edit-vcf', $this->formContext() + compact('link', 'vcf', 'projects', 'aliasLimits'));
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $link = Link::create([
            'user_id'    => $request->user()->id,
            'type'       => 'vcf',
            'alias'      => $validated['alias'] ?: Link::generateAlias(),
            'title'      => $this->buildLinkTitle($validated),
            'project_id' => $validated['project_id'] ?? null,
            'is_active'  => true,
            'settings'   => $request->boolean('show_preview_page') ? ['show_preview_page' => true] : null,
        ]);

        $payload = $this->buildVcfPayload($request, $validated, null);
        $payload['link_id'] = $link->id;
        VcfData::create($payload);

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Digital Card created successfully.');
    }

    public function update(Request $request, Link $link)
    {
        abort_if($link->user_id !== $request->user()->id, 403);
        abort_if($link->type !== 'vcf', 404);

        $validated = $this->validatePayload($request, $link);

        $link->update([
            'alias'      => $validated['alias'] ?: $link->alias,
            'title'      => $this->buildLinkTitle($validated),
            'project_id' => $validated['project_id'] ?? null,
            'settings'   => array_merge((array) $link->settings, [
                'show_preview_page' => $request->boolean('show_preview_page'),
            ]),
        ]);

        $vcf = $link->vcfData ?: new VcfData(['link_id' => $link->id]);
        $payload = $this->buildVcfPayload($request, $validated, $vcf);
        $payload['link_id'] = $link->id;

        if ($vcf->exists) {
            $vcf->update($payload);
        } else {
            VcfData::create($payload);
        }

        return redirect()->route('user.links.show', $link)
            ->with('success', 'Digital Card updated.');
    }

    // ---- Helpers ----------------------------------------------------------

    private function formContext(): array
    {
        return [
            'emailLabels'    => self::EMAIL_LABELS,
            'phoneLabels'    => self::PHONE_LABELS,
            'urlLabels'      => self::URL_LABELS,
            'addrLabels'     => self::ADDR_LABELS,
            'socialServices' => self::SOCIAL_SERVICES,
        ];
    }

    private function buildLinkTitle(array $v): string
    {
        $name = trim(implode(' ', array_filter([
            $v['prefix'] ?? null, $v['first_name'] ?? null, $v['middle_name'] ?? null,
            $v['last_name'] ?? null, $v['suffix'] ?? null,
        ])));
        return $name ?: ($v['organization'] ?? 'Digital Card');
    }

    private function validatePayload(Request $request, ?Link $link = null): array
    {
        $aliasLimits = $request->user()->getAliasLengthLimits();
        $aliasUnique = 'unique:links,alias' . ($link ? ',' . $link->id : '');

        return $request->validate([
            'alias' => array_merge(
                ['nullable', 'string', 'alpha_dash', $aliasUnique],
                ['min:' . $aliasLimits['min']],
                ['max:' . $aliasLimits['max']],
            ),
            'project_id' => ['nullable', 'exists:projects,id', function ($attribute, $value, $fail) use ($request) {
                if ($value && !\App\Modules\User\Models\Project::where('id', $value)->where('user_id', $request->user()->id)->exists()) {
                    $fail('The selected project does not belong to you.');
                }
            }],

            'prefix'        => 'nullable|string|max:50',
            'first_name'    => 'required|string|max:255',
            'middle_name'   => 'nullable|string|max:100',
            'last_name'     => 'nullable|string|max:255',
            'suffix'        => 'nullable|string|max:50',
            'nickname'      => 'nullable|string|max:100',

            'photo'         => 'nullable|image|max:5120',
            'remove_photo'  => 'nullable|boolean',

            'organization'  => 'nullable|string|max:255',
            'department'    => 'nullable|string|max:255',
            'title'         => 'nullable|string|max:255',
            'role'          => 'nullable|string|max:255',
            'birthday'      => 'nullable|date',
            'anniversary'   => 'nullable|date',

            'emails'                    => 'nullable|array|max:10',
            'emails.*.label'            => 'nullable|string|max:50',
            'emails.*.value'            => 'nullable|email|max:255',

            'phones'                    => 'nullable|array|max:10',
            'phones.*.label'            => 'nullable|string|max:50',
            'phones.*.value'            => 'nullable|string|max:50',

            'urls'                      => 'nullable|array|max:10',
            'urls.*.label'              => 'nullable|string|max:50',
            'urls.*.value'              => 'nullable|url|max:2048',

            'addresses'                 => 'nullable|array|max:5',
            'addresses.*.label'         => 'nullable|string|max:50',
            'addresses.*.street'        => 'nullable|string|max:500',
            'addresses.*.city'          => 'nullable|string|max:255',
            'addresses.*.state'         => 'nullable|string|max:255',
            'addresses.*.zip'           => 'nullable|string|max:20',
            'addresses.*.country'       => 'nullable|string|max:255',

            'social_profiles'                 => 'nullable|array|max:15',
            'social_profiles.*.service'       => 'nullable|string|max:50',
            'social_profiles.*.value'         => 'nullable|string|max:500',

            'note' => 'nullable|string|max:2000',
        ]);
    }

    /**
     * Compose the column payload for VcfData. Handles photo upload /
     * removal and strips empty rows from each multi-value array so the
     * stored JSON stays compact.
     */
    private function buildVcfPayload(Request $request, array $v, ?VcfData $existing): array
    {
        $payload = [
            'prefix'       => $v['prefix']       ?? null,
            'first_name'   => $v['first_name'],
            'middle_name'  => $v['middle_name']  ?? null,
            'last_name'    => $v['last_name']    ?? null,
            'suffix'       => $v['suffix']       ?? null,
            'nickname'     => $v['nickname']     ?? null,
            'organization' => $v['organization'] ?? null,
            'department'   => $v['department']   ?? null,
            'title'        => $v['title']        ?? null,
            'role'         => $v['role']         ?? null,
            'birthday'     => $v['birthday']     ?? null,
            'anniversary'  => $v['anniversary']  ?? null,
            'note'         => $v['note']         ?? null,

            'emails'          => $this->compactRows($v['emails']          ?? [], ['value']),
            'phones'          => $this->compactRows($v['phones']          ?? [], ['value']),
            'urls'            => $this->compactRows($v['urls']            ?? [], ['value']),
            'addresses'       => $this->compactRows($v['addresses']       ?? [], ['street','city','state','zip','country']),
            'social_profiles' => $this->compactRows($v['social_profiles'] ?? [], ['value']),
        ];

        // Mirror the first email/phone/url/address into the legacy
        // single-value columns so old code paths (preview snippet,
        // analytics search, etc.) keep working without changes.
        $payload['email']      = $payload['emails'][0]['value'] ?? null;
        $payload['phone']      = $payload['phones'][0]['value'] ?? null;
        $payload['phone_work'] = collect($payload['phones'])->firstWhere('label', 'Work')['value'] ?? null;
        $payload['website']    = $payload['urls'][0]['value']   ?? null;
        $firstAddr             = $payload['addresses'][0] ?? [];
        $payload['street']     = $firstAddr['street']  ?? null;
        $payload['city']       = $firstAddr['city']    ?? null;
        $payload['state']      = $firstAddr['state']   ?? null;
        $payload['zip']        = $firstAddr['zip']     ?? null;
        $payload['country']    = $firstAddr['country'] ?? null;

        // Photo: upload a new one, remove the existing one, or keep as-is.
        if ($request->boolean('remove_photo') && $existing && $existing->photo_path) {
            Storage::disk('public')->delete($existing->photo_path);
            $payload['photo_path'] = null;
        } elseif ($request->hasFile('photo')) {
            if ($existing && $existing->photo_path) {
                Storage::disk('public')->delete($existing->photo_path);
            }
            $payload['photo_path'] = $request->file('photo')->store('vcf-photos', 'public');
        }

        return $payload;
    }

    /**
     * Drop array rows where every requested key is empty. Re-indexes the
     * surviving rows so the JSON stays a clean ordered list.
     */
    private function compactRows(array $rows, array $requiredAnyOf): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            foreach ($requiredAnyOf as $k) {
                if (!empty($row[$k])) { $out[] = $row; continue 2; }
            }
        }
        return $out;
    }
}
