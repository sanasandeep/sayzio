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
 * WHICH OF THE TWO RENDERS WAS REVERSED AFTER THIS TABLE WAS BUILT.
 *
 * The paragraph above is the argument for rendering the snapshot, and it
 * is still a fair argument -- it is left standing rather than quietly
 * deleted. It lost to a commoner case: most events being edited have not
 * happened yet, and an organiser who corrects a job title in their CRM
 * expects every event billing that person to say the new thing, not only
 * the ones added after the correction. So the live Contact renders
 * whenever one is linked, and the archived-page cost described above is
 * real and accepted. EventPerson::resolved() is the single place that
 * decides this, and it carries the exact few lines that reverse it again
 * for past events if the cost ever outgrows the benefit.
 *
 * The snapshot is therefore not legacy. It is the whole story for anyone
 * typed in rather than picked, the fallback for any field the Contact
 * leaves blank, and what the line-up falls back to the day someone is
 * unlinked -- which is why `contact_id` is nulled rather than cascaded
 * on delete: losing the CRM record must not blank a past event's
 * line-up.
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
