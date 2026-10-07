<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ProfileRequest;


class UserController extends Controller
{

    public function myPage(Request $request)
    {
        // マイページに並べるのは、自分が出品した商品と、自分が購入した商品だけ。
        // タブの指定がなければ「出品した商品」（知らない値も同じ）
        if ($request->query('tab') === 'buy') {
            return $this->getPurchasedItems();
        }

        return $this->getExhibitedItems();
    }

    public function getPurchasedItems()

    {
        $auth_id = Auth::id();
        /*$items = Item::whereHas('purchase')
            ->where('user_id', $auth_id) //本番はAuth::id()となる
           ->get();*/
    
        // 支払い済みの商品と、自分が確保中（期限内）の商品
        $items = Item::with('activePurchase')->whereHas('activePurchase', function ($query) {
            $query->where('user_id', Auth::id()); // Purchaseのuser_idがAuthと一致するもの
        })->get();
        //dd($item);
        $profile = Profile::where('user_id', $auth_id)->first(); //本番はAuth::id()となる
        $user = User::find($auth_id); //本番はAuth::id()となる

        return view(
            'mypage',
            [
                'items' => $items,
                //'keyword' => $
                'profile' => $profile,
                'user' => $user,
                'awaiting_payment' => true, // 確保中の商品を「お支払い待ち」と表示する
            ]


        );
    }
    public function getExhibitedItems()
    {
        $auth_id = Auth::id();
        $items = Item::with('activePurchase')->where('user_id', $auth_id)->get();
        //dd($item);
        $profile = Profile::where('user_id', $auth_id)->first(); //本番はAuth::id()となる
        $user = User::find($auth_id); //本番はAuth::id()となる

        return view(
            'mypage',
            [
                'items' => $items,
                //'keyword' => $
                'profile' => $profile,
                'user' => $user

            ]
        );
    }
    public function showProfile()
    {
        $auth_id = Auth::id();
        $user = User::find($auth_id);
        $profile = Profile::where('user_id', $auth_id)->first();

        return view(
            'profile',
            [
                'profile' => $profile,
                'user' => $user,
            ]
        );
    }
    public function updateProfile(ProfileRequest $request)
    {
        //dd($request);
        $user_id = Auth::id();
        $user = User::find($user_id);   //1は本番ではAuth::id()となる
        $profile = Profile::where('user_id', $user_id)->first();

        $user->name = $request->user_name;
        // 画像が選択された場合だけ差し替える（選択されていない場合は現在の画像のまま）
        $disk = Storage::disk(config('filesystems.images'));
        $old_image = $profile->profile_image;
        if ($request->hasFile('profile_image')) {
            $profile->profile_image = $request->file('profile_image')->store('profiles', config('filesystems.images'));
        }
        $profile->post_code = $request->post_code;
        $profile->address = $request->address;
        $profile->building = $request->building;
        $user->save();
        $profile->save();

        // 差し替えた場合は、保存が終わってから古い画像を削除する
        if ($old_image && $old_image !== $profile->profile_image) {
            $disk->delete($old_image);
        }

        return redirect()->route('mypage');
    }
}
