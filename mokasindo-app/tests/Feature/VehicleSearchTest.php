<?php

namespace Tests\Feature;

use App\Models\Province;
use App\Models\City;
use App\Models\District;
use App\Models\SubDistrict;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VehicleSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLocationData();
    }

    protected function seedLocationData(): void
    {
        $province = Province::create([
            'code' => '31',
            'name' => 'DKI Jakarta'
        ]);

        $city = City::create([
            'province_id' => $province->id,
            'code' => '3171',
            'name' => 'Jakarta Pusat',
            'type' => 'Kota'
        ]);

        $district = District::create([
            'city_id' => $city->id,
            'code' => '317101',
            'name' => 'Gambir'
        ]);

        SubDistrict::create([
            'district_id' => $district->id,
            'code' => '3171011001',
            'name' => 'Gambir',
            'postal_code' => '10110'
        ]);
    }

    protected function createApprovedVehicle(array $attributes = []): Vehicle
    {
        $user = User::factory()->create();
        $province = Province::first();
        $city = City::first();

        return Vehicle::create(array_merge([
            'user_id' => $user->id,
            'category' => 'mobil',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'year' => 2020,
            'description' => 'Test vehicle',
            'starting_price' => 150000000,
            'status' => 'approved',
            'province_id' => $province->id,
            'city_id' => $city->id,
            'latitude' => -6.1754,
            'longitude' => 106.8272,
            'approved_at' => now(),
        ], $attributes));
    }

    public function test_can_search_vehicles_by_keyword(): void
    {
        $this->createApprovedVehicle(['brand' => 'Honda', 'model' => 'Jazz']);
        $this->createApprovedVehicle(['brand' => 'Toyota', 'model' => 'Yaris']);

        $response = $this->getJson('/api/vehicles/search?q=Honda');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_vehicles_by_category(): void
    {
        $this->createApprovedVehicle(['category' => 'mobil']);
        $this->createApprovedVehicle(['category' => 'motor']);

        $response = $this->getJson('/api/vehicles/search?category=mobil');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        foreach ($data as $vehicle) {
            $this->assertEquals('mobil', $vehicle['category']);
        }
    }

    public function test_can_filter_vehicles_by_province(): void
    {
        $province = Province::first();
        $this->createApprovedVehicle(['province_id' => $province->id]);

        $otherProvince = Province::create(['code' => '32', 'name' => 'Jawa Barat']);
        $otherCity = City::create([
            'province_id' => $otherProvince->id,
            'code' => '3273',
            'name' => 'Kota Bandung',
            'type' => 'Kota'
        ]);
        $this->createApprovedVehicle(['province_id' => $otherProvince->id, 'city_id' => $otherCity->id]);

        $response = $this->getJson("/api/vehicles/search?province_id={$province->id}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_vehicles_by_price_range(): void
    {
        $this->createApprovedVehicle(['starting_price' => 100000000]);
        $this->createApprovedVehicle(['starting_price' => 200000000]);
        $this->createApprovedVehicle(['starting_price' => 300000000]);

        $response = $this->getJson('/api/vehicles/search?min_price=150000000&max_price=250000000');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_vehicles_by_year_range(): void
    {
        $this->createApprovedVehicle(['year' => 2018]);
        $this->createApprovedVehicle(['year' => 2020]);
        $this->createApprovedVehicle(['year' => 2022]);

        $response = $this->getJson('/api/vehicles/search?year_from=2019&year_to=2021');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_search_vehicles_by_radius(): void
    {
        // Vehicle in Jakarta (-6.1754, 106.8272)
        $this->createApprovedVehicle([
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ]);

        // Vehicle far away (Bandung approximately -6.9175, 107.6191)
        $this->createApprovedVehicle([
            'latitude' => -6.9175,
            'longitude' => 107.6191,
        ]);

        // Search from Jakarta center with 50km radius
        $response = $this->getJson('/api/vehicles/search?lat=-6.2088&lng=106.8456&radius=50');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        // Should only return the Jakarta vehicle
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('distance_km', $data[0]);
    }

    public function test_can_sort_vehicles_by_price(): void
    {
        $this->createApprovedVehicle(['starting_price' => 200000000]);
        $this->createApprovedVehicle(['starting_price' => 100000000]);
        $this->createApprovedVehicle(['starting_price' => 300000000]);

        $response = $this->getJson('/api/vehicles/search?sort=price_asc');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertLessThanOrEqual($data[1]['price'], $data[0]['price']);
    }

    public function test_can_get_nearby_vehicles(): void
    {
        $this->createApprovedVehicle([
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ]);

        $response = $this->getJson('/api/vehicles/nearby?lat=-6.2088&lng=106.8456&radius=50');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'brand', 'model', 'distance_km']
                ]
            ]);
    }

    public function test_can_get_vehicle_map_data(): void
    {
        $vehicle = $this->createApprovedVehicle([
            'latitude' => -6.1754,
            'longitude' => 106.8272,
            'full_address' => 'Jl. Test No. 1, Jakarta',
        ]);

        $response = $this->getJson("/api/vehicles/{$vehicle->id}/map");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => ['id', 'brand', 'model', 'latitude', 'longitude', 'location']
            ]);
    }

    public function test_vehicle_map_returns_404_for_non_approved(): void
    {
        $vehicle = $this->createApprovedVehicle();
        $vehicle->update(['status' => 'pending']);

        $response = $this->getJson("/api/vehicles/{$vehicle->id}/map");

        $response->assertStatus(404);
    }

    public function test_search_pagination_works(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->createApprovedVehicle(['brand' => "Brand{$i}"]);
        }

        $response = $this->getJson('/api/vehicles/search?per_page=10');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonCount(10, 'data');
    }

    public function test_location_api_returns_provinces(): void
    {
        $response = $this->getJson('/api/locations/provinces');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name']
                ]
            ]);
    }

    public function test_location_api_returns_cities_by_province(): void
    {
        $province = Province::first();

        $response = $this->getJson("/api/locations/cities/{$province->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_location_api_returns_districts_by_city(): void
    {
        $city = City::first();

        $response = $this->getJson("/api/locations/districts/{$city->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    public function test_location_api_returns_sub_districts_with_postal_code(): void
    {
        $district = District::first();

        $response = $this->getJson("/api/locations/sub-districts/{$district->id}");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'postal_code']
                ]
            ]);
    }
}
