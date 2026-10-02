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

class LikeFunctionTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

    public function testAdditionaLikeByClick()
    {
        var_dump("***** 1st_Method:testAdditionaLikeByClick");
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

        $users = User::all();
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
        //---------------Ｌｉｋｅテーブル-----------------------------
        //-テスト（１）の場合コメントアウト
/*
        $user = User::first(); // usersテーブルの最初のレコードを取得
        $item = Item::first(); // itemsテーブルの最初のレコードを取得
        //最初のアイテムにいいね
        $likes = [
            'user_id' => $user['id'], // 最初のユーザーのIDをリンク
            'item_id' => $item['id'], // アイテムのIDをリンク
        ];
        // 一括挿入
        Like::insert($likes); // likesテーブルにいいねを保存
        //-----------------------------------------------------------------
*/
        //コメント
        $user = User::first(); // usersテーブルの最初のレコードを取得
        $item = Item::first(); // itemsテーブルの最初のレコードを取得

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
        $category = Category::first(); // categoriesテーブルの最初のレコードを取得
        $item = Item::first(); // itemsテーブルの最初のレコードを取得

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
        $user = User::first(); // usersテーブルの最初のレコードを取得
        $profiles = [
            [
                'user_id' => $user['id'], // ユーザーのIDをリンク
                'profile_image' => 'comment.png',
                'post_code' => '111-1111',
                'address' => 'Tokyo'
            ],
        ];
        Profile::insert($profiles); //profilesテーブルに一括を保存



        //---------------- テスト実行　--------------------------------

        /*
//（１）likeテーブルにいいね登録、画像切り替え（色変更）、いいね解除のテスト　（テーブルにレコードなし）

        // ログイン
        $this->actingAs($user);
        $response = $this->get('/');
        $response = $this->get('/item/1');

        // いいね数’０’を確認( リレーション使用)
        $this->assertEquals(0, $item->likes()->count());

        // いいね追加のリクエスト送信
        $responseAdd = $this->get("http://localhost/like/{$item->id}/add");
        $responseAdd->assertStatus(200);

        // APIのレスポンスで画像切替えを確認
        $responseAdd->assertJson([
            'likes' => 1, // いいね数が1であることを確認
        ]);

        // ＤＢのいいね数が増加を確認
        $item->refresh(); // 最新のデータを取得( リレーション使用)
        $this->assertEquals(1, $item->likes()->count());

        // いいね削除のリクエスト送信
        $responseRemove = $this->get("http://localhost/like/{$item->id}/remove");
        $responseRemove->assertStatus(200);

        // APIのレスポンスで画像切替えを確認

        $responseRemove->assertJson([
            'likes' => 0, // いいね数が0であることを確認
        ]);

        // ＤＢのいいね数減少を確認( リレーション使用)
        $item->refresh(); // 最新のデータを取得
        $this->assertEquals(0, $item->likes()->count());

        return;

*/

        //-----------（２）クリックのテスト／likeテーブルに自分のレコードがある場合、ない場合のテスト-----------------------------

        //最初に現在のいいね全レコードを取得して以下を確認するテストコードを作成
        //自分のいいねがある場合：最初のクリックでい取得したいいね数ー１、二回めで一回目＋１、三回目で二回めー１
        //自分のいいねがない場合：最初のクリックでい取得したいいね数＋１、二回めで一回目ー！、三回目で二回め＋１


        // 現在のいいね数を取得
        $initialLikesCount = Like::where('item_id', $item->id)->count();

        // ユーザーとしてログイン
        $this->actingAs($user);

        // 自分のいいねがあるかどうかを確認
        $userLikeExists = Like::where('user_id', $user->id)->where('item_id', $item->id)->exists();

        // 自分のいいねがある場合
        if ($userLikeExists) {
            // 初回クリック: いいねを削除する
            $responseFirstClick = $this->get("/like/{$item->id}/remove");
            $responseFirstClick->assertStatus(200);
            $this->assertFalse(Like::where('user_id', $user->id)->where('item_id', $item->id)->exists());
            $this->assertEquals($initialLikesCount - 1, Like::where('item_id', $item->id)->count());

            // 2回目クリック: いいねを追加する
            $responseSecondClick = $this->get("/like/{$item->id}/add");
            $responseSecondClick->assertStatus(200);
            $this->assertTrue(Like::where('user_id', $user->id)->where('item_id', $item->id)->exists());
            $this->assertEquals($initialLikesCount, Like::where('item_id', $item->id)->count());

            // 3回目クリック: いいねを削除する
            $responseThirdClick = $this->get("/like/{$item->id}/remove");
            $responseThirdClick->assertStatus(200);
            $this->assertFalse(Like::where('user_id', $user->id)->where('item_id', $item->id)->exists());
            $this->assertEquals($initialLikesCount - 1, Like::where('item_id', $item->id)->count());
        } else {
            // 自分のいいねがない場合
            // 初回クリック: いいねを追加する
            $responseFirstClick = $this->get("/like/{$item->id}/add");
            $responseFirstClick->assertStatus(200);
            $this->assertTrue(Like::where('user_id', $user->id)->where('item_id', $item->id)->exists());
            $this->assertEquals($initialLikesCount + 1, Like::where('item_id', $item->id)->count());

            // 2回目クリック: いいねを削除する
            $responseSecondClick = $this->get("/like/{$item->id}/remove");
            $responseSecondClick->assertStatus(200);
            $this->assertFalse(Like::where('user_id', $user->id)->where('item_id', $item->id)->exists());
            $this->assertEquals($initialLikesCount, Like::where('item_id', $item->id)->count());

            // 3回目クリック: いいねを追加する
            $responseThirdClick = $this->get("/like/{$item->id}/add");
            $responseThirdClick->assertStatus(200);
            $this->assertTrue(Like::where('user_id', $user->id)->where('item_id', $item->id)->exists());
            $this->assertEquals($initialLikesCount + 1, Like::where('item_id', $item->id)->count());
        }
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
