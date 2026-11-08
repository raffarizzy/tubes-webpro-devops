<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItems;
use App\Models\Rating;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        // Order 1: Status 'paid' (sedang menunggu diproses penjual)
        $order1 = Order::updateOrCreate(
            ['id' => 1],
            [
                'user_id' => 1,
                'alamat_id' => 1,
                'total_harga' => 190000,
                'status' => 'paid',
                'courier_code' => 'jne',
                'courier_name' => 'JNE',
                'service_name' => 'REG',
                'shipping_cost' => 18000,
                'payment_reference' => 'DUITKU-1718001122',
            ]
        );

        OrderItems::updateOrCreate(
            ['order_id' => $order1->id, 'product_id' => 1],
            [
                'nama_produk' => 'Busi Iridium Power Denso Racing IU27',
                'harga' => 85500,
                'qty' => 2,
                'subtotal' => 171000,
            ]
        );

        // Order 2: Status 'finished' (sudah selesai dan ada ulasan rating)
        $order2 = Order::updateOrCreate(
            ['id' => 2],
            [
                'user_id' => 1,
                'alamat_id' => 1,
                'total_harga' => 242000,
                'status' => 'finished',
                'nomor_resi' => 'JNE103022330138',
                'courier_code' => 'jne',
                'courier_name' => 'JNE',
                'service_name' => 'YES',
                'shipping_cost' => 22000,
                'payment_reference' => 'DUITKU-1717009988',
            ]
        );

        OrderItems::updateOrCreate(
            ['order_id' => $order2->id, 'product_id' => 2],
            [
                'nama_produk' => 'Kampas Rem Depan Bendix Stealth Hybrid',
                'harga' => 187000,
                'qty' => 1,
                'subtotal' => 187000,
            ]
        );

        // Berikan contoh ulasan rating untuk Order 2
        Rating::updateOrCreate(
            ['user_id' => 1, 'product_id' => 2],
            [
                'rating' => 5,
                'review' => 'Barang original, pengereman empuk dan presisi. Recommended seller!',
            ]
        );
    }
}

