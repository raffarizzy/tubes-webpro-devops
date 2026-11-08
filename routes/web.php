<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\KeranjangController;
use App\Http\Controllers\BarangKeranjangController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\AlamatController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderProxyController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TokoController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// ============================================
// PUBLIC ROUTES
// ============================================

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/produk/{id}', [ProductController::class, 'show'])
    ->name('produk.detail');

// API Products (Public)
Route::get('/api/products', [ProductController::class, 'index'])->name('api.products.index');
Route::get('/api/products/{id}', [ProductController::class, 'show'])->name('api.products.show');

// API Categories (Public)
Route::get('/api/categories', [CategoryController::class, 'index'])->name('api.categories.index');
Route::post('/api/categories', [CategoryController::class, 'store'])->name('api.categories.store');


// ============================================
// AUTH REQUIRED ROUTES (Harus Login)
// ============================================

Route::middleware('auth')->group(function () {
    
    // Dashboard
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->middleware('verified')->name('dashboard');
    
    // Profile Routes
    Route::get('/profile', function () {
        return view('profil');
    })->name('profile.show');
    
    Route::get('/edit_profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/edit_profil', [ProfileController::class, 'update_pfp'])->name('profile.update.pfpPath');
    
    // ============================================
    // KERANJANG (CART)
    // ============================================
    Route::get('/keranjang', [KeranjangController::class, 'index'])->name('keranjang');
    Route::get('/keranjang/data', [KeranjangController::class, 'getCartData'])->name('keranjang.data');
    Route::delete('/keranjang/clear', [KeranjangController::class, 'clear'])->name('keranjang.clear');
    
    // Item di dalam keranjang
    Route::post('/keranjang/item', [BarangKeranjangController::class, 'store'])->name('keranjang.item.store');
    Route::put('/keranjang/item/{id}', [BarangKeranjangController::class, 'update'])->name('keranjang.item.update');
    Route::delete('/keranjang/item/{id}', [BarangKeranjangController::class, 'destroy'])->name('keranjang.item.destroy');
    
    // ============================================
    // CHECKOUT & PAYMENT (Server-Side)
    // ============================================
    // Menampilkan halaman checkout dengan data DB
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout');
    
    // Proses pembuatan invoice Xendit dan simpan Order ke DB
    Route::post('/checkout/pay', [CheckoutController::class, 'pay'])->name('checkout.pay');
    
    // Callback Pembayaran
    Route::get('/payment/success', [CheckoutController::class, 'paymentSuccess'])->name('payment.success');
    Route::get('/payment/failed', function () {
        return redirect()->route('checkout')->with('error', 'Pembayaran gagal atau dibatalkan.');
    })->name('payment.failed');

    // ============================================
    // ORDERS / RIWAYAT PESANAN
    // ============================================
    Route::get('/riwayat-pesanan', [OrderController::class, 'riwayatPesanan'])->name('riwayat.pesanan');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.detail');
    Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelForm'])->name('orders.cancel');
    Route::post('/orders/{id}/finish', [OrderController::class, 'finishOrder'])->name('orders.finish');
    Route::get('/ratings', [RatingController::class, 'index'])->name('ratings.index');
    Route::get('/ratings/create/{productId}', [RatingController::class, 'createRating'])->name('ratings.create');
    Route::post('/ratings', [RatingController::class, 'store'])->name('ratings.store');
    Route::delete('/ratings/{id}', [RatingController::class, 'destroy'])->name('ratings.destroy');
    //sblmaneh2


    // ============================================
    // ORDERS API - Node.js Proxy (NEW)
    // ============================================
    Route::post('/api/node/orders', [OrderProxyController::class, 'createOrder'])->name('orders.node.create');
    Route::get('/api/node/orders/{id}', [OrderProxyController::class, 'getOrder'])->name('orders.node.get');
    Route::get('/api/node/orders/user/{userId}', [OrderProxyController::class, 'getUserOrders'])->name('orders.node.user');

    // API Orders (Laravel - untuk backward compatibility)
    Route::get('/api/orders/history', [OrderController::class, 'history'])->name('orders.history');
    Route::post('/api/orders', [OrderController::class, 'store'])->name('orders.store');
    Route::post('/api/orders/{id}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel.api');
    Route::get('/api/orders/{id}', [OrderController::class, 'showApi'])->name('orders.show.api');
    
    // ============================================
    // ALAMAT MANAGEMENT (AJAX & Web)
    // ============================================
    Route::get('/alamat', [AlamatController::class, 'index'])->name('alamat.index');
    Route::post('/alamat', [AlamatController::class, 'store'])->name('alamat.store');
    Route::put('/alamat/{id}', [AlamatController::class, 'update'])->name('alamat.update');
    Route::delete('/alamat/{id}', [AlamatController::class, 'destroy'])->name('alamat.destroy');
    
    // ============================================
    // TOKO MANAGEMENT
    // ============================================
    Route::get('/toko', [TokoController::class, 'index'])->name('profil_toko');
    Route::get('/toko/verify', [TokoController::class, 'verify'])->name('toko.verify');
    Route::get('/toko/create', [TokoController::class, 'create'])->name('toko.create');
    Route::get('/toko/{id}', [TokoController::class, 'show'])->name('toko.show');
    Route::post('/toko', [TokoController::class, 'store'])->name('toko.store');
    Route::put('/toko/{id}', [TokoController::class, 'update'])->name('toko.update');
    Route::delete('/toko/{id}', [TokoController::class, 'destroy'])->name('toko.destroy');
    
    // Alur Pesanan Toko
    Route::post('/toko/orders/{id}/accept', [TokoController::class, 'acceptOrder'])->name('toko.order.accept');
    Route::post('/toko/orders/{id}/reject', [TokoController::class, 'rejectOrder'])->name('toko.order.reject');
    Route::post('/toko/orders/{id}/ship', [TokoController::class, 'shipOrder'])->name('toko.order.ship');
    
    // ============================================
    // PRODUCT MANAGEMENT (Oleh Penjual)
    // ============================================
    Route::post('/product/store', [ProductController::class, 'store'])->name('product.store');
    Route::post('/product/import', [ProductController::class, 'import'])->name('product.import');
    Route::post('/product/import-bulk', [ProductController::class, 'importBulk'])->name('product.import.bulk');
    Route::put('/product/{id}', [ProductController::class, 'update'])->name('product.update');
    Route::delete('/product/{id}', [ProductController::class, 'destroy'])->name('product.destroy');

    // ============================================
    // WILAYAH (PROVINSI, KOTA, KECAMATAN)
    // ============================================
    Route::get('/api/wilayah/provinsi', [\App\Http\Controllers\WilayahController::class, 'getProvinces']);
    Route::get('/api/wilayah/kota/{provinsi_kode}', [\App\Http\Controllers\WilayahController::class, 'getCities']);
    Route::get('/api/wilayah/kecamatan/{kota_kode}', [\App\Http\Controllers\WilayahController::class, 'getDistricts']);

    // ============================================
    // SHIPPING (KLIKRESI)
    // ============================================
    Route::post('/api/shipping/rates', [\App\Http\Controllers\ShippingController::class, 'getRates'])->name('shipping.rates');
    Route::get('/api/shipping/track/{tracking_number}/{courier_code}', [\App\Http\Controllers\ShippingController::class, 'trackOrder'])->name('shipping.track');
});

require __DIR__.'/auth.php';