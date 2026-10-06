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

class CommentFunctionTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    use RefreshDatabase;

//----------------------- 1st method ---------------------------------

    public function testCantPostCommentBeforeLogin()
    {
        var_dump("***** 1st_Method:testCantPostCommentBeforeLogin");
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



        //---------------- コメント登録確認　--------------------------------

        //ログイン前

        // コメント送信リクエスト
        $response = $this->post("/item/comment", [
            'item_id' => $item->id,
            'comment' => 'test',
        ]);

        // ステータスコード確認（ログイン画面へ送られる）
        $response->assertStatus(302);
        $response->assertRedirect('/login');
        $this->assertGuest();

        // コメントのデータベースに保存なしを確認
        $this->assertDatabaseMissing('comments', [
            'item_id' => $item->id,
            'comment' => 'test',
        ]);
        $this->assertEquals(0, Comment::count());


                return;
           }
    //------------------------- 2 nd method  ---------------------------------
    /*

     public function testCantPostCommentAfterLogin()
    {
        var_dump("***** 2nd_Method:testCantPostCommentAfterLogin");
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



        //---------------- コメント登録確認　--------------------------------


        //ログイン後
        // ログイン
        $this->actingAs($user);
        $response = $this->get('/');
        $response = $this->get('/item/1');

        $user = User::first();
        $item = Item::first();

        // コメント送信リクエスト
        $response = $this->post("/item/comment", [
            'item_id' => $item->id,
            'user_id' => $user->id,
            'comment' => 'test',
        ]);

        // ステータスコードを確認（403 Forbiddenなど）
        $response->assertStatus(302);

        // コメントのデータベースに保存を確認
        $this->assertDatabasehas('comments', [
            'item_id' => $item->id,
            'user_id' => $user->id,
            'comment' => 'test',
        ]);



                return;
           }

*/
    //------------------------ コメント投稿者の保存確認 ------------------------------

    public function testCommentIsSavedWithLoginUser()
    {
        $seller = User::factory()->create();
        $commenter = User::factory()->create(); //出品者とは別のユーザーで投稿する
        Profile::create(['user_id' => $commenter->id, 'profile_image' => 'person.png', 'post_code' => '111-1111', 'address' => 'Tokyo']);

        $item = new Item;
        $item->user_id = $seller->id;
        $item->item_image = 'Item-Armani+Mens+Clock.jpg';
        $item->item_name = '腕時計';
        $item->brand_name = 'Armani';
        $item->price = 15000;
        $item->description = 'スタイリッシュなデザインのメンズ腕時計';
        $item->condition = '良好';
        $item->save();

        $this->actingAs($commenter);

        $response = $this->post('/item/comment', [
            'item_id' => $item->id,
            'comment' => 'test',
        ]);
        $response->assertStatus(302);

        // ログインユーザーが投稿者として保存されていることを確認
        $this->assertDatabaseHas('comments', [
            'item_id' => $item->id,
            'user_id' => $commenter->id,
            'comment' => 'test',
        ]);
        $this->assertEquals(1, Comment::count());
    }

    //------------------------ 3rd method -------------------------------------------


    public function testNoInputValidation()
    {
        var_dump("***** 3rd_Method:testNoInputValidation");
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



        //---------------- コメント登録確認　--------------------------------
        //------------- バリデーションメッセージ確認 ----------------
        //ログイン後
        // ログイン

        $user = User::first();
        $this->actingAs($user);
        $response = $this->get('/');
        $response = $this->get('/item/' . Item::first()->id);

        //コメント透谷確認
        $item = Item::first();

        $response = $this->post('/item/comment', [
            'item_id' => $item->id,
            'user_id' => $user->id,
            'comment' =>  ""
        ]);
        $response->assertStatus(302); // リダイレクトを確認

        //バリデーション確認
        //dd(session()->all());
        $response->assertSessionHasErrors(['comment' => 'コメントを入力してください。']);
        //dd(session('errors'));
        $response = $this->post('/item/comment', [
            'comment' =>  str_repeat('あ', 256)
        ]);
        $response->assertSessionHasErrors(['comment' => '２５５文字以内で入力してください。']);

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
