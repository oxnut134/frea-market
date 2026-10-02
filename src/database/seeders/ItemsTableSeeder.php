<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Concerns\ResetsAutoIncrement;
use Database\Seeders\Concerns\SeedsImages;

class ItemsTableSeeder extends Seeder
{
    use ResetsAutoIncrement, SeedsImages;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        DB::table('items')->upsert([
            [
                'id' => 1,
                'user_id' => 1,
                'item_image' => $this->seedImage('items/armani-mens-clock.jpg'),
                'item_name' => '腕時計',
                'brand_name' => 'Armani',
                'price' => 15000,
                'description' => 'スタイリッシュなデザインのメンズ腕時計',
                'condition' => '良好',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'user_id' => 1,
                'item_image' => $this->seedImage('items/hdd-hard-disk.jpg'),
                'item_name' => 'HDD',
                'brand_name' => '',
                'price' => 5000,
                'description' => '高速で信頼性の高いハードディスク',
                'condition' => '目立った傷や汚れなし',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'user_id' => 1,
                'item_image' => $this->seedImage('items/onions.jpg'),
                'item_name' => '玉ねぎ3束',
                'brand_name' => null,
                'price' => 300,
                'description' => '新鮮な玉ねぎ3束のセット',
                'condition' => '状態が悪い',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'user_id' => 2,
                'item_image' => $this->seedImage('items/leather-shoes.jpg'),
                'item_name' => '革靴',
                'brand_name' => null,
                'price' => 4000,
                'description' => 'クラシックなデザインの革靴',
                'condition' => '状態が悪い',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'user_id' => 2,
                'item_image' => $this->seedImage('items/laptop.jpg'),
                'item_name' => 'ノートPC',
                'brand_name' => null,
                'price' => 45000,
                'description' => '高性能なノートパソコン',
                'condition' => '良好',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 6,
                'user_id' => 3,
                'item_image' => $this->seedImage('items/microphone.jpg'),
                'item_name' => 'マイク',
                'brand_name' => null,
                'price' => 8000,
                'description' => '高音質のレコーディング用マイク',
                'condition' => '目立った傷や汚れなし',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 7,
                'user_id' => 3,
                'item_image' => $this->seedImage('items/shoulder-bag.jpg'),
                'item_name' => 'ショルダーバッグ',
                'brand_name' => 'Nine West',
                'price' => 3500,
                'description' => 'おしゃれなショルダーバッグ',
                'condition' => 'やや傷や汚れあり',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 8,
                'user_id' => 3,
                'item_image' => $this->seedImage('items/tumbler.jpg'),
                'item_name' => 'タンブラー',
                'brand_name' => null,
                'price' => 500,
                'description' => '使いやすいタンブラー',
                'condition' => '状態が悪い',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 9,
                'user_id' => 3,
                'item_image' => $this->seedImage('items/coffee-grinder.jpg'),
                'item_name' => 'コーヒーミル',
                'brand_name' => null,
                'price' => 4000,
                'description' => '手動のコーヒーミル',
                'condition' => '良好',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 10,
                'user_id' => 4,
                'item_image' => $this->seedImage('items/makeup-set.jpg'),
                'item_name' => 'メイクセット',
                'brand_name' => null,
                'price' => 2500,
                'description' => '便利なメイクアップセット',
                'condition' => '目立った傷や汚れなし',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['id'], ['user_id', 'item_image', 'item_name', 'brand_name', 'price', 'description', 'condition', 'updated_at']);

        $this->resetAutoIncrement('items');
    }
}
