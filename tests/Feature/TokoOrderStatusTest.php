<?php

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Penjual dengan satu produk, dan satu pesanan berisi produk itu.
function pesananToko(string $status): array
{
    $penjual = User::factory()->create();
    $toko = Toko::forceCreate(['user_id' => $penjual->id, 'nama_toko' => 'Toko Uji', 'deskripsi_toko' => '-', 'lokasi' => 'Bandung']);
    $kategori = Category::forceCreate(['judulKategori' => 'Oli']);
    $produk = Product::forceCreate(['toko_id' => $toko->id, 'category_id' => $kategori->id, 'nama' => 'Oli Mesin',
        'harga' => 50000, 'stok' => 10, 'imagePath' => 'x.jpg']);
    $order = Order::forceCreate(['user_id' => User::factory()->create()->id, 'total_harga' => 50000, 'status' => $status]);
    $order->items()->forceCreate(['product_id' => $produk->id, 'nama_produk' => 'Oli Mesin', 'harga' => 50000, 'qty' => 1, 'subtotal' => 50000]);

    return [$penjual, $order];
}

test('penjual menerima pesanan paid menjadi processing', function () {
    [$penjual, $order] = pesananToko('paid');

    $this->actingAs($penjual)->post("/toko/orders/{$order->id}/accept")->assertSessionHas('success');

    expect($order->fresh()->status)->toBe('processing');
});

test('pesanan pending tidak bisa diterima', function () {
    [$penjual, $order] = pesananToko('pending');

    $this->actingAs($penjual)->post("/toko/orders/{$order->id}/accept")->assertSessionHas('error');

    expect($order->fresh()->status)->toBe('pending');
});

test('pesanan processing bisa dibatalkan, pesanan shipped tidak', function () {
    [$penjual, $order] = pesananToko('processing');
    $this->actingAs($penjual)->post("/toko/orders/{$order->id}/reject")->assertSessionHas('success');
    expect($order->fresh()->status)->toBe('cancelled');

    [$penjual, $order] = pesananToko('shipped');
    $this->actingAs($penjual)->post("/toko/orders/{$order->id}/reject")->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('shipped');
});

test('hanya pesanan processing yang bisa dikirim', function () {
    [$penjual, $order] = pesananToko('processing');
    $this->actingAs($penjual)->post("/toko/orders/{$order->id}/ship", ['nomor_resi' => 'JNE123'])->assertSessionHas('success');
    expect($order->fresh())->status->toBe('shipped')->nomor_resi->toBe('JNE123');

    [$penjual, $order] = pesananToko('paid');
    $this->actingAs($penjual)->post("/toko/orders/{$order->id}/ship", ['nomor_resi' => 'JNE123'])->assertSessionHas('error');
    expect($order->fresh()->status)->toBe('paid');
});
