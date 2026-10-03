<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Item;
use App\Models\User;

class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array
     */
    protected $model = Item::class;
    public function definition()
    {
        return [

             'user_id' => 1, // Userファクトリを使用して関連付け
            'item_image' => $this->faker->image, // ランダムな画像URLを生成
            'item_name' => $this->faker->word, // ランダムな商品名を生成
            'brand_name' => $this->faker->company, // ランダムなブランド名を生成
            'price' => $this->faker->numberBetween(1000, 100000), // ランダムな価格を生成
            'description' => $this->faker->sentence, // ランダムな説明文を生成
            'condition' => $this->faker->randomElement(['新品', '良好', '使用感あり']), // ランダムな商品の状態を生成

        ];
    }}
