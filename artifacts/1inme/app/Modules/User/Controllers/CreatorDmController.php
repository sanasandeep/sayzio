<?php

namespace App\Modules\User\Controllers;

use App\Modules\User\Models\DmBroadcast;
use App\Modules\User\Models\DmWelcomeRule;
use App\Modules\User\Models\SubscriptionTier;
use App\Modules\User\Models\User;
use App\Services\Dm\DmDispatcher;
use Illuminate\Http\Request;

/**
 * Creator-side dashboard for the Paid DMs feature (Task #1210).
 *
 * Houses three surfaces:
 *   - DM access settings (mode, price, min tier, read receipts).
 *   - Mass-message broadcasts (CRUD + send).
 *   - Welcome-message rules (CRUD).
 *
 * Read receipts and the unified inbox itself live in the existing
 * InboxDirectMessageController / InboxUnifiedController; this is the
 * config + automations surface.
 */
class CreatorDmController
{
    /** GET /user/inbox/dms/settings — DM access mode + price + min tier. */
    public function settings()
    {
        $user  = User::find(workspace_owner_id());
        $tiers = SubscriptionTier::where('user_id', $user->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'sort_order', 'is_free']);

        return view('user.inbox.dms.settings', [
            'user'  => $user,
            'tiers' => $tiers,
            'modes' => User::DM_MODES,
        ]);
    }

    /** POST /user/inbox/dms/settings */
    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'dm_access_mode'           => 'required|in:open,subs,paid,closed',
            'dm_pay_price_cents'       => 'nullable|integer|min:0|max:100000',
            'dm_pay_currency'          => 'nullable|string|size:3',
            'dm_min_tier_id'           => 'nullable|integer',
            'dm_read_receipts_enabled' => 'nullable|boolean',
        ]);

        $user = User::find(workspace_owner_id());
        $user->dm_access_mode           = $data['dm_access_mode'];
        $user->dm_pay_price_cents       = (int) ($data['dm_pay_price_cents'] ?? 0);
        $user->dm_pay_currency          = strtoupper($data['dm_pay_currency'] ?? 'USD');
        $user->dm_min_tier_id           = $data['dm_min_tier_id'] ?: null;
        $user->dm_read_receipts_enabled = (bool) ($data['dm_read_receipts_enabled'] ?? false);
        $user->save();

        return back()->with('success', 'DM settings saved.');
    }

    // ── Mass-message broadcasts ────────────────────────────────────────

    /** GET /user/inbox/dms/broadcasts */
    public function broadcastsIndex()
    {
        $userId = workspace_owner_id();
        $broadcasts = DmBroadcast::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->paginate(20);
        $tiers = SubscriptionTier::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        return view('user.inbox.dms.broadcasts.index', [
            'broadcasts' => $broadcasts,
            'tiers'      => $tiers,
            'audiences'  => DmBroadcast::AUDIENCES,
        ]);
    }

    /** POST /user/inbox/dms/broadcasts — saves draft. */
    public function broadcastStore(Request $request)
    {
        $data = $request->validate([
            'audience_kind'                => 'required|in:followers,subscribers,tier,all_dm_threads',
            'audience_value'               => 'nullable|string|max:64',
            'body'                         => 'required|string|max:5000',
            'attachment_url'               => 'nullable|url|max:1024',
            'attachment_thumb_url'         => 'nullable|url|max:1024',
            'attachment_kind'              => 'nullable|in:image,gallery,video,audio,voice,file',
            'attachment_lock_price_cents'  => 'nullable|integer|min:0|max:100000',
            'attachment_lock_currency'     => 'nullable|string|size:3',
            'send_now'                     => 'nullable|boolean',
        ]);

        $b = DmBroadcast::create([
            'user_id'                     => workspace_owner_id(),
            'audience_kind'               => $data['audience_kind'],
            'audience_value'              => $data['audience_value'] ?? null,
            'body'                        => $data['body'],
            'attachment_url'              => $data['attachment_url'] ?? null,
            'attachment_thumb_url'        => $data['attachment_thumb_url'] ?? null,
            'attachment_kind'             => $data['attachment_kind'] ?? null,
            'attachment_lock_price_cents' => (int) ($data['attachment_lock_price_cents'] ?? 0),
            'attachment_lock_currency'    => strtoupper($data['attachment_lock_currency'] ?? 'USD'),
            'status'                      => DmBroadcast::STATUS_DRAFT,
        ]);

        if ($request->boolean('send_now')) {
            app(DmDispatcher::class)->dispatchBroadcast($b);
            return redirect()->route('user.inbox.dms.broadcasts.index')
                ->with('success', "Broadcast sent to {$b->sent_count} recipient(s).");
        }
        return redirect()->route('user.inbox.dms.broadcasts.index')
            ->with('success', 'Draft saved.');
    }

    /** POST /user/inbox/dms/broadcasts/{broadcast}/send */
    public function broadcastSend(DmBroadcast $broadcast)
    {
        abort_unless((int) $broadcast->user_id === (int) workspace_owner_id(), 404);
        if ($broadcast->status === DmBroadcast::STATUS_SENT) {
            return back()->with('error', 'Already sent.');
        }
        app(DmDispatcher::class)->dispatchBroadcast($broadcast);
        return back()->with('success', "Broadcast sent to {$broadcast->sent_count} recipient(s).");
    }

    /** DELETE /user/inbox/dms/broadcasts/{broadcast} */
    public function broadcastDestroy(DmBroadcast $broadcast)
    {
        abort_unless((int) $broadcast->user_id === (int) workspace_owner_id(), 404);
        $broadcast->delete();
        return back()->with('success', 'Broadcast deleted.');
    }

    // ── Welcome-message automation ─────────────────────────────────────

    /** GET /user/inbox/dms/welcome-rules */
    public function welcomeIndex()
    {
        $userId = workspace_owner_id();
        $rules  = DmWelcomeRule::where('user_id', $userId)->orderByDesc('id')->get();
        $tiers  = SubscriptionTier::where('user_id', $userId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name']);
        return view('user.inbox.dms.welcome.index', [
            'rules'    => $rules,
            'tiers'    => $tiers,
            'triggers' => DmWelcomeRule::TRIGGERS,
        ]);
    }

    public function welcomeStore(Request $request)
    {
        $data = $request->validate([
            'trigger'                      => 'required|in:new_follower,new_subscriber',
            'tier_id'                      => 'nullable|integer',
            'body'                         => 'required|string|max:5000',
            'attachment_url'               => 'nullable|url|max:1024',
            'attachment_thumb_url'         => 'nullable|url|max:1024',
            'attachment_kind'              => 'nullable|in:image,gallery,video,audio,voice,file',
            'attachment_lock_price_cents'  => 'nullable|integer|min:0|max:100000',
            'attachment_lock_currency'     => 'nullable|string|size:3',
            'is_active'                    => 'nullable|boolean',
        ]);

        DmWelcomeRule::create([
            'user_id'                     => workspace_owner_id(),
            'trigger'                     => $data['trigger'],
            'tier_id'                     => $data['tier_id'] ?: null,
            'body'                        => $data['body'],
            'attachment_url'              => $data['attachment_url'] ?? null,
            'attachment_thumb_url'        => $data['attachment_thumb_url'] ?? null,
            'attachment_kind'             => $data['attachment_kind'] ?? null,
            'attachment_lock_price_cents' => (int) ($data['attachment_lock_price_cents'] ?? 0),
            'attachment_lock_currency'    => strtoupper($data['attachment_lock_currency'] ?? 'USD'),
            'is_active'                   => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Welcome rule created.');
    }

    public function welcomeDestroy(DmWelcomeRule $rule)
    {
        abort_unless((int) $rule->user_id === (int) workspace_owner_id(), 404);
        $rule->delete();
        return back()->with('success', 'Welcome rule deleted.');
    }

    public function welcomeToggle(DmWelcomeRule $rule)
    {
        abort_unless((int) $rule->user_id === (int) workspace_owner_id(), 404);
        $rule->is_active = !$rule->is_active;
        $rule->save();
        return back()->with('success', $rule->is_active ? 'Rule enabled.' : 'Rule disabled.');
    }
}
