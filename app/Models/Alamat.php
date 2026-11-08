<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Alamat extends Model
{
    /** Berat tagihan minimal yang dikenakan kurir (kg). */
    public const MIN_BILLABLE_WEIGHT_KG = 1;

    /** Sisa berat (gram) di atas batas ini dibulatkan ke atas menjadi 1 kg. */
    public const WEIGHT_ROUNDING_THRESHOLD_GRAMS = 300;

    protected $fillable = [
        'user_id',
        'alamat',
        'provinsi',
        'kota',
        'kecamatan',
        'kode_pos',
        'kode_wilayah',
        'is_default', // UBAH DARI isDefault
        'nama_penerima',
        'nomor_penerima'
    ];

    // Tambahkan cast untuk memastikan is_default selalu boolean
    protected $casts = [
        'is_default' => 'boolean',
    ];

    /**
     * Jadikan alamat ini sebagai alamat utama user dan reset alamat utama
     * lama milik user yang sama, sehingga hanya ada satu alamat utama.
     */
    public function setAsPrimary(): void
    {
        DB::transaction(function () {
            static::query()
                ->where('user_id', $this->user_id)
                ->where('is_default', true)
                ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
                ->update(['is_default' => false]);

            $this->is_default = true;
            $this->save();
        });
    }

    /**
     * Kode pos Indonesia harus tepat 5 digit angka sebelum dikirim ke API kurir.
     */
    public static function isValidPostalCode(?string $postalCode): bool
    {
        if ($postalCode === null) {
            return false;
        }

        return preg_match('/^\d{5}$/D', $postalCode) === 1;
    }

    /**
     * Hitung berat tagihan kurir dalam kg: minimal 1 kg, dan sisa berat
     * di atas 0,3 kg dibulatkan ke atas (contoh: 1,3 kg -> 1 kg, 1,31 kg -> 2 kg).
     */
    public static function calculateBillableWeight(int $totalWeightInGrams): int
    {
        if ($totalWeightInGrams <= 0) {
            return self::MIN_BILLABLE_WEIGHT_KG;
        }

        $kilograms = intdiv($totalWeightInGrams, 1000);
        $remainderGrams = $totalWeightInGrams % 1000;

        if ($remainderGrams > self::WEIGHT_ROUNDING_THRESHOLD_GRAMS) {
            $kilograms++;
        }

        return max(self::MIN_BILLABLE_WEIGHT_KG, $kilograms);
    }
}
