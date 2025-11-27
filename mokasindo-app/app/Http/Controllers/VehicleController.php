<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleImage;
use App\Models\Province;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $query = Vehicle::with(['user', 'images', 'province', 'city', 'auction'])
            ->approved();

        // Filter by category
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filter by brand
        if ($request->filled('brand')) {
            $query->where('brand', 'like', '%' . $request->brand . '%');
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('starting_price', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('starting_price', '<=', $request->max_price);
        }

        // Filter by year
        if ($request->filled('year_from')) {
            $query->where('year', '>=', $request->year_from);
        }
        if ($request->filled('year_to')) {
            $query->where('year', '<=', $request->year_to);
        }

        // Filter by location
        if ($request->filled('province_id')) {
            $query->where('province_id', $request->province_id);
        }
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->city_id);
        }

        // Sort
        $sortBy = $request->get('sort', 'created_at');
        $sortOrder = $request->get('order', 'desc');
        
        if ($sortBy === 'price') {
            $query->orderBy('starting_price', $sortOrder);
        } elseif ($sortBy === 'year') {
            $query->orderBy('year', $sortOrder);
        } elseif ($sortBy === 'popular') {
            $query->orderBy('views_count', 'desc');
        } else {
            $query->orderBy('created_at', $sortOrder);
        }

        $vehicles = $query->paginate(12);
        $provinces = Province::orderBy('name')->get();

        return view('vehicles.index', compact('vehicles', 'provinces'));
    }

    public function show(Vehicle $vehicle)
    {
        $vehicle->load(['user', 'images', 'province', 'city', 'district', 'subDistrict', 'auction.bids']);
        $vehicle->incrementViews();

        return view('vehicles.show', compact('vehicle'));
    }

    public function create()
    {
        $user = Auth::user();
        
        if (!$user->canPostThisWeek()) {
            return back()->with('error', 'Anda sudah mencapai batas posting minggu ini (2 iklan/minggu). Upgrade ke Member untuk posting unlimited.');
        }

        $provinces = Province::orderBy('name')->get();
        return view('vehicles.create', compact('provinces'));
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        
        if (!$user->canPostThisWeek()) {
            return back()->with('error', 'Batas posting tercapai.');
        }

        $validated = $request->validate([
            'category' => 'required|in:motor,mobil',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'license_plate' => 'nullable|string|max:20',
            'mileage' => 'nullable|integer|min:0',
            'description' => 'required|string',
            'starting_price' => 'required|numeric|min:1000000',
            'transmission' => 'nullable|string|max:50',
            'fuel_type' => 'nullable|string|max:50',
            'engine_capacity' => 'nullable|integer|min:0',
            'condition' => 'nullable|string|max:50',
            'province_id' => 'nullable|exists:provinces,id',
            'city_id' => 'nullable|exists:cities,id',
            'district_id' => 'nullable|exists:districts,id',
            'sub_district_id' => 'nullable|exists:sub_districts,id',
            'postal_code' => 'nullable|string|max:10',
            'images.*' => 'nullable|image|max:5120',
        ]);

        $validated['user_id'] = $user->id;
        $validated['status'] = 'pending';

        $vehicle = Vehicle::create($validated);

        // Handle images
        if ($request->hasFile('images')) {
            $order = 0;
            foreach ($request->file('images') as $image) {
                $path = $image->store('vehicles', 'public');
                VehicleImage::create([
                    'vehicle_id' => $vehicle->id,
                    'image_path' => $path,
                    'order' => $order,
                    'is_primary' => $order === 0,
                ]);
                $order++;
            }
        }

        $user->incrementPostCount();

        return redirect()->route('dashboard.vehicles')
            ->with('success', 'Kendaraan berhasil ditambahkan dan menunggu persetujuan admin.');
    }

    public function edit(Vehicle $vehicle)
    {
        $this->authorize('update', $vehicle);
        
        $provinces = Province::orderBy('name')->get();
        return view('vehicles.edit', compact('vehicle', 'provinces'));
    }

    public function update(Request $request, Vehicle $vehicle)
    {
        $this->authorize('update', $vehicle);

        $validated = $request->validate([
            'category' => 'required|in:motor,mobil',
            'brand' => 'required|string|max:100',
            'model' => 'required|string|max:100',
            'year' => 'required|integer|min:1900|max:' . (date('Y') + 1),
            'color' => 'nullable|string|max:50',
            'license_plate' => 'nullable|string|max:20',
            'mileage' => 'nullable|integer|min:0',
            'description' => 'required|string',
            'starting_price' => 'required|numeric|min:1000000',
            'transmission' => 'nullable|string|max:50',
            'fuel_type' => 'nullable|string|max:50',
            'engine_capacity' => 'nullable|integer|min:0',
            'condition' => 'nullable|string|max:50',
            'province_id' => 'nullable|exists:provinces,id',
            'city_id' => 'nullable|exists:cities,id',
            'district_id' => 'nullable|exists:districts,id',
            'sub_district_id' => 'nullable|exists:sub_districts,id',
            'postal_code' => 'nullable|string|max:10',
        ]);

        // Reset to pending if already approved
        if ($vehicle->isApproved()) {
            $validated['status'] = 'pending';
        }

        $vehicle->update($validated);

        return redirect()->route('dashboard.vehicles')
            ->with('success', 'Kendaraan berhasil diperbarui.');
    }

    public function destroy(Vehicle $vehicle)
    {
        $this->authorize('delete', $vehicle);

        // Delete images from storage
        foreach ($vehicle->images as $image) {
            Storage::disk('public')->delete($image->image_path);
        }

        $vehicle->delete();

        return redirect()->route('dashboard.vehicles')
            ->with('success', 'Kendaraan berhasil dihapus.');
    }
}
