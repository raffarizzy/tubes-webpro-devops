<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WilayahSeeder extends Seeder
{
    public function run(): void
    {
        $wilayahData = [
            // Provinsi (Panjang kode = 2)
            ['kode' => '32', 'nama' => 'JAWA BARAT'],
            ['kode' => '31', 'nama' => 'DKI JAKARTA'],
            ['kode' => '33', 'nama' => 'JAWA TENGAH'],
            ['kode' => '35', 'nama' => 'JAWA TIMUR'],
            ['kode' => '36', 'nama' => 'BANTEN'],

            // Kota / Kabupaten (Panjang kode = 5)
            ['kode' => '32.73', 'nama' => 'KOTA BANDUNG'],
            ['kode' => '32.04', 'nama' => 'KAB. BANDUNG'],
            ['kode' => '32.75', 'nama' => 'KOTA BEKASI'],
            ['kode' => '31.71', 'nama' => 'KOTA ADM. JAKARTA PUSAT'],
            ['kode' => '31.74', 'nama' => 'KOTA ADM. JAKARTA SELATAN'],
            ['kode' => '33.74', 'nama' => 'KOTA SEMARANG'],
            ['kode' => '35.78', 'nama' => 'KOTA SURABAYA'],
            ['kode' => '36.74', 'nama' => 'KOTA TANGERANG SELATAN'],

            // Kecamatan (Panjang kode = 8)
            ['kode' => '32.73.23', 'nama' => 'DAYEUHKOLOT'],
            ['kode' => '32.73.01', 'nama' => 'COBLONG'],
            ['kode' => '32.73.02', 'nama' => 'SUKASARI'],
            ['kode' => '32.73.03', 'nama' => 'LENGKONG'],
            ['kode' => '32.04.05', 'nama' => 'BOJONGSOANG'],
            ['kode' => '31.71.01', 'nama' => 'GAMBIR'],
            ['kode' => '31.74.01', 'nama' => 'SETIABUDI'],
            ['kode' => '33.74.01', 'nama' => 'SEMARANG TENGAH'],
            ['kode' => '35.78.01', 'nama' => 'TEGALSARI'],
        ];

        foreach ($wilayahData as $item) {
            DB::table('wilayah')->updateOrInsert(
                ['kode' => $item['kode']],
                ['nama' => $item['nama']]
            );
        }
    }
}
