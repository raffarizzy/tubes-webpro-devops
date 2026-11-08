<?php

namespace Database\Seeders;

use App\Models\BarangKeranjang;
use App\Models\Keranjang;
use Illuminate\Database\Seeder;

class KeranjangSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat keranjang aktif untuk user pembeli (user_id = 1)
        $keranjang = Keranjang::updateOrCreate(
            ['user_id' => 1],
            ['status' => 'active']
        );

        // 2. Masukkan item contoh ke dalam keranjang
        $items = [
            [
                'keranjang_id' => $keranjang->id,
                'product_id' => 1, // Busi Iridium Denso (Rp 95.000)
                'jumlah' => 2,
                'harga' => 95000,
            ],
            [
                'keranjang_id' => $keranjang->id,
                'product_id' => 3, // Oli Mesin Motul (Rp 145.000)
                'jumlah' => 1,
                'harga' => 145000,
            ],
        ];

        foreach ($items as $itemData) {
            BarangKeranjang::updateOrCreate(
                [
                    'keranjang_id' => $itemData['keranjang_id'],
                    'product_id' => $itemData['product_id'],
                ],
                $itemData
            );
        }
    }
}

