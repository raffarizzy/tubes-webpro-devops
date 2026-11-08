<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'birthDate',
        'gender',
        'pfpPath',
        'email_verified_at',
        // 'is_verified_seller' sengaja TIDAK fillable: status seller hanya boleh
        // diubah secara eksplisit (forceFill oleh admin), bukan lewat input form.
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_verified_seller' => 'boolean',
        ];
    }

    public function toko()
    {
        return $this->hasOne(Toko::class);
    }

    /**
     * Get the user's shopping cart
     *
     * @return HasOne
     */
    public function keranjang(): HasOne
    {
        return $this->hasOne(Keranjang::class);
    }

    /**
     * Get the user's active cart or create one
     *
     * @return Keranjang
     */
    public function getOrCreateCart(): Keranjang
    {
        return $this->keranjang()->firstOrCreate(
            ['user_id' => $this->id],
            ['status' => 'active']
        );
    }

    /**
     * Normalisasi nomor HP Indonesia ke format MSISDN standar "628xxxxxxxxx".
     *
     * Menerima format "08...", "+628...", "628...", atau "8...", termasuk yang
     * mengandung spasi, strip, titik, atau tanda kurung.
     *
     * @throws \InvalidArgumentException jika nomor kosong atau bukan nomor HP Indonesia yang valid
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/[\s\-\.\(\)]/', '', trim((string) $phone));

        if ($digits === '' || !preg_match('/^\+?\d+$/', $digits)) {
            throw new \InvalidArgumentException('Format nomor HP tidak valid.');
        }

        $digits = ltrim($digits, '+');

        if (str_starts_with($digits, '62')) {
            $digits = substr($digits, 2);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // Nomor seluler Indonesia: diawali 8, total 9-12 digit setelah kode negara.
        if (!preg_match('/^8\d{8,11}$/', $digits)) {
            throw new \InvalidArgumentException('Format nomor HP tidak valid.');
        }

        return '62' . $digits;
    }

    /**
     * Email yang disamarkan untuk tampilan publik, contoh: "r***y@gmail.com".
     */
    public function getMaskedEmail(): string
    {
        $email = (string) $this->email;
        $atPos = strrpos($email, '@');

        if ($atPos === false) {
            return self::maskWord($email, 1);
        }

        $local = substr($email, 0, $atPos);
        $domain = substr($email, $atPos);

        $masked = mb_strlen($local) <= 2
            ? mb_substr($local, 0, 1) . '***'
            : mb_substr($local, 0, 1) . '***' . mb_substr($local, -1);

        return $masked . $domain;
    }

    /**
     * Nama yang disamarkan untuk tampilan publik, contoh: "Raf*** Riz***".
     */
    public function getMaskedName(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($words)) {
            return 'Anonymous';
        }

        return implode(' ', array_map(fn (string $word) => self::maskWord($word, 3), $words));
    }

    /**
     * Tampilkan beberapa huruf awal lalu "***". Kata pendek hanya
     * menampilkan huruf pertama agar tidak terbaca utuh.
     */
    private static function maskWord(string $word, int $visible): string
    {
        $shown = mb_strlen($word) > $visible ? $visible : 1;

        return mb_substr($word, 0, $shown) . '***';
    }
}