<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Item;
use App\Models\Like;
use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;


class MylistFunctionTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;


    // ---------------- Mylist-1 いいねアイテムのみ表示　---------------------


        public function testCheckSoldOutSign()
    {
        var_dump("*****1st_Method:testCheckSoldOutSign");
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

            $new_user->save();
        }

        $users = User::all();
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
        $user = User::first(); // usersテーブルの最初のレコードを取得
        $item = Item::first(); // itemsテーブルの最初のレコードを取得


        $likes = [
            [
                'user_id' => $user->id, // ユーザーのIDをリンク
                'item_id' => $item->id, // アイテムのIDをリンク
            ],
        ];

        foreach ($likes as $like) {

            $new_like = new Like;
            $new_like->user_id = $like['user_id'];
            $new_like->item_id = $like['item_id'];

            $new_like->save();
        }

        $user = User::where('email', 'cat@test.com')->first();
        $like = Like::first();
        $response = $this->actingAs($user)->get('/?tab=mylist');
        $response->assertSee(
            Like::where('item_id', $item->id)
                ->where('user_id', $user->id)
                ->exists() ? $item->item_image : null
        );
        //$response->assertSee($item->Item_image->$like->where('user_id',$user->id)->where('item_id',$item->id)->exist());
        //$response->assertSee( asset('storage/liked.png') );


        return;
    }

    //--------------------mylist-2 'sold' 表示　--------------------------
/*

    public function testCheckSoldOutSign()
    {
        var_dump("****2nd_Method: testCheckSoldOutSign");
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

            $new_user->save();
        }

        $users = User::all();
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

        $user = User::first(); // usersテーブルの最初のレコードを取得
        $item = Item::first(); // itemsテーブルの最初のレコードを取得

//        $user = User::first(); // usersテーブルの最初のレコードを取得
//       $item = Item::first(); // itemsテーブルの最初のレコードを取得
        //いいねテーブル作成
        $likes = [
            [
                'user_id' => $user->id, // ユーザーのIDをリンク
                'item_id' => $item->id, // アイテムのIDをリンク
            ],
        ];

        foreach ($likes as $like) {

            $new_like = new Like;
            $new_like->user_id = $like['user_id'];
            $new_like->item_id = $like['item_id'];

            $new_like->save();
        }


        // 購入テーブル作成
        $purchases = [
            [
                'user_id' => $user->id, // ユーザーのIDをリンク
                'item_id' => $item->id, // アイテムのIDをリンク
                //'payment_method' => 'コンビニ払い',
                //'delivery_address' => 'test'
            ],
        ];

        foreach ($purchases as $purchase) {

            $new_purchase = new Purchase;
            $new_purchase->user_id = $purchase['user_id'];
            $new_purchase->item_id = $purchase['item_id'];
            //$new_purchase->payment_method = $purchase['payment_method'];
            //$new_purchase->delivery_address = $purchase['delivery_address'];

            $new_purchase->save();
        }
//

        //いいねしたものだけ表示チェック
        $user = User::where('email', 'cat@test.com')->first();

                $like = Like::first();
               $response = $this->actingAs($user)->get('/?tab=mylist');
                $response->assertSee(
                    Like::where('item_id', $item->id)
                        ->where('user_id', $user->id)
                        ->exists() ? $item->item_image : null
               );

        //sold表示チェック

        $response = $this->actingAs($user)->get('/');

        $response = $this->actingAs($user)->get('/?tab=mylist');
        //dd($response);
        $response->assertSee('sold');


        return;
    }

*/
    //--------------------mylist-3 自己出品は非表示　--------------------------
/*

    public function testCheckNotShowSelfExhibitedItems()
    {
        var_dump("***3rd_Method: testCheckNotShowSelfExhibitedItems");
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

            $new_user->save();
        }

        $users = User::all();
        //$users = User::factory()->count(3)->create();

        $items = [
            [
                'user_id' => $users[0]->id,
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

        $user = User::first(); // usersテーブルの最初のレコードを取得
        $item = Item::first(); // itemsテーブルの最初のレコードを取得

        //いいねテーブル作成
        $likes = [
            [
                'user_id' => $user->id, // ユーザーのIDをリンク
                'item_id' => $item->id, // アイテムのIDをリンク
            ],
        ];

        foreach ($likes as $like) {

            $new_like = new Like;
            $new_like->user_id = $like['user_id'];
            $new_like->item_id = $like['item_id'];

            $new_like->save();
        }


        // 購入テーブル作成
        $purchases = [
            [
                'user_id' => $user->id, // ユーザーのIDをリンク
                'item_id' => $item->id, // アイテムのIDをリンク
            ],
        ];

        foreach ($purchases as $purchase) {

            $new_purchase = new Purchase;
            $new_purchase->user_id = $purchase['user_id'];
            $new_purchase->item_id = $purchase['item_id'];

            $new_purchase->save();
        }
        //自己出品　非表示

        $user = User::where('email', 'cat@test.com')->first();
        $response = $this->actingAs($user)->get('/');
        $response = $this->actingAs($user)->get('/?tab=mylist');
        $user_id = Auth::id();
        //dd('****',$user_id);
        //出品アイテム取得
        $userItems = Item::where('user_id', $user_id)->get();
        foreach ($userItems as $item) {
            $response->assertDontSee($item->item_name); // アイテム名が含まれていないことを確認
        }

        $response->assertStatus(200);


        return;
    }

*/

    //--------------------mylist-4 未認証ユーザーには全非表示　--------------------------
/*

    public function testCheckNotAuthorizedUser()
    {
        var_dump("***4th_Method: testCheckNotAuthorizedUser");
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

            $new_user->save();
        }

        $users = User::all();
        //$users = User::factory()->count(3)->create();

        $items = [
            [
                'user_id' => $users[0]->id,
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

        $user = User::first(); // usersテーブルの最初のレコードを取得
        $item = Item::first(); // itemsテーブルの最初のレコードを取得



        // 購入テーブル作成
        $purchases = [
            [
                'user_id' => $user->id, // ユーザーのIDをリンク
                'item_id' => $item->id, // アイテムのIDをリンク
            ],
        ];

        foreach ($purchases as $purchase) {

            $new_purchase = new Purchase;
            $new_purchase->user_id = $purchase['user_id'];
            $new_purchase->item_id = $purchase['item_id'];

            $new_purchase->save();
        }
        //自己出品　非表示
        $response = $this->get('/?tab=mylist');
        $userItems = Item::all();
        foreach ($userItems as $item) {
            $response->assertDontSee($item->item_name); // アイテム名が含まれていないことを確認
        }

        $response->assertStatus(302);


        return;
    }*/
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
