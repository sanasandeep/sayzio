<?php

namespace Tests\Feature;

use App\Modules\User\Controllers\CalendarController;
use App\Modules\User\Controllers\SubscriberController;
use App\Modules\User\Models\Calendar;
use App\Modules\User\Models\CalendarEvent;
use App\Modules\User\Models\Subscriber;
use App\Modules\User\Models\User;
use App\Modules\User\Services\WorkspaceContext;
use App\Support\FilterValues;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class UserMultiSelectFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalization_preserves_legacy_queries_and_sanitizes_arrays(): void
    {
        $this->assertSame(['email'], FilterValues::get(Request::create('/', 'GET', ['type' => 'email']), 'type'));
        $req = Request::create('/', 'GET', ['type' => 'email', 'type_multi' => [' form ', 'form', 'email', ['nested'], 'invalid']]);
        $this->assertSame(['form', 'email'], FilterValues::get($req, 'type', ['email', 'form']));
        $this->assertSame([12, 34], array_values(FilterValues::ids(Request::create('/', 'GET', ['calendar_multi' => ['12', 'garbage', '34', '99999999999999999999999']]), 'calendar')));
    }

    public function test_subscriber_filters_union_values_intersect_dimensions_and_export_matches(): void
    {
        Queue::fake();
        $u = User::factory()->create();
        $this->actingAs($u);
        app()->instance('current_workspace', app(WorkspaceContext::class)->resolve($u));
        foreach ([['email', 'active', 'Match Email'], ['whatsapp_number', 'active', 'Match Phone'], ['email', 'unsubscribed', 'Excluded status'], ['telegram', 'active', 'Excluded type']] as [$type,$status,$name]) {
            Subscriber::create(['user_id' => $u->id, 'type' => $type, 'status' => $status, 'name' => $name, 'email' => uniqid().'@example.com', 'subscribed_at' => now()]);
        }
        $req = Request::create('/', 'GET', ['type_multi' => ['email', 'whatsapp_number'], 'status_multi' => ['active'], 'search' => 'Match']);
        $req->setUserResolver(fn () => $u);
        $controller = app(SubscriberController::class);
        $data = $controller->index($req)->getData();
        $this->assertSame(2, $data['subscribers']->total());
        ob_start();
        $controller->export($req)->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('Match Email', $csv);
        $this->assertStringContainsString('Match Phone', $csv);
        $this->assertStringNotContainsString('Excluded', $csv);
    }

    public function test_calendar_selection_intersects_access_and_keeps_other_options_available(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u);
        $ws = app(WorkspaceContext::class)->resolve($u);
        app()->instance('current_workspace', $ws);
        app()->instance('workspace_owner', $u);
        $ids = [];
        foreach (['One', 'Two', 'Three'] as $title) {
            $cal = Calendar::create(['user_id' => $u->id, 'workspace_id' => $ws->id, 'title' => $title, 'slug' => uniqid(), 'is_public' => true, 'timezone' => 'UTC']);
            $ids[] = $cal->id;
            CalendarEvent::create(['calendar_id' => $cal->id, 'user_id' => $u->id, 'title' => $title, 'start_at' => now()->addDay(), 'timezone' => 'UTC']);
        }
        $other = User::factory()->create();
        $secret = Calendar::create(['user_id' => $other->id, 'title' => 'Not followed', 'slug' => uniqid(), 'is_public' => false, 'timezone' => 'UTC']);
        $req = Request::create('/', 'GET', ['calendar_multi' => [$ids[0], $ids[1], $secret->id], 'view' => 'agenda']);
        $req->setUserResolver(fn () => $u);
        $method = new \ReflectionMethod(CalendarController::class, 'buildMyCalendarQuery');
        $result = $method->invoke(app(CalendarController::class), $req, $u);
        $this->assertEqualsCanonicalizing([$ids[0], $ids[1]], $result['query']->pluck('calendar_id')->all());
        $this->assertEqualsCanonicalizing($ids, $result['calendarIds']->all());
    }
}
