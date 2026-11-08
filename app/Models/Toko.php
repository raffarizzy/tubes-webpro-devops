<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Toko extends Model
{
    protected $fillable = [
        'user_id',
        'node_toko_id',
        'nama_toko',
        'deskripsi_toko',
        'lokasi',
        'provinsi',
        'kota',
        'kecamatan',
        'kode_pos',
        'kode_wilayah',
        'logo_path'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Otorisasi kepemilikan toko terpusat untuk mencegah celah IDOR.
     */
    public function isOwnedBy(int|User|null $user): bool
    {
        if ($user === null) {
            return false;
        }

        $userId = $user instanceof User ? $user->id : (int) $user;
        return (int) $this->user_id === $userId;
    }

    /**
     * Hapus toko secara aman beserta seluruh produknya (cascade soft-delete)
     * untuk mencegah timbulnya orphan records dan HTTP 500 error di halaman pembeli.
     */
    public function deleteWithProducts(): bool
    {
        // Soft-delete atau hapus seluruh produk terkait toko ini
        foreach ($this->products as $product) {
            $product->delete();
        }

        return (bool) $this->delete();
    }

    /**
     * Sanitasi nama toko: membuang tag HTML/script berbahaya dan spasi berlebih.
     */
    public static function cleanName(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        return trim(strip_tags($name));
    }

    /**
     * Validasi batas panjang nama toko (Boundary Value Analysis).
     */
    public static function isValidName(?string $name, int $min = 1, int $max = 255): bool
    {
        $cleaned = self::cleanName($name);
        $length = mb_strlen($cleaned);

        return $length >= $min && $length <= $max;
    }

    /**
     * Generate slug URL toko yang SEO-friendly dan bersih dari karakter spesial.
     */
    public static function generateSlug(?string $name): string
    {
        $cleaned = self::cleanName($name);
        if ($cleaned === '') {
            return 'toko-' . uniqid();
        }

        return \Illuminate\Support\Str::slug($cleaned, '-');
    }
}

