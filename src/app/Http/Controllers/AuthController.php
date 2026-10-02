<?php

namespace App\Http\Controllers;


use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ProfileFirstRequest;

class AuthController extends Controller
{

    public function ProfileFirst()
    {
        // プロフィール登録済みの場合は商品一覧へ
        if (Profile::where('user_id', Auth::id())->exists()) {
            return redirect('/');
        }

        $user = User::find(Auth::id());
        /*$post_code = null;
        $address = null;
        $building = null;*/

        return view(
            'profile_first',
            [
                'name' => $user->name,
             /*   'post_code' => $post_code,
                'address' => $address,
                'building' => $building,*/
            ]
        );
    }
    public function addProfile(ProfileFirstRequest $request)
    {
        // プロフィール登録済みの場合は商品一覧へ（プロフィールの重複を防ぐ）
        if (Profile::where('user_id', Auth::id())->exists()) {
            return redirect('/');
        }

        $profile = new Profile;
        $profile->user_id = Auth::id();
        // 画像は任意。選択された場合は画像用ディスクに保存し、保存先のパスを記録する
        if ($request->hasFile('profile_image')) {
            $profile->profile_image = $request->file('profile_image')->store('profiles', config('filesystems.images'));
        }

        $profile->post_code = $request->post_code;
        $profile->address = $request->address;
        $profile->building = $request->building;

        // ユーザー名は users テーブルに保存する
        $user = User::find(Auth::id());
        $user->name = $request->user_name;

        $user->save();
        $profile->save();

        return redirect('/');
    }

}
