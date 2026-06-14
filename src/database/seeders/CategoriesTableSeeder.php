<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Concerns\ResetsAutoIncrement;

class CategoriesTableSeeder extends Seeder
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

        $categories = [
            'ファッション',
            '家電',
            'インテリア',
            'レディース',
            'メンズ',
            'コスメ',
            '本',
            'ゲーム',
            'スポーツ',
            'キッチン',
            'ハンドメイド',
            'アクセサリー',
            'おもちゃ',
            'ベビー・キッズ',
        ];

        $rows = [];
        foreach ($categories as $index => $category) {
            $rows[] = [
                'id' => $index + 1,
                'category' => $category,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('categories')->upsert($rows, ['id'], ['category', 'updated_at']);

        $this->resetAutoIncrement('categories');
    }
}
