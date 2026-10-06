<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Item;
use App\Models\Like;
use App\Models\Comment;
use App\Models\Category;
use App\Models\ItemCategory;
use App\Models\Profile;
use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;


class ShowItemDetailTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

    //--------------------ShowItemDetail-1 アイテム詳細 全項目表示確認 --------------------------

    public function testShowItemDetail()
    {
        var_dump("*****1st_Method:testShowItemDetail");
        $users =
            [
                [
                    'name' => 'cat',
                    'email' => 'cat@test.com',
                    'password' => bcrypt('abc12345'),
                ],
                [
                    'name' => 'dog',
                    'email' => 'dog@test.com',
                    'password' => bcrypt('abc12345'),
                ],
                [
                    'name' => 'rat',
                    'email' => 'rat@test.com',
                    'password' => bcrypt('abc12345'),
                ]
            ];
        foreach ($users as $user) {

            $new_user = new User;
            $new_user->name = $user['name'];
            $new_user->email = $user['email'];
            $new_user->password = $user['password'];
            $new_user->email_verified_at = now();

            $new_user->save();
        }

        $users = User::orderBy('id')->get();
        //$users = User::factory()->count(3)->create();

        $items = [
            [
                'user_id' => $users[1]->id,
                'item_image' => 'Item-Armani+Mens+Clock.jpg',
                'item_name' => '腕時計',
                'brand_name' => 'Armani',
                'price' => 15000,
                'description' => 'スタイリッシュなデザインのメンズ腕時計',
                'condition' => '良好',
            ],
            [
                'user_id' => $users[1]->id,
                'item_image' => 'Item-HDD+Hard+Disk.jpg',
                'item_name' => 'HDD',
                'brand_name' => '',
                'price' => 5000,
                'description' => '高速で信頼性の高いハードディスク',
                'condition' => '目立った傷や汚れなし',
            ],
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-iLoveIMG+d.jpg',
                'item_name' => '玉ねぎ3束',
                'brand_name' => null,
                'price' => 300,
                'description' => '新鮮な玉ねぎ3束のセット',
                'condition' => '状態が悪い',
            ],
        ];

        foreach ($items as $item) {

            $new_item = new Item;
            $new_item->user_id = $item['user_id'];
            $new_item->item_image = $item['item_image'];
            $new_item->item_name = $item['item_name'];
            $new_item->brand_name = $item['brand_name'];
            $new_item->price = $item['price'];
            $new_item->description = $item['description'];
            $new_item->condition = $item['condition'];

            $new_item->save();
        }

        $categories = [

            ['category' => 'ファッション',],
            ['category' => '家電',],
            ['category' => 'インテリア',],
            ['category' => 'レディース',],
            ['category' => 'メンズ',],
            ['category' => 'コスメ',],
            ['category' => '本',],
            ['category' => 'ゲーム',],
            ['category' => 'スポーツ',],
            ['category' => 'キッチン',],
            ['category' => 'ハンドメイド',],
            ['category' => 'アクセサリー',],
            ['category' => 'おもちゃ',],
            ['category' => 'ベビー・キッズ',]
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }


        $user = User::orderBy('id')->first(); // usersテーブルの最初のレコードを取得
        $item = Item::orderBy('id')->first(); // itemsテーブルの最初のレコードを取得
        //最初のアイテムにいいね
        $likes = [
            'user_id' => $user['id'], // 最初のユーザーのIDをリンク
            'item_id' => $item['id'], // アイテムのIDをリンク
        ];
        // 一括挿入
        Like::insert($likes); // likesテーブルにいいねを保存


        //コメント
        $user = User::orderBy('id')->first(); // usersテーブルの最初のレコードを取得
        $item = Item::orderBy('id')->first(); // itemsテーブルの最初のレコードを取得

        $comments = [
            [
                'user_id' => $user['id'], // 最初のユーザーのIDをリンク
                'item_id' => $item['id'], // ２番目のアイテムのIDをリンク
                'comment' => "test",
            ],
            [
                'user_id' => $user['id'], // 最初のユーザーのIDをリンク
                'item_id' => $item['id'] + 1, // ３番目のアイテムのIDをリンク
                'comment' => "test",
            ]
        ];
        // 一括挿入
        Comment::insert($comments); // commentsテーブルに一括保存

        //カテゴリー
        $category = Category::orderBy('id')->first(); // categoriesテーブルの最初のレコードを取得
        $item = Item::orderBy('id')->first(); // itemsテーブルの最初のレコードを取得

        $item_category = [
            [
                'item_id' => $item['id'], // 最初のユーザーのIDをリンク
                'category_id' => $category['id'], // ２番目のアイテムのIDをリンク
            ],
            [
                'item_id' => $item['id'], // 最初のユーザーのIDをリンク
                'category_id' => $category['id'] + 1, // ３番目のアイテムのIDをリンク
            ]
        ];
        // 一括挿入
        ItemCategory::insert($item_category); // item_categoryテーブルに一括を保存

        //プロファイル登録
        $user = User::orderBy('id')->first(); // usersテーブルの最初のレコードを取得
        $profiles = [
            [
                'user_id' => $user['id'], // ユーザーのIDをリンク
                'profile_image' => 'comment.png',
                'post_code' => '111-1111',
                'address' => 'Tokyo'
            ],
        ];
        // 一括挿入
        Profile::insert($profiles); //profilesテーブルに一括登録

        //----------テスト開始 ---------------------------------------------------

        //----------必要情報表示 ------------------------------

        $this->actingAs($user);
        $response = $this->get('/');
        $response = $this->get('/item/' . Item::orderBy('id')->first()->id);
        //dd($response);
        $response->assertSee('Item-Armani+Mens+Clock.jpg');
        $response->assertSee('腕時計');
        $response->assertSee('Armani');
        $response->assertSee('15,000');
        $response->assertSee('スタイリッシュなデザインのメンズ腕時計');
        $response->assertSee('良好');

        //comment
        $response->assertSee('(1)');
        $response->assertSee('comment.png');
        $response->assertSee('test');

        //---------- カテゴリー 表示------------

        //category
        $response->assertSee('ファッション');
        $response->assertSee('家電');

        $response->assertDontSee('インテリア');//非表示


        return;
    }
}

