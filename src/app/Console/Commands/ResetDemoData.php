<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use App\Models\Comment;
use App\Models\Item;
use App\Models\Like;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;

// デモ用アカウント（config/demo.php）の操作を初期状態に戻す。
// 起動時（docker/render/entrypoint.sh）と、毎日 4:00（Console\Kernel）に実行する。
// デモ用アカウント以外のユーザーの操作には触らない
class ResetDemoData extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'デモ用アカウントの購入・いいね・コメント・出品・プロフィールを初期状態に戻す';

    public function handle()
    {
        $demo = User::where('email', config('demo.email'))->first();
        if (! $demo) {
            $this->info('Demo account was not found. Nothing to reset.');

            return 0;
        }

        $now = now();
        $disk = Storage::disk(config('filesystems.images'));

        // 期限内の確保（支払い待ち）
        $waiting_for_payment = function ($query) use ($now) {
            $query->where('status', Purchase::STATUS_PENDING)->where('expires_at', '>', $now);
        };

        // 購入：期限内の確保だけを残す（あとから支払いの通知が届くため）。
        // 支払い済み・期限切れ・失敗と、期限を過ぎたまま残った確保を消す。商品は販売中に戻る
        $purchases = Purchase::where('user_id', $demo->id)
            ->where(function ($query) use ($now) {
                $query->where('status', '!=', Purchase::STATUS_PENDING)->orWhere('expires_at', '<=', $now);
            })
            ->delete();

        $likes = Like::where('user_id', $demo->id)->delete();
        $comments = Comment::where('user_id', $demo->id)->delete();

        // 出品：付いているいいね・コメント・購入ごと消す（外部キーの連鎖）。
        // 期限内の確保が付いている商品は、確保を消さないように今回は残す
        $images = Item::where('user_id', $demo->id)->pluck('item_image', 'id');
        $items = Item::where('user_id', $demo->id)->whereDoesntHave('purchases', $waiting_for_payment)->delete();
        $remaining = Item::whereIn('id', $images->keys())->pluck('id');
        $disk->delete($images->except($remaining->all())->filter()->values()->all());

        // プロフィール：名前と住所を初期値に戻し、アップロードした画像を消す
        $profile_image = Profile::where('user_id', $demo->id)->value('profile_image');
        User::where('id', $demo->id)->update(['name' => config('demo.name')]);
        Profile::updateOrCreate(['user_id' => $demo->id], ['profile_image' => null] + config('demo.profile'));
        if ($profile_image) {
            $disk->delete($profile_image);
        }

        // 結果は標準出力に 1 行で出す（起動時もスケジューラーからも、コンテナのログに残る）
        $this->info('Demo data was reset: ' . json_encode(compact('purchases', 'likes', 'comments', 'items')));

        return 0;
    }
}
