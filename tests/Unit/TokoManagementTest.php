<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Toko;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createSampleTokoFixture(int $userId): Toko
{
    return Toko::create([
        'user_id' => $userId,
        'node_toko_id' => 'node-toko-99',
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
}

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

test('findByIdOrNodeId dapat menemukan toko berdasarkan ID Laravel maupun node_toko_id', function () {
    $user = User::factory()->create();
    $toko = createSampleTokoFixture($user->id);

    expect(Toko::findByIdOrNodeId($toko->id)?->id)->toBe($toko->id)
        ->and(Toko::findByIdOrNodeId('node-toko-99')?->id)->toBe($toko->id)
        ->and(Toko::findByIdOrNodeId(99999))->toBeNull();
});

test('cleanName membersihkan tag HTML dan script berbahaya dari nama toko', function () {
    $dirty = '<script>alert("hack")</script> Toko Obat Sehat <b>Asli</b> ';
    $cleaned = Toko::cleanName($dirty);

    expect($cleaned)->toBe('alert("hack") Toko Obat Sehat Asli')
        ->and($cleaned)->not->toContain('<script>')
        ->and($cleaned)->not->toContain('<b>');
});

test('isValidName memvalidasi batas panjang karakter nama toko sesuai boundary', function () {
    expect(Toko::isValidName('A'))->toBeTrue();
    expect(Toko::isValidName('Apotek Medcom'))->toBeTrue();
    expect(Toko::isValidName(str_repeat('A', 255)))->toBeTrue();
    expect(Toko::isValidName(''))->toBeFalse()
        ->and(Toko::isValidName('   '))->toBeFalse()
        ->and(Toko::isValidName(null))->toBeFalse();
    expect(Toko::isValidName(str_repeat('A', 256)))->toBeFalse();
});

test('generateSlug menghasilkan slug URL yang rapi dan konsisten', function () {
    expect(Toko::generateSlug('Apotek Sehat Medcom 24 Jam!'))->toBe('apotek-sehat-medcom-24-jam')
        ->and(Toko::generateSlug('Toko & Kimia Farma'))->toBe('toko-kimia-farma');
    expect(Toko::generateSlug(''))->toStartWith('toko-');
});

test('deleteWithProducts menghapus toko dan meng-cascade produk miliknya untuk mencegah orphan records', function () {
    $user = User::factory()->create();
    $toko = createSampleTokoFixture($user->id);

    $category = new Category();
    $category->judulKategori = 'Obat Bebas';
    $category->save();

    Product::create([
        'toko_id' => $toko->id,
        'category_id' => $category->id,
        'nama' => 'Paracetamol 500mg',
        'deskripsi' => 'Obat penurun panas',
        'harga' => 15000,
        'diskon' => 0,
        'stok' => 50,
        'imagePath' => 'products/paracetamol.jpg',
    ]);

    expect(Product::where('toko_id', $toko->id)->count())->toBe(1);

    $toko->deleteWithProducts();

    expect(Toko::find($toko->id))->toBeNull();
    expect(Product::where('toko_id', $toko->id)->count())->toBe(0);
});

test('update toko endpoint mengembalikan 404 jika toko tidak ada', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->putJson('/toko/99999', [
        'nama_toko' => 'Toko Baru',
        'deskripsi_toko' => 'Deskripsi',
        'lokasi' => 'Bandung',
        'provinsi' => 'Jabar',
        'kota' => 'Bandung',
        'kecamatan' => 'Coblong',
        'kode_pos' => '40132',
        'kode_wilayah' => '32.73',
    ]);

    $response->assertStatus(404)
        ->assertJson(['success' => false, 'message' => 'Toko tidak ditemukan']);
});

test('update toko endpoint mengembalikan 403 jika diakses bukan oleh pemilik', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $toko = createSampleTokoFixture($owner->id);

    $response = $this->actingAs($stranger)->putJson("/toko/{$toko->id}", [
        'nama_toko' => 'Hacked Toko',
        'deskripsi_toko' => 'Deskripsi',
        'lokasi' => 'Bandung',
        'provinsi' => 'Jabar',
        'kota' => 'Bandung',
        'kecamatan' => 'Coblong',
        'kode_pos' => '40132',
        'kode_wilayah' => '32.73',
    ]);

    $response->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'Anda tidak memiliki akses untuk mengupdate toko ini']);
});

test('update toko endpoint berhasil jika diakses oleh pemilik yang sah', function () {
    $owner = User::factory()->create();
    $toko = createSampleTokoFixture($owner->id);

    Http::fake([
        '*/api/toko*' => Http::response(['success' => true, 'data' => []], 200),
    ]);

    $response = $this->actingAs($owner)->putJson("/toko/{$toko->id}", [
        'nama_toko' => 'Nama Toko Baru',
        'deskripsi_toko' => 'Deskripsi Baru',
        'lokasi' => 'Jakarta',
        'provinsi' => 'DKI',
        'kota' => 'Jakarta Selatan',
        'kecamatan' => 'Tebet',
        'kode_pos' => '12810',
        'kode_wilayah' => '31.74',
    ]);

    $response->assertStatus(200)
        ->assertJson(['success' => true]);

    expect($toko->fresh()->nama_toko)->toBe('Nama Toko Baru');
});

test('destroy toko endpoint mengembalikan 404 jika toko tidak ada', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->deleteJson('/toko/99999');

    $response->assertStatus(404)
        ->assertJson(['success' => false, 'message' => 'Toko tidak ditemukan']);
});

test('destroy toko endpoint mengembalikan 403 jika diakses bukan oleh pemilik', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $toko = createSampleTokoFixture($owner->id);

    $response = $this->actingAs($stranger)->deleteJson("/toko/{$toko->id}");

    $response->assertStatus(403)
        ->assertJson(['success' => false, 'message' => 'Anda tidak memiliki akses untuk menghapus toko ini']);
});

test('destroy toko endpoint berhasil menghapus toko dan meng-cascade produk miliknya jika diakses pemilik', function () {
    $owner = User::factory()->create();
    $toko = createSampleTokoFixture($owner->id);

    $category = new Category();
    $category->judulKategori = 'Obat Umum';
    $category->save();

    Product::create([
        'toko_id' => $toko->id,
        'category_id' => $category->id,
        'nama' => 'Amoxicillin',
        'deskripsi' => 'Antibiotik',
        'harga' => 20000,
        'diskon' => 0,
        'stok' => 20,
        'imagePath' => 'products/amox.jpg',
    ]);

    Http::fake([
        '*/api/toko*' => Http::response(['success' => true], 200),
    ]);

    $response = $this->actingAs($owner)->deleteJson("/toko/{$toko->id}");

    $response->assertStatus(200)
        ->assertJson(['success' => true, 'message' => 'Toko berhasil dihapus']);

    expect(Toko::find($toko->id))->toBeNull();
    expect(Product::where('toko_id', $toko->id)->count())->toBe(0);
});
