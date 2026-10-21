<?php

namespace App\Modules\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person billed on an event: host, speaker, moderator, panellist.
 *
 * Carries a reference to a reusable {@see Contact} AND a snapshot of
 * how that person read when they were billed.
 *
 * Which of the two renders is decided in exactly one place --
 * {@see resolved()} -- and the answer turns on whether the event has
 * started:
 *
 * BEFORE it starts, the live Contact wins whenever one is linked. An
 * organiser who corrects a job title expects every event still being
 * planned to say the new thing.
 *
 * ONCE it starts, the snapshot wins and the billing is frozen. What
 * attendees read is what the event said on the day, permanently.
 *
 * The snapshot is the fallback either way, and the only source at all
 * for somebody typed in rather than picked.
 *
 * One consequence worth knowing, since nothing warns about it: on an
 * upcoming event, a per-event override of a LINKED person does not
 * render. Typing "VP Eng" for one event against a Contact that says
 * "CTO" shows CTO until the event starts. To bill somebody differently
 * for one upcoming event, unlink them so the snapshot is all there is.
 * {@see followsContact()} is what the editor uses to say so.
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

    /**
     * The reusable profile, WITHOUT the workspace filter.
     *
     * Dropping it here rather than at each call site is the point. A
     * Contact belongs to the workspace owner and deliberately crosses
     * workspaces, so the scope would come back null for a billing whose
     * profile was saved in another workspace -- and since billing now
     * resolves through the Contact, a null relation does not error, it
     * silently reverts to the snapshot. #516 was that bug one layer up:
     * an eager load ran its own query and did not inherit the parent's
     * unscoping. A constraint declared in the relation method applies to
     * both the lazy and the eager path, so there is no call site left to
     * forget. The cross-workspace guards in
     * ABillingFollowsTheContactTest cover both paths.
     *
     * `workspace_record_access` is deliberately left ON: it only
     * narrows things for a team MEMBER who was denied that record, and
     * falling back to the snapshot for them is correct, not a bug.
     *
     * This is not a hole: contact_id is validated against the
     * organiser's own contacts when the line-up is saved
     * (IcsLinkController::syncEventPeople), so an id that is not theirs
     * never reaches this column.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withoutGlobalScope('workspace');
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

    /**
     * Where a billing's display values come from -- the ONE place that
     * decision is made, so changing the policy means changing one
     * condition here rather than hunting every caller.
     *
     * Current policy: the live Contact wins whenever one is linked. An
     * organiser who corrects a job title expects every event billing
     * that person to say the new thing, and most events being edited
     * have not happened yet.
     *
     * ONCE THE EVENT HAS STARTED, THAT STOPS. The snapshot takes over
     * and nothing a profile does afterwards reaches the page. "Spoke as
     * VP Eng at Globex" on a March event somebody keynoted as CTO of
     * Acme is not a stale label, it is untrue, on a public page, about a
     * thing that happened -- and anyone who saved that link attended a
     * different event than the one it now describes.
     *
     * The cut is the START, not the end. From the moment an event
     * begins, the billing attendees are reading is the billing of
     * record. An organiser who still needs to fix a typo during or
     * after it can: the editor writes these columns directly, and for a
     * started event the columns are exactly what renders, so the
     * correction lands immediately.
     *
     * An empty live value falls through to the snapshot rather than
     * blanking the billing: a Contact with no job title should not
     * erase a title the organiser typed.
     */
    protected function resolved(string $field): ?string
    {
        if ($this->eventHasStarted()) {
            return trim((string) $this->{$field}) ?: null;
        }

        $contact = $this->contact;

        if ($contact) {
            $live = match ($field) {
                'name'         => $contact->display_name,
                'title'        => $contact->job_title,
                'company'      => $contact->organization,
                'linkedin_url' => self::linkedinFrom($contact),
                'bio'          => $contact->notes,
                'photo_url'    => $contact->photoUrl(),
                default        => null,
            };

            if (trim((string) $live) !== '') {
                return trim((string) $live);
            }
        }

        return trim((string) $this->{$field}) ?: null;
    }

    /**
     * How this person reads on the event. Every public surface goes
     * through these rather than the columns, so none of them has to
     * know the snapshot exists.
     *
     * displayName() never returns null: name is the one required
     * column, and a billing with no name is not a billing.
     */
    public function displayName(): string
    {
        return $this->resolved('name') ?? (string) $this->name;
    }

    /**
     * Has the event begun? Everything billed on it is frozen from that
     * moment on.
     *
     * No start date means NOT started. Note what that case actually is:
     * `ics_data.start_date` is NOT NULL, so this is not a half-filled
     * date field -- it is a billing whose event has no ics row at all,
     * or none this query could see. A half-built event has no attendees
     * to mislead, so following the profile is the right way to fail.
     *
     * Memoised because resolved() asks once per field, six times per
     * person. The relation is cached after the first touch anyway, but
     * whether `link.icsData` was eager-loaded decides between one query
     * for a page and two per speaker -- see the eager load in
     * event-rich-content.blade.php and the relation it sets.
     */
    public function eventHasStarted(): bool
    {
        if ($this->startedMemo === null) {
            $start = $this->link?->icsData?->start_date;

            $this->startedMemo = $start instanceof \DateTimeInterface
                && $start < now();
        }

        return $this->startedMemo;
    }

    /** @var bool|null Memo for eventHasStarted(); not a column. */
    protected ?bool $startedMemo = null;

    /**
     * Does this billing still follow the linked profile? False for
     * anyone typed in, and false for every person on an event that has
     * started. The editor shows this so its fields do not silently
     * claim to control a value the page reads from somewhere else.
     */
    public function followsContact(): bool
    {
        return $this->contact_id !== null && !$this->eventHasStarted();
    }

    public function displayTitle(): ?string       { return $this->resolved('title'); }
    public function displayCompany(): ?string     { return $this->resolved('company'); }
    public function displayLinkedinUrl(): ?string { return $this->resolved('linkedin_url'); }
    public function displayBio(): ?string         { return $this->resolved('bio'); }
    public function displayPhotoUrl(): ?string    { return $this->resolved('photo_url'); }

    /** "CTO at Acme", "CTO", "Acme", or null -- whichever parts exist. */
    public function billing(): ?string
    {
        $title   = (string) $this->displayTitle();
        $company = (string) $this->displayCompany();

        if ($title !== '' && $company !== '') {
            return "{$title} at {$company}";
        }

        return ($title ?: $company) ?: null;
    }

    // ── Writing ──────────────────────────────────────────────────────

    /**
     * Build the snapshot from a reusable profile. Values the caller
     * passes win.
     *
     * Note what an override does and does not buy on an upcoming event:
     * it sets the columns, and the columns are not what renders until
     * the event starts. It is the billing of record from that moment on,
     * so an override is a way of deciding how a past event will read,
     * not a way of overriding an upcoming one. Unlink the person for
     * that. {@see resolved()}
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
     * Keep every not-yet-started billing of this profile in step with
     * it. Called from Contact::updated.
     *
     * THE FREEZE IS WHY THIS HAS TO EXIST. resolved() freezes a billing
     * to its columns the moment the event starts, so those columns are
     * what attendees will read for good. If they still held whatever
     * was true on the day the speaker was ADDED, the page would follow
     * the profile right up to the start and then jump back to a value
     * nobody ever saw -- a visible change at the one moment the whole
     * point was that nothing changes. Mirroring the profile until the
     * start makes "frozen at the start" mean what it says.
     *
     * Started events are excluded, which is the entire guarantee.
     *
     * The cost: a per-event override typed against a LINKED person is
     * overwritten here. It was already not rendering (see resolved()),
     * so this makes a half-working thing plainly not work rather than
     * leaving it to surprise somebody later. Overriding one event means
     * unlinking that person; {@see followsContact()} is what the editor
     * uses to say so.
     *
     * The link is reached unscoped on purpose: contacts cross
     * workspaces, so a profile billed on an event in another of the
     * owner's workspaces must still be found. A scoped whereHas would
     * silently skip exactly those -- #516 again, this time in a
     * subquery.
     */
    public static function syncSnapshotsForContact(Contact $contact): void
    {
        $snapshot = self::snapshotFrom($contact);
        unset($snapshot['contact_id']);

        self::where('contact_id', $contact->id)
            ->whereHas('link', fn ($l) => $l->withoutGlobalScope('workspace')
                ->whereHas('icsData', fn ($i) => $i->where('start_date', '>', now())))
            ->update($snapshot);
    }

    /**
     * Re-take the snapshot from the linked profile, for one person.
     *
     * syncSnapshotsForContact() is the automatic path; this stays for a
     * deliberate single refresh, and because the snapshot has to be
     * right for the day somebody is unlinked.
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

    /**
     * True when the linked profile has since drifted from the snapshot.
     * On a not-yet-started event this should now be false -- the mirror
     * keeps it so -- which makes it a usable check on whether the
     * mirror is doing its job.
     */
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
