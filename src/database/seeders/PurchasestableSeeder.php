<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Concerns\ResetsAutoIncrement;

class PurchasestableSeeder extends Seeder
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
                'user_id' => 1,
                'item_id' => 1,
                'payment_method' => 'コンビニ払い',
                'delivery_address' => '東京都中区１－１－１',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['id'], ['user_id', 'item_id', 'payment_method', 'delivery_address', 'updated_at']);

        $this->resetAutoIncrement('purchases');
    }
}
