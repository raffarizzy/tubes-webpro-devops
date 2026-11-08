<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Rating;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function produkDenganRating(array $nilai): Product
{
    $toko = Toko::forceCreate(['user_id' => User::factory()->create()->id, 'nama_toko' => 'Toko Uji', 'deskripsi_toko' => '-', 'lokasi' => 'Bandung']);
    $produk = Product::forceCreate(['toko_id' => $toko->id, 'category_id' => Category::forceCreate(['judulKategori' => 'Oli'])->id,
        'nama' => 'Oli Mesin', 'harga' => 50000, 'stok' => 10, 'imagePath' => 'x.jpg']);
    foreach ($nilai as $n) {
        Rating::forceCreate(['user_id' => User::factory()->create()->id, 'product_id' => $produk->id, 'rating' => $n]);
    }

    return $produk;
}

test('detail produk memakai Rating::summarize saat Node API gagal', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $produk = produkDenganRating([5, 4, 4]);

    $this->get("/produk/{$produk->id}")
        ->assertOk()
        ->assertViewHas('avgRating', 4.3)
        ->assertViewHas('ratingCount', 3);
});

test('detail produk memakai Rating::summarize saat Node API berhasil', function () {
    $produk = produkDenganRating([5, 1]);
    Http::fake(['*' => Http::response(['data' => ['id' => $produk->id, 'nama' => 'Oli Mesin', 'deskripsi' => '-',
        'harga' => 50000, 'diskon' => 0, 'stok' => 10, 'berat' => 1000, 'imagePath' => 'x.jpg',
        'toko_id' => $produk->toko_id, 'nama_toko' => 'Toko Uji', 'is_verified_seller' => false,
        'category_id' => $produk->category_id, 'category_nama' => 'Oli']], 200)]);

    $this->get("/produk/{$produk->id}")
        ->assertOk()
        ->assertViewHas('avgRating', 3.0)
        ->assertViewHas('ratingCount', 2);
});
