<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Test untuk fitur Autentikasi, Akun & Privasi Pengguna:
 * 1. Normalisasi nomor HP Indonesia
 * 2. Data masking PII (email & nama)
 * 3. Proteksi mass-assignment is_verified_seller
 */
class UserProfileSecurityTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 1. Normalisasi nomor HP
    // ---------------------------------------------------------------

    public static function validPhoneProvider(): array
    {
        return [
            'format lokal 08' => ['081234567890', '6281234567890'],
            'format internasional +62' => ['+6281234567890', '6281234567890'],
            'format 62 tanpa plus' => ['6281234567890', '6281234567890'],
            'tanpa awalan 0' => ['81234567890', '6281234567890'],
            'dengan spasi dan strip' => ['0812-3456 7890', '6281234567890'],
            'dengan titik dan kurung' => ['(0812) 3456.7890', '6281234567890'],
            '+62 dengan spasi' => ['+62 812 3456 7890', '6281234567890'],
            'spasi di awal/akhir' => ['  08123456789  ', '628123456789'],
            'panjang minimum (9 digit)' => ['0812345678', '62812345678'],
            'panjang maksimum (12 digit)' => ['0812345678901', '62812345678901'],
        ];
    }

    #[DataProvider('validPhoneProvider')]
    public function test_normalize_phone_formats_various_inputs_to_standard_indonesian_msisdn(string $input, string $expected): void
    {
        $this->assertSame($expected, User::normalizePhone($input));
    }

    public static function invalidPhoneProvider(): array
    {
        return [
            'null' => [null],
            'string kosong' => [''],
            'hanya spasi' => ['   '],
            'mengandung huruf' => ['0812abc4567'],
            'terlalu pendek' => ['12345'],
            'terlalu panjang' => ['08123456789012345'],
            'nomor telepon rumah' => ['0215551234'],
            'kode negara lain' => ['+15551234567'],
            'plus di tengah' => ['0812+34567890'],
            'simbol tidak dikenal' => ['0812#3456789'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_normalize_phone_rejects_invalid_phone_formats(?string $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Format nomor HP tidak valid.');

        User::normalizePhone($input);
    }

    public function test_profile_update_stores_normalized_phone(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '0812-3456-7890',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        Http::assertSent(fn ($request) => $request['phone'] === '6281234567890');
    }

    public function test_profile_update_rejects_invalid_phone_without_saving(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $user = User::factory()->create(['name' => 'Nama Lama']);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Nama Baru',
                'email' => $user->email,
                'phone' => '12345',
            ])
            ->assertSessionHasErrors('phone');

        $this->assertSame('Nama Lama', $user->refresh()->name);
        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------
    // 2. Data masking PII
    // ---------------------------------------------------------------

    public function test_user_email_and_name_are_properly_masked_for_public_privacy(): void
    {
        $user = new User(['name' => 'Raffa Rizky', 'email' => 'raffarizky@gmail.com']);

        $this->assertSame('r***y@gmail.com', $user->getMaskedEmail());
        $this->assertSame('Raf*** Riz***', $user->getMaskedName());
        $this->assertStringNotContainsString('raffarizky', $user->getMaskedEmail());
        $this->assertStringNotContainsString('Raffa', $user->getMaskedName());
    }

    public function test_masked_email_handles_short_local_part(): void
    {
        $this->assertSame('a***@mail.com', (new User(['email' => 'a@mail.com']))->getMaskedEmail());
        $this->assertSame('a***@mail.com', (new User(['email' => 'ab@mail.com']))->getMaskedEmail());
        $this->assertSame('a***c@mail.com', (new User(['email' => 'abc@mail.com']))->getMaskedEmail());
    }

    public function test_masked_email_keeps_only_last_at_sign_as_domain(): void
    {
        $user = new User(['email' => 'nama.panjang+promo@student.telkomuniversity.ac.id']);

        $this->assertSame('n***o@student.telkomuniversity.ac.id', $user->getMaskedEmail());
    }

    public function test_masked_name_handles_short_words_and_extra_spaces(): void
    {
        $this->assertSame('M*** Ali***', (new User(['name' => 'M  Alif']))->getMaskedName());
        $this->assertSame('A*** Bud***', (new User(['name' => '  Abc   Budi ']))->getMaskedName());
    }

    public function test_masked_name_falls_back_to_anonymous_for_empty_name(): void
    {
        $this->assertSame('Anonymous', (new User(['name' => '']))->getMaskedName());
        $this->assertSame('Anonymous', (new User(['name' => '   ']))->getMaskedName());
    }

    // ---------------------------------------------------------------
    // 3. Proteksi mass-assignment is_verified_seller
    // ---------------------------------------------------------------

    public function test_user_cannot_self_verify_seller_status_via_profile_update(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $user = User::factory()->create(['is_verified_seller' => false]);

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'is_verified_seller' => true,
            ])
            ->assertForbidden();

        $this->assertFalse($user->refresh()->is_verified_seller);
        Http::assertNothingSent();
    }

    public function test_guard_also_blocks_falsy_is_verified_seller_value(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'is_verified_seller' => '0',
            ])
            ->assertForbidden();
    }

    public function test_is_verified_seller_is_not_mass_assignable(): void
    {
        $user = User::factory()->create(['is_verified_seller' => false]);

        $this->assertFalse($user->isFillable('is_verified_seller'));

        $user->fill(['is_verified_seller' => true])->save();
        $this->assertFalse($user->refresh()->is_verified_seller);

        $user->forceFill(['is_verified_seller' => true])->save();
        $this->assertTrue($user->refresh()->is_verified_seller);
    }

    public function test_normal_profile_update_still_works_without_seller_field(): void
    {
        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Admiral',
                'email' => $user->email,
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('Admiral', $user->refresh()->name);
    }
}
