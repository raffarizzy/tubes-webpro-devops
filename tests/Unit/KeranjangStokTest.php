<?php

use App\Models\BarangKeranjang;
use App\Models\Keranjang;
use App\Models\Product;

test('cannot add item when quantity exceeds stock', function () {
    // Stok ada 5, tapi meminta 6
    expect(BarangKeranjang::canAddQuantity(5, 6))->toBeFalse();
});

test('can add item when stock is sufficient', function () {
    // Stok ada 10, meminta 3
    expect(BarangKeranjang::canAddQuantity(10, 3))->toBeTrue()
        // Boundary case: meminta tepat sama dengan stok
        ->and(BarangKeranjang::canAddQuantity(5, 5))->toBeTrue();
});

test('cannot add item when quantity or stock is non positive', function () {
    // Requested qty 0 atau negatif
    expect(BarangKeranjang::canAddQuantity(10, 0))->toBeFalse()
        ->and(BarangKeranjang::canAddQuantity(10, -2))->toBeFalse()
        // Stok gudang 0 atau minus
        ->and(BarangKeranjang::canAddQuantity(0, 1))->toBeFalse()
        ->and(BarangKeranjang::canAddQuantity(-5, 1))->toBeFalse();
});

test('calculate subtotal with product discount accurately', function () {
    $product = new Product([
        'nama' => 'Kampas Rem Bendix',
        'harga' => 100000,
        'diskon' => 20, // diskon 20% -> harga satuan 80.000
        'stok' => 15,
    ]);

    $item = new BarangKeranjang([
        'jumlah' => 3,
        'harga' => 100000,
    ]);
    $item->setRelation('product', $product);

    // Subtotal: (100.000 - 20%) * 3 = 80.000 * 3 = 240.000
    expect($item->getSubtotal())->toBe(240000);
});

test('calculate subtotal handles full discount or zero price', function () {
    $freeProduct = new Product([
        'harga' => 50000,
        'diskon' => 100, // 100% gratis
        'stok' => 5,
    ]);

    $item = new BarangKeranjang([
        'jumlah' => 2,
        'harga' => 50000,
    ]);
    $item->setRelation('product', $freeProduct);

    expect($item->getSubtotal())->toBe(0);

    // Tanpa diskon sama sekali
    $normalProduct = new Product([
        'harga' => 50000,
        'diskon' => 0,
        'stok' => 5,
    ]);
    $item->setRelation('product', $normalProduct);
    expect($item->getSubtotal())->toBe(100000);
});

test('calculate subtotal handles nominal discount, excess discount, and zero quantity or price', function () {
    // 1. Diskon nominal langsung (diskon > 100 && diskon < harga)
    $nominalDiscountProduct = new Product([
        'harga' => 50000,
        'diskon' => 15000, // diskon nominal Rp 15.000 -> harga satuan 35.000
    ]);
    $nominalItem = new BarangKeranjang(['jumlah' => 2, 'harga' => 50000]);
    $nominalItem->setRelation('product', $nominalDiscountProduct);
    expect($nominalItem->getSubtotal())->toBe(70000);

    // 2. Diskon melebihi harga (diskon >= harga)
    $excessDiscountProduct = new Product([
        'harga' => 50000,
        'diskon' => 60000, // diskon lebih besar dari harga
    ]);
    $excessItem = new BarangKeranjang(['jumlah' => 1, 'harga' => 50000]);
    $excessItem->setRelation('product', $excessDiscountProduct);
    expect($excessItem->getSubtotal())->toBe(0);

    // 3. Harga atau kuantitas <= 0
    $zeroPriceItem = new BarangKeranjang(['jumlah' => 2, 'harga' => 0]);
    expect($zeroPriceItem->getSubtotal())->toBe(0);

    $zeroQtyItem = new BarangKeranjang(['jumlah' => 0, 'harga' => 50000]);
    expect($zeroQtyItem->getSubtotal())->toBe(0);

    // 4. Fallback harga dari product relasi jika $item->harga null
    $fallbackItem = new BarangKeranjang(['jumlah' => 2]);
    $fallbackProduct = new Product(['harga' => 25000, 'diskon' => 0]);
    $fallbackItem->setRelation('product', $fallbackProduct);
    expect($fallbackItem->getSubtotal())->toBe(50000)
        // 5. Test Accessor $item->subtotal
        ->and($fallbackItem->subtotal)->toBe(50000);
});

test('filter available items removes out of stock or deleted products', function () {
    $cart = new Keranjang(['user_id' => 1]);

    // Item 1: Produk aktif & stok ada (VALID)
    $prodValid = (new Product)->forceFill(['id' => 1, 'nama' => 'Busi Denso', 'stok' => 10, 'harga' => 50000]);
    $item1 = (new BarangKeranjang)->forceFill(['id' => 101, 'product_id' => 1, 'jumlah' => 2, 'harga' => 50000]);
    $item1->setRelation('product', $prodValid);

    // Item 2: Produk stok habis (stok = 0)
    $prodOutOfStock = (new Product)->forceFill(['id' => 2, 'nama' => 'Oli Motul', 'stok' => 0, 'harga' => 120000]);
    $item2 = (new BarangKeranjang)->forceFill(['id' => 102, 'product_id' => 2, 'jumlah' => 1, 'harga' => 120000]);
    $item2->setRelation('product', $prodOutOfStock);

    // Item 3: Produk soft-deleted
    $prodDeleted = (new Product)->setRawAttributes([
        'id' => 3,
        'nama' => 'Filter Oli',
        'stok' => 5,
        'harga' => 35000,
        'deleted_at' => '2026-11-08 20:00:00',
    ], true);
    $item3 = (new BarangKeranjang)->forceFill(['id' => 103, 'product_id' => 3, 'jumlah' => 1, 'harga' => 35000]);
    $item3->setRelation('product', $prodDeleted);

    // Item 4: Produk null / missing
    $item4 = (new BarangKeranjang)->forceFill(['id' => 104, 'product_id' => 999, 'jumlah' => 1, 'harga' => 10000]);
    $item4->setRelation('product', null);

    // Set relasi items pada keranjang
    $cart->setRelation('items', collect([$item1, $item2, $item3, $item4]));

    $available = $cart->filterAvailableItems();

    expect($available)->toHaveCount(1)
        ->and($available->first()->id)->toBe(101)
        ->and($available->first()->product->nama)->toBe('Busi Denso')
        // Total Items dan Total Price terhitung hanya dari item yang valid
        ->and($cart->total_items)->toBe(2)
        ->and($cart->total_price)->toBe(100000);
});
