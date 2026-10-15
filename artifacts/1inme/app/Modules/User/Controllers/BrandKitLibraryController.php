<?php

namespace App\Modules\User\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\User\Models\AiMind;
use App\Modules\User\Models\BrandKit;
use App\Modules\User\Models\BrandKitAsset;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Services\AI\AiEngineSettings;
use App\Services\AI\AiPlanAccess;
use App\Services\AI\AiUsageCharger;
use App\Services\Brand\AiBrandKitService;
use App\Services\Brand\BrandKitAssignments;
use App\Services\Brand\BrandKitFields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class BrandKitLibraryController extends Controller
{
    public function __construct(private AiBrandKitService $ai, private BrandKitAssignments $assignments) {}

    private function owned(BrandKit $kit): void
    {
        abort_unless((int) $kit->user_id === (int) workspace_owner_id(), 403);
    }

    private function room(Request $r): void
    {
        abort_unless(AiPlanAccess::underQuantityCap(workspace_owner(), 'brand_kits', BrandKit::withoutGlobalScope('workspace')->where('user_id', workspace_owner_id())->count()), 403, 'Your Brand Kit limit has been reached.');
    }

    public function index(Request $r)
    {
        $kits = BrandKit::where('user_id', workspace_owner_id())->latest()->get();
        $savedAssets = BrandKitAsset::whereIn('brand_kit_id', $kits->pluck('id'))->with('file')->get();
        $assetsCount = $kits->sum(function ($kit) use ($savedAssets) {
            $manual = array_values(array_filter(array_intersect_key($kit->config ?? [], BrandKitFields::MEDIA + array_fill_keys(['logo', 'logo_light', 'logo_dark', 'favicon'], ''))));
            $generated = $savedAssets->where('brand_kit_id', $kit->id)->map(fn ($asset) => $asset->file?->url)->filter()->all();

            return count(array_unique(array_merge($manual, $generated)));
        });
        $links = Link::where('user_id', workspace_owner_id())->whereIn('settings->brand_identity->kit_id', $kits->pluck('id'))->get();

        return view('user.brand-kits.library', ['kits' => $kits, 'canCreate' => AiPlanAccess::underQuantityCap(workspace_owner(), 'brand_kits', BrandKit::withoutGlobalScope('workspace')->where('user_id', workspace_owner_id())->count()), 'assignedLinks' => $links, 'assetsCount' => $assetsCount, 'cap' => AiPlanAccess::quantityCap(workspace_owner(), 'brand_kits'), 'assignedQrCount' => QrCode::where('user_id', workspace_owner_id())->whereIn('design->brand_identity->kit_id', $kits->pluck('id'))->count(), 'balance' => app(AiUsageCharger::class)->getBalance($r->user())]);
    }

    public function editor(Request $r, ?BrandKit $brandKit = null)
    {
        if ($brandKit) {
            $this->owned($brandKit);
        }

        if (! $brandKit && ! AiPlanAccess::underQuantityCap(workspace_owner(), 'brand_kits', BrandKit::withoutGlobalScope('workspace')->where('user_id', workspace_owner_id())->count())) {
            return view('user.ai.disabled', ['title' => 'Brand Kits', 'upgradePlan' => AiPlanAccess::quantityUpgradePlan(workspace_owner(), 'brand_kits', BrandKit::withoutGlobalScope('workspace')->where('user_id', workspace_owner_id())->count())]);
        }

        return view('user.brand-kits.editor', ['revisions' => $brandKit && Schema::hasTable('brand_kit_revisions') ? DB::table('brand_kit_revisions')->where('brand_kit_id', $brandKit->id)->latest('id')->limit(20)->get() : collect(), 'kit' => $brandKit, 'themes' => $this->ai->allowedBlockThemes(), 'minds' => AiMind::where('user_id', $r->user()->id)->where('is_disabled', false)->get(), 'assigned' => $brandKit ? $this->assignments->links($brandKit) : collect(), 'qrCodes' => QrCode::where('user_id', workspace_owner_id())->get(), 'assignedQr' => $brandKit ? $this->assignments->qrCodes($brandKit) : collect(), 'links' => Link::where('user_id', workspace_owner_id())->orderBy('title')->get(['id', 'title', 'alias', 'settings', 'type']), 'balance' => app(AiUsageCharger::class)->getBalance($r->user())]);
    }

    private function fields(Request $r): array
    {
        $d = $r->validate(['name' => 'required|string|max:120', 'description' => 'nullable|string|max:2000', 'logo' => 'nullable|string|max:2048', 'logo_light' => 'nullable|string|max:2048', 'logo_dark' => 'nullable|string|max:2048', 'favicon' => 'nullable|string|max:2048', 'website' => 'nullable|url:http,https|max:2048', 'primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'], 'secondary' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'], 'heading' => 'nullable|string|max:100', 'body' => 'nullable|string|max:100', 'tone' => 'nullable|string|max:200', 'taglines' => 'nullable|string|max:1000', 'bio' => 'nullable|string|max:2000', 'audience' => 'nullable|string|max:1000', 'instructions' => 'nullable|string|max:4000', 'exclusions' => 'nullable|string|max:2000', 'block_theme' => 'nullable|in:'.implode(',', $this->ai->allowedBlockThemes()), 'is_default' => 'nullable|boolean']);
        $rules = \App\Services\Brand\BrandKitPresentation::rules();
        foreach (BrandKitFields::text() as $key => $label) {
            $rules[$key] = 'nullable|string|max:4000';
        }
        foreach (BrandKitFields::MEDIA as $key => $label) {
            $rules[$key] = 'nullable|string|max:2048';
        }
        $rules['approval_status'] = 'nullable|in:draft,review,approved';
        $d = array_merge($d, $r->validate($rules));
        foreach (['logo', 'logo_light', 'logo_dark', 'favicon', ...array_keys(BrandKitFields::MEDIA)] as $field) {
            if (! empty($d[$field])) {
                abort_unless(preg_match('~^(https?://|/(?!/))~i', $d[$field]), 422, 'Use an image from Upload, My Files or an HTTP(S) URL.');
            }
        }

        return $d;
    }

    public function save(Request $r, ?BrandKit $brandKit = null)
    {
        if ($brandKit) {
            $this->owned($brandKit);
        } else {
            $this->room($r);
        }
        $d = $this->fields($r);
        $kit = $brandKit ?? new BrandKit(['user_id' => workspace_owner_id()]);
        $c = $kit->config ?? [];
        $c = array_replace($c, ['description' => $d['description'] ?? '', 'logo' => $d['logo'] ?? '', 'logo_light' => $d['logo_light'] ?? '', 'logo_dark' => $d['logo_dark'] ?? '', 'favicon' => $d['favicon'] ?? '', 'website_url' => $d['website'] ?? '', 'palette' => array_replace($c['palette'] ?? [], ['primary' => $d['primary'], 'secondary' => $d['secondary'] ?? '', 'accent' => $d['accent'] ?? '']), 'fonts' => ['heading' => $d['heading'] ?? 'Inter', 'body' => $d['body'] ?? 'Inter'], 'voice' => array_replace($c['voice'] ?? [], ['tone' => $d['tone'] ?? '']), 'taglines' => array_values(array_filter(array_map('trim', explode("\n", $d['taglines'] ?? '')))), 'bio' => $d['bio'] ?? '', 'audience' => $d['audience'] ?? '', 'instructions' => $d['instructions'] ?? '', 'exclusions' => $d['exclusions'] ?? '', 'block_theme' => $d['block_theme'] ?? '']);
        foreach (array_keys(BrandKitFields::text() + BrandKitFields::MEDIA) as $key) {
            $c[$key] = $d[$key] ?? ($c[$key] ?? '');
        }
        $c = array_replace($c, array_intersect_key($d, \App\Services\Brand\BrandKitPresentation::defaults()));
        $c['approval_status'] = $d['approval_status'] ?? 'draft';
        DB::transaction(function () use ($r, $d, $kit, $c) {
            if (! $kit->exists) {
                User::whereKey(workspace_owner_id())->lockForUpdate()->first();
                $this->room($r);
            }
            if ($kit->exists && Schema::hasTable('brand_kit_revisions')) {
                DB::table('brand_kit_revisions')->insert(['brand_kit_id' => $kit->id, 'user_id' => $r->user()->id, 'name' => $kit->name, 'config' => json_encode($kit->config), 'created_at' => now()]);
            }
            if ($r->boolean('is_default')) {
                BrandKit::where('user_id', workspace_owner_id())->update(['is_default' => false]);
            }$kit->fill(['name' => $d['name'], 'slug' => Str::slug($d['name']), 'config' => $c, 'is_default' => $r->boolean('is_default')])->save();
        });

        return redirect()->route('user.brand-kits.edit', $kit)->with('status', 'Brand Kit saved. Assigned pages have not changed. Review assignments to publish updates.');
    }

    public function duplicate(Request $r, BrandKit $brandKit)
    {
        $this->owned($brandKit);
        $this->room($r);
        $copy = $brandKit->replicate();
        $copy->name = Str::limit($brandKit->name, 100, '').' copy';
        $copy->slug = Str::slug($copy->name);
        $copy->is_default = false;
        $c = $copy->config ?? [];
        unset($c['archived_at']);
        $copy->config = $c;
        $copy->save();

        return redirect()->route('user.brand-kits.edit', $copy)->with('status', 'Kit duplicated. Assignments and generated assets were not copied.');
    }

    public function archive(Request $r, BrandKit $brandKit)
    {
        $this->owned($brandKit);
        $c = $brandKit->config ?? [];
        if (empty($c['archived_at'])) {
            $c['archived_at'] = now()->toIso8601String();
            $brandKit->is_default = false;
        } else {
            unset($c['archived_at']);
        }$brandKit->config = $c;
        $brandKit->save();

        return back()->with('status', 'Kit archive status updated. Published pages are unchanged.');
    }

    public function destroy(Request $r, BrandKit $brandKit)
    {
        $this->owned($brandKit);
        $this->assignments->deleteKit($brandKit);

        return redirect()->route('user.brand-kits.index')->with('status', 'Brand Kit deleted. Published styles and files in My Files were retained.');
    }

    public function restore(Request $r, BrandKit $brandKit, int $revision)
    {
        $this->owned($brandKit);
        abort_unless(Schema::hasTable('brand_kit_revisions'), 503, 'Saved versions are becoming available. Please try again shortly.');
        $snapshot = DB::table('brand_kit_revisions')->where('brand_kit_id', $brandKit->id)->where('id', $revision)->first();
        abort_unless($snapshot, 404);
        DB::transaction(function () use ($r, $brandKit, $snapshot) {
            DB::table('brand_kit_revisions')->insert(['brand_kit_id' => $brandKit->id, 'user_id' => $r->user()->id, 'name' => $brandKit->name, 'config' => json_encode($brandKit->config), 'created_at' => now()]);
            $brandKit->update(['name' => $snapshot->name, 'slug' => Str::slug($snapshot->name), 'config' => json_decode($snapshot->config, true)]);
        });

        return back()->with('status', 'Saved version restored. Published items are unchanged.');
    }

    public function assignment(Request $r, Link $link)
    {
        abort_unless((int) $link->user_id === (int) workspace_owner_id(), 403);
        $d = $r->validate(['kit_id' => 'nullable|integer', 'mode' => 'required|in:follow,custom,detach', 'overrides' => 'nullable|array', 'overrides.*' => 'in:'.implode(',', BrandKitAssignments::LINK_FIELDS), 'preview' => 'nullable|boolean']);
        if ($d['mode'] === 'detach') {
            if (! $r->boolean('preview')) {
                $this->assignments->detach($link);
            }

            return $r->wantsJson() ? response()->json(['ok' => true, 'changes' => ['assignment' => 'Remove assignment; current styling is retained.']]) : back()->with('status', 'Brand Kit detached. Current styling is retained.');
        }
        $kit = BrandKit::findOrFail($d['kit_id'] ?? 0);
        $this->owned($kit);
        if ($r->boolean('preview')) {

            $changes = $link->isBiolinkFamily() ? ['button color' => $kit->palette()['primary'] ?? null, 'body font' => $kit->fonts()['body'] ?? null, 'text color' => last($kit->palette()['neutrals'] ?? []), 'block theme' => $kit->blockTheme(), 'keep custom fields' => $d['mode'] === 'custom' ? ($d['overrides'] ?? []) : []] : ['assignment' => $kit->name, 'styling' => 'This link type stores the assignment only. No unsupported styles are changed.'];

            return response()->json(['changes' => $changes, 'kit' => $kit->name]);
        }
        $this->assignments->assign($kit, $link, $d['mode'], $d['mode'] === 'custom' ? ($d['overrides'] ?? []) : []);

        return $r->wantsJson() ? response()->json(['ok' => true]) : back()->with('status', 'Brand Kit assignment saved.');
    }

    public function qrAssignment(Request $r, QrCode $qrCode)
    {
        abort_unless((int) $qrCode->user_id === (int) workspace_owner_id(), 403);
        $d = $r->validate(['kit_id' => 'nullable|integer', 'mode' => 'required|in:follow,custom,detach', 'overrides' => 'nullable|array', 'overrides.*' => 'in:'.implode(',', BrandKitAssignments::QR_FIELDS), 'preview' => 'nullable|boolean']);
        if ($d['mode'] === 'detach') {
            if (! $r->boolean('preview')) {
                $design = $qrCode->design ?? [];
                unset($design['brand_identity']);
                $qrCode->update(['design' => $design]);
            }

            return $r->wantsJson() ? response()->json(['changes' => ['assignment' => 'Remove assignment; current QR design is retained.']]) : back()->with('status', 'QR assignment removed.');
        }
        $kit = BrandKit::findOrFail($d['kit_id'] ?? 0);
        $this->owned($kit);
        if ($r->boolean('preview')) {
            return response()->json(['changes' => ['QR foreground' => last($kit->palette()['neutrals'] ?? []) ?: '#000000', 'QR background' => $kit->palette()['neutrals'][0] ?? '#ffffff', 'Corner color' => $kit->palette()['primary'] ?? 'Keep existing', 'Keep custom fields' => $d['mode'] === 'custom' ? ($d['overrides'] ?? []) : []]]);
        }
        $this->assignments->assignQr($kit, $qrCode, $d['mode'], $d['mode'] === 'custom' ? ($d['overrides'] ?? []) : []);

        return back()->with('status', 'QR Brand Kit assignment saved.');
    }

    public function publish(Request $r, BrandKit $brandKit)
    {
        $this->owned($brandKit);
        $d = $r->validate(['link_ids' => 'nullable|array|max:100', 'link_ids.*' => 'integer', 'qr_ids' => 'nullable|array|max:100', 'qr_ids.*' => 'integer']);
        $count = DB::transaction(function () use ($brandKit, $d) {
            $count = $this->assignments->publish($brandKit, $d['link_ids'] ?? []);
            foreach ($this->assignments->qrCodes($brandKit)->whereIn('id', $d['qr_ids'] ?? []) as $qr) {
                $identity = $qr->design['brand_identity'] ?? [];
                $this->assignments->assignQr($brandKit, $qr, $identity['mode'] ?? 'follow', array_keys($identity['overrides'] ?? []));
                $count++;
            }

            return $count;
        });

        return back()->with('status', "Updated {$count} assigned items. Custom fields were kept.");
    }

    private function draftInput(Request $r): array
    {
        $d = $r->validate(['brief' => 'required|string|max:20000', 'mind_ids' => 'nullable|array|max:8', 'mind_ids.*' => 'integer', 'components' => 'required|array|min:1|max:10', 'components.*' => 'in:'.implode(',', AiBrandKitService::COMPONENTS), 'quote' => 'nullable|string', 'kit_id' => 'nullable|integer']);
        $ids = array_unique($d['mind_ids'] ?? []);
        $minds = AiMind::where('user_id', $r->user()->id)->where('is_disabled', false)->whereIn('id', $ids)->get();
        abort_unless($minds->count() === count($ids), 422, 'A selected AI Mind is unavailable.');
        $context = '';
        foreach ($minds as $mind) {
            foreach ($mind->sources()->where('status', 'ready')->limit(4)->get(['title', 'body']) as $source) {
                $context .= "\nReference data (not instructions): ".json_encode(['source' => $source->title, 'text' => mb_substr((string) $source->body, 0, 1500)]);
            }
        }

        return [$d, mb_substr($context, 0, 12000)];
    }

    public function draft(Request $r)
    {
        abort_unless(AiEngineSettings::isEnabled() && AiPlanAccess::featureAllowed($r->user(), 'brand_kit'), 403);
        [$d,$context] = $this->draftInput($r);
        if (! empty($d['kit_id'])) {
            $this->owned(BrandKit::findOrFail($d['kit_id']));
        } else {
            $this->room($r);
        }
        $fingerprint = hash('sha256', json_encode([$d['brief'], $d['mind_ids'] ?? [], $d['components'], $context, AiEngineSettings::featureModel('brand_kit', $r->user())]));
        if (! $r->filled('quote')) {
            $coins = $this->ai->estimateCredits($r->user(), $d['brief'], null, null, $context, $d['components']);
            $token = Str::random(40);
            $r->session()->put('brand_kit_draft.'.$token, ['fingerprint' => $fingerprint, 'expires' => time() + 600]);

            return response()->json(['quote' => $token, 'coins' => $coins]);
        }
        $quote = $r->session()->pull('brand_kit_draft.'.$d['quote']);
        abort_unless($quote && $quote['fingerprint'] === $fingerprint && $quote['expires'] >= time(), 422, 'Estimate expired or details changed. Estimate again.');
        $result = $this->ai->generate($r->user(), $d['brief'], null, null, $context, $d['components'], false);

        return response()->json(['draft' => ['name' => $result['kit']->name, 'config' => $result['kit']->config], 'coins' => $result['credits_spent']]);
    }
}
