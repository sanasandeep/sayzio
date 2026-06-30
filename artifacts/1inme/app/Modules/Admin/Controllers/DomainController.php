<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Models\AccountBadge;
use App\Modules\Admin\Models\Plan;
use App\Modules\User\Models\Domain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DomainController extends Controller
{
    /**
     * Per-domain branding upload slots, mirroring BrandingController:
     * column => [form field, allowed mimes, max KB].
     */
    private const LOGO_SLOTS = [
        'brand_logo_light_url' => ['logo_light', ['png', 'jpg', 'jpeg', 'webp', 'svg'], 4096],
        'brand_logo_dark_url'  => ['logo_dark',  ['png', 'jpg', 'jpeg', 'webp', 'svg'], 4096],
        'brand_icon_url'       => ['icon',       ['png', 'jpg', 'jpeg', 'webp', 'ico'], 1024],
    ];

    public function index()
    {
        $domains = Domain::whereNull('user_id')
            ->with(['plans', 'badges'])
            ->orderBy('domain')
            ->get();
        $userDomains = Domain::whereNotNull('user_id')
            ->with(['user', 'plans'])
            ->orderBy('domain')
            ->get();
        $plans  = Plan::ordered()->get();
        $badges = AccountBadge::orderBy('name')->get();

        return view('admin.domains.index', compact('domains', 'userDomains', 'plans', 'badges'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain'      => 'required|string|max:191|unique:domains,domain',
            'cname_target' => 'nullable|string|max:191',
            'plan_ids'    => 'nullable|array',
            'plan_ids.*'  => 'exists:plans,id',
            'badge_ids'   => 'nullable|array',
            'badge_ids.*' => 'exists:account_badges,id',
            'is_active'   => 'nullable|boolean',
            'relationship_blurb' => 'nullable|string|max:500',
        ] + $this->logoUploadRules());

        // Global domains start UNVERIFIED. Admins must point a CNAME (or
        // configure DNS to terminate at this app's edge) and then click
        // Verify so we don't accidentally route traffic through a host
        // that hasn't been provisioned yet.
        $domain = Domain::create([
            'user_id'            => null,
            'domain'             => strtolower($data['domain']),
            'cname_target'       => $data['cname_target'] ?? null,
            'is_active'          => $request->boolean('is_active', true),
            'is_verified'        => false,
            'verified_at'        => null,
            'verification_token' => Str::random(32),
            'type'               => 'redirect',
            'relationship_blurb' => $data['relationship_blurb'] ?? null,
        ]);

        $domain->plans()->sync($data['plan_ids'] ?? []);
        $domain->badges()->sync($data['badge_ids'] ?? []);
        $this->storeLogoUploads($request, $domain);

        return redirect()->route('admin.domains.index')
            ->with('success', "Global domain {$domain->domain} added.");
    }

    public function update(Request $request, Domain $domain)
    {
        abort_unless($domain->isGlobal(), 403);

        $data = $request->validate([
            'cname_target' => 'nullable|string|max:191',
            'plan_ids'     => 'nullable|array',
            'plan_ids.*'   => 'exists:plans,id',
            'badge_ids'    => 'nullable|array',
            'badge_ids.*'  => 'exists:account_badges,id',
            'is_active'    => 'nullable|boolean',
            'relationship_blurb' => 'nullable|string|max:500',
        ] + $this->logoUploadRules());

        $domain->update([
            'cname_target'       => $data['cname_target'] ?? null,
            'is_active'          => $request->boolean('is_active'),
            'relationship_blurb' => $data['relationship_blurb'] ?? null,
        ]);
        $domain->plans()->sync($data['plan_ids'] ?? []);
        $domain->badges()->sync($data['badge_ids'] ?? []);
        $this->storeLogoUploads($request, $domain);

        return back()->with('success', 'Domain updated.');
    }

    /** Validation rules for the three optional per-domain logo uploads. */
    private function logoUploadRules(): array
    {
        $rules = [];
        foreach (self::LOGO_SLOTS as [$field, $mimes, $max]) {
            $rules[$field] = ['nullable', 'file', 'mimes:' . implode(',', $mimes), 'max:' . $max];
        }
        return $rules;
    }

    /**
     * Move any uploaded per-domain logos into public/branding/domains/{id}
     * and persist their public URLs onto the domain row. Slots without a
     * new upload are left untouched.
     */
    private function storeLogoUploads(Request $request, Domain $domain): void
    {
        $relDir = 'branding/domains/' . $domain->id;
        $publicDir = public_path($relDir);
        $updates = [];

        foreach (self::LOGO_SLOTS as $column => [$field, , ]) {
            if (!$request->hasFile($field)) {
                continue;
            }
            if (!File::isDirectory($publicDir)) {
                File::makeDirectory($publicDir, 0755, true);
            }
            $file = $request->file($field);
            $ext = strtolower($file->getClientOriginalExtension());
            $name = $field . '-' . time() . '.' . $ext;
            $file->move($publicDir, $name);
            $updates[$column] = '/' . $relDir . '/' . $name;
        }

        if (!empty($updates)) {
            $domain->update($updates);
        }
    }

    public function verify(Request $request, Domain $domain)
    {
        abort_unless($domain->isGlobal(), 403);

        $expected = $domain->cname_target;
        $matched  = false;
        if ($expected) {
            $records = @dns_get_record($domain->domain, DNS_CNAME);
            if (is_array($records)) {
                foreach ($records as $r) {
                    if (!empty($r['target']) && rtrim(strtolower($r['target']), '.') === strtolower($expected)) {
                        $matched = true;
                        break;
                    }
                }
            }
        }

        // Admins can also force-verify (e.g. when the host is fronted by
        // an LB that doesn't expose CNAMEs). The form posts ?force=1 from
        // a clearly-labeled secondary button.
        if (!$matched && $request->boolean('force')) {
            $matched = true;
        }

        if (!$matched) {
            return back()->with('error', "CNAME for {$domain->domain} does not point at {$expected} yet.");
        }

        $domain->update(['is_verified' => true, 'verified_at' => now()]);
        return back()->with('success', "Domain {$domain->domain} verified.");
    }

    public function makePrimary(Domain $domain)
    {
        abort_unless($domain->isGlobal(), 403);

        $domain->makePrimary();

        return back()->with('success', "{$domain->domain} is now the primary global domain.");
    }

    public function destroy(Domain $domain)
    {
        abort_unless($domain->isGlobal(), 403);
        $domain->delete();
        return back()->with('success', 'Domain removed.');
    }
}
