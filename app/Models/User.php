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
        'is_verified_seller',
        'email_verified_at',
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

}