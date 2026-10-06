<?php
namespace Tests\Feature;

use App\Modules\User\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PostalAddressLookupTest extends TestCase
{
    public function test_lookup_returns_multiple_localities_and_indian_region_codes(): void
    {
        Cache::forget('postal_lookup:v2:IN:500001');
        Http::fake(['api.zippopotam.us/*' => Http::response(['places' => [['place name' => 'Hyderabad', 'state' => 'Telangana', 'state abbreviation' => 'TS'], ['place name' => 'Abids', 'state' => 'Telangana', 'state abbreviation' => 'TS']]])]);
        $response = app(ProfileController::class)->postalLookup(Request::create('/', 'GET', ['country' => 'IN', 'postal_code' => '500001']));
        $data = $response->getData(true);
        $this->assertCount(2, $data['places']);
        $this->assertSame('TG', $data['region_code']);
        Cache::forget('postal_lookup:v2:IN:500001');
    }

    public function test_missing_postal_matches_leave_manual_entry_available(): void
    {
        Cache::forget('postal_lookup:v2:US:00000');
        Http::fake(['api.zippopotam.us/*' => Http::response([], 404)]);
        $response = app(ProfileController::class)->postalLookup(Request::create('/', 'GET', ['country' => 'US', 'postal_code' => '00000']));
        $this->assertSame([], $response->getData(true)['places']);
        Cache::forget('postal_lookup:v2:US:00000');
    }
}
