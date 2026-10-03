<?php

namespace App\Providers;

use App\Services\Stripe\StripeCheckoutService;
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
        //
    }
}
