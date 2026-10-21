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
 * {@see resolved()} -- and the current answer is: the live Contact,
 * whenever one is linked. The snapshot is the fallback, and the only
 * source for somebody typed in rather than picked.
 *
 * Two consequences, both real and neither hidden:
 *
 * A PAST event reports what a person is today, not what they were when
 * they spoke. See resolved() for why that is worse than it sounds and
 * how narrowly it can be undone.
 *
 * And a per-event override of a linked person's billing does not
 * survive. Typing "VP Eng" for one event against a Contact that says
 * "CTO" shows CTO. To bill somebody differently for one event, unlink
 * them from the Contact so the snapshot is all there is.
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
     * The cost, stated plainly because it is real: a PAST event's page
     * reports what the person is TODAY. "Spoke as VP Eng at Globex" on
     * a March event they keynoted as CTO of Acme is not a stale label,
     * it is untrue, on a public page, about a thing that happened.
     *
     * If that starts to matter, this is the whole fix and nothing else
     * in the codebase changes:
     *
     *     \$ics = \$this->link?->icsData;
     *     if (\$ics?->start_date && \$ics->start_date->isPast()) {
     *         return trim((string) \$this->{\$field}) ?: null;
     *     }
     *
     * An empty live value falls through to the snapshot rather than
     * blanking the billing: a Contact with no job title should not
     * erase a title the organiser typed.
     */
    protected function resolved(string $field): ?string
    {
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
     * Re-take the snapshot from the linked profile.
     *
     * Nothing calls this while billing reads live -- a linked person is
     * already current, so there is nothing to refresh. Kept because it
     * and hasDrifted() are exactly what the freeze-past-events variant
     * in resolved() needs, and because the snapshot still has to be
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
     * Unused while billing reads live, for the same reason as above.
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
