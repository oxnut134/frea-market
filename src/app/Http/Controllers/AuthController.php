<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\Purchase;
use App\Models\Item;
use App\Models\PurchaseItem;
use App\Models\Like;
use App\Models\Comment;
use App\Models\User;
use App\Models\Profile;
use App\Models\Category;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\ProfileFirstRequest;

class AuthController extends Controller
{

    /*public function redirectByReferer(Request $request)
    {
        // リクエストからrefererを取得
        $referer = $request->headers->get('referer');

        // ここで登録処理を行う（例: フォームのバリデーションなど）

        // 登録処理が成功した場合のリダイレクト
        return $this->redirectToRoute('redirectAfterRegister', [
            'referer' => $referer // refererを渡す
        ]);
    }*/

    /**
     * @Route("/redirectAfterRegister", name="redirectAfterRegister")
     */
    /*public function redirectAfterRegister(Request $request)
    {
        // refererを取得
        $referer = $request->query->get('referer');
        //dd($referer);
        // refererに基づいてリダイレクト先を決定
        if (strpos($referer, 'login') !== false) {
            return $this->redirectToRoute('login_success'); // ログインページから来た場合
        } elseif (strpos($referer, 'homepage') !== false) {
            return $this->redirectToRoute('homepage'); // ホームページから来た場合
        } else {
            return $this->redirectToRoute('default'); // デフォルトのリダイレクト先
        }
    }*/
    public function index(Request $request) //商品一覧
    {
        //dd($request);
        $referer = $request->headers->get('referer');
        $verified = $request->verified;
        //dd($verified);

        if ($referer == "http://localhost/register") { //in case from register

            // I would like to add some codes for switching ways to profile page or guidance of  verification mail.
            // But now it's impossible because this method is passed by bypass root mede by laravel session.
            // timer ??min
            //
            //dd(Auth::id());
            //Auth::logout();

            return view('emails.guide_verification');
            //return redirect('/profile/first');
        } elseif ($verified == 1) {                    // in case from verification mail

            //return view('emails.verify_email');
            return redirect('/profile/first');
        } else {
            $items = Item::all();

            //return view('index');
            return view('index', ['items' => $items]);
        }
    }

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

    // Following 2 methods are used just for testing
    public function login()
    {
        return view('index');
    }
    public function register()
    {
        return view('profile');
    }

}
