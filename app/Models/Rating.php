<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rating extends Model
{
    use HasFactory;

    protected $table = 'ratings';

    protected $fillable = [
        'user_id',
        'product_id',
        'rating',
        'review',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    // Rating dimiliki oleh User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Rating dimiliki oleh Product
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Ringkasan ulasan: rata-rata (1 desimal), jumlah, dan sebaran per bintang 1-5.
     * Nilai di luar 1..5 diabaikan.
     *
     * @param  iterable<int, object|array>  $ratings
     * @return array{average: float, count: int, distribution: array<int, int>}
     */
    public static function summarize(iterable $ratings): array
    {
        $distribution = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $sum = 0;
        $count = 0;

        foreach ($ratings as $item) {
            $value = (int) (is_array($item) ? ($item['rating'] ?? 0) : ($item->rating ?? 0));
            if ($value < 1 || $value > 5) {
                continue;
            }
            $distribution[$value]++;
            $sum += $value;
            $count++;
        }

        return [
            'average' => $count > 0 ? round($sum / $count, 1) : 0.0,
            'count' => $count,
            'distribution' => $distribution,
        ];
    }
}
