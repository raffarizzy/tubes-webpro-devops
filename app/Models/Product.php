<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'toko_id',
        'category_id',
        'nama',
        'deskripsi',
        'harga',
        'diskon',
        'stok',
        'imagePath',
        'berat',
    ];

    /* ================= RELATION ================= */

    public function orderItems()
    {
        return $this->hasMany(OrderItems::class);
    }

    public function toko()
    {
        return $this->belongsTo(Toko::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Mengembalikan informasi indikator status ketersediaan stok produk.
     *
     * @return array{label: string, color: string, level: string}
     */
    public static function getStockStatusBadge(int $stok): array
    {
        if ($stok <= 0) {
            return [
                'label' => 'Habis',
                'color' => 'danger',
                'level' => 'out_of_stock',
            ];
        }

        if ($stok <= 5) {
            return [
                'label' => 'Stok Menipis',
                'color' => 'warning',
                'level' => 'low_stock',
            ];
        }

        return [
            'label' => 'Tersedia',
            'color' => 'success',
            'level' => 'in_stock',
        ];
    }
}
