<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Comment;
use App\Models\Item;
use App\Models\Like;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Demo\DemoLimits;

// デモ環境を初期状態に戻す。
// 起動時（docker/render/entrypoint.sh）と、毎日 4:00（Console\Kernel）に実行する。
// デモ環境（DEMO_MODE=true）でだけ動く。
//
// - デモ用アカウント（config/demo.php）：購入・いいね・コメント・出品を消し、プロフィールを初期値に戻す
// - 新しく登録したユーザー：同じものを消したうえで、ユーザーごと削除する
// - シードのユーザー（config('demo.seeded_emails')）の操作には触らない
//
// 期限内の確保（支払い待ち）だけは、あとから支払いの通知が届くので残す。
// それが付いている出品と、それを持つユーザーも今回は残し、片付いたあとの実行で消す
class ResetDemoData extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'デモ用アカウントの操作を初期状態に戻し、新しく登録したユーザーを削除する';

    public function handle()
    {
        // デモ環境以外では何もしない（登録したユーザーを削除するので、通常の環境で動くと利用者が消える）
        if (! config('demo.enabled')) {
            $this->info('DEMO_MODE is off. Nothing to reset.');

            return 0;
        }

        $now = now();
        $disk = Storage::disk(config('filesystems.images'));

        $demo = User::where('email', config('demo.email'))->first();
        // 新しく登録したユーザー：デモ用アカウントでも、シードのユーザーでもないもの
        $registered = DemoLimits::registeredUsers()->pluck('id');
        $targets = $registered->merge($demo ? [$demo->id] : []);

        // 期限内の確保（支払い待ち）
        $waiting_for_payment = function ($query) use ($now) {
            $query->where('status', Purchase::STATUS_PENDING)->where('expires_at', '>', $now);
        };

        // 購入：支払い済み・期限切れ・失敗と、期限を過ぎたまま残った確保を消す。商品は販売中に戻る
        $purchases = Purchase::whereIn('user_id', $targets)
            ->where(function ($query) use ($now) {
                $query->where('status', '!=', Purchase::STATUS_PENDING)->orWhere('expires_at', '<=', $now);
            })
            ->delete();

        $likes = Like::whereIn('user_id', $targets)->delete();
        $comments = Comment::whereIn('user_id', $targets)->delete();

        // 出品：付いているいいね・コメント・購入ごと消す（外部キーの連鎖）。
        // 期限内の確保が付いている商品は、確保を消さないように残す
        $images = Item::whereIn('user_id', $targets)->pluck('item_image', 'id');
        $items = Item::whereIn('user_id', $targets)->whereDoesntHave('purchases', $waiting_for_payment)->delete();
        $kept_items = Item::whereIn('id', $images->keys())->pluck('id');
        $disk->delete($images->except($kept_items->all())->filter()->values()->all());

        // デモ用アカウントのプロフィール：名前と住所を初期値に戻し、アップロードした画像を消す
        if ($demo) {
            $profile_image = Profile::where('user_id', $demo->id)->value('profile_image');
            User::where('id', $demo->id)->update(['name' => config('demo.name')]);
            Profile::updateOrCreate(['user_id' => $demo->id], ['profile_image' => null] + config('demo.profile'));
            if ($profile_image) {
                $disk->delete($profile_image);
            }
        }

        // 新しく登録したユーザー：購入（ここまでで残るのは期限内の確保だけ）も出品も残っていなければ、
        // ユーザーごと削除する。プロフィールは外部キーの連鎖で消える
        $profile_images = Profile::whereIn('user_id', $registered)->pluck('profile_image', 'user_id');
        $users = User::whereIn('id', $registered)
            ->whereNotIn('id', Purchase::select('user_id'))
            ->whereNotIn('id', Item::select('user_id'))
            ->delete();
        $deleted = $registered->diff(User::whereIn('id', $registered)->pluck('id'));
        $disk->delete($profile_images->only($deleted->all())->filter()->values()->all());
        // 削除したユーザーのセッション（sessions.user_id に外部キーはない）
        DB::table('sessions')->whereIn('user_id', $deleted)->delete();

        // 結果は標準出力に 1 行で出す（起動時もスケジューラーからも、コンテナのログに残る）。
        // 件数だけを出し、メールアドレスなどは出さない
        $this->info('Demo data was reset: ' . json_encode(compact('purchases', 'likes', 'comments', 'items', 'users')));

        return 0;
    }
}
