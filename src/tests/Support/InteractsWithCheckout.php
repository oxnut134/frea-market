<?php

namespace Tests\Support;

use App\Models\Item;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use Stripe\ApiRequestor;

/**
 * 購入フローの Feature テスト用の補助
 *
 * Stripe には実際のリクエストを送らない（FakeStripeHttpClient に差し替える）。
 * 使うテストは tearDown で resetStripe() を呼ぶこと。
 */
trait InteractsWithCheckout
{
    protected FakeStripeHttpClient $stripeHttp;

    // Stripe への通信を偽のクライアントに差し替える。$responses は返す応答（または投げる例外）を順に並べたもの
    protected function fakeStripe(array $responses = []): FakeStripeHttpClient
    {
        $this->stripeHttp = new FakeStripeHttpClient($responses);
        ApiRequestor::setHttpClient($this->stripeHttp);

        return $this->stripeHttp;
    }

    // ほかのテストに偽の HTTP クライアントが残らないようにする
    protected function resetStripe(): void
    {
        ApiRequestor::setHttpClient(null);
    }

    // Checkout Session の作成・期限切れに対する Stripe の応答
    protected function checkoutSessionResponse(array $overrides = []): array
    {
        return [200, array_merge([
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            'expires_at' => now()->addMinutes(31)->timestamp,
            'status' => 'open',
            'payment_status' => 'unpaid',
        ], $overrides)];
    }

    protected function createUserWithProfile(array $profile = []): User
    {
        $user = User::factory()->create();
        Profile::create(array_merge([
            'user_id' => $user->id,
            'post_code' => '111-1111',
            'address' => 'Tokyo',
        ], $profile));

        return $user;
    }

    protected function createItem(User $seller, array $overrides = []): Item
    {
        return Item::forceCreate(array_merge([
            'user_id' => $seller->id,
            'item_image' => 'items/shoes.jpg',
            'item_name' => '革靴',
            'price' => 4000,
            'description' => 'クラシックなデザインの革靴',
            'condition' => '良好',
        ], $overrides));
    }

    // 署名付きの Webhook を送る（phpunit.xml のダミーのシークレットで署名する）
    protected function postStripeWebhook(string $type, array $session = [], ?string $signature = null)
    {
        $payload = json_encode([
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => $type,
            'data' => [
                'object' => array_merge([
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                ], $session),
            ],
        ]);

        $timestamp = time();
        $signature ??= 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, config('services.stripe.webhook_secret'));

        return $this->call('POST', '/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }

    // 購入に対応する Checkout Session（Webhook のイベントに入る内容）
    protected function sessionFor(Purchase $purchase, array $overrides = []): array
    {
        return array_merge([
            'id' => $purchase->stripe_checkout_session_id ?? 'cs_test_123',
            'payment_status' => 'paid',
            'amount_total' => $purchase->amount,
            'payment_intent' => 'pi_test_123',
            'payment_method_types' => [$purchase->payment_method],
            'metadata' => ['purchase_id' => (string) $purchase->id],
        ], $overrides);
    }
}
