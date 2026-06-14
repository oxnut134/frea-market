<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Purchase;
use App\Models\Item;
use App\Models\Like;
use App\Models\Comment;
use App\Models\User;
use App\Models\Profile;
use App\Models\Category;
use App\Models\ItemCategory;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ExhibitRequest;

class ItemController extends Controller
{
    public function index(Request $request)
    {
        //dd($request);
        $referer = $request->headers->get('referer');
        $verified = $request->verified;
        //dd($verified);

        // routing by header of $request sent from Fortify

        if ($referer == "http://localhost/register") { //in case from register

            return view('emails.guide_verification');
            //return redirect('/profile/first');
        } elseif ($verified == 1) {                    // in case from verification mail

            //return view('emails.verify_email');
            return redirect('/profile/first');
        }

        $tab = $request->query('tab');
        $keyword = $request->query('keyword');
        //dd(Auth::id());
        if ($tab === 'mylist') {
            $keyword = $request->keyword;
            //dd($keyword);
            //検索状態保持
            $items = Item::where('item_name', 'like', '%' . $keyword . '%')
                ->whereHas('likeToUser', function ($query) {
                    $query->where('user_id', Auth::id());
                }) // 認証されたユーザーのIDでフィルタリング
                //->whereHas('likes')
                //->whereHas('likeToUser', function ($query) {
                //  $query->where('user_id', Auth::id());
                //}) // 認証されたユーザーのIDでフィルタリング
                ->where('user_id', '!=', Auth::id()) //本番はこちらを追記/自分の出品でない
                ->get();

            return view('index', [
                'items' => $items,
                'keyword' => $keyword,
            ]);
        } else {
            $items = Item::all(); //全てのアイテムを取得
            //$items = Item::where('user_id', '!=', Auth::id())->get(); //自分の出品は表示なし

            //購入済みの時statusカラムにsoldをセット
            foreach ($items as $item) {
                if (Purchase::where('item_id', $item->id)->exists()) {
                    $item->status = "sold";
                } else {
                    $item->status = "";
                }
                $item->save();
            }


            return view(
                'index',
                [
                    'items' => $items,
                    //'keyword' => $keyword
                ]
            );
        }
    }
    /* ***This method is included in the function 'index' because of additional tab-processing***
    public function showMyList(Request $request)
    {
        //$items = Item::where('item_name', 'like', '%' . $request['keyword'] . '%')->get();
        //$items = $items->whereHas('likes')->get();
        //dd($request);
        $keyword = $request->keyword;
        //dd($keyword);
        $items = Item::where('item_name', 'like', '%' . $keyword . '%')
            ->whereHas('likeToUser', function ($query) {
                $query->where('user_id', Auth::id());}) // 認証されたユーザーのIDでフィルタリング
            ->where('user_id', '!=', Auth::id()) //本番はこちらを追記/自分の出品は表示しない
            ->get();

            //dd($items);

        return view('index', ['items' => $items]);
    }*/
    public function search(Request $request)
    {
        //dd($request);
        $items = Item::where('item_name', 'like', '%' . $request['keyword'] . '%')->get();

        //dd($items);

        return view('index', [
            'items' => $items,
            'keyword' => $request->keyword
        ]);
    }

    public function getItemDetail($id)
    {
        //dd('1');

        $item = Item::find($id);
        if (isset($item->price)) {
            $item->price = number_format($item->price); //三桁カンマ
        }
        //いいね数取得
        $like_count = $item->likes_count;
        $user_id = Auth::id();
        $my_like = Like::where('user_id', $user_id)
            ->where('item_id', $id)->count();
        //dd($my_like);
        //コメント数取得

        $comment_count = Comment::where('item_id', $id)->count();
        $first_comment = Comment::where('item_id', $id)->orderBy('created_at', 'desc')->first();

        $auth_id = Auth::id();
        $user = User::find($auth_id);
        $profile = Profile::where('user_id', $auth_id)->first();

        //中間テーブル経由で関係するカテゴリーをすべて取得
        //          Itemモデルの当該レコード->モデルItemのﾘﾚｰｼｮﾝMethod名
        $categories = $item->category;
        //dd($categories);

        $auth_id = Auth::id();
        if ($auth_id != NULL) {
            $user = User::find($auth_id);
            $profile = Profile::where('user_id', $auth_id)->first();
            //dd($first_comment);
            return view(
                'detail',

                [
                    'item' => $item,
                    'likes' => $like_count,
                    'my_like' => $my_like,
                    'comments' => $comment_count,
                    'first_comment' => $first_comment,
                    'user_name' => $user->name,
                    'profile_image' => $profile->profile_image,
                    'categories' => $categories //配列渡し
                ]
            );
        } else {
            return view(
                'detail',

                [
                    'item' => $item,
                    'likes' => $like_count,
                    'my_like' => $my_like,
                    'comments' => $comment_count,
                    'first_comment' => $first_comment,
                    'categories' => $categories //配列渡し
                ]
            );
        }
    }

    public function exhibitItems()
    {
        $categories = Category::all();

        //dd($categories);
        return view('exhibit', ['categories' => $categories]);
    }
    public function upItem(ExhibitRequest $request)
    {

        //dd($request);
        $item = new Item;
        $item->user_id = Auth::id();              //1は本番ではAuth::id()となる
        //$item->item_image = $request->item_image;
        $item->item_name = $request->item_name;
        $item->brand_name = $request->brand_name;
        $item->price = $request->price;
        $item->description = $request->description;
        $item->condition = $request->condition;
        if ($request->item_image == null) {
        } else {
            // get new file attributes from temporary directory of PHP when image file was replaced.
            $file = $request->file('item_image');
            //get new file name
            $originalFileName = $file->getClientOriginalName();
            //set new file name
            $item->item_image = $originalFileName;
        }
        $item->save();

        // 新しいアイテムIDを取得
        $new_item_id = $item->id;

        // categories 配列の各要素に対して処理、要素数分のループ　　
        for ($i = 0; $i < count($request->categories); $i++) {
            $item_category = new ItemCategory();  // 新しい ItemCategory インスタンスを作成
            $item_category->item_id = $new_item_id;
            $category = Category::where('category', $request->categories[$i])->first();
            // カテゴリーが存在する場合のみ設定
            if ($category) {
                $item_category->category_id = $category->id;
                $item_category->save(); // データベースに保存
            }
        }
        //return view('exhibit');
        return back();
    }
}
