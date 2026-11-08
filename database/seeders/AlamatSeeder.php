<?php

namespace Database\Seeders;

use App\Models\Alamat;
use Illuminate\Database\Seeder;

class AlamatSeeder extends Seeder
{
    public function run(): void
    {
        $alamats = [
            [
                'id' => 1,
                'user_id' => 1, // Pembeli (Frizam)
                'nama_penerima' => 'Frizam Maulana',
                'nomor_penerima' => '081987654321',
                'alamat' => 'Kost Asri Mandiri, Jl. Sukabirus No. 45',
                'provinsi' => 'Jawa Barat',
                'kota' => 'Kab. Bandung',
                'kecamatan' => 'Dayeuhkolot',
                'kode_wilayah' => '32.73.23',
                'kode_pos' => '40257',
                'is_default' => true,
            ],
            [
                'id' => 2,
                'user_id' => 1, // Alamat Kantor / Kampus
                'nama_penerima' => 'Frizam Maulana (Kampus)',
                'nomor_penerima' => '081987654321',
                'alamat' => 'Fakultas Informatika Telkom University, Gedung Selaru',
                'provinsi' => 'Jawa Barat',
                'kota' => 'Kab. Bandung',
                'kecamatan' => 'Dayeuhkolot',
                'kode_wilayah' => '32.73.23',
                'kode_pos' => '40257',
                'is_default' => false,
            ],
        ];

        foreach ($alamats as $alamatData) {
            Alamat::updateOrCreate(
                ['id' => $alamatData['id']],
                $alamatData
            );
        }
    }
}
