<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Province;
use App\Models\City;
use App\Models\District;
use App\Models\SubDistrict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class LocationController extends Controller
{
    /**
     * Get all provinces
     */
    public function provinces()
    {
        $provinces = Cache::remember('provinces', 3600, function () {
            return Province::orderBy('name')->get(['id', 'code', 'name']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $provinces
        ]);
    }

    /**
     * Get cities by province
     */
    public function cities($provinceId)
    {
        $cities = Cache::remember("cities_{$provinceId}", 3600, function () use ($provinceId) {
            return City::where('province_id', $provinceId)
                ->orderBy('name')
                ->get(['id', 'province_id', 'code', 'name', 'type']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $cities
        ]);
    }

    /**
     * Get districts by city
     */
    public function districts($cityId)
    {
        $districts = Cache::remember("districts_{$cityId}", 3600, function () use ($cityId) {
            return District::where('city_id', $cityId)
                ->orderBy('name')
                ->get(['id', 'city_id', 'code', 'name']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $districts
        ]);
    }

    /**
     * Get sub-districts by district
     */
    public function subDistricts($districtId)
    {
        $subDistricts = Cache::remember("sub_districts_{$districtId}", 3600, function () use ($districtId) {
            return SubDistrict::where('district_id', $districtId)
                ->orderBy('name')
                ->get(['id', 'district_id', 'code', 'name', 'postal_code']);
        });

        return response()->json([
            'status' => 'success',
            'data' => $subDistricts
        ]);
    }

    /**
     * Reverse geocode coordinates to address using Google Maps API
     */
    public function reverseGeocode(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
        ]);

        $lat = $request->lat;
        $lng = $request->lng;

        // Check cache first
        $cacheKey = "geocode_{$lat}_{$lng}";
        $cached = Cache::get($cacheKey);
        if ($cached) {
            return response()->json([
                'status' => 'success',
                'data' => $cached
            ]);
        }

        $apiKey = config('services.google_maps.geocoding_api_key');
        
        if (!$apiKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Google Maps API key not configured'
            ], 500);
        }

        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'latlng' => "{$lat},{$lng}",
                'key' => $apiKey,
                'language' => 'id',
                'result_type' => 'street_address|administrative_area_level_4|administrative_area_level_3|administrative_area_level_2|administrative_area_level_1'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if ($data['status'] === 'OK' && !empty($data['results'])) {
                    $result = $this->parseGeocodingResult($data['results']);
                    
                    // Cache for 24 hours
                    Cache::put($cacheKey, $result, 86400);
                    
                    return response()->json([
                        'status' => 'success',
                        'data' => $result
                    ]);
                }
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'No results found for the given coordinates'
                ], 404);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch geocoding data'
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Geocoding service unavailable'
            ], 500);
        }
    }

    /**
     * Parse Google Maps geocoding result
     */
    private function parseGeocodingResult(array $results): array
    {
        $address = [
            'full_address' => $results[0]['formatted_address'] ?? '',
            'province' => null,
            'city' => null,
            'district' => null,
            'sub_district' => null,
            'postal_code' => null,
        ];

        foreach ($results as $result) {
            foreach ($result['address_components'] as $component) {
                $types = $component['types'];

                if (in_array('administrative_area_level_1', $types)) {
                    $address['province'] = $component['long_name'];
                }
                if (in_array('administrative_area_level_2', $types)) {
                    $address['city'] = $component['long_name'];
                }
                if (in_array('administrative_area_level_3', $types)) {
                    $address['district'] = $component['long_name'];
                }
                if (in_array('administrative_area_level_4', $types)) {
                    $address['sub_district'] = $component['long_name'];
                }
                if (in_array('postal_code', $types)) {
                    $address['postal_code'] = $component['long_name'];
                }
            }
        }

        return $address;
    }
}
