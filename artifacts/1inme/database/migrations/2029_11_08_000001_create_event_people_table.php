<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * People on an event: hosts, speakers, moderators, panellists.
 *
 * TWO COLUMNS FOR ONE PERSON, ON PURPOSE.
 *
 * `contact_id` is the reference. A speaker is a Contact -- that model
 * already carries display_name, job_title, organization, socials, notes
 * and a photo, and it is owned by the workspace OWNER and deliberately
 * crosses workspaces, so one profile is reusable from every event
 * without re-typing it.
 *
 * The name/title/company/linkedin/bio/photo columns beside it are a
 * SNAPSHOT taken when the person was added. They exist because a pure
 * reference rewrites history: a speaker who was "CTO at Acme" at last
 * year's conference becomes "CEO at Beta" on that same archived page the
 * day she changes jobs, and anyone who saved the link sees a different
 * event than the one they attended.
 *
 * WHICH OF THE TWO RENDERS WAS SETTLED AFTER THIS TABLE WAS BUILT, AND
 * IT IS NOT "THE SNAPSHOT", AS THE PARAGRAPH ABOVE ASSUMED.
 *
 * That paragraph is left standing because its argument holds; it just
 * does not hold all the time. It was being applied to events that had
 * not happened yet, where there is no history to rewrite and an
 * organiser correcting a job title in their CRM reasonably expects the
 * event to follow.
 *
 * So the cut is the event's START:
 *
 *   - before it starts, the live Contact renders;
 *   - from the start onwards, these columns render, for good.
 *
 * EventPerson::resolved() is the single place that decides it.
 *
 * WHICH MAKES THESE COLUMNS A LIVE MIRROR, NOT A SNAPSHOT, UNTIL THE
 * EVENT BEGINS. Contact::updated keeps them in step
 * (EventPerson::syncSnapshotsForContact) and stops at events that have
 * started. Without that, freezing at the start would freeze to whatever
 * was true the day the speaker was ADDED -- so the page would follow
 * the profile for weeks and then jump to a value nobody ever saw, at
 * the one moment the whole point was that nothing changes.
 *
 * One consequence of the mirror: a per-event override typed against a
 * LINKED person on an upcoming event does not survive, and did not
 * render in the first place. Overriding one upcoming event means
 * unlinking that person so these columns are all there is.
 *
 * The columns are therefore the whole story for anyone typed in rather
 * than picked, the fallback for any field the Contact leaves blank, the
 * billing of record from the start of the event onwards, and what the
 * line-up falls back to the day someone is unlinked -- which is why
 * `contact_id` is nulled rather than cascaded on delete: losing the CRM
 * record must not blank a past event's line-up.
 *
 * A person with no Contact at all is allowed: a one-off guest should not
 * force a CRM entry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_people', function (Blueprint $table) {
            $table->id();

            $table->foreignId('link_id')->constrained('links')->cascadeOnDelete();

            // Optional: the reusable profile this was taken from.
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();

            // Which agenda item, if any. Null means event-level -- a host
            // or a headline speaker who is not tied to one session.
            $table->string('agenda_item_id', 16)->nullable();

            $table->string('role', 20)->default('speaker');

            // The snapshot. `name` is the only one that must be there:
            // a line-up entry with no name is not a line-up entry.
            $table->string('name', 160);
            $table->string('title', 160)->nullable();
            $table->string('company', 160)->nullable();
            $table->string('linkedin_url', 255)->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_url', 2048)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            // The two reads the public page makes: everyone on this event,
            // and everyone on one agenda item.
            $table->index(['link_id', 'agenda_item_id']);
            $table->index(['link_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_people');
    }
};
