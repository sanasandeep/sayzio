<?php

namespace Tests\Feature;

use App\Modules\User\Models\Link;
use App\Modules\User\Models\ServiceBooking;
use App\Modules\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalonEditorRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_calendar_connection_state_renders_for_both_appointment_types(): void
    {
        $owner = User::factory()->create(['onboarded_at' => now()]);
        $this->actingAs($owner);
        foreach ([Link::TYPE_SALON_SPA, Link::TYPE_SERVICE_BOOKING] as $type) {
            $link = Link::create(['user_id' => $owner->id, 'type' => $type, 'alias' => Link::generateAlias(), 'is_active' => true]);
            $config = ServiceBooking::create(['link_id' => $link->id, 'user_id' => $owner->id, 'mode' => 'booking', 'currency' => 'USD']);
            $html = view('user.links.service-booking.editor', [
                'link' => $link, 'config' => $config, 'openBookings' => 0,
                'calendarAccounts' => collect(), 'staffCap' => -1, 'calendarSyncAllowed' => true,
            ])->render();
            $this->assertStringContainsString(route('user.calendar.index'), $html);
            $this->assertStringContainsString('Calendar connections', $html);
        }
    }
}
