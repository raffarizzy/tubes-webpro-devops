<?php

use App\Http\Controllers\RatingController;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Product;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createTestProductFixture(): array
{
    $seller = User::factory()->create();
    $buyer = User::factory()->create();

    $category = new Category();
    $category->judulKategori = 'Kesehatan & Suplemen';
    $category->save();

    $toko = Toko::create([
        'user_id' => $seller->id,
        'nama_toko' => 'Apotek Resmi Zach',
        'deskripsi_toko' => 'Apotek resmi terpercaya',
        'lokasi' => 'Jakarta Selatan',
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
        'nama' => 'Vitamin C 500mg',
        'deskripsi' => 'Suplemen vitamin daya tahan tubuh',
        'harga' => 25000,
        'diskon' => 0,
        'stok' => 50,
        'imagePath' => 'products/vitaminc.jpg',
    ]);

    return [$seller, $buyer, $product];
}

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
    expect(RatingController::isVerifiedBuyer($user->id, 999))->toBeFalse();
    expect(RatingController::isVerifiedBuyer(null, 999))->toBeFalse();
});

test('isVerifiedBuyer mengembalikan true jika user memiliki pesanan sukses untuk produk tersebut', function () {
    [, $buyer, $product] = createTestProductFixture();

    $order = Order::create([
        'user_id' => $buyer->id,
        'status' => 'paid',
        'total_harga' => 25000,
        'alamat_id' => null,
        'shipping_cost' => 10000,
    ]);

    OrderItems::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'nama_produk' => $product->nama,
        'harga' => 25000,
        'qty' => 1,
        'subtotal' => 25000,
    ]);

    expect(RatingController::isVerifiedBuyer($buyer->id, $product->id))->toBeTrue();

    $otherUser = User::factory()->create();
    expect(RatingController::isVerifiedBuyer($otherUser->id, $product->id))->toBeFalse();
});

test('isVerifiedBuyer menolak jika status pesanan masih pending atau cancelled', function () {
    [, $buyer, $product] = createTestProductFixture();

    $orderPending = Order::create([
        'user_id' => $buyer->id,
        'status' => 'pending',
        'total_harga' => 25000,
        'alamat_id' => null,
        'shipping_cost' => 5000,
    ]);

    OrderItems::create([
        'order_id' => $orderPending->id,
        'product_id' => $product->id,
        'nama_produk' => $product->nama,
        'harga' => 25000,
        'qty' => 1,
        'subtotal' => 25000,
    ]);

    expect(RatingController::isVerifiedBuyer($buyer->id, $product->id))->toBeFalse();

    $orderPending->update(['status' => 'cancelled']);
    expect(RatingController::isVerifiedBuyer($buyer->id, $product->id))->toBeFalse();
});

test('store endpoint menolak ulasan dari pembeli yang belum terverifikasi', function () {
    [, $buyer, $product] = createTestProductFixture();

    $response = $this->actingAs($buyer)->post('/ratings', [
        'product_id' => $product->id,
        'rating' => 5,
        'review' => 'Produk sangat manjur!',
    ]);

    $response->assertSessionHasErrors('product_id');
});

test('store endpoint menolak ulasan yang hanya berisi tag HTML atau whitespace kosong', function () {
    [, $buyer, $product] = createTestProductFixture();

    $order = Order::create([
        'user_id' => $buyer->id,
        'status' => 'paid',
        'total_harga' => 25000,
        'alamat_id' => null,
        'shipping_cost' => 10000,
    ]);

    OrderItems::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'nama_produk' => $product->nama,
        'harga' => 25000,
        'qty' => 1,
        'subtotal' => 25000,
    ]);

    $response = $this->actingAs($buyer)->post('/ratings', [
        'product_id' => $product->id,
        'rating' => 4,
        'review' => '   <b>  </b>   ',
    ]);

    $response->assertSessionHasErrors('review');
});

test('store endpoint berhasil menyimpan ulasan setelah melewati sanitasi dan verifikasi pembeli', function () {
    [, $buyer, $product] = createTestProductFixture();

    $order = Order::create([
        'user_id' => $buyer->id,
        'status' => 'paid',
        'total_harga' => 25000,
        'alamat_id' => null,
        'shipping_cost' => 10000,
    ]);

    OrderItems::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'nama_produk' => $product->nama,
        'harga' => 25000,
        'qty' => 1,
        'subtotal' => 25000,
    ]);

    Http::fake([
        '*/api/ratings*' => Http::response([
            'success' => true,
            'data' => [],
        ], 200),
    ]);

    $response = $this->actingAs($buyer)->post('/ratings', [
        'product_id' => $product->id,
        'rating' => 5,
        'review' => '<script>alert("xss")</script> Obat asli dan pengiriman cepat!',
    ]);

    $response->assertRedirect('/ratings')
        ->assertSessionHas('success');

    Http::assertSent(function ($request) {
        if ($request->method() === 'POST') {
            return $request['review'] === 'alert("xss") Obat asli dan pengiriman cepat!';
        }
        return true;
    });
});
