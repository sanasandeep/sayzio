<?php

namespace Tests\Unit;

use App\Modules\User\Controllers\CreatorStatsController;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreatorStatsDateRangeTest extends TestCase
{
    private function resolve(array $query): array
    {
        $controller = new class extends CreatorStatsController {
            public function dates(Request $request): array { return $this->resolveRange($request); }
        };
        return $controller->dates(Request::create('/user/stats', 'GET', $query));
    }

    public function test_custom_range_has_inclusive_day_boundaries(): void
    {
        [$range, $start, $end] = $this->resolve(['range' => 'custom', 'from' => '2020-01-02', 'to' => '2020-01-04']);
        $this->assertSame('custom', $range);
        $this->assertSame('2020-01-02 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2020-01-04 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    public function test_inverted_custom_dates_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->resolve(['range' => 'custom', 'from' => '2020-01-04', 'to' => '2020-01-02']);
    }

    public function test_missing_custom_dates_are_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->resolve(['range' => 'custom']);
    }

    public function test_excessive_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->resolve(['range' => 'custom', 'from' => '2020-01-01', 'to' => '2022-01-01']);
    }
}
