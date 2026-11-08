<?php

use App\Models\Alamat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Fitur 1: Single Primary Address Integrity (Alamat::setAsPrimary)
|--------------------------------------------------------------------------
*/
describe('setAsPrimary', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->buatAlamat = fn (User $pemilik, bool $isDefault) => Alamat::create([
            'user_id' => $pemilik->id,
            'alamat' => 'Jl. Telekomunikasi No. 1',
            'kota' => 'Bandung',
            'kode_pos' => '40257',
            'nama_penerima' => 'Penerima Uji',
            'nomor_penerima' => '081234567890',
            'is_default' => $isDefault,
        ]);
    });

    test('setting primary address resets previous primary addresses', function () {
        $alamatLama = ($this->buatAlamat)($this->user, true);
        $alamatBaru = ($this->buatAlamat)($this->user, false);

        $alamatBaru->setAsPrimary();

        expect($alamatBaru->fresh()->is_default)->toBeTrue()
            ->and($alamatLama->fresh()->is_default)->toBeFalse();
    });

    test('setting primary address does not reset primary address of other users', function () {
        $userLain = User::factory()->create();
        $alamatUserLain = ($this->buatAlamat)($userLain, true);
        $alamatBaru = ($this->buatAlamat)($this->user, false);

        $alamatBaru->setAsPrimary();

        expect($alamatUserLain->fresh()->is_default)->toBeTrue()
            ->and($alamatBaru->fresh()->is_default)->toBeTrue();
    });

    test('setting an address that is already primary stays safe', function () {
        $alamatUtama = ($this->buatAlamat)($this->user, true);
        $alamatBiasa = ($this->buatAlamat)($this->user, false);

        // Dipanggil dua kali pada alamat yang sudah utama tidak boleh error / merusak dataa
        $alamatUtama->setAsPrimary();
        $alamatUtama->setAsPrimary();

        expect($alamatUtama->fresh()->is_default)->toBeTrue()
            ->and($alamatBiasa->fresh()->is_default)->toBeFalse()
            ->and(Alamat::where('user_id', $this->user->id)->where('is_default', true)->count())->toBe(1);
    });

    test('setting primary address fixes data that already has multiple primary addresses', function () {
        // Simulasi data rusak: user sudah punya dua alamat utama sekaligus
        $alamatUtamaA = ($this->buatAlamat)($this->user, true);
        $alamatUtamaB = ($this->buatAlamat)($this->user, true);
        $alamatBaru = ($this->buatAlamat)($this->user, false);

        $alamatBaru->setAsPrimary();

        expect($alamatUtamaA->fresh()->is_default)->toBeFalse()
            ->and($alamatUtamaB->fresh()->is_default)->toBeFalse()
            ->and(Alamat::where('user_id', $this->user->id)->where('is_default', true)->count())->toBe(1);
    });

    test('setting primary on a new unsaved address saves it as the only primary address', function () {
        $alamatLama = ($this->buatAlamat)($this->user, true);

        $alamatBaru = new Alamat([
            'user_id' => $this->user->id,
            'alamat' => 'Jl. Sukabirus No. 2',
            'nama_penerima' => 'Penerima Baru',
            'nomor_penerima' => '081298765432',
        ]);
        $alamatBaru->setAsPrimary();

        expect($alamatBaru->exists)->toBeTrue()
            ->and($alamatBaru->fresh()->is_default)->toBeTrue()
            ->and($alamatLama->fresh()->is_default)->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| Fitur 2: Validasi Kode Pos Indonesia (Alamat::isValidPostalCode)
|--------------------------------------------------------------------------
*/
describe('isValidPostalCode', function () {
    test('postal code validation accepts valid 5 digit number', function () {
        expect(Alamat::isValidPostalCode('40257'))->toBeTrue()
            ->and(Alamat::isValidPostalCode('10110'))->toBeTrue()
            ->and(Alamat::isValidPostalCode('99999'))->toBeTrue();
    });

    test('postal code validation rejects alphanumeric or wrong length', function () {
        // Mengandung huruf
        expect(Alamat::isValidPostalCode('4025a'))->toBeFalse()
            ->and(Alamat::isValidPostalCode('4025A'))->toBeFalse()
            ->and(Alamat::isValidPostalCode('ABCDE'))->toBeFalse();

        // Boundary panjang: 4 digit dan 6 digit
        expect(Alamat::isValidPostalCode('4025'))->toBeFalse()
            ->and(Alamat::isValidPostalCode('402571'))->toBeFalse();
    });

    test('postal code validation rejects null and empty string', function () {
        expect(Alamat::isValidPostalCode(null))->toBeFalse()
            ->and(Alamat::isValidPostalCode(''))->toBeFalse();
    });

    test('postal code validation rejects postal code containing whitespace', function () {
        expect(Alamat::isValidPostalCode(' 40257'))->toBeFalse()
            ->and(Alamat::isValidPostalCode('40257 '))->toBeFalse()
            ->and(Alamat::isValidPostalCode('402 57'))->toBeFalse()
            ->and(Alamat::isValidPostalCode("40257\n"))->toBeFalse();
    });

    test('postal code validation rejects symbols and separators', function () {
        expect(Alamat::isValidPostalCode('40-257'))->toBeFalse()
            ->and(Alamat::isValidPostalCode('40.257'))->toBeFalse()
            ->and(Alamat::isValidPostalCode('-4025'))->toBeFalse()
            ->and(Alamat::isValidPostalCode('4025!'))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| Fitur 3: Kalkulasi Berat Paket Logistik (Alamat::calculateBillableWeight)
|--------------------------------------------------------------------------
*/
describe('calculateBillableWeight', function () {
    test('billable weight calculates correct courier charge weight', function () {
        expect(Alamat::calculateBillableWeight(1000))->toBe(1)
            ->and(Alamat::calculateBillableWeight(2000))->toBe(2)
            ->and(Alamat::calculateBillableWeight(2700))->toBe(3)
            ->and(Alamat::calculateBillableWeight(5000))->toBe(5)
            ->and(Alamat::calculateBillableWeight(12450))->toBe(13);
    });

    test('billable weight charges minimum 1 kg for weight under 1 kg', function () {
        expect(Alamat::calculateBillableWeight(0))->toBe(1)
            ->and(Alamat::calculateBillableWeight(1))->toBe(1)
            ->and(Alamat::calculateBillableWeight(500))->toBe(1)
            ->and(Alamat::calculateBillableWeight(999))->toBe(1);
    });

    test('billable weight rounds down when remainder is at most 0.3 kg', function () {
        expect(Alamat::calculateBillableWeight(1300))->toBe(1)
            ->and(Alamat::calculateBillableWeight(2100))->toBe(2)
            ->and(Alamat::calculateBillableWeight(2300))->toBe(2);
    });

    test('billable weight rounds up when remainder is above 0.3 kg', function () {
        expect(Alamat::calculateBillableWeight(1301))->toBe(2)
            ->and(Alamat::calculateBillableWeight(1500))->toBe(2)
            ->and(Alamat::calculateBillableWeight(2301))->toBe(3);
    });

    test('billable weight handles negative weight as minimum 1 kg', function () {
        expect(Alamat::calculateBillableWeight(-1))->toBe(1)
            ->and(Alamat::calculateBillableWeight(-500))->toBe(1)
            ->and(Alamat::calculateBillableWeight(-2500))->toBe(1);
    });
});
