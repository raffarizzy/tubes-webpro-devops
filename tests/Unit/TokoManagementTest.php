<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('isOwnedBy mengembalikan true jika user_id sesuai', function () {
    $toko = new Toko(['user_id' => 10]);

    expect($toko->isOwnedBy(10))->toBeTrue()
        ->and($toko->isOwnedBy(99))->toBeFalse()
        ->and($toko->isOwnedBy(null))->toBeFalse();
});

test('isOwnedBy mendukung input instance model User', function () {
    $user = new User();
    $user->id = 7;

    $toko = new Toko(['user_id' => 7]);

    expect($toko->isOwnedBy($user))->toBeTrue();

    $otherUser = new User();
    $otherUser->id = 8;
    expect($toko->isOwnedBy($otherUser))->toBeFalse();
});

test('cleanName membersihkan tag HTML dan script berbahaya dari nama toko', function () {
    $dirty = '<script>alert("hack")</script> Toko Obat Sehat <b>Asli</b> ';
    $cleaned = Toko::cleanName($dirty);

    expect($cleaned)->toBe('alert("hack") Toko Obat Sehat Asli')
        ->and($cleaned)->not->toContain('<script>')
        ->and($cleaned)->not->toContain('<b>');
});

test('isValidName memvalidasi batas panjang karakter nama toko sesuai boundary', function () {
    // Batas bawah valid: min 1 karakter
    expect(Toko::isValidName('A'))->toBeTrue();

    // Nama normal
    expect(Toko::isValidName('Apotek Medcom'))->toBeTrue();

    // Tepat batas atas valid: 255 karakter
    $maxValid = str_repeat('A', 255);
    expect(Toko::isValidName($maxValid))->toBeTrue();

    // Di bawah batas minimum: kosong / spasi
    expect(Toko::isValidName(''))->toBeFalse()
        ->and(Toko::isValidName('   '))->toBeFalse()
        ->and(Toko::isValidName(null))->toBeFalse();

    // Melampaui batas maksimum: 256 karakter
    $tooLong = str_repeat('A', 256);
    expect(Toko::isValidName($tooLong))->toBeFalse();
});

test('generateSlug menghasilkan slug URL yang rapi dan konsisten', function () {
    expect(Toko::generateSlug('Apotek Sehat Medcom 24 Jam!'))->toBe('apotek-sehat-medcom-24-jam')
        ->and(Toko::generateSlug('Toko & Kimia Farma'))->toBe('toko-kimia-farma');

    // Jika nama kosong, kembalikan slug default
    expect(Toko::generateSlug(''))->toStartWith('toko-');
});

test('deleteWithProducts menghapus toko dan meng-cascade produk miliknya untuk mencegah orphan records', function () {
    $user = User::factory()->create();

    $category = new Category();
    $category->judulKategori = 'Obat Bebas';
    $category->save();

    $toko = Toko::create([
        'user_id' => $user->id,
        'nama_toko' => 'Apotek Uji',
        'deskripsi_toko' => 'Deskripsi Apotek',
        'lokasi' => 'Bandung',
        'provinsi' => 'Jawa Barat',
        'kota' => 'Bandung',
        'kecamatan' => 'Coblong',
        'kode_pos' => '40132',
        'kode_wilayah' => '32.73',
        'logo_path' => 'toko/logo.webp',
    ]);

    $product1 = Product::create([
        'toko_id' => $toko->id,
        'category_id' => $category->id,
        'nama' => 'Paracetamol 500mg',
        'deskripsi' => 'Obat penurun panas',
        'harga' => 15000,
        'diskon' => 0,
        'stok' => 50,
        'imagePath' => 'products/paracetamol.jpg',
    ]);

    $product2 = Product::create([
        'toko_id' => $toko->id,
        'category_id' => $category->id,
        'nama' => 'Vitamin C 500mg',
        'deskripsi' => 'Suplemen daya tahan tubuh',
        'harga' => 25000,
        'diskon' => 10,
        'stok' => 30,
        'imagePath' => 'products/vitaminc.jpg',
    ]);

    expect(Product::where('toko_id', $toko->id)->count())->toBe(2);

    // Hapus toko beserta produknya
    $toko->deleteWithProducts();

    // Verifikasi toko terhapus dari tabel aktif
    expect(Toko::find($toko->id))->toBeNull();

    // Verifikasi produk toko ikut terhapus/dibersihkan dari katalog aktif (mencegah orphan crash)
    expect(Product::where('toko_id', $toko->id)->count())->toBe(0);
});
