<?php

namespace Database\Seeders;

use App\Models\Toko;
use Illuminate\Database\Seeder;

class TokoSeeder extends Seeder
{
    public function run(): void
    {
        $tokos = [
            [
                'id' => 1,
                'user_id' => 2, // Penjual (Garasi SpareHub Penjual)
                'nama_toko' => 'Garasi SpareHub Bandung',
                'deskripsi_toko' => 'Pusat suku cadang otomotif resmi, original, dan bergaransi.',
                'lokasi' => 'Bandung, Jawa Barat',
                'provinsi' => 'Jawa Barat',
                'kota' => 'Kota Bandung',
                'kecamatan' => 'Dayeuhkolot',
                'kode_wilayah' => '32.73.23',
                'kode_pos' => '40257',
                'logo_path' => null,
            ],
        ];

        foreach ($tokos as $tokoData) {
            Toko::updateOrCreate(
                ['user_id' => $tokoData['user_id']],
                $tokoData
            );
        }
    }
}

