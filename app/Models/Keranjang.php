<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keranjang extends Model
{
    protected $fillable = [
        'user_id',
        'status',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * Get the user that owns the cart
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all items in this cart
     */
    public function items(): HasMany
    {
        return $this->hasMany(BarangKeranjang::class, 'keranjang_id');
    }

    /**
     * Menyaring item keranjang yang valid dan siap di-checkout.
     * Membuang/mengabaikan item jika produk telah dihapus (soft-delete / null)
     * atau stok produk di gudang habis (<= 0).
     *
     * @return \Illuminate\Support\Collection<int, BarangKeranjang>
     */
    public function filterAvailableItems(): \Illuminate\Support\Collection
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->with('product')->get();

        return $items->filter(function ($item) {
            // 1. Produk harus ada dan tidak terhapus (soft delete)
            if (!$item->product) {
                return false;
            }

            $deletedAt = $item->product->getAttributes()['deleted_at'] ?? null;
            if (!empty($deletedAt)) {
                return false;
            }

            // 2. Stok produk harus tersedia (> 0)
            if ((int) ($item->product->stok ?? 0) <= 0) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * Get total items count in cart
     */
    public function getTotalItemsAttribute(): int
    {
        return $this->filterAvailableItems()->sum('jumlah');
    }

    /**
     * Get total price of all available items in cart
     */
    public function getTotalPriceAttribute(): int
    {
        return $this->filterAvailableItems()->sum(function ($item) {
            return $item->getSubtotal();
        });
    }
}