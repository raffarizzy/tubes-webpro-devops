<?php

use App\Models\Category;
use App\Models\Product;

/* =========================================================================
 * 1. FITUR ALERT STATUS KETERSEDIAAN STOK (Product::getStockStatusBadge)
 * ========================================================================= */

test('test_stock_status_badge_returns_correct_level_and_color', function () {
    // Stok 0 -> Habis
    $badgeHabis = Product::getStockStatusBadge(0);
    expect($badgeHabis['label'])->toBe('Habis')
        ->and($badgeHabis['color'])->toBe('danger')
        ->and($badgeHabis['level'])->toBe('out_of_stock');

    // Stok 3 (1-5) -> Stok Menipis
    $badgeMenipis = Product::getStockStatusBadge(3);
    expect($badgeMenipis['label'])->toBe('Stok Menipis')
        ->and($badgeMenipis['color'])->toBe('warning')
        ->and($badgeMenipis['level'])->toBe('low_stock');

    // Stok 20 (>5) -> Tersedia
    $badgeTersedia = Product::getStockStatusBadge(20);
    expect($badgeTersedia['label'])->toBe('Tersedia')
        ->and($badgeTersedia['color'])->toBe('success')
        ->and($badgeTersedia['level'])->toBe('in_stock');
});

test('test_stock_status_badge_handles_negative_stock_as_out_of_stock', function () {
    // Edge case: Stok negatif karena anomali inventori
    $badge = Product::getStockStatusBadge(-5);
    expect($badge['label'])->toBe('Habis')
        ->and($badge['color'])->toBe('danger')
        ->and($badge['level'])->toBe('out_of_stock');
});

test('test_stock_status_badge_handles_boundary_values', function () {
    // Boundary test: stok tepat 1
    $badgeOne = Product::getStockStatusBadge(1);
    expect($badgeOne['label'])->toBe('Stok Menipis')
        ->and($badgeOne['level'])->toBe('low_stock');

    // Boundary test: stok tepat 5 (batas atas Stok Menipis)
    $badgeFive = Product::getStockStatusBadge(5);
    expect($badgeFive['label'])->toBe('Stok Menipis')
        ->and($badgeFive['level'])->toBe('low_stock');

    // Boundary test: stok tepat 6 (batas bawah Tersedia)
    $badgeSix = Product::getStockStatusBadge(6);
    expect($badgeSix['label'])->toBe('Tersedia')
        ->and($badgeSix['level'])->toBe('in_stock');
});

/* =========================================================================
 * 2. BUGFIX VALIDASI RENTANG FILTER HARGA (Category::sanitizePriceRange)
 * ========================================================================= */

test('test_price_range_sanitizer_swaps_min_and_max_when_inverted', function () {
    // Input terbalik: min 500.000, max 100.000
    $range = Category::sanitizePriceRange(500000, 100000);

    expect($range['min'])->toBe(100000)
        ->and($range['max'])->toBe(500000);
});

test('test_price_range_sanitizer_handles_negative_values_gracefully', function () {
    // Input min bernilai negatif
    $range1 = Category::sanitizePriceRange(-50000, 200000);
    expect($range1['min'])->toBe(0)
        ->and($range1['max'])->toBe(200000);

    // Input kedua batas bernilai negatif
    $range2 = Category::sanitizePriceRange(-100000, -20000);
    expect($range2['min'])->toBe(0)
        ->and($range2['max'])->toBe(0);
});

test('test_price_range_sanitizer_handles_non_numeric_and_null_inputs', function () {
    // Null inputs
    $rangeNull = Category::sanitizePriceRange(null, null);
    expect($rangeNull['min'])->toBe(0)
        ->and($rangeNull['max'])->toBe(0);

    // String dari URL query parameter
    $rangeString = Category::sanitizePriceRange('75000', '150000');
    expect($rangeString['min'])->toBe(75000)
        ->and($rangeString['max'])->toBe(150000);

    // String non-numerik acak
    $rangeInvalid = Category::sanitizePriceRange('free', 'expensive');
    expect($rangeInvalid['min'])->toBe(0)
        ->and($rangeInvalid['max'])->toBe(0);
});

test('test_price_range_sanitizer_handles_inverted_string_inputs', function () {
    $range = Category::sanitizePriceRange('900000', '250000');
    expect($range['min'])->toBe(250000)
        ->and($range['max'])->toBe(900000);
});

/* =========================================================================
 * 3. FITUR AUTO SLUG KATEGORI UNIK (Category::createSlug)
 * ========================================================================= */

test('test_category_slug_generator_creates_url_safe_slug', function () {
    $slug = Category::createSlug('Oli & Pelumas Mesin Motor');

    expect($slug)->toBe('oli-pelumas-mesin-motor');
});

test('test_category_slug_generator_handles_special_characters_and_symbols', function () {
    $slug = Category::createSlug('Spare Part #1 @Telkom [V-Belt] 100% Ori!!');

    expect($slug)->toBe('spare-part-1-telkom-v-belt-100-ori');
});

test('test_category_slug_generator_trims_whitespace_and_excessive_dashes', function () {
    $slug = Category::createSlug('   Knalpot---Racing    Bobokan   ');

    expect($slug)->toBe('knalpot-racing-bobokan');
});

test('test_category_slug_generator_provides_fallback_for_empty_string', function () {
    $slugEmpty = Category::createSlug('');
    expect($slugEmpty)->toBe('kategori-umum');

    $slugSymbolsOnly = Category::createSlug('!@#$%^&*()');
    expect($slugSymbolsOnly)->toBe('kategori-umum');
});
