<?php

namespace App\Http\Controllers;

use App\Models\Auction;
use App\Models\Bid;
use App\Services\AuctionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BidController extends Controller
{
    protected $auctionService;
    protected $notificationService;

    public function __construct(AuctionService $auctionService, NotificationService $notificationService)
    {
        $this->auctionService = $auctionService;
        $this->notificationService = $notificationService;
    }

    public function store(Request $request, Auction $auction)
    {
        $user = Auth::user();

        // Check if auction is active
        if (!$auction->isActive()) {
            return response()->json(['error' => 'Lelang tidak aktif.'], 400);
        }

        // Check if user has paid deposit
        $deposit = $auction->deposits()
            ->where('user_id', $user->id)
            ->where('status', 'paid')
            ->first();

        if (!$deposit) {
            return response()->json(['error' => 'Anda harus membayar deposit terlebih dahulu.'], 400);
        }

        // Check if user is the vehicle owner
        if ($auction->vehicle->user_id === $user->id) {
            return response()->json(['error' => 'Anda tidak dapat menawar kendaraan sendiri.'], 400);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:' . ($auction->current_price + 100000),
        ]);

        // Get current highest bidder for notification
        $previousHighestBid = $auction->bids()->orderBy('bid_amount', 'desc')->first();

        // Create the bid
        $bid = Bid::create([
            'auction_id' => $auction->id,
            'user_id' => $user->id,
            'bid_amount' => $validated['amount'],
            'previous_amount' => $auction->current_price,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Update auction current price
        $auction->update([
            'current_price' => $validated['amount'],
            'total_bids' => $auction->total_bids + 1,
        ]);

        // Check for auto-extend (if bid in last 5 minutes)
        $this->auctionService->checkAutoExtend($auction);

        // Notify previous highest bidder that they've been outbid
        if ($previousHighestBid && $previousHighestBid->user_id !== $user->id) {
            $this->notificationService->sendOutbidNotification(
                $previousHighestBid->user,
                $auction,
                $validated['amount']
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Bid berhasil!',
            'current_price' => $auction->current_price,
            'total_bids' => $auction->total_bids,
            'end_time' => $auction->end_time->toIso8601String(),
        ]);
    }

    public function history(Auction $auction)
    {
        $bids = $auction->bids()
            ->with('user:id,name')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        return response()->json($bids);
    }
}
