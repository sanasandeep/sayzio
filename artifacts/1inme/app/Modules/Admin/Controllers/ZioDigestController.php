<?php

namespace App\Modules\Admin\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendZioDigestEmailJob;
use App\Jobs\SendZioDigestWhatsAppJob;
use App\Modules\Admin\Models\Plan;
use App\Modules\Common\Models\ZioDigest;
use App\Modules\Common\Models\ZioDigestRecipient;
use App\Services\Integrations\SendGridSettings;
use App\Services\ZioDigest\SendGridMailer;
use App\Services\ZioDigest\ZioDigestBranding;
use App\Services\ZioDigest\ZioDigestAudience;
use App\Services\ZioDigest\ZioDigestRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Admin CRUD + broadcast controls for Zio Digests (Task #5620).
 */
class ZioDigestController extends Controller
{
    public function index()
    {
        $digests = ZioDigest::query()->orderByDesc('id')->paginate(25);

        return view('admin.zio-digests.index', [
            'digests'        => $digests,
            'sendgridStatus' => SendGridSettings::status(),
            'sendgridMasked' => SendGridSettings::maskedApiKey(),
            'sendgridFrom'   => ['email' => SendGridSettings::fromEmail(), 'name' => SendGridSettings::fromName()],
            'brandLogoUrl'   => ZioDigestBranding::logoUrl(),
            'brandHasCustomLogo' => ZioDigestBranding::hasCustomLogo(),
        ]);
    }

    public function create()
    {
        return view('admin.zio-digests.edit', [
            'digest' => new ZioDigest(['status' => 'draft', 'blocks' => [], 'audience' => ['mode' => 'opted_in', 'plan_ids' => []]]),
            'plans'  => $this->planOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $digest = ZioDigest::create($data + [
            'slug'                => ZioDigest::uniqueSlug($data['title']),
            'created_by_admin_id' => Auth::guard('admin')->id(),
            'published_at'        => $data['status'] === 'published' ? now() : null,
        ]);

        return redirect()->route('admin.zio-digests.edit', $digest)
            ->with('success', 'Digest created.');
    }

    public function edit(ZioDigest $digest)
    {
        return view('admin.zio-digests.edit', [
            'digest' => $digest,
            'plans'  => $this->planOptions(),
        ]);
    }

    public function update(Request $request, ZioDigest $digest)
    {
        $data = $this->validated($request);

        if ($data['status'] === 'published' && !$digest->published_at) {
            $data['published_at'] = now();
        }
        $digest->update($data);

        return redirect()->route('admin.zio-digests.edit', $digest)
            ->with('success', 'Digest saved.');
    }

    public function destroy(ZioDigest $digest)
    {
        $digest->delete();

        return redirect()->route('admin.zio-digests.index')->with('success', 'Digest deleted.');
    }

    public function duplicate(ZioDigest $digest)
    {
        $copy = $digest->replicate([
            'slug', 'status', 'published_at',
            'email_status', 'wa_status',
            'email_queued_count', 'email_sent_count', 'email_failed_count', 'email_skipped_count',
            'wa_queued_count', 'wa_sent_count', 'wa_failed_count', 'wa_skipped_count',
            'unsubscribed_count', 'email_sent_at', 'wa_sent_at',
        ]);
        $copy->title = $digest->title . ' (copy)';
        $copy->slug = ZioDigest::uniqueSlug($copy->title);
        $copy->status = 'draft';
        $copy->email_status = 'idle';
        $copy->wa_status = 'idle';
        $copy->created_by_admin_id = Auth::guard('admin')->id();
        $copy->save();

        return redirect()->route('admin.zio-digests.edit', $copy)->with('success', 'Digest duplicated.');
    }

    /** Render the public page as an admin preview (drafts allowed). */
    public function preview(ZioDigest $digest)
    {
        return view('common.zio-digest', ['digest' => $digest, 'isPreview' => true]);
    }

    /** Live audience counts for the composer (JSON). */
    public function audienceCount(Request $request)
    {
        $audience = ZioDigestAudience::normalize([
            'mode'     => $request->input('mode'),
            'plan_ids' => $request->input('plan_ids', []),
        ]);

        return response()->json(ZioDigestAudience::counts($audience));
    }

    /** Queue the broadcast for the selected channels. */
    public function send(Request $request, ZioDigest $digest)
    {
        $request->validate([
            'channels'   => ['required', 'array', 'min:1'],
            'channels.*' => ['in:email,whatsapp'],
        ]);
        $channels = array_values(array_unique($request->input('channels')));

        if (!$digest->isPublished()) {
            return back()->with('error', 'Publish the digest before sending — the email links to its public page.');
        }
        if (in_array('email', $channels, true) && $digest->email_status === 'sending') {
            return back()->with('error', 'An email send is already in progress for this digest.');
        }
        if (in_array('whatsapp', $channels, true) && $digest->wa_status === 'sending') {
            return back()->with('error', 'A WhatsApp send is already in progress for this digest.');
        }
        if (in_array('email', $channels, true) && !SendGridSettings::configured()) {
            return back()->with('error', 'SendGrid is not configured. Add a SendGrid API key in the settings card on the Zio Digests page before sending email.');
        }

        $audience = ZioDigestAudience::normalize($digest->audience);
        $messages = [];

        if (in_array('email', $channels, true)) {
            $queued = $this->queueRecipients($digest, 'email', ZioDigestAudience::emailQuery($audience));
            $totalAudience = ZioDigestAudience::baseQuery($audience)->count();
            $digest->forceFill([
                'email_status'        => 'queued',
                'email_queued_count'  => $queued,
                'email_sent_count'    => 0,
                'email_failed_count'  => 0,
                'email_skipped_count' => max(0, $totalAudience - $queued),
            ])->save();
            SendZioDigestEmailJob::dispatch($digest->id);
            $messages[] = "Email queued to {$queued} recipient(s).";
        }

        if (in_array('whatsapp', $channels, true)) {
            $queued = $this->queueRecipients($digest, 'whatsapp', ZioDigestAudience::whatsappQuery($audience));
            $totalAudience = ZioDigestAudience::baseQuery($audience)->count();
            $digest->forceFill([
                'wa_status'        => 'queued',
                'wa_queued_count'  => $queued,
                'wa_sent_count'    => 0,
                'wa_failed_count'  => 0,
                'wa_skipped_count' => max(0, $totalAudience - $queued),
            ])->save();
            SendZioDigestWhatsAppJob::dispatch($digest->id);
            $messages[] = "WhatsApp queued to {$queued} recipient(s); users without a phone number are skipped.";
        }

        return back()->with('success', implode(' ', $messages));
    }

    /** Send a one-off test email to the given address (no recipient rows). */
    public function sendTest(Request $request, ZioDigest $digest)
    {
        $request->validate(['email' => ['required', 'email']]);

        if (!SendGridSettings::configured()) {
            return back()->with('error', 'SendGrid is not configured. Add an API key before test-sending.');
        }

        $result = (new SendGridMailer())->send(
            'digest.test',
            $request->input('email'),
            null,
            '[TEST] ' . $digest->title,
            (new ZioDigestRenderer())->emailHtml($digest, null),
            [],
            ['related_type' => $digest->getMorphClass(), 'related_id' => $digest->id],
        );

        return $result['ok']
            ? back()->with('success', 'Test email sent to ' . $request->input('email') . '.')
            : back()->with('error', 'Test send failed: ' . ($result['error'] ?? 'unknown error'));
    }

    /** Per-channel delivery report. */
    public function report(ZioDigest $digest, Request $request)
    {
        $channel = in_array($request->query('channel'), ['email', 'whatsapp'], true)
            ? $request->query('channel') : 'email';
        $status = in_array($request->query('status'), ['queued', 'sent', 'failed', 'skipped'], true)
            ? $request->query('status') : null;

        $rows = ZioDigestRecipient::query()
            ->where('digest_id', $digest->id)
            ->where('channel', $channel)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with('user:id,name,email,phone,mobile')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        return view('admin.zio-digests.report', [
            'digest'  => $digest,
            'channel' => $channel,
            'status'  => $status,
            'rows'    => $rows,
        ]);
    }

    /** Save the SendGrid settings card. */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'api_key'    => ['nullable', 'string', 'max:500'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'from_name'  => ['nullable', 'string', 'max:255'],
            'clear_key'  => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('clear_key')) {
            SendGridSettings::setApiKey(null);
        } elseif (trim((string) $request->input('api_key')) !== '') {
            SendGridSettings::setApiKey($request->input('api_key'));
        }
        SendGridSettings::setFromEmail($request->input('from_email'));
        SendGridSettings::setFromName($request->input('from_name'));

        return back()->with('success', 'SendGrid settings saved.');
    }

    /** Upload a replacement platform-wide Zio Digest logo. */
    public function updateLogo(Request $request)
    {
        $request->validate([
            'logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:4096'],
        ]);

        ZioDigestBranding::storeUploadedLogo($request->file('logo'));

        return back()->with('success', 'Zio Digest logo updated. It now appears on the public pages, emails, and this admin section.');
    }

    /** Revert to the bundled default Zio Digest logo. */
    public function removeLogo()
    {
        ZioDigestBranding::revertToDefault();

        return back()->with('success', 'Reverted to the default Zio Digest logo.');
    }

    /** Upload an image for use in digest blocks; returns its public URL. */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'max:8192'],
        ]);

        $file = $request->file('file');
        $name = Str::random(20) . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $path = $file->storeAs('zio-digests', $name, ['disk' => config('filesystems.default', 'public')]);
        $disk = Storage::disk(config('filesystems.default', 'public'));

        return response()->json(['url' => $disk->url($path)]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'status'             => ['required', 'in:draft,published'],
            'summary'            => ['nullable', 'string', 'max:5000'],
            'lead_image'         => ['nullable', 'url', 'max:2048'],
            'blocks_json'        => ['nullable', 'string', 'max:2000000'],
            'audience_mode'      => ['required', 'in:all,opted_in,plans'],
            'audience_plan_ids'  => ['nullable', 'array'],
            'audience_plan_ids.*' => ['integer'],
        ]);

        $blocks = json_decode((string) ($data['blocks_json'] ?? '[]'), true);

        return [
            'title'      => $data['title'],
            'status'     => $data['status'],
            'summary'    => $data['summary'] ?? null,
            'lead_image' => $data['lead_image'] ?? null,
            'blocks'     => ZioDigest::sanitizeBlocks($blocks),
            'audience'   => ZioDigestAudience::normalize([
                'mode'     => $data['audience_mode'],
                'plan_ids' => $data['audience_plan_ids'] ?? [],
            ]),
        ];
    }

    /** Bulk-insert queued recipient rows; returns the queued count. */
    private function queueRecipients(ZioDigest $digest, string $channel, $query): int
    {
        ZioDigestRecipient::where('digest_id', $digest->id)->where('channel', $channel)->delete();

        $now = now();
        $count = 0;
        $query->select('users.id')->orderBy('users.id')->chunkById(1000, function ($users) use ($digest, $channel, $now, &$count) {
            $rows = [];
            foreach ($users as $user) {
                $rows[] = [
                    'digest_id'  => $digest->id,
                    'user_id'    => $user->id,
                    'channel'    => $channel,
                    'status'     => 'queued',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if ($rows) {
                DB::table('zio_digest_recipients')->insertOrIgnore($rows);
                $count += count($rows);
            }
        }, 'users.id', 'id');

        return $count;
    }

    private function planOptions()
    {
        return Plan::query()->orderBy('id')->get(['id', 'name']);
    }
}
