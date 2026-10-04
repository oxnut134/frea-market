<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Purchase extends Model
{
    use HasFactory;

    const STATUS_PENDING = 'pending';
    const STATUS_PAID = 'paid';
    const STATUS_EXPIRED = 'expired';
    const STATUS_FAILED = 'failed';

    const PAYMENT_METHOD_CARD = 'card';
    const PAYMENT_METHOD_KONBINI = 'konbini';

    // 支払い方法の表示名
    const PAYMENT_METHOD_LABELS = [
        self::PAYMENT_METHOD_CARD => 'カード支払い',
        self::PAYMENT_METHOD_KONBINI => 'コンビニ払い',
    ];

    protected $fillable = [
        'user_id',
        'item_id',
        'status',
        'payment_method',
        'amount',
        'delivery_address',
        'stripe_checkout_session_id',
        'stripe_checkout_url',
        'stripe_payment_intent_id',
        'expires_at',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    // 支払い方法の表示名（$purchase->payment_method_label）
    public function getPaymentMethodLabelAttribute()
    {
        return self::PAYMENT_METHOD_LABELS[$this->payment_method] ?? '';
    }

    // 有効な購入：支払い済み、または期限内の確保中
    // 現在時刻は SQL の NOW() ではなく PHP の now() を渡す（DB のタイムゾーンがアプリと違ってもずれないように）
    public function scopeActive($query)
    {
        return $query->where(function ($query) {
            $query->where('status', self::STATUS_PAID)
                ->orWhere(function ($query) {
                    $query->where('status', self::STATUS_PENDING)
                        ->where('expires_at', '>', now());
                });
        });
    }
}
