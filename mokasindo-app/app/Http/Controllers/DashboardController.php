<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Transaction;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $stats = [
            'total_vehicles' => $user->vehicles()->count(),
            'pending_vehicles' => $user->vehicles()->pending()->count(),
            'active_bids' => $user->bids()->whereHas('auction', function ($q) {
                $q->where('status', 'active');
            })->count(),
            'won_auctions' => $user->wonAuctions()->count(),
            'total_purchases' => $user->purchases()->count(),
            'total_sales' => $user->sales()->count(),
        ];

        $recentVehicles = $user->vehicles()->with('images')->latest()->take(5)->get();
        $recentBids = $user->bids()->with(['auction.vehicle'])->latest()->take(5)->get();
        $notifications = $user->notifications()->unread()->latest()->take(5)->get();

        return view('dashboard.index', compact('stats', 'recentVehicles', 'recentBids', 'notifications'));
    }

    public function vehicles()
    {
        $vehicles = Auth::user()->vehicles()
            ->with(['images', 'auction'])
            ->latest()
            ->paginate(10);

        return view('dashboard.vehicles', compact('vehicles'));
    }

    public function bids()
    {
        $bids = Auth::user()->bids()
            ->with(['auction.vehicle.images'])
            ->latest()
            ->paginate(10);

        return view('dashboard.bids', compact('bids'));
    }

    public function transactions()
    {
        $user = Auth::user();
        
        $purchases = $user->purchases()
            ->with(['vehicle', 'auction', 'seller'])
            ->latest()
            ->paginate(10, ['*'], 'purchases_page');

        $sales = $user->sales()
            ->with(['vehicle', 'auction', 'buyer'])
            ->latest()
            ->paginate(10, ['*'], 'sales_page');

        return view('dashboard.transactions', compact('purchases', 'sales'));
    }

    public function notifications()
    {
        $notifications = Auth::user()->notifications()
            ->latest()
            ->paginate(20);

        return view('dashboard.notifications', compact('notifications'));
    }

    public function markNotificationRead(Notification $notification)
    {
        $this->authorize('update', $notification);
        
        $notification->markAsRead();

        return back();
    }

    public function profile()
    {
        return view('dashboard.profile', ['user' => Auth::user()]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:500',
            'province_id' => 'nullable|exists:provinces,id',
            'city_id' => 'nullable|exists:cities,id',
            'district_id' => 'nullable|exists:districts,id',
            'sub_district_id' => 'nullable|exists:sub_districts,id',
            'postal_code' => 'nullable|string|max:10',
        ]);

        $user->update($validated);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}
