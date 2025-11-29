<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'category',
        'brand',
        'model',
        'year',
        'color',
        'license_plate',
        'mileage',
        'description',
        'starting_price',
        'transmission',
        'fuel_type',
        'engine_capacity',
        'condition',
        'province_id',
        'city_id',
        'district_id',
        'sub_district_id',
        'postal_code',
        'latitude',
        'longitude',
        'full_address',
        'status',
        'rejection_reason',
        'approved_at',
        'approved_by',
        'views_count',
    ];

    protected $casts = [
        'year' => 'integer',
        'mileage' => 'integer',
        'starting_price' => 'decimal:2',
        'engine_capacity' => 'integer',
        'approved_at' => 'datetime',
        'views_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function images()
    {
        return $this->hasMany(VehicleImage::class)->orderBy('order');
    }

    public function primaryImage()
    {
        return $this->hasOne(VehicleImage::class)->where('is_primary', true);
    }

    public function auction()
    {
        return $this->hasOne(Auction::class);
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function subDistrict()
    {
        return $this->belongsTo(SubDistrict::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeMotor($query)
    {
        return $query->where('category', 'motor');
    }

    public function scopeMobil($query)
    {
        return $query->where('category', 'mobil');
    }

    // Helper Methods
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    public function isApproved()
    {
        return $this->status === 'approved';
    }

    public function isPending()
    {
        return $this->status === 'pending';
    }

    public function isSold()
    {
        return $this->status === 'sold';
    }

    /**
     * Scope for searching vehicles within a radius using Haversine formula
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $lat Latitude of center point
     * @param float $lng Longitude of center point
     * @param float $radius Radius in kilometers (default: 50)
     * @return \Illuminate\Database\Eloquent\Builder
     */
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
}
