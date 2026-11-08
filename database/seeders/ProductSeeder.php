<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'id' => 1,
                'toko_id' => 1,
                'category_id' => 3, // Kelistrikan & Busi
                'nama' => 'Busi Iridium Power Denso Racing IU27',
                'deskripsi' => 'Busi elektroda iridium 0.4mm, pengapian fokus, efisiensi bahan bakar maksimal.',
                'harga' => 95000,
                'diskon' => 10,
                'stok' => 50,
                'berat' => 100,
                'imagePath' => 'images/products/busi-denso.jpg',
            ],
            [
                'id' => 2,
                'toko_id' => 1,
                'category_id' => 2, // Sistem Pengereman
                'nama' => 'Kampas Rem Depan Bendix Stealth Hybrid',
                'deskripsi' => 'Kampas rem ceramic stealth hybrid bebas debu dan tanpa decit untuk harian & touring.',
                'harga' => 220000,
                'diskon' => 15,
                'stok' => 30,
                'berat' => 600,
                'imagePath' => 'images/products/kampas-rem-bendix.jpg',
            ],
            [
                'id' => 3,
                'toko_id' => 1,
                'category_id' => 6, // Oli & Pelumas
                'nama' => 'Oli Mesin Motul 5100 4T 10W-40 Technosynthese 1L',
                'deskripsi' => 'Pelumas semi sintetik dengan teknologi Ester, perlindungan gearbox sempurna.',
                'harga' => 145000,
                'diskon' => 0,
                'stok' => 100,
                'berat' => 1000,
                'imagePath' => 'images/products/oli-motul.jpg',
            ],
            [
                'id' => 4,
                'toko_id' => 1,
                'category_id' => 8, // Filter
                'nama' => 'Filter Oli Original Denso DXE-1004',
                'deskripsi' => 'Filter oli berkualitas menyaring partikel mikron pada sirkulasi oli mesin.',
                'harga' => 45000,
                'diskon' => 0,
                'stok' => 75,
                'berat' => 250,
                'imagePath' => 'images/products/filter-oli-denso.jpg',
            ],
            [
                'id' => 5,
                'toko_id' => 1,
                'category_id' => 3, // Kelistrikan & Busi
                'nama' => 'Aki Kering GS Astra MF GTZ5S 12V 3.5Ah',
                'deskripsi' => 'Aki maintenance free siap pakai tanpa perlu isi air aki, daya starter kuat.',
                'harga' => 285000,
                'diskon' => 5,
                'stok' => 25,
                'berat' => 2100,
                'imagePath' => 'images/products/aki-gs-astra.jpg',
            ],
            [
                'id' => 6,
                'toko_id' => 1,
                'category_id' => 7, // Cairan Pembersih & Coolant
                'nama' => 'Cairan Radiator Coolant Prestone Ready to Use 1L',
                'deskripsi' => 'Mencegah overheating mesin dan korosi sistem radiator.',
                'harga' => 55000,
                'diskon' => 0,
                'stok' => 40,
                'berat' => 1100,
                'imagePath' => 'images/products/radiator-prestone.jpg',
            ],
            [
                'id' => 7,
                'toko_id' => 1,
                'category_id' => 4, // Transmisi & Kopling
                'nama' => 'Kampas Kopling Daytona Hi-Carbon Kevlar',
                'deskripsi' => 'Material carbon kevlar anti selip pada putaran rpm tinggi, tarikan responsif.',
                'harga' => 175000,
                'diskon' => 10,
                'stok' => 20,
                'berat' => 350,
                'imagePath' => 'images/products/kopling-daytona.jpg',
            ],
            [
                'id' => 8,
                'toko_id' => 1,
                'category_id' => 5, // Suspensi & Kaki-kaki
                'nama' => 'Shockbreaker Belakang RCB MB2 Plus Black Series 330mm',
                'deskripsi' => 'Suspensi dengan tabung nitrogen dan setelan preload per, redaman stabil.',
                'harga' => 850000,
                'diskon' => 5,
                'stok' => 12,
                'berat' => 3200,
                'imagePath' => 'images/products/shock-rcb.jpg',
            ],
        ];

        foreach ($products as $prodData) {
            Product::updateOrCreate(
                ['id' => $prodData['id']],
                $prodData
            );
        }
    }
}
