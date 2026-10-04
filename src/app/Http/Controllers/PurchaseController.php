<?php

namespace App\Http\Controllers;

use App\Models\Purchase;
use App\Models\Item;
use App\Models\User;
use App\Models\Profile;
use App\Http\Requests\PurchaseRequest;
use App\Http\Requests\RedirectRequest;
use App\Services\Stripe\CreateCheckoutSessionParams;
use App\Services\Stripe\StripeCheckoutService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Exception\ApiErrorException;

class PurchaseController extends Controller
{
    // コンビニ払いで支払える上限（Stripe の制限）
    const KONBINI_MAX_AMOUNT = 300000;

    // PostgreSQL の一意制約違反
    const SQLSTATE_UNIQUE_VIOLATION = '23505';

    public function purchaseItems(Request $request, StripeCheckoutService $stripe, $item_id)
    {
        $item = Item::findOrFail($item_id);
        $login_user_id = Auth::id();

        // Stripe の決済画面から「戻る」で帰ってきた場合は、確保を解除する
        $canceled = false;
        if ($request->query('checkout') === 'canceled') {
            $canceled = $this->releaseReservation($stripe, $item, $login_user_id);
        }

        if ($item->user_id == $login_user_id) {
            return redirect('/item/' . $item->id)->with('message', '自分が出品した商品は購入できません。');
        }

        // 自分が確保中（期限内）なら、その続きを案内する。他の人の確保中・売り切れは購入できない
        $own_pending = null;
        $active = $item->activePurchase;
        if ($active) {
            if ($active->user_id != $login_user_id || $active->status !== Purchase::STATUS_PENDING) {
                return redirect('/item/' . $item->id)->with('message', 'この商品は現在購入できません。');
            }
            $own_pending = $active;
        }

        $profile = Profile::where('user_id', $login_user_id)->first();

        return view(
            'purchase',
            [
                'item' => $item,
                'post_code' => $profile->post_code,
                'address' => $profile->address,
                'building' => $profile->building,
                'payment_methods' => Purchase::PAYMENT_METHOD_LABELS,
                'own_pending' => $own_pending,
                'canceled' => $canceled,
            ]
        );
    }

    // 商品を確保し、Checkout Session を作って Stripe の決済画面へ進む
    public function checkout(PurchaseRequest $request, StripeCheckoutService $stripe, $item_id)
    {
        $item = Item::findOrFail($item_id);
        $user = Auth::user();

        if ($item->user_id == $user->id) {
            return redirect('/item/' . $item->id)->with('message', '自分が出品した商品は購入できません。');
        }

        $payment_method = $request->payment_method;
        if ($payment_method === Purchase::PAYMENT_METHOD_KONBINI && $item->price > self::KONBINI_MAX_AMOUNT) {
            return redirect()->route('purchase', ['item_id' => $item->id])
                ->withErrors(['payment_method' => 'コンビニ払いは300,000円までです。'])
                ->withInput();
        }

        $now = now();

        // 自分が確保中（期限内）なら、新しく確保せずに続きへ進む
        $own_pending = Purchase::where('item_id', $item->id)
            ->where('user_id', $user->id)
            ->where('status', Purchase::STATUS_PENDING)
            ->where('expires_at', '>', $now)
            ->first();
        if ($own_pending) {
            if ($own_pending->stripe_checkout_url) {
                return redirect()->away($own_pending->stripe_checkout_url);
            }

            // コンビニ払いの支払い番号を発行済み。購入画面で案内する
            return redirect()->route('purchase', ['item_id' => $item->id]);
        }

        // 期限切れの確保を掃除する
        Purchase::where('item_id', $item->id)
            ->where('status', Purchase::STATUS_PENDING)
            ->where('expires_at', '<=', $now)
            ->update(['status' => Purchase::STATUS_EXPIRED]);

        // 確保する。金額と送付先はリクエストから受け取らず、ここで決める。
        // 期限は仮のもの（Stripe の呼び出しと解除が両方失敗しても、掃除の対象になるように）
        $profile = Profile::where('user_id', $user->id)->first();
        $expires_minutes = (int) config('services.stripe.checkout_expires_minutes');
        try {
            $purchase = Purchase::create([
                'user_id' => $user->id,
                'item_id' => $item->id,
                'status' => Purchase::STATUS_PENDING,
                'payment_method' => $payment_method,
                'amount' => $item->price,
                'delivery_address' => $profile->post_code . $profile->address . $profile->building,
                'expires_at' => $now->copy()->addMinutes($expires_minutes),
            ]);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== self::SQLSTATE_UNIQUE_VIOLATION) {
                throw $e;
            }

            // 部分ユニークインデックスに弾かれた：他の人が確保中、または売り切れ
            return redirect('/item/' . $item->id)->with('message', '他の方が購入手続き中です。');
        }

