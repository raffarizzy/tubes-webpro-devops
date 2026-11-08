<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $fillable = [
        'judulKategori',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }

    /**
     * Memvalidasi dan menormalkan rentang filter harga (min & max).
     * Nilai negatif dikoreksi menjadi 0, dan posisi ditukar jika min > max.
     *
     * @return array{min: int, max: int}
     */
    public static function sanitizePriceRange(mixed $min, mixed $max): array
    {
        $cleanMin = is_numeric($min) ? max(0, (int) round((float) $min)) : 0;
        $cleanMax = is_numeric($max) ? max(0, (int) round((float) $max)) : 0;

        if ($cleanMin > $cleanMax) {
            [$cleanMin, $cleanMax] = [$cleanMax, $cleanMin];
        }

        return [
            'min' => $cleanMin,
            'max' => $cleanMax,
        ];
    }

    /**
     * Menghasilkan URL-safe slug dari nama kategori.
     */
    public static function createSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'kategori-umum';
    }
}
