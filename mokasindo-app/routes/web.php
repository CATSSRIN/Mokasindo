<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WishlistController;
use App\Models\Page;

Route::get('/', function () {
    return view('landing');
});

Route::prefix('etalase')->group(function () {
    Route::get('/filters', [VehicleController::class, 'filters']);
    Route::get('/vehicles', [VehicleController::class, 'index']);
    Route::get('/vehicles/{id}', [VehicleController::class, 'show']);
});

Route::middleware('auth')->group(function () {
    Route::get('/wishlists', [WishlistController::class, 'index']);
    Route::post('/wishlists', [WishlistController::class, 'store']);
    Route::delete('/wishlists/{id}', [WishlistController::class, 'destroy']);
});

// ====================================================
// 4. HELPER TESTING (Force Login)
// ====================================================
// Jalankan URL ini sekali di browser agar kamu login otomatis sebagai ID 1 (http://mokasindo.test/force-login)

Route::get('/force-login', function () {
    $user = \App\Models\User::find(1);
    
    if (!$user) {
        // Buat user dummy jika belum ada
        $user = \App\Models\User::create([
            'id' => 1,
            'name' => 'Tester User',
            'email' => 'tester@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    Auth::login($user);
    
    return "<h1>Berhasil Login!</h1> <p>Login sebagai: <b>" . $user->name . "</b></p><p>Silakan akses <a href='/wishlists'>/wishlists</a> atau <a href='/etalase/vehicles'>/etalase/vehicles</a></p>";
});
