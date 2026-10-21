<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person billed on an event: host, speaker, moderator, panellist.
 *
 * Carries a reference to a reusable {@see Contact} AND a snapshot of
 * how that person should read on THIS event. See the migration for why
 * both exist; the short version is that a pure reference silently
 * rewrites a past event's line-up the day someone changes jobs.
 *
 * The snapshot is what renders. The reference is what makes reuse and
 * `refreshFromContact()` possible.
 */
class EventPerson extends Model
{
    protected $table = 'event_people';

    public const ROLE_HOST      = 'host';
    public const ROLE_SPEAKER   = 'speaker';
    public const ROLE_MODERATOR = 'moderator';
    public const ROLE_PANELLIST = 'panellist';

    /** Billing order on the page: a panel reads moderator first. */
    public const ROLES = [
        self::ROLE_HOST,
        self::ROLE_MODERATOR,
        self::ROLE_SPEAKER,
        self::ROLE_PANELLIST,
    ];

    /** How each role is labelled to an attendee. */
    public const ROLE_LABELS = [
        self::ROLE_HOST      => 'Host',
        self::ROLE_MODERATOR => 'Moderator',
        self::ROLE_SPEAKER   => 'Speaker',
        self::ROLE_PANELLIST => 'Panellist',
    ];

    protected $fillable = [
        'link_id', 'contact_id', 'agenda_item_id', 'role',
        'name', 'title', 'company', 'linkedin_url', 'bio', 'photo_url',
        'sort_order',
    ];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    // ── Reading ──────────────────────────────────────────────────────

    /** Everyone on one agenda item, in billing order. */
    public function scopeForAgendaItem($query, string $agendaItemId)
    {
        return $query->where('agenda_item_id', $agendaItemId)->ordered();
    }

    /** People not tied to a session -- hosts, headline speakers. */
    public function scopeEventLevel($query)
    {
        return $query->whereNull('agenda_item_id')->ordered();
    }

    /**
     * The organiser's own ordering, then id so the result is stable
     * rather than whatever the database felt like.
     *
     * Role precedence is NOT done here. It was, as an orderByRaw CASE
     * with bindings, and that is a database-specific construct earning
     * its keep on a handful of rows -- these collections are a line-up,
     * not a feed. {@see billingOrder()} does it in PHP instead.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Sort a line-up the way it should read: moderator before the panel
     * they are moderating, host before either, and within a role the
     * order the organiser chose.
     *
     * @param  \Illuminate\Support\Collection<int,self>  $people
     * @return \Illuminate\Support\Collection<int,self>
     */
    public static function billingOrder($people)
    {
        $rank = array_flip(self::ROLES);

        return $people
            ->sortBy(fn (self $p) => [$rank[$p->role] ?? 99, $p->sort_order, $p->id])
            ->values();
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? 'Speaker';
    }

    /** "CTO at Acme", "CTO", "Acme", or null -- whichever parts exist. */
    public function billing(): ?string
    {
        $title   = trim((string) $this->title);
        $company = trim((string) $this->company);

        if ($title !== '' && $company !== '') {
            return "{$title} at {$company}";
        }

        return ($title ?: $company) ?: null;
    }

    // ── Writing ──────────────────────────────────────────────────────

    /**
     * Build the snapshot from a reusable profile. Values the caller
     * passes win, so an organiser can bill someone differently for one
     * event without editing the underlying Contact.
     *
     * @param array<string,mixed> $overrides
     */
    public static function snapshotFrom(Contact $contact, array $overrides = []): array
    {
        $snapshot = [
            'contact_id'   => $contact->id,
            'name'         => (string) $contact->display_name,
            'title'        => $contact->job_title   ?: null,
            'company'      => $contact->organization ?: null,
            'linkedin_url' => self::linkedinFrom($contact),
            'bio'          => $contact->notes ?: null,
            'photo_url'    => $contact->photoUrl(),
        ];

        foreach ($overrides as $key => $value) {
            if (array_key_exists($key, $snapshot)) {
                $snapshot[$key] = $value;
            }
        }

        return $snapshot;
    }

    /**
     * Re-take the snapshot from the linked profile. This is the explicit
     * action an organiser takes; it is deliberately NOT automatic, which
     * is the whole point of storing a snapshot.
     */
    public function refreshFromContact(): bool
    {
        $contact = $this->contact;

        if (!$contact) {
            return false;
        }

        $this->fill(self::snapshotFrom($contact));

        return $this->save();
    }

    /** True when the linked profile has since drifted from the snapshot. */
    public function hasDrifted(): bool
    {
        $contact = $this->contact;

        if (!$contact) {
            return false;
        }

        $fresh = self::snapshotFrom($contact);

        foreach (['name', 'title', 'company', 'linkedin_url', 'photo_url'] as $field) {
            if ((string) $fresh[$field] !== (string) $this->{$field}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pull a LinkedIn URL out of a Contact's socials, which are stored as
     * [{platform, label, url}]. Matches on either the platform or the URL
     * so a row saved with a blank platform still resolves.
     */
    public static function linkedinFrom(Contact $contact): ?string
    {
        foreach ((array) $contact->socials as $social) {
            if (!is_array($social)) {
                continue;
            }

            $url      = trim((string) ($social['url'] ?? ''));
            $platform = strtolower(trim((string) ($social['platform'] ?? '')));

            if ($url === '') {
                continue;
            }

            if ($platform === 'linkedin' || str_contains(strtolower($url), 'linkedin.com')) {
                return $url;
            }
        }

        return null;
    }
}
