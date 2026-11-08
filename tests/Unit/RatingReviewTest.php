<?php

use App\Http\Controllers\RatingController;
use App\Models\Alamat;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('sanitizeReview membersihkan tag HTML dan skrip XSS berbahaya', function () {
    $dirty = '<script>alert("hack")</script> Kualitas obat <b>sangat baik</b>! ';
    $cleaned = RatingController::sanitizeReview($dirty);

    expect($cleaned)->toBe('alert("hack") Kualitas obat sangat baik!')
        ->and($cleaned)->not->toContain('<script>')
        ->and($cleaned)->not->toContain('<b>');
});

test('sanitizeReview menangani teks kosong atau hanya whitespace', function () {
    expect(RatingController::sanitizeReview(''))->toBe('')
        ->and(RatingController::sanitizeReview('   '))->toBe('')
        ->and(RatingController::sanitizeReview(null))->toBe('');
});

test('getStarLabel mengembalikan label sentimen ulasan yang sesuai', function () {
    expect(RatingController::getStarLabel(1))->toBe('Sangat Buruk')
        ->and(RatingController::getStarLabel(2))->toBe('Buruk')
        ->and(RatingController::getStarLabel(3))->toBe('Cukup')
        ->and(RatingController::getStarLabel(4))->toBe('Puas')
        ->and(RatingController::getStarLabel(5))->toBe('Sangat Puas')
        ->and(RatingController::getStarLabel(0))->toBe('Tidak Valid')
        ->and(RatingController::getStarLabel(6))->toBe('Tidak Valid');
});

test('isVerifiedBuyer mengembalikan false jika user belum pernah membeli produk', function () {
    $user = User::factory()->create();
    $productId = 999;

    expect(RatingController::isVerifiedBuyer($user->id, $productId))->toBeFalse();
    expect(RatingController::isVerifiedBuyer(null, $productId))->toBeFalse();
});

test('isVerifiedBuyer mengembalikan true jika user memiliki pesanan sukses untuk produk tersebut', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    $category = new Category();
    $category->judulKategori = 'Vitamin & Suplemen';
    $category->save();

    $toko = Toko::create([
        'user_id' => $seller->id,
        'nama_toko' => 'Apotek Zach',
        'deskripsi_toko' => 'Apotek resmi',
        'lokasi' => 'Jakarta',
        'provinsi' => 'DKI Jakarta',
        'kota' => 'Jakarta Selatan',
        'kecamatan' => 'Tebet',
        'kode_pos' => '12810',
        'kode_wilayah' => '31.74',
        'logo_path' => 'toko/zach.webp',
    ]);

    $product = Product::create([
        'toko_id' => $toko->id,
        'category_id' => $category->id,
        'nama' => 'Vitamin D3 1000IU',
        'deskripsi' => 'Suplemen kesehatan tulang',
        'harga' => 50000,
        'diskon' => 0,
        'stok' => 100,
        'imagePath' => 'products/vitamind3.jpg',
    ]);

    $order = Order::create([
        'user_id' => $buyer->id,
        'status' => 'paid',
        'total_harga' => 50000,
        'alamat_id' => null,
        'shipping_cost' => 10000,
    ]);

    OrderItems::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'nama_produk' => $product->nama,
        'harga' => 50000,
        'qty' => 1,
        'subtotal' => 50000,
    ]);

    // Verifikasi pembeli sah terdeteksi
    expect(RatingController::isVerifiedBuyer($buyer->id, $product->id))->toBeTrue();

    // Verifikasi pengguna lain yang tidak beli produk ini tetap ditolak
    $otherUser = User::factory()->create();
    expect(RatingController::isVerifiedBuyer($otherUser->id, $product->id))->toBeFalse();
});

test('isVerifiedBuyer menolak jika status pesanan masih pending atau cancelled', function () {
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    $category = new Category();
    $category->judulKategori = 'Obat Umum';
    $category->save();

    $toko = Toko::create([
        'user_id' => $seller->id,
        'nama_toko' => 'Apotek Uji',
        'deskripsi_toko' => 'Deskripsi',
        'lokasi' => 'Bandung',
        'provinsi' => 'Jabar',
        'kota' => 'Bandung',
        'kecamatan' => 'Coblong',
        'kode_pos' => '40132',
        'kode_wilayah' => '32.73',
        'logo_path' => 'toko/logo.webp',
    ]);

    $product = Product::create([
        'toko_id' => $toko->id,
        'category_id' => $category->id,
        'nama' => 'Paracetamol',
        'deskripsi' => 'Obat demam',
        'harga' => 10000,
        'stok' => 20,
        'imagePath' => 'products/paracetamol.jpg',
    ]);

    $orderPending = Order::create([
        'user_id' => $buyer->id,
        'status' => 'pending',
        'total_harga' => 10000,
        'alamat_id' => null,
        'shipping_cost' => 5000,
    ]);

    OrderItems::create([
        'order_id' => $orderPending->id,
        'product_id' => $product->id,
        'nama_produk' => $product->nama,
        'harga' => 10000,
        'qty' => 1,
        'subtotal' => 10000,
    ]);

    // Pesanan pending belum dibayar, belum sah memberikan ulasan
    expect(RatingController::isVerifiedBuyer($buyer->id, $product->id))->toBeFalse();

    // Jika dibatalkan juga tidak sah
    $orderPending->update(['status' => 'cancelled']);
    expect(RatingController::isVerifiedBuyer($buyer->id, $product->id))->toBeFalse();
});
