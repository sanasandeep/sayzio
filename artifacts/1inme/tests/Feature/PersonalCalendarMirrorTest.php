<?php

namespace Tests\Feature;

use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\DialerNote;
use App\Modules\User\Models\TaskBoard;
use App\Modules\User\Models\User;
use App\Modules\User\Models\Workspace;
use App\Modules\User\Services\WorkspaceContext;
use App\Modules\User\Support\PersonalCalendarSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Task #6477 — mirroring Task Board due dates and Dialer note reminders onto
 * the per-user "Tasks & Reminders" calendar:
 *
 *   (1) Setting/clearing a card due date creates/updates/removes an all-day
 *       "Task: …" event; deleting the card removes the event.
 *   (2) Note remind_at creates a timed "Reminder: …" event; clearing or
 *       deleting cleans up.
 *   (3) Mirrored events surface in the mobile /api/v1/my-calendar feed.
 *   (4) Opt-out toggles remove mirrored events; re-enabling backfills.
 */
class PersonalCalendarMirrorTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $prefix = 'u'): User
    {
        return User::factory()->create([
            'name' => $prefix . Str::random(4),
            'email' => $prefix . '-' . Str::random(8) . '@example.com',
        ]);
    }

    private function bindWorkspace(User $user): Workspace
    {
        $ws = app(WorkspaceContext::class)->resolve($user);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $user);
        return $ws;
    }

    private function makeCard(User $user, array $attrs = [])
    {
        $this->bindWorkspace($user);
        $this->actingAs($user)->post('/user/tasks/boards', ['name' => 'B' . Str::random(4), 'scope' => 'team']);
        $board = TaskBoard::orderByDesc('id')->first();
        $column = $board->columns()->orderBy('position')->first();

        return $board->cards()->create(array_merge([
            'workspace_id'       => $board->workspace_id,
            'column_id'          => $column->id,
            'created_by_user_id' => $user->id,
            'title'              => 'Ship the thing',
            'position'           => 1,
        ], $attrs));
    }

    private function api(User $user)
    {
        return $this->withToken($user->createToken('mirror-test')->plainTextToken);
    }

    public function test_card_due_date_mirrors_all_day_event_and_cleans_up(): void
    {
        $user = $this->makeUser();
        $card = $this->makeCard($user, ['due_date' => now()->addDays(3)->toDateString()]);

        $card->refresh();
        $this->assertNotNull($card->calendar_event_id);

        $event = CalendarEvent::find($card->calendar_event_id);
        $this->assertSame('Task: Ship the thing', $event->title);
        $this->assertTrue((bool) $event->all_day);
        $this->assertSame((int) $user->id, (int) $event->user_id);

        $calendar = Calendar::find($event->calendar_id);
        $this->assertSame('Tasks & Reminders', $calendar->title);
        $this->assertSame(PersonalCalendarSync::SLUG_PREFIX . $user->id, $calendar->slug);
        $this->assertFalse((bool) $calendar->is_public);

        // Title edit propagates.
        $card->update(['title' => 'Ship it v2']);
        $this->assertSame('Task: Ship it v2', $event->fresh()->title);

        // Clearing the due date removes the mirrored event.
        $card->update(['due_date' => null]);
        $this->assertNull($card->fresh()->calendar_event_id);
        $this->assertNull(CalendarEvent::find($event->id));

        // Re-set then delete the card — event goes too.
        $card->update(['due_date' => now()->addDay()->toDateString()]);
        $eventId = $card->fresh()->calendar_event_id;
        $this->assertNotNull($eventId);
        $card->delete();
        $this->assertNull(CalendarEvent::find($eventId));
    }

    public function test_note_reminder_mirrors_timed_event_and_cleans_up(): void
    {
        $user = $this->makeUser();

        $create = $this->api($user)->postJson('/api/v1/dialer/notes', [
            'title' => 'Call the plumber',
            'remind_at' => now()->addHours(2)->toIso8601String(),
        ]);
        $create->assertStatus(201);

        $note = DialerNote::find($create->json('data.id'));
        $this->assertNotNull($note->calendar_event_id);

        $event = CalendarEvent::find($note->calendar_event_id);
        $this->assertSame('Reminder: Call the plumber', $event->title);
        $this->assertFalse((bool) $event->all_day);
        $this->assertNotNull($event->end_at);

        // Changing the reminder time moves the event.
        $newTime = now()->addHours(5);
        $this->api($user)->patchJson("/api/v1/dialer/notes/{$note->id}", [
            'remind_at' => $newTime->toIso8601String(),
        ])->assertStatus(200);
        $this->assertSame(
            $newTime->copy()->utc()->format('Y-m-d H:i'),
            $event->fresh()->start_at->utc()->format('Y-m-d H:i')
        );

        // Clearing remind_at removes the event.
        $this->api($user)->patchJson("/api/v1/dialer/notes/{$note->id}", ['remind_at' => null])
            ->assertStatus(200);
        $this->assertNull($note->fresh()->calendar_event_id);
        $this->assertNull(CalendarEvent::find($event->id));

        // Delete path.
        $note->update(['remind_at' => now()->addHour()]);
        $eventId = $note->fresh()->calendar_event_id;
        $this->assertNotNull($eventId);
        $this->api($user)->deleteJson("/api/v1/dialer/notes/{$note->id}")->assertStatus(200);
        $this->assertNull(CalendarEvent::find($eventId));
    }

    public function test_mirrored_events_appear_in_mobile_my_calendar_feed(): void
    {
        $user = $this->makeUser();
        $this->makeCard($user, ['due_date' => now()->addDays(2)->toDateString()]);

        DialerNote::create([
            'user_id'   => $user->id,
            'title'     => 'Follow up',
            'remind_at' => now()->addDay(),
        ]);

        $feed = $this->api($user)->getJson('/api/v1/my-calendar')->assertStatus(200);
        $titles = collect($feed->json('data.items'))->pluck('title');
        $this->assertTrue($titles->contains('Task: Ship the thing'), $titles->implode(', '));
        $this->assertTrue($titles->contains('Reminder: Follow up'), $titles->implode(', '));
    }

    public function test_opt_out_removes_events_and_reenable_backfills(): void
    {
        $user = $this->makeUser();
        $card = $this->makeCard($user, ['due_date' => now()->addDays(2)->toDateString()]);
        $note = DialerNote::create([
            'user_id'   => $user->id,
            'title'     => 'Follow up',
            'remind_at' => now()->addDay(),
        ]);

        $this->assertNotNull($card->fresh()->calendar_event_id);
        $this->assertNotNull($note->fresh()->calendar_event_id);

        // Turn both toggles off via the API.
        $this->api($user)->patchJson('/api/v1/my-calendar/mirror-preferences', [
            'task_due_dates' => false,
            'note_reminders' => false,
        ])->assertStatus(200)->assertJsonPath('data.task_due_dates', false);

        $this->assertNull($card->fresh()->calendar_event_id);
        $this->assertNull($note->fresh()->calendar_event_id);
        $this->assertSame(0, CalendarEvent::whereIn(
            'calendar_id',
            Calendar::where('user_id', $user->id)->pluck('id')
        )->count());

        // While off, new due dates / reminders are NOT mirrored.
        $card->update(['due_date' => now()->addDays(5)->toDateString()]);
        $this->assertNull($card->fresh()->calendar_event_id);

        // Re-enable via the web form → backfill restores both events.
        $this->actingAs($user)->post('/user/my-calendar/mirror-preferences', [
            'task_due_dates' => 1,
            'note_reminders' => 1,
        ])->assertRedirect();

        $this->assertNotNull($card->fresh()->calendar_event_id);
        $this->assertNotNull($note->fresh()->calendar_event_id);
    }

    public function test_mirror_preferences_endpoints_report_defaults(): void
    {
        $user = $this->makeUser();
        $this->api($user)->getJson('/api/v1/my-calendar/mirror-preferences')
            ->assertStatus(200)
            ->assertJsonPath('data.task_due_dates', true)
            ->assertJsonPath('data.note_reminders', true);
    }
}
