<?php

namespace Database\Factories;

use App\Models\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'item_id' => \App\Models\Item::factory(), // 関連するアイテムのIDを生成
            'user_id' => \App\Models\User::factory(), // 関連するユーザーのIDを生成
            'status' => Purchase::STATUS_PAID,
            'payment_method' => Purchase::PAYMENT_METHOD_CARD,
            'amount' => $this->faker->numberBetween(1000, 100000),
            'delivery_address' => $this->faker->address,
            'expires_at' => now(),
            'paid_at' => now(),
        ];
    }

    // 確保中（期限内）
    public function pending()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Purchase::STATUS_PENDING,
                'expires_at' => now()->addMinutes(30),
                'paid_at' => null,
            ];
        });
    }

    // 確保中のまま期限が切れている（まだ expired に更新されていない）
    public function pendingExpired()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Purchase::STATUS_PENDING,
                'expires_at' => now()->subMinute(),
                'paid_at' => null,
            ];
        });
    }

    // 期限切れ
    public function expired()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => Purchase::STATUS_EXPIRED,
                'expires_at' => now()->subMinute(),
                'paid_at' => null,
            ];
        });
    }
}
