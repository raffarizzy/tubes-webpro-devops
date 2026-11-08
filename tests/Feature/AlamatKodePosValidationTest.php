<?php

use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Validasi kode pos di AlamatController (store & update)
|--------------------------------------------------------------------------
| Memastikan controller memakai Alamat::isValidPostalCode(): kode pos yang
| salah ditolak 422 dan tidak pernah dikirim ke Node API.
*/

beforeEach(function () {
    Http::fake(['*' => Http::response(['success' => true, 'data' => ['id' => 1]], 201)]);

    $this->user = User::factory()->create();
    $this->dataAlamat = [
        'alamat' => 'Jl. Telekomunikasi No. 1',
        'nama_penerima' => 'Penerima Uji',
        'nomor_penerima' => '081234567890',
    ];
});

test('store alamat rejects invalid postal code before sending it to node api', function () {
    foreach (['4025a', '4025', '402571', '40-257'] as $kodePos) {
        $this->actingAs($this->user)
            ->postJson('/alamat', $this->dataAlamat + ['kode_pos' => $kodePos])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_pos' => 'Kode pos harus terdiri dari tepat 5 digit angka.']);
    }

    Http::assertNothingSent();
});

test('update alamat rejects invalid postal code before sending it to node api', function () {
    foreach (['4025a', '4025', '402571', '40-257'] as $kodePos) {
        $this->actingAs($this->user)
            ->putJson('/alamat/1', $this->dataAlamat + ['kode_pos' => $kodePos])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['kode_pos' => 'Kode pos harus terdiri dari tepat 5 digit angka.']);
    }

    Http::assertNothingSent();
});

test('store alamat rejects non string postal code with 422 instead of server error', function () {
    $this->actingAs($this->user)
        ->postJson('/alamat', $this->dataAlamat + ['kode_pos' => ['40257']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('kode_pos');

    Http::assertNothingSent();
});

test('store alamat accepts valid postal code and forwards it to node api', function () {
    $this->actingAs($this->user)
        ->postJson('/alamat', $this->dataAlamat + ['kode_pos' => '40257'])
        ->assertStatus(201);

    Http::assertSent(fn (Request $request) => $request->method() === 'POST'
        && str_ends_with($request->url(), '/api/alamat')
        && $request['kode_pos'] === '40257');
});

test('store alamat still accepts empty postal code because it is optional', function () {
    $this->actingAs($this->user)
        ->postJson('/alamat', $this->dataAlamat)
        ->assertStatus(201);

    Http::assertSentCount(1);
});
