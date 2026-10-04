<?php

namespace App\Providers;

use App\Services\Stripe\StripeCheckoutService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(StripeCheckoutService::class, function () {
            return new StripeCheckoutService(
                new StripeClient((string) config('services.stripe.secret_key')),
                (string) config('services.stripe.webhook_secret'),
                (int) config('services.stripe.konbini_expires_after_days'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // @versioned('css/header.css')：public 配下のファイルの URL に、更新時刻をバージョンとして付ける
        // （ファイルを変更したら URL が変わるので、ブラウザが古いキャッシュを使い続けない）
        Blade::directive('versioned', function ($path) {
            return "<?php echo e(asset({$path}) . '?v=' . filemtime(public_path({$path}))); ?>";
        });
    }
}
