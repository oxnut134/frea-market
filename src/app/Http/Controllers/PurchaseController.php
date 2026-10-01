<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Item;
use App\Models\User;
use App\Models\Profile;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\RedirectRequest;

class PurchaseController extends Controller
{
    public function purchaseItems($item_id)
    {
        $item = Item::find($item_id);

        $login_user_id = Auth::id(); // 1は本番ではAuth::id();
        $profile = Profile::where('user_id', $login_user_id)->first();
        $post_code = $profile->post_code;
        $address = $profile->address;
        $building = $profile->building;

        //$purchase = Purchase::find($login_user_id);
        $purchase = Purchase::where('item_id', $item_id)
            ->where('user_id', $login_user_id)
            ->first(); // 1件だけ取得する場合
        $payment_method = $purchase ? $purchase->payment_method : "コンビニ払い";

        $email = User::find($login_user_id)->email;


        return view(
            'purchase',
            [
                'item' => $item,
                'post_code' => $post_code,
                'address' => $address,
                'building' => $building,
                'payment_method' => $payment_method,
                'email' => $email
            ]
        );
    }

    public function redirectAddress($item_id)
    {
        //dd($request);

        $item = Item::find($item_id);

        $login_user_id = Auth::id(); // 1は本番ではAuth::id();
        $profile = Profile::find($login_user_id);
        $post_code = $profile->post_code;
        $address = $profile->address;
        $building = $profile->building;
        //dd($payment_method);
        //$purchase = Purchase::where('user_id',$login_user_id)->first();
        $email = User::find($login_user_id)->email;
        return view(
            'redirect',
            [
                'item' => $item,
                'post_code' => $post_code,
                'address' => $address,
                'building' => $building,
                'email' => $email,
            ]
        );
    }
    public function returnPurchase(RedirectRequest $request)
    {

        $profile = Profile::find(Auth::id()); //1は本番時Auth::id()に置き換え

        $profile->post_code = $request->post_code;
        $profile->address = $request->address;
        $profile->building = $request->building;
        $profile->save();

        $item = Item::find($request->item_id);


        return redirect()->route('purchase', ['item_id' => $item->id]);
    }
}
