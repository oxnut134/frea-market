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
        $profile = new Profile;
        $profile->user_id = Auth::id(); //1は本番ではAuth::id()となる
        if ($request->profile_image == null) {
            //$profile->profile_image = $request->backup_image;
        } else {
            // get new file attributes from temporary directory of PHP when image file was replaced.
            $file = $request->file('profile_image');
            //get new file name
            $originalFileName = $file->getClientOriginalName();
            //set new file name
            $profile->profile_image = $originalFileName;
        }
        //$profile->profile_image = $request->profile_image;

        $profile->post_code = $request->post_code;
        $profile->address = $request->address;
        $profile->building = $request->building;

        $profile->save();

        return redirect('/');
    }

}
