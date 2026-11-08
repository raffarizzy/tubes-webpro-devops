<?php

use App\Models\Rating;

test('rata-rata dan jumlah dihitung dari kumpulan ulasan', function () {
    $summary = Rating::summarize([['rating' => 5], ['rating' => 4], ['rating' => 3]]);

    expect($summary['average'])->toBe(4.0)
        ->and($summary['count'])->toBe(3);
});

test('tanpa ulasan rata-rata nol dan jumlah nol', function () {
    $summary = Rating::summarize([]);

    expect($summary['average'])->toBe(0.0)
        ->and($summary['count'])->toBe(0)
        ->and(array_sum($summary['distribution']))->toBe(0);
});

test('rata-rata dibulatkan satu desimal', function () {
    expect(Rating::summarize([['rating' => 5], ['rating' => 4], ['rating' => 4]])['average'])->toBe(4.3);
});

test('sebaran per bintang terhitung benar', function () {
    $summary = Rating::summarize([['rating' => 5], ['rating' => 5], ['rating' => 1]]);

    expect($summary['distribution'])->toBe([1 => 1, 2 => 0, 3 => 0, 4 => 0, 5 => 2]);
});

test('nilai di luar 1 sampai 5 diabaikan', function () {
    $summary = Rating::summarize([['rating' => 0], ['rating' => 6], ['rating' => -1], ['rating' => 4]]);

    expect($summary['count'])->toBe(1)
        ->and($summary['average'])->toBe(4.0);
});

test('menerima objek model maupun array dari API Node', function () {
    $summary = Rating::summarize([(object) ['rating' => 2], ['rating' => 4]]);

    expect($summary['average'])->toBe(3.0)
        ->and($summary['count'])->toBe(2);
});
