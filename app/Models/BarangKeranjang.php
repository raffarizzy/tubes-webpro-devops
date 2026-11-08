<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BarangKeranjang extends Model
{
    protected $fillable = [
        'keranjang_id',
        'product_id',
        'jumlah',
        'harga',
    ];

    protected $casts = [
        'jumlah' => 'integer',
        'harga' => 'integer',
    ];

    /**
     * Get the cart that owns this item
     */
    public function keranjang(): BelongsTo
    {
        return $this->belongsTo(Keranjang::class);
    }

    /**
     * Get the product for this cart item
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Validasi apakah penambahan kuantitas barang ke keranjang diizinkan
     * berdasarkan stok gudang yang tersedia.
     * Mencegah stok minus dan input kuantitas non-positif.
     */
    public static function canAddQuantity(int $stokTersedia, int $requestedQty): bool
    {
        if ($requestedQty <= 0 || $stokTersedia <= 0) {
            return false;
        }

        return $requestedQty <= $stokTersedia;
    }

    /**
     * Menghitung total subtotal harga item keranjang setelah dipotong diskon produk.
     * Rumus: (harga - diskon) * quantity
     * Mendukung diskon berupa persentase (1..99%) atau nilai nominal rupiah.
     */
    public function getSubtotal(): int
    {
        $harga = (int) ($this->harga ?? ($this->product?->harga ?? 0));
        $qty = (int) ($this->jumlah ?? 1);

        if ($harga <= 0 || $qty <= 0) {
            return 0;
        }

        $diskon = (int) ($this->product?->diskon ?? 0);

        if ($diskon > 0 && $diskon <= 100) {
            // Diskon persentase (1% s/d 100%)
            $potongan = (int) round(($harga * $diskon) / 100);
            $hargaSetelahDiskon = max(0, $harga - $potongan);
        } elseif ($diskon > 100 && $diskon < $harga) {
            // Diskon nominal langsung (misal: potongan Rp 10.000)
            $hargaSetelahDiskon = max(0, $harga - $diskon);
        } elseif ($diskon >= $harga) {
            // Diskon melebihi harga
            $hargaSetelahDiskon = 0;
        } else {
            $hargaSetelahDiskon = $harga;
        }

        return (int) ($hargaSetelahDiskon * $qty);
    }

    /**
     * Get subtotal for this item
     */
    public function getSubtotalAttribute(): int
    {
        return $this->getSubtotal();
    }
}