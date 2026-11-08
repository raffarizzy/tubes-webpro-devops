<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('store returns 422 when requested quantity exceeds available stock', function () {
    $user = User::factory()->create();
    $toko = Toko::create([
        'user_id' => $user->id,
        'nama_toko' => 'Toko Demo',
        'deskripsi_toko' => 'Deskripsi Toko',
        'lokasi' => 'Bandung',
    ]);
    $category = Category::forceCreate(['judulKategori' => 'Mesin']);
    $product = Product::create([
        'toko_id' => $toko->id,
        'category_id' => $category->id,
        'nama' => 'Kampas Rem',
        'harga' => 100000,
        'stok' => 3, // stok hanya 3
        'imagePath' => 'kampas.jpg',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson('/keranjang/item', [
            'product_id' => $product->id,
            'jumlah' => 10, // minta 10 (melebihi stok 3)
        ]);

    $response->assertStatus(422)
        ->assertJson([
            'success' => false,
            'message' => 'Stok tidak mencukupi untuk jumlah yang diminta.',
        ]);
});

test('store succeeds and proxies to node api when quantity is within available stock', function () {
    Http::fake([
        '*/cart/item' => Http::response(['success' => true, 'data' => ['id' => 99]], 200),
    ]);

    $user = User::factory()->create();
    $toko = Toko::create([
        'user_id' => $user->id,
        'nama_toko' => 'Toko Demo',
        'deskripsi_toko' => 'Deskripsi Toko',
        'lokasi' => 'Bandung',
    ]);
    $category = Category::forceCreate(['judulKategori' => 'Mesin']);
    $product = Product::create([
        'toko_id' => $toko->id,
        'category_id' => $category->id,
        'nama' => 'Kampas Rem',
        'harga' => 100000,
        'stok' => 10,
        'imagePath' => 'kampas.jpg',
    ]);

    $response = $this
        ->actingAs($user)
        ->postJson('/keranjang/item', [
            'product_id' => $product->id,
            'jumlah' => 2, // minta 2 (cukup)
        ]);

    $response->assertStatus(200)
        ->assertJson([
            'success' => true,
            'message' => 'Produk berhasil ditambahkan ke keranjang',
        ]);
});
