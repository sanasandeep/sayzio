<?php

namespace App\Services\WhatsApp;

use App\Modules\User\Models\FileLink;
use App\Modules\User\Models\IcsData;
use App\Modules\User\Models\Link;
use App\Modules\User\Models\QrCode;
use App\Modules\User\Models\User;
use App\Modules\User\Models\UserFile;
use App\Modules\User\Models\VcfData;
use App\Services\Biolink\AiBiolinkBuilderService;
use Illuminate\Support\Facades\Log;

/**
 * Deterministic link-building tools the WhatsApp agent's LLM loop can
 * call (Task #2759). Each tool creates or edits a real link for the
 * resolved user, reusing the same models the web controllers use, and
 * returns a short human summary the model relays back over WhatsApp.
 *
 * Construct one per conversation turn with the resolved user and the
 * pending media bucket (images/files the user sent ahead of the
 * instruction). Tools that consume media pull from that bucket.
 */
class WhatsAppAgentTools
{
    /** @var array<int,array<string,mixed>> media items: {kind,url,user_file_id,name,...} */
    private array $pending;

    /** @var array<int,Link> links touched this turn (for the closing summary) */
    public array $touched = [];

    /**
     * @param array<int,array<string,mixed>> $pending
     */
    public function __construct(private User $user, array $pending = [])
    {
        $this->pending = array_values($pending);
    }