/*
　　　　------------------- ダミーデータ ---------------------------------
                 //4
                 [
                    'user_id' => 1,
                    'item_image' => 'Item-Leather+Shoes+Product+Photo.jpg',
                    'item_name' => '革靴',
                    'brand_name' => null,
                    'price' => 4000,
                    'description' => 'クラシックなデザインの革靴',
                    'condition' => '状態が悪い',

                ],
                //5
                [
                    'user_id' => 2,
                    'item_image' => 'Item-Living+Room+Laptop.jpg',
                    'item_name' => 'ノートPC',
                    'brand_name' => null,
                    'price' => 45000,
                    'description' => '高性能なノートパソコン',
                    'condition' => '良好',

                ],
                //6
                [
                    'user_id' => 2,
                    'item_image' => 'Item-Music+Mic+4632231.jpg',
                    'item_name' => 'マイク',
                    'brand_name' => null,
                    'price' => 8000,
                    'description' => '高音質のレコーディング用マイク',
                    'condition' => '目立った傷や汚れなし',

                ],
                //7
                [
                    'user_id' => 2,
                    'item_image' => 'Item-Purse+fashion+pocket.jpg',
                    'item_name' => 'ショルダーバッグ',
                    'brand_name' => 'Nine West',
                    'price' => 3500,
                    'description' => 'おしゃれなショルダーバッグ',
                    'condition' => 'やや傷や汚れあり',


                ],
                //8
                [
                    'user_id' => 3,
                    'item_image' => 'Item-Tumbler+souvenir.jpg',
                    'item_name' => 'タンブラー',
                    'brand_name' => null,
                    'price' => 500,
                    'description' => '使いやすいタンブラー',
                    'condition' => '状態が悪い',

                ],
                //9
                [
                    'user_id' => 3,
                    'item_image' => 'Item-Waitress+with+Coffee+Grinder.jpg',
                    'item_name' => 'コーヒーミル',
                    'brand_name' => null,
                    'price' => 4000,
                    'description' => '手動のコーヒーミル',
                    'condition' => '良好',

                ],
                //10
                [
                    'user_id' => 3,
                    'item_image' => 'Item-外出メイクアップセット.jpg',
                    'item_name' => 'メイクセット',
                    'brand_name' => null,
                    'price' => 2500,
                    'description' => '便利なメイクアップセット',
                    'condition' => '目立った傷や汚れなし',

                ],*/
