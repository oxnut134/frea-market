<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LikeController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\StripeWebhookController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


//---------------- 模擬テスト　frea-market -----------------------------

//未ログインユーザー閲覧可能
Route::middleware('profile.exists')->group(function () {
    Route::get('/frea', [ItemController::class, 'index']);
    Route::get('/item/{item_id}', [ItemController::class, 'getItemDetail']);
});

//メール認証済みユーザーのみ
Route::middleware(['auth', 'verified'])->group(function () {

    //会員登録後のプロフィール入力
    Route::get('/profile/first', [AuthController::class, 'Profilefirst'])->name('profile.first');
    Route::post('/profile/first', [AuthController::class, 'addProfile']);
});

//メール認証済み、かつプロフィール登録済みユーザーのみ
Route::middleware(['auth', 'verified', 'profile.exists'])->group(function () {

    //http://localhost
    Route::get('/', [ItemController::class, 'index']);

    //商品検索
    Route::post('/search', [ItemController::class, 'search']);

    //商品購入
    Route::get('/purchase/complete', [PurchaseController::class, 'complete'])->name('purchase.complete');
    Route::get('/purchase/{item_id}', [PurchaseController::class, 'purchaseItems'])->whereNumber('item_id')->name('purchase');
    Route::post('/purchase/{item_id}/checkout', [PurchaseController::class, 'checkout'])->whereNumber('item_id')->name('purchase.checkout');

    //納品先住所変更
    Route::get('/purchase/address/{item_id}', [PurchaseController::class, 'redirectAddress'])->whereNumber('item_id');
    Route::post('/purchase/address/return', [PurchaseController::class, 'returnPurchase'])->name('purchase.redirect');

    //プロフィール（マイページ）
    Route::get('/mypage', [UserController::class, 'mypage'])->name('mypage');
    Route::get('/mypage/purchasedItems', [UserController::class, 'getPurchasedItems']);
    Route::get('/mypage/exhibitedItems', [UserController::class, 'getExhibitedItems']);
    Route::get('/mypage/profile', [UserController::class, 'showProfile']);
    Route::post('/mypage/profile', [UserController::class, 'updateProfile']);

    //商品出品
    Route::get('/sell', [ItemController::class, 'exhibitItems']);
    Route::post('/sell', [ItemController::class, 'upItem']);

    //ログインしてから商品詳細に戻るための入り口（未ログインなら /login を経由して、ここに戻ってくる）
    Route::get('/item/{item_id}/login', [ItemController::class, 'returnToItemAfterLogin'])->whereNumber('item_id')->name('item.login');

    //いいね＆コメント
    Route::post('/like/{id}', [LikeController::class, 'add'])->whereNumber('id');
    Route::delete('/like/{id}', [LikeController::class, 'remove'])->whereNumber('id');
    Route::post('/item/comment', [CommentController::class, 'addComment']);
});

//Stripe の Webhook（Stripe から呼ばれるので、ログイン不要・CSRF 除外。署名で検証する）
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('stripe.webhook');