        try {
            $session = $stripe->createCheckoutSession(new CreateCheckoutSessionParams(
                purchaseId: $purchase->id,
                itemId: $item->id,
                userId: $user->id,
                itemName: $item->item_name,
                amount: $item->price,
                paymentMethod: $payment_method,
                customerEmail: $user->email,
                // {CHECKOUT_SESSION_ID} は Stripe が置き換えるので、エンコードせずに付ける
                successUrl: route('purchase.complete') . '?session_id={CHECKOUT_SESSION_ID}',
                cancelUrl: route('purchase', ['item_id' => $item->id, 'checkout' => 'canceled']),
                expiresAt: $now->timestamp + $expires_minutes * 60 + (int) config('services.stripe.checkout_expiry_buffer_seconds'),
            ));
        } catch (ApiErrorException | RuntimeException $e) {
            Log::error('Checkout: failed to create checkout session', [
                'purchase_id' => $purchase->id,
                'item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);

            // 確保を解除する
            Purchase::where('id', $purchase->id)
                ->where('status', Purchase::STATUS_PENDING)
                ->update(['status' => Purchase::STATUS_EXPIRED]);

            return redirect()->route('purchase', ['item_id' => $item->id])
                ->withErrors(['checkout' => '決済を開始できませんでした。時間をおいてもう一度お試しください。'])
                ->withInput();
        }

        // 期限は Stripe が返したものに合わせる
        Purchase::where('id', $purchase->id)
            ->where('status', Purchase::STATUS_PENDING)
            ->update([
                'stripe_checkout_session_id' => $session->sessionId,
                'stripe_checkout_url' => $session->url,
                'expires_at' => Carbon::createFromTimestamp($session->expiresAt),
            ]);

        return redirect()->away($session->url);
    }

    // 決済後に戻ってくる画面。表示するだけで、購入の確定は Webhook で行う
    public function complete(Request $request)
    {
        $session_id = $request->query('session_id');
        abort_unless(is_string($session_id) && $session_id !== '', 404);

        $purchase = Purchase::where('stripe_checkout_session_id', $session_id)
            ->where('user_id', Auth::id())
            ->first();
        abort_unless($purchase, 404);

        return view('purchase_complete', [
            'purchase' => $purchase,
            'item' => $purchase->item,
        ]);
    }

    // 自分の確保を解除する（Checkout Session を期限切れにできた場合だけ）。解除したら true
    private function releaseReservation(StripeCheckoutService $stripe, Item $item, $user_id)
    {
        $purchase = Purchase::where('item_id', $item->id)
            ->where('user_id', $user_id)
            ->where('status', Purchase::STATUS_PENDING)
            ->whereNotNull('stripe_checkout_session_id')
            ->first();
        if (!$purchase) {
            return false;
        }

        try {
            // false：すでに完了・期限切れの Session。状態は Webhook に任せる
            if (!$stripe->expireCheckoutSession($purchase->stripe_checkout_session_id)) {
                return false;
            }
        } catch (ApiErrorException $e) {
            // 通信エラーなど。確保は期限で掃除される
            Log::warning('Checkout: failed to expire checkout session on cancel', [
                'purchase_id' => $purchase->id,
                'checkout_session_id' => $purchase->stripe_checkout_session_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        Purchase::where('id', $purchase->id)
            ->where('status', Purchase::STATUS_PENDING)
            ->update(['status' => Purchase::STATUS_EXPIRED]);

        return true;
    }

    public function redirectAddress($item_id)
    {
        //dd($request);

        $item = Item::find($item_id);

        $login_user_id = Auth::id(); // 1は本番ではAuth::id();
        $profile = Profile::where('user_id', $login_user_id)->first();
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

        $profile = Profile::where('user_id', Auth::id())->first();

        $profile->post_code = $request->post_code;
        $profile->address = $request->address;
        $profile->building = $request->building;
        $profile->save();

        $item = Item::find($request->item_id);


        return redirect()->route('purchase', ['item_id' => $item->id]);
    }
}