    /**
     * OpenAI function-calling tool definitions. Kept intentionally small —
     * the six headline link types in scope plus a recent-links lookup.
     *
     * @return array<int,array<string,mixed>>
     */
    public function functionDefinitions(): array
    {
        return [
            $this->fn('create_biolink', 'Create a link-in-bio (mini-website) page from a description. Use this when the user wants a profile/bio page with multiple links, sections, or images.', [
                'title'       => ['type' => 'string', 'description' => 'Short page title.'],
                'description' => ['type' => 'string', 'description' => 'What the page should contain — bio, the links/sections to include, tone. Be detailed.'],
                'links'       => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'URLs the user wants featured on the page.'],
                'use_images'  => ['type' => 'boolean', 'description' => 'Use images the user sent in this chat as page media.'],
            ], ['description']),

            $this->fn('create_short_link', 'Shorten a single destination URL into a trackable short link.', [
                'long_url' => ['type' => 'string', 'description' => 'The destination URL to shorten (must start with http).'],
                'title'    => ['type' => 'string', 'description' => 'Optional label for the link.'],
            ], ['long_url']),

            $this->fn('create_qr_code', 'Create a QR code that points at a URL. Returns a trackable short link the QR encodes.', [
                'content_url' => ['type' => 'string', 'description' => 'The URL the QR code should open.'],
                'name'        => ['type' => 'string', 'description' => 'Optional name for the QR code.'],
            ], ['content_url']),

            $this->fn('create_file_link', 'Turn a file the user sent in this chat into a shareable download link. Only works when the user has attached a file or image.', [
                'title' => ['type' => 'string', 'description' => 'Optional title for the download page.'],
            ], []),

            $this->fn('create_event', 'Create an event (.ics) link people can add to their calendar.', [
                'event_name'  => ['type' => 'string', 'description' => 'Event name.'],
                'start'       => ['type' => 'string', 'description' => 'Start datetime, ISO 8601 e.g. 2026-07-01T18:00:00.'],
                'end'         => ['type' => 'string', 'description' => 'End datetime, ISO 8601. Defaults to one hour after start when omitted.'],
                'timezone'    => ['type' => 'string', 'description' => 'IANA timezone e.g. America/New_York. Defaults to UTC.'],
                'location'    => ['type' => 'string', 'description' => 'Optional location.'],
                'description' => ['type' => 'string', 'description' => 'Optional event description.'],
            ], ['event_name', 'start']),

            $this->fn('create_vcard', 'Create a digital contact card (vCard) link.', [
                'first_name'   => ['type' => 'string', 'description' => 'First name.'],
                'last_name'    => ['type' => 'string', 'description' => 'Last name.'],
                'organization' => ['type' => 'string', 'description' => 'Company / organization.'],
                'job_title'    => ['type' => 'string', 'description' => 'Job title.'],
                'email'        => ['type' => 'string', 'description' => 'Email address.'],
                'phone'        => ['type' => 'string', 'description' => 'Phone number.'],
                'website'      => ['type' => 'string', 'description' => 'Website URL.'],
            ], ['first_name']),

            $this->fn('list_recent_links', 'List the user\'s most recently created links so they can reference or edit them.', [
                'limit' => ['type' => 'integer', 'description' => 'How many to list (max 10).'],
            ], []),
        ];
    }

    /** Tool name → handler dispatch. Returns ['ok'=>bool,'summary'=>string]. */
    public function run(string $name, array $args): array
    {
        try {
            return match ($name) {
                'create_biolink'    => $this->createBiolink($args),
                'create_short_link' => $this->createShortLink($args),
                'create_qr_code'    => $this->createQrCode($args),
                'create_file_link'  => $this->createFileLink($args),
                'create_event'      => $this->createEvent($args),
                'create_vcard'      => $this->createVcard($args),
                'list_recent_links' => $this->listRecent($args),
                default             => ['ok' => false, 'summary' => 'Unknown tool.'],
            };
        } catch (\Throwable $e) {
            Log::warning("WhatsApp agent tool {$name} failed: " . $e->getMessage());
            return ['ok' => false, 'summary' => 'That action failed: ' . $e->getMessage()];
        }
    }

    // ── Tool handlers ─────────────────────────────────────────────

    private function createBiolink(array $args): array
    {
        $description = trim((string) ($args['description'] ?? ''));
        if ($description === '') {
            return ['ok' => false, 'summary' => 'I need a description of what the page should contain.'];
        }

        $title = trim((string) ($args['title'] ?? ''));
        $links = array_values(array_filter(array_map('strval', (array) ($args['links'] ?? []))));
        $images = !empty($args['use_images']) ? $this->pendingImageUrls() : [];

        $link = Link::create([
            'user_id'   => $this->user->id,
            'type'      => 'biolink',
            'alias'     => Link::generateAlias(),
            'title'     => $title !== '' ? $title : ($this->user->name ? $this->user->name . "'s page" : 'My Link in Bio'),
            'is_active' => true,
        ]);

        try {
            app(AiBiolinkBuilderService::class)->generate($this->user, $link, $description, $links, $images);
        } catch (\Throwable $e) {
            // The builder auto-refunds its own credits on failure; drop the
            // empty shell link so we don't leave debris behind.
            $link->delete();
            throw $e;
        }

        $this->touched[] = $link;
        return ['ok' => true, 'summary' => 'Created link-in-bio page "' . $link->title . '": ' . $link->getShortUrl()];
    }

    private function createShortLink(array $args): array
    {
        $url = trim((string) ($args['long_url'] ?? ''));
        if (!$this->isHttpUrl($url)) {
            return ['ok' => false, 'summary' => 'That doesn\'t look like a valid URL. It should start with http:// or https://'];
        }

        $link = Link::create([
            'user_id'   => $this->user->id,
            'type'      => 'url',
            'alias'     => Link::generateAlias(),
            'title'     => ($t = trim((string) ($args['title'] ?? ''))) !== '' ? $t : null,
            'long_url'  => $url,
            'redirect_type' => 301,
            'is_active' => true,
        ]);

        $this->touched[] = $link;
        return ['ok' => true, 'summary' => 'Created short link: ' . $link->getShortUrl() . ' → ' . $url];
    }

    private function createQrCode(array $args): array
    {
        $url = trim((string) ($args['content_url'] ?? ''));
        if (!$this->isHttpUrl($url)) {
            return ['ok' => false, 'summary' => 'I need a valid URL (starting with http) for the QR code.'];
        }

        // Back the QR with a trackable short link so scans are measured.
        $link = Link::create([
            'user_id'   => $this->user->id,
            'type'      => 'url',
            'alias'     => Link::generateAlias(),
            'long_url'  => $url,
            'redirect_type' => 301,
            'is_active' => true,
        ]);

        QrCode::create([
            'user_id' => $this->user->id,
            'link_id' => $link->id,
            'name'    => ($n = trim((string) ($args['name'] ?? ''))) !== '' ? $n : 'QR code',
            'type'    => 'url',
            'payload' => ['url' => $url],
        ]);

        $this->touched[] = $link;
        return ['ok' => true, 'summary' => 'Created QR code for ' . $url . '. It encodes the trackable link ' . $link->getShortUrl()];
    }

    private function createFileLink(array $args): array
    {
        $file = $this->takePendingFile();
        if (!$file) {
            return ['ok' => false, 'summary' => 'Send me the file (or image) first, then ask me to make a download link for it.'];
        }

        $link = Link::create([
            'user_id'   => $this->user->id,
            'type'      => 'file',
            'alias'     => Link::generateAlias(),
            'title'     => ($t = trim((string) ($args['title'] ?? ''))) !== '' ? $t : $file->original_name,
            'is_active' => true,
        ]);

        FileLink::create([
            'link_id'       => $link->id,
            'original_name' => $file->original_name,
            'stored_path'   => $file->path,
            'mime_type'     => $file->mime_type,
            'file_size'     => $file->size_bytes,
            'disk'          => $file->disk,
        ]);

        $this->touched[] = $link;
        return ['ok' => true, 'summary' => 'Created download link for "' . $file->original_name . '": ' . $link->getShortUrl()];
    }

    private function createEvent(array $args): array
    {
        $name = trim((string) ($args['event_name'] ?? ''));
        $start = trim((string) ($args['start'] ?? ''));
        if ($name === '' || $start === '') {
            return ['ok' => false, 'summary' => 'I need at least an event name and a start date/time.'];
        }

        try {
            $startAt = new \DateTimeImmutable($start);
        } catch (\Throwable $e) {
            return ['ok' => false, 'summary' => 'I couldn\'t understand the start date/time. Try e.g. "July 1 2026 6pm".'];
        }

        $endRaw = trim((string) ($args['end'] ?? ''));
        try {
            $endAt = $endRaw !== '' ? new \DateTimeImmutable($endRaw) : $startAt->modify('+1 hour');
        } catch (\Throwable $e) {
            $endAt = $startAt->modify('+1 hour');
        }

        $tz = trim((string) ($args['timezone'] ?? '')) ?: \App\Support\PlatformTimezone::forUser($this->user);

        $link = Link::create([
            'user_id'    => $this->user->id,
            'type'       => 'ics',
            'alias'      => Link::generateAlias(),
            'title'      => $name,
            'is_active'  => true,
            'visibility' => 'public',
        ]);

        IcsData::create([
            'link_id'     => $link->id,
            'event_name'  => $name,
            'description' => ($d = trim((string) ($args['description'] ?? ''))) !== '' ? $d : null,
            'location'    => ($l = trim((string) ($args['location'] ?? ''))) !== '' ? $l : null,
            'start_date'  => $startAt->format('Y-m-d H:i:s'),
            'end_date'    => $endAt->format('Y-m-d H:i:s'),
            'timezone'    => $tz,
        ]);

        $this->touched[] = $link;
        return ['ok' => true, 'summary' => 'Created event "' . $name . '" (' . $startAt->format('M j, Y g:ia') . '): ' . $link->getShortUrl()];
    }

    private function createVcard(array $args): array
    {
        $first = trim((string) ($args['first_name'] ?? ''));
        if ($first === '') {
            return ['ok' => false, 'summary' => 'I need at least a first name for the contact card.'];
        }
        $last = trim((string) ($args['last_name'] ?? ''));
        $title = trim(($first . ' ' . $last));

        $link = Link::create([
            'user_id'    => $this->user->id,
            'type'       => 'vcf',
            'alias'      => Link::generateAlias(),
            'title'      => $title,
            'is_active'  => true,
            'visibility' => 'public',
        ]);

        VcfData::create([
            'link_id'      => $link->id,
            'first_name'   => $first,
            'last_name'    => $last !== '' ? $last : null,
            'organization' => ($o = trim((string) ($args['organization'] ?? ''))) !== '' ? $o : null,
            'title'        => ($jt = trim((string) ($args['job_title'] ?? ''))) !== '' ? $jt : null,
            'email'        => ($e = trim((string) ($args['email'] ?? ''))) !== '' ? $e : null,
            'phone'        => ($p = trim((string) ($args['phone'] ?? ''))) !== '' ? $p : null,
            'website'      => ($w = trim((string) ($args['website'] ?? ''))) !== '' ? $w : null,
        ]);

        $this->touched[] = $link;
        return ['ok' => true, 'summary' => 'Created contact card for ' . $title . ': ' . $link->getShortUrl()];
    }

    private function listRecent(array $args): array
    {
        $limit = max(1, min(10, (int) ($args['limit'] ?? 5)));
        $links = Link::where('user_id', $this->user->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'type', 'title', 'alias']);

        if ($links->isEmpty()) {
            return ['ok' => true, 'summary' => 'You haven\'t created any links yet.'];
        }

        $lines = $links->map(function (Link $l) {
            $label = $l->title ?: ucfirst((string) $l->type);
            return '• ' . $label . ' (' . $l->type . ') — ' . $l->getShortUrl();
        })->implode("\n");

        return ['ok' => true, 'summary' => "Your recent links:\n" . $lines];
    }

    // ── Pending-media helpers ─────────────────────────────────────

    /** Public URLs for pending image media (for the biolink builder). */
    private function pendingImageUrls(): array
    {
        $urls = [];
        foreach ($this->pending as $item) {
            if (($item['kind'] ?? '') === 'image' && !empty($item['url'])) {
                $urls[] = (string) $item['url'];
            }
        }
        return $urls;
    }

    /** Pop the most recent pending file/image as a UserFile, or null. */
    private function takePendingFile(): ?UserFile
    {
        for ($i = count($this->pending) - 1; $i >= 0; $i--) {
            $item = $this->pending[$i];
            if (in_array(($item['kind'] ?? ''), ['file', 'image'], true) && !empty($item['user_file_id'])) {
                $file = UserFile::find($item['user_file_id']);
                if ($file && $file->user_id === $this->user->id) {
                    array_splice($this->pending, $i, 1);
                    return $file;
                }
            }
        }
        return null;
    }

    // ── Misc helpers ──────────────────────────────────────────────

    private function isHttpUrl(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Build one OpenAI function tool definition.
     *
     * @param array<string,array<string,mixed>> $properties
     * @param array<int,string>                  $required
     */
    private function fn(string $name, string $description, array $properties, array $required): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => $name,
                'description' => $description,
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => $properties,
                    'required'   => $required,
                ],
            ],
        ];
    }
}
