<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Vehicle;
use App\Models\Deposit;
use App\Models\Setting;
use App\Services\AuctionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuctionController extends Controller
{
    protected $auctionService;

    public function __construct(AuctionService $auctionService)
    {
        $this->auctionService = $auctionService;
    }

    public function index(Request $request)
    {
        $query = Auction::with(['vehicle.images', 'vehicle.user', 'bids'])
            ->whereHas('vehicle', function ($q) {
                $q->where('status', 'approved');
            });

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->whereIn('status', ['active', 'scheduled']);
        }

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('vehicle', function ($q) use ($request) {
                $q->where('category', $request->category);
            });
        }

        $auctions = $query->orderBy('end_time', 'asc')->paginate(12);

        return view('auctions.index', compact('auctions'));
    }

    public function show(Auction $auction)
    {
        $auction->load(['vehicle.images', 'vehicle.user', 'bids.user', 'winner', 'deposits']);
        
        $userDeposit = null;
        $canBid = false;
        
        if (Auth::check()) {
            $userDeposit = $auction->deposits()
                ->where('user_id', Auth::id())
                ->where('status', 'paid')
                ->first();
            $canBid = $userDeposit && $auction->isActive();
        }

        return view('auctions.show', compact('auction', 'userDeposit', 'canBid'));
    }

    public function create(Vehicle $vehicle)
    {
        $this->authorize('createAuction', $vehicle);

        if (!$vehicle->isApproved()) {
            return back()->with('error', 'Kendaraan harus disetujui terlebih dahulu.');
        }

        if ($vehicle->auction) {
            return back()->with('error', 'Kendaraan sudah memiliki lelang aktif.');
        }

        $settings = [
            'default_duration' => Setting::get('auction_default_duration_hours', 24),
            'deposit_percentage' => Setting::get('auction_deposit_percentage', 5),
        ];

        return view('auctions.create', compact('vehicle', 'settings'));
    }

    public function store(Request $request, Vehicle $vehicle)
    {
        $this->authorize('createAuction', $vehicle);

        $validated = $request->validate([
            'starting_price' => 'required|numeric|min:1000000',
            'reserve_price' => 'nullable|numeric|min:0',
            'duration_hours' => 'required|integer|min:12|max:168',
            'start_time' => 'required|date|after:now',
        ]);

        $depositPercentage = Setting::get('auction_deposit_percentage', 5);
        $depositAmount = $validated['starting_price'] * ($depositPercentage / 100);

        $auction = Auction::create([
            'vehicle_id' => $vehicle->id,
            'starting_price' => $validated['starting_price'],
            'current_price' => $validated['starting_price'],
            'reserve_price' => $validated['reserve_price'],
            'deposit_amount' => $depositAmount,
            'deposit_percentage' => $depositPercentage,
            'start_time' => $validated['start_time'],
            'end_time' => now()->parse($validated['start_time'])->addHours($validated['duration_hours']),
            'duration_hours' => $validated['duration_hours'],
            'status' => 'scheduled',
            'payment_deadline_hours' => Setting::get('auction_payment_deadline_hours', 24),
        ]);

        return redirect()->route('auctions.show', $auction)
            ->with('success', 'Lelang berhasil dibuat.');
    }

    public function payDeposit(Request $request, Auction $auction)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        // Check if already has paid deposit
        $existingDeposit = $auction->deposits()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->first();

        if ($existingDeposit) {
            return back()->with('info', 'Anda sudah membayar deposit untuk lelang ini.');
        }

        // Create deposit record
        $deposit = Deposit::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'amount' => $auction->deposit_amount,
            'status' => 'pending',
        ]);

        // For demo purposes, mark as paid immediately (in production, redirect to payment gateway)
        $deposit->markAsPaid();

        return back()->with('success', 'Deposit berhasil dibayar. Anda sekarang dapat mengikuti lelang.');
    }
}
