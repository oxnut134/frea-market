<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Item;
use App\Models\Purchase;
use Illuminate\Support\Facades\Auth;


class IndexFunctionTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    /*public function test_example()
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }*/
    use RefreshDatabase;

//---------------   全商品　取得　---------------------------
/*

       public function testGetAllItems()
    {
 var_dump("1st_Method: testGetAllItems");
       //dd(2);
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


        $items =
            [
                //1
                [
                    'user_id' => 1,
                    'item_image' => 'Item-Armani+Mens+Clock.jpg',
                    'item_name' => '腕時計',
                    'brand_name' => 'Armani',
                    'price' => 15000,
                    'description' => 'スタイリッシュなデザインのメンズ腕時計',
                    'condition' => '良好',
                ],
                //2
                [
                    'user_id' => 1,
                    'item_image' => 'Item-HDD+Hard+Disk.jpg',
                    'item_name' => 'HDD',
                    'brand_name' => '',
                    'price' => 5000,
                    'description' => '高速で信頼性の高いハードディスク',
                    'condition' => '目立った傷や汚れなし',
                ],
                //3
                [
                    'user_id' => 1,
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

        // 2. ユーザーとしてログイン
        //$user = User::first();
        //$this->actingAs($user);
            // 2. ユーザーとしてログイン
    $user = User::first(); // 最初のユーザーを取得
    $this->actingAs($user);


        // 3. 商品一覧ページにアクセス
        $response = $this->get('/');

        // 4. 結果の確認
        // ステータスコード200を確認
        $response->assertStatus(200);

        // 商品データがレスポンスに含まれているか確認
        foreach ($items as $item) {
            $response->assertSee($item['item_name']);
            $response->assertSee($item['item_image']);
//            $response->assertSee($item['status']);
        }

        return ;


    }
*/
//-------------------- 'sold' 表示　--------------------------


/*          public function testCeckSoldOutSign()
    {
var_dump("2nd_Method: testGetAllItems");
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

// ユーザーとアイテムを取得
$user = User::first(); // usersテーブルの最初のレコードを取得
$item = Item::first(); // itemsテーブルの最初のレコードを取得

// 購入データを作成
$purchases = [
    [
        'user_id' => $user->id, // ユーザーのIDをリンク
        'item_id' => $item->id, // アイテムのIDをリンク
        //'payment_method' => 'コンビニ払い',
        //'delivery_address' => 'test'
    ],
];

//foreach ($purchases as $purchase) {
//    Purchase::create($purchase);
//}
        foreach ($purchases as $purchase) {

            $new_purchase = new Purchase;
            $new_purchase->user_id = $purchase['user_id'];
            $new_purchase->item_id = $purchase['item_id'];
            //$new_purchase->payment_method = $purchase['payment_method'];
            //$new_purchase->delivery_address = $purchase['delivery_address'];

            $new_purchase->save();
        }

        $user = User::where('email', 'cat@test.com')->first();

        $response = $this->actingAs($user)->get('/');
        $response->assertSee('sold');


        return ;

    }

*/
//----------------------  自己出品　非表示　-------------------------------


    public function testWithoutMyExhibition()
    {
var_dump("3rd_Method: testGetAllItems");
         $response = $this->get('/');
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

            $new_user->save();
        }

        $user = User::where('email', 'cat@test.com')->first();

        $response = $this->actingAs($user)->get('/');
        //$response->assertSee('sold');
        $user_id = Auth::id();
        $userItems = Item::where('user_id', $user_id)->get();
        foreach ($userItems as $item) {
            $response->assertDontSee($item->item_name); // アイテム名が含まれていないことを確認
        }
        $response->assertStatus(200);

        return;
    }
} // -------------------- end of class  -----------------------------------

//----------------------- item ダミーデータ --------------------------------


                //4
/*                [
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
