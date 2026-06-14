<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Concerns\ResetsAutoIncrement;

class ItemCategoryTableSeeder extends Seeder
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

        $pairs = [
            ['item_id' => 1, 'category_id' => 1],
            ['item_id' => 1, 'category_id' => 5],
            ['item_id' => 1, 'category_id' => 12],
            ['item_id' => 2, 'category_id' => 2],
            ['item_id' => 3, 'category_id' => 10],
            ['item_id' => 4, 'category_id' => 1],
            ['item_id' => 4, 'category_id' => 5],
            ['item_id' => 5, 'category_id' => 2],
            ['item_id' => 6, 'category_id' => 2],
            ['item_id' => 7, 'category_id' => 4],
            ['item_id' => 7, 'category_id' => 12],
            ['item_id' => 8, 'category_id' => 9],
            ['item_id' => 8, 'category_id' => 10],
            ['item_id' => 9, 'category_id' => 10],
            ['item_id' => 9, 'category_id' => 11],
            ['item_id' => 10, 'category_id' => 1],
            ['item_id' => 10, 'category_id' => 4],
            ['item_id' => 10, 'category_id' => 6],
        ];

        $rows = [];
        foreach ($pairs as $index => $pair) {
            $rows[] = [
                'id' => $index + 1,
                'item_id' => $pair['item_id'],
                'category_id' => $pair['category_id'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('item_category')->upsert($rows, ['id'], ['item_id', 'category_id', 'updated_at']);

        $this->resetAutoIncrement('item_category');
    }
}
