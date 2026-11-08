<?php

use App\Models\Product;

test('harga asli dihitung dari harga jual dan persen diskon', function () {
    expect(Product::hargaAsli(80000, 20))->toBe(100000);
});

test('tanpa diskon harga asli sama dengan harga jual', function () {
    expect(Product::hargaAsli(50000, 0))->toBe(50000)
        ->and(Product::hargaAsli(50000, null))->toBe(50000);
});

test('diskon 100 persen tidak menyebabkan pembagian dengan nol', function () {
    expect(Product::hargaAsli(50000, 100))->toBe(50000);
});

test('diskon di atas 100 atau negatif diabaikan', function () {
    expect(Product::hargaAsli(50000, 150))->toBe(50000)
        ->and(Product::hargaAsli(50000, -10))->toBe(50000);
});

test('harga nol atau negatif menghasilkan nol', function () {
    expect(Product::hargaAsli(0, 20))->toBe(0)
        ->and(Product::hargaAsli(-5000, 20))->toBe(0);
});

test('nilai berupa string dari API Node tetap dihitung', function () {
    expect(Product::hargaAsli('90000', '10'))->toBe(100000);
});
