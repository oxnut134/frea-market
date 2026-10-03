<?php

namespace Tests\Feature;

use App\Services\Stripe\StripeCheckoutService;
use Tests\TestCase;

class StripeCheckoutServiceBindingTest extends TestCase
{
    // サービスはコンテナから1つだけ作られ、設定の Webhook シークレットで署名を検証する
    public function testServiceIsResolvedWithConfiguredWebhookSecret(): void
    {
        $service = app(StripeCheckoutService::class);
        $this->assertSame($service, app(StripeCheckoutService::class));

        $payload = json_encode(['id' => 'evt_test_123', 'object' => 'event', 'type' => 'checkout.session.expired', 'data' => ['object' => ['id' => 'cs_test_123', 'object' => 'checkout.session']]]);
        $timestamp = time();
        $signature = 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, config('services.stripe.webhook_secret'));

        $this->assertSame('whsec_test_dummy', config('services.stripe.webhook_secret'));
        $this->assertTrue($service->verifyWebhookEvent($payload, $signature)->success);
    }

    // テストでは本物の鍵を使わない
    public function testTestsUseDummyStripeKeys(): void
    {
        $this->assertSame('sk_test_dummy', config('services.stripe.secret_key'));
        $this->assertSame(30, config('services.stripe.checkout_expires_minutes'));
        $this->assertSame(3, config('services.stripe.konbini_expires_after_days'));
    }
}
