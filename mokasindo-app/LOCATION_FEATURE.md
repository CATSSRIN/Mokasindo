# Location Feature Documentation

This document describes the location feature implementation for vehicle registration and search in Mokasindo.

## Table of Contents
1. [Setup](#setup)
2. [Google Maps API Configuration](#google-maps-api-configuration)
3. [Database Setup](#database-setup)
4. [API Endpoints](#api-endpoints)
5. [Vue Components](#vue-components)
6. [Search with Radius](#search-with-radius)
7. [Troubleshooting](#troubleshooting)

---

## Setup

### Prerequisites
- Laravel 10+
- PHP 8.1+
- Vue.js 3
- Google Cloud Platform account

### Installation Steps

1. **Run migrations**
   ```bash
   php artisan migrate
   ```

2. **Seed location data**
   ```bash
   php artisan db:seed --class=IndonesiaLocationSeeder
   ```

3. **Configure Google Maps API** (see below)

4. **Build frontend assets**
   ```bash
   npm run build
   ```

---

## Google Maps API Configuration

### 1. Enable Required APIs in Google Cloud Console

Go to [Google Cloud Console](https://console.cloud.google.com/) and enable:

- ✅ **Maps JavaScript API** - For interactive maps
- ✅ **Geocoding API** - For reverse geocoding (coordinates to address)
- ✅ **Places API** - For location search box

### 2. Create API Keys

Create two API keys (recommended for security):

1. **Frontend Key** (restricted to HTTP referrers)
   - Add restrictions: `yourdomain.com/*`, `localhost:*`
   - Enable: Maps JavaScript API, Places API

2. **Backend Key** (restricted to IP addresses)
   - Add your server IP addresses
   - Enable: Geocoding API

### 3. Configure Environment Variables

Add to your `.env` file:

```env
GOOGLE_MAPS_API_KEY=your_frontend_api_key_here
GOOGLE_MAPS_GEOCODING_API_KEY=your_backend_api_key_here
```

### 4. Add API Key Meta Tag

In your main Blade layout (`resources/views/layouts/app.blade.php`):

```html
<meta name="google-maps-api-key" content="{{ config('services.google_maps.api_key') }}">
```

### 5. Security Best Practices

- Set API key restrictions in Google Cloud Console
- Enable billing alerts
- Monitor API usage regularly
- Use separate keys for frontend and backend
- Never commit API keys to version control

---

## Database Setup

### Migration

The migration adds the following columns:

**vehicles table:**
- `latitude` DECIMAL(10,8) - GPS latitude
- `longitude` DECIMAL(11,8) - GPS longitude
- `full_address` TEXT - Full address from geocoding

**users table:**
- `latitude` DECIMAL(10,8)
- `longitude` DECIMAL(11,8)
- `full_address` TEXT

### Seeder Data

The `IndonesiaLocationSeeder` includes:

- **34 Provinces** - All Indonesian provinces with BPS codes
- **100+ Cities** - Major cities and kabupaten
- **Districts** - Kecamatan for major cities (Jakarta, Surabaya, Bandung, Medan)
- **Sub-districts** - Kelurahan with postal codes for popular areas

Run seeder:
```bash
php artisan db:seed --class=IndonesiaLocationSeeder
```

---

## API Endpoints

### Location API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/locations/provinces` | List all provinces |
| GET | `/api/locations/cities/{province_id}` | List cities by province |
| GET | `/api/locations/districts/{city_id}` | List districts by city |
| GET | `/api/locations/sub-districts/{district_id}` | List sub-districts with postal codes |
| POST | `/api/locations/reverse-geocode` | Convert coordinates to address |

#### Reverse Geocode Request

```json
POST /api/locations/reverse-geocode
{
    "lat": -6.2088,
    "lng": 106.8456
}
```

#### Reverse Geocode Response

```json
{
    "status": "success",
    "data": {
        "full_address": "Jl. Sudirman No.1, Jakarta Pusat, DKI Jakarta",
        "province": "DKI Jakarta",
        "city": "Jakarta Pusat",
        "district": "Menteng",
        "sub_district": "Menteng",
        "postal_code": "10310"
    }
}
```

### Vehicle Search API

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/vehicles/search` | Search with filters |
| GET | `/api/vehicles/nearby` | Get nearby vehicles |
| GET | `/api/vehicles/{id}/map` | Get vehicle location for map |

#### Search Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `q` | string | Search keyword |
| `category` | string | `motor` or `mobil` |
| `province_id` | integer | Filter by province |
| `city_id` | integer | Filter by city |
| `district_id` | integer | Filter by district |
| `sub_district_id` | integer | Filter by sub-district |
| `lat` | float | User latitude (for radius search) |
| `lng` | float | User longitude (for radius search) |
| `radius` | float | Search radius in km (default: 50) |
| `min_price` | float | Minimum price |
| `max_price` | float | Maximum price |
| `year_from` | integer | Minimum year |
| `year_to` | integer | Maximum year |
| `sort` | string | `price_asc`, `price_desc`, `distance`, `newest` |
| `per_page` | integer | Items per page (default: 20) |

#### Search Response

```json
{
    "status": "success",
    "data": [
        {
            "id": 1,
            "brand": "Toyota",
            "model": "Avanza",
            "year": 2020,
            "price": 150000000,
            "distance_km": 5.2,
            "location": {
                "province": "DKI Jakarta",
                "city": "Jakarta Selatan",
                "district": "Kebayoran Baru",
                "sub_district": "Senayan"
            },
            "images": [...],
            "auction": {...}
        }
    ],
    "meta": {
        "total": 150,
        "per_page": 20,
        "current_page": 1,
        "last_page": 8
    }
}
```

---

## Vue Components

### LocationPicker

Interactive Google Maps picker component.

```vue
<LocationPicker
    v-model="location"
    :initial-lat="-6.2088"
    :initial-lng="106.8456"
    @location-changed="handleLocationChange"
/>
```

**Props:**
- `initialLat` (Number) - Initial latitude
- `initialLng` (Number) - Initial longitude
- `modelValue` (Object) - `{ lat, lng, address }`

**Events:**
- `update:modelValue` - Emitted when location changes
- `location-changed` - Emitted with full geocode data

**Features:**
- Draggable marker
- Search box with autocomplete
- "Use My Location" button
- Reverse geocoding
- Auto-fill form data

### VehicleLocationForm

Complete location form with cascade dropdowns.

```vue
<VehicleLocationForm
    v-model="locationForm"
    :errors="formErrors"
/>
```

**Props:**
- `modelValue` (Object) - Form data
- `errors` (Object) - Validation errors

**Features:**
- Google Maps picker integration
- Province → City → District → Sub-district cascade
- Auto-fill from map selection
- Postal code from sub-district

### VehicleSearch

Full search page with filters and map view.

**Features:**
- Keyword search
- Category filter
- Location filters (province, city)
- Price and year range filters
- Radius-based search
- List and map view toggle
- Pagination

---

## Search with Radius

The radius search uses the Haversine formula to calculate distance between coordinates.

### Vehicle Model Scope

```php
public function scopeNearby($query, $lat, $lng, $radius = 50)
{
    return $query->selectRaw("
        vehicles.*,
        (6371 * acos(cos(radians(?)) 
        * cos(radians(latitude)) 
        * cos(radians(longitude) - radians(?)) 
        + sin(radians(?)) 
        * sin(radians(latitude)))) AS distance_km
    ", [$lat, $lng, $lat])
    ->whereNotNull('latitude')
    ->whereNotNull('longitude')
    ->having('distance_km', '<=', $radius)
    ->orderBy('distance_km');
}
```

### Usage Example

```php
// Get vehicles within 25km of a location
$vehicles = Vehicle::approved()
    ->nearby(-6.2088, 106.8456, 25)
    ->get();

// Each vehicle has distance_km attribute
foreach ($vehicles as $vehicle) {
    echo "{$vehicle->brand} - {$vehicle->distance_km} km away";
}
```

---

## Troubleshooting

### Google Maps not loading

1. Check API key is set in `.env`
2. Verify meta tag is present in HTML
3. Check browser console for errors
4. Ensure APIs are enabled in Google Cloud Console
5. Check API key restrictions

### Geocoding returns empty

1. Verify Geocoding API is enabled
2. Check backend API key has correct permissions
3. Review server IP restrictions
4. Check Laravel logs for errors

### Location cascade not working

1. Run seeder: `php artisan db:seed --class=IndonesiaLocationSeeder`
2. Check API routes are registered
3. Verify database has location data
4. Check browser network tab for API errors

### Distance calculation issues

1. Ensure latitude/longitude columns exist
2. Verify coordinates are stored correctly (-90 to 90 for lat, -180 to 180 for lng)
3. Check MySQL version supports the query syntax

### Performance optimization

1. Index latitude and longitude columns (done in migration)
2. Cache location API responses (implemented)
3. Use marker clustering for many results
4. Limit search radius to reasonable values

---

## Support

For issues or questions:
1. Check this documentation
2. Review server logs
3. Check browser console
4. Create an issue in the repository
