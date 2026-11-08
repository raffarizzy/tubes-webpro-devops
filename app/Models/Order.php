<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    /** Perpindahan status yang diizinkan: status sekarang => daftar status tujuan. */
    public const TRANSITIONS = [
        'pending'    => ['paid', 'cancelled'],
        'paid'       => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped'    => ['finished'],
        'finished'   => [],
        'cancelled'  => [],
    ];

    protected $fillable = [
        'user_id',
        'alamat_id',
        'total_harga',
        'status',
        'nomor_resi',
        'payment_url',
        'courier_code',
        'courier_name',
        'service_name',
        'shipping_cost',
    ];

    public function items()
    {
        return $this->hasMany(OrderItems::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function alamat()
    {
        return $this->belongsTo(Alamat::class);
    }

    public function canTransitionTo(string $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status] ?? [], true);
    }
}
