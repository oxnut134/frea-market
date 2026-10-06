<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Profile;
use App\Models\Item;
use App\Models\Like;
use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;


class SearchItemsTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;


    // ---------------- Search-1 いいねアイテムのみ表示　---------------------





    public function testPartialMatchSearchItemName()
    {
        var_dump("*****1st_Method:testPartialMatchSearchItemName");
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
            User::create($user);
        }

        $users = User::orderBy('id')->get();
        //$users = User::factory()->count(3)->create();

        $items = [
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-Armani+Mens+Clock.jpg',
                'item_name' => '腕時計',
                'brand_name' => 'Armani',
                'price' => 15000,
                'description' => 'スタイリッシュなデザインのメンズ腕時計',
                'condition' => '良好',
            ],
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-HDD+Hard+Disk.jpg',
                'item_name' => 'HDDセット',
                'brand_name' => '',
                'price' => 5000,
                'description' => '高速で信頼性の高いハードディスク',
                'condition' => '目立った傷や汚れなし',
            ],
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-iLoveIMG+d.jpg',
                'item_name' => '玉ねぎセット',
                'brand_name' => null,
                'price' => 300,
                'description' => '新鮮な玉ねぎ3束のセット',
                'condition' => '状態が悪い',
            ],
            //4
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-Leather+Shoes+Product+Photo.jpg',
                'item_name' => '革靴',
                'brand_name' => null,
                'price' => 4000,
                'description' => 'クラシックなデザインの革靴',
                'condition' => '状態が悪い',

            ],
        ];

        foreach ($items as $item) {
            (new Item)->forceFill($item)->save(); // item_image は $fillable 対象外のため
        }
        $user = User::orderBy('id')->first(); // usersテーブルの最初のレコードを取得
        $user->forceFill(['email_verified_at' => now()])->save(); // メール認証済みにする
        Profile::create(['user_id' => $user->id, 'profile_image' => 'person.png', 'post_code' => '111-1111', 'address' => 'Tokyo']);
        $item = Item::orderBy('id')->first(); // itemsテーブルの最初のレコードを取得

        // /search は auth ミドルウェア配下のためログインする
        $this->actingAs($user);


        // 検索キーワード
        $keyword = 'セット';

        // 検索リクエストをPOSTで実行
        $response = $this->post('/search', [
            'keyword' => $keyword,
        ]);
        //dd($response);
        // 部分一致する商品が表示されることを確認
        $response->assertStatus(200);
        $response->assertSee('HDDセット');
        $response->assertSee('玉ねぎセット');

        // 部分一致しない商品が表示されないことを確認
        $response->assertDontSee('腕時計');
        $response->assertDontSee('革靴');

        return;
    }



    //--------------------Search-2 myList表示時 検索状態保持　--------------------------


    /*

    public function testMyListWithKeepSearchCondition()
    {
        var_dump("*****1st_Method:testMyListWithKeepSearchCondition");
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
            User::create($user);
        }

        $users = User::orderBy('id')->get();
        //$users = User::factory()->count(3)->create();

        $items = [
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-Armani+Mens+Clock.jpg',
                'item_name' => '腕時計',
                'brand_name' => 'Armani',
                'price' => 15000,
                'description' => 'スタイリッシュなデザインのメンズ腕時計',
                'condition' => '良好',
            ],
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-HDD+Hard+Disk.jpg',
                'item_name' => 'ＨＤＤセット',
                'brand_name' => null,
                'price' => 5000,
                'description' => '高速で信頼性の高いハードディスク',
                'condition' => '目立った傷や汚れなし',
            ],
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-iLoveIMG+d.jpg',
                'item_name' => '玉ねぎセット',
                'brand_name' => null,
                'price' => 300,
                'description' => '新鮮な玉ねぎ3束のセット',
                'condition' => '状態が悪い',
            ],
            //4
            [
                'user_id' => $users[2]->id,
                'item_image' => 'Item-Leather+Shoes+Product+Photo.jpg',
                'item_name' => '革靴',
                'brand_name' => null,
                'price' => 4000,
                'description' => 'クラシックなデザインの革靴',
                'condition' => '状態が悪い',

            ],
        ];

        //        foreach ($items as $item) {
        //    Item::create($item);
        //}
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



        $user = User::orderBy('id')->first(); // usersテーブルの最初のレコードを取得
        $item = Item::orderBy('id')->first(); // itemsテーブルの最初のレコードを取得

        $likes = [
            [
                'user_id' => $user['id'],
                'item_id' => $item['id']+1,
            ]
        ];

        // 一括挿入
        Like::insert($likes); // likesテーブルに一括でいいねを保存

        //login
        $this->actingAs($user);
        $response = $this->get('/');

        // 検索キーワード
        $keyword = 'セット';

        // 検索リクエストをPOSTで実行
        $response = $this->post('/search', [
            'keyword' => $keyword,
        ]);
        //dd($response)get
        // 部分一致する商品の表示を確認
        $response->assertStatus(200);
        $response->assertSee('ＨＤＤセット');
        $response->assertSee('玉ねぎセット');

        // 部分一致なしの商品の非表示を確認
        $response->assertDontSee('腕時計');
        $response->assertDontSee('革靴');

        $response = $this->get('/?tab=mylist');

        //検索結果の保持の確認
        $response->assertSee('ＨＤＤセット');

        $response->assertDontSee('玉ねぎセット');
        $response->assertDontSee('腕時計');
        $response->assertDontSee('革靴');


        return;
    }
*/
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
