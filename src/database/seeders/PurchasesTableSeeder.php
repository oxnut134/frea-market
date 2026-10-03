<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Concerns\ResetsAutoIncrement;

class PurchasesTableSeeder extends Seeder
{
    use ResetsAutoIncrement;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        DB::table('purchases')->upsert([
            [
                'id' => 1,
                'user_id' => 2, // 商品 1 の出品者（ユーザー 1）以外
                'item_id' => 1,
                'status' => 'paid',
                'payment_method' => 'card',
                'amount' => 15000, // 商品 1 の価格
                'delivery_address' => '東京都中区１－１－１',
                'expires_at' => $now,
                'paid_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['id'], ['user_id', 'item_id', 'status', 'payment_method', 'amount', 'delivery_address', 'expires_at', 'paid_at', 'updated_at']);

        $this->resetAutoIncrement('purchases');
    }
}
