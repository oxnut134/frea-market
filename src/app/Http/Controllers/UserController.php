<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Purchase;
use App\Models\Item;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ProfileRequest;


class UserController extends Controller
{

    public function myPage(Request $request)
    {
        $tab = $request->query('tab');
        //dd($tab);

        switch ($tab) {
            case "buy":
                return $this->getPurchasedItems();

            case "sell":
                return $this->getExhibitedItems();

            default:
                return $this->getProfile();
        }
    }




    public function getProfile()
    {
        //$items = Item::all();
        $items = Item::where('user_id', '!=', Auth::id())->get(); //本番はこちらに変更/自分の出品は表示なし
        foreach ($items as $item) {
            if (Purchase::where('item_id', $item->id)->exists()) {
                $item->status = "sold";
            } else {
                $item->status = "";
            }
            $item->save();
        }
        $auth_id = Auth::id();
        $user = User::find($auth_id);
        $profile = Profile::where('user_id', $auth_id)->first(); //本番はAuth::id()となる
        //dd($profile);
        return view(
            'mypage',
            [
                'items' => $items,
                //'keyword' => $
                'profile' => $profile,
                'user' => $user,

            ]
        );
        //dd('on getProfile');
        //return view('mypage');
    }
    public function getPurchasedItems()

    {
        $auth_id = Auth::id();
        /*$items = Item::whereHas('purchase')
            ->where('user_id', $auth_id) //本番はAuth::id()となる
           ->get();*/
    
        $items = Item::whereHas('purchase', function ($query) {
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
                'user' => $user
            ]


        );
    }
    public function getExhibitedItems()
    {
        $auth_id = Auth::id();
        $items = Item::all()->where('user_id', $auth_id);
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
    public function showProfile(Request $request)
    {
        //dd($request);
        $backup_image = $request->profile_image;

        return view(
            'profile',
            [
                'profile' => $request,
                'backup_image' => $backup_image
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
        if ($request->profile_image == null) {
            $profile->profile_image = $request->backup_image;
        } else {
            // get new file attributes from temporary directory of PHP when image file was replaced.
            $file = $request->file('profile_image');
            //get new file name
            $originalFileName = $file->getClientOriginalName();
            //set new file name
            $profile->profile_image = $originalFileName;
        }
        $profile->post_code = $request->post_code;
        $profile->address = $request->address;
        $profile->building = $request->building;
        //dd($profile);
        $user->save();
        $profile->save();

        //dd(Profile::find(1));
        return redirect()->route('mypage');
    }
}
