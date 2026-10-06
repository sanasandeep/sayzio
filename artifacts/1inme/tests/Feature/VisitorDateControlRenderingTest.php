<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

class VisitorDateControlRenderingTest extends TestCase
{
    public function test_shared_visitor_date_controls_render_valid_dates(): void
    {
        $html = view('user.partials.visitor-range-control', [
            'buildUrl' => fn (array $query = []) => '/user/visitors?' . http_build_query($query),
            'period' => 'custom',
            'startDate' => Carbon::parse('2026-10-01'),
            'endDate' => Carbon::parse('2026-10-07'),
        ])->render();

        $this->assertStringContainsString('name="start" value="2026-10-01"', $html);
        $this->assertStringContainsString('name="end" value="2026-10-07"', $html);
        $this->assertStringContainsString('<label>From date<input', $html);
        $this->assertStringContainsString('<label>To date<input', $html);
        $this->assertSame(2, substr_count($html, '</label>'));
        $this->assertStringContainsString('data-date-filters data-list-filters', $html);
    }
}
