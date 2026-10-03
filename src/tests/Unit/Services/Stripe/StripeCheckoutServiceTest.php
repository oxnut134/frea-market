<?php

namespace Tests\Unit\Services\Stripe;

use App\Services\Stripe\CreateCheckoutSessionParams;
use App\Services\Stripe\StripeCheckoutService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\StripeClient;
use Tests\Support\FakeStripeHttpClient;

class StripeCheckoutServiceTest extends TestCase
{
    private FakeStripeHttpClient $http;

    protected function tearDown(): void
    {
        // ほかのテストに偽の HTTP クライアントが残らないようにする
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    private function service(array $responses): StripeCheckoutService
    {
        $this->http = new FakeStripeHttpClient($responses);
        ApiRequestor::setHttpClient($this->http);

        return new StripeCheckoutService(new StripeClient('sk_test_dummy'), 'whsec_test_dummy', 3);
    }

    private function params(string $paymentMethod = 'card'): CreateCheckoutSessionParams
    {
        return new CreateCheckoutSessionParams(
            purchaseId: 12,
            itemId: 3,
            userId: 7,
            itemName: '腕時計',
            amount: 15000,
            paymentMethod: $paymentMethod,
            customerEmail: 'buyer@test.com',
            successUrl: 'http://localhost/purchase/complete?session_id={CHECKOUT_SESSION_ID}',
            cancelUrl: 'http://localhost/purchase/3?checkout=canceled',
            expiresAt: 1790000000,
        );
    }

    private function sessionResponse(array $overrides = []): array
    {
        return [200, array_merge([
            'id' => 'cs_test_123',
            'object' => 'checkout.session',
            'url' => 'https://checkout.stripe.com/c/pay/cs_test_123',
            'expires_at' => 1790000000,
        ], $overrides)];
    }

    // カード払いの Checkout Session を、DB の金額と購入の情報で作成する
    public function testCreateCardCheckoutSession(): void
    {
        $service = $this->service([$this->sessionResponse()]);

        $result = $service->createCheckoutSession($this->params('card'));

        $this->assertSame('cs_test_123', $result->sessionId);
        $this->assertSame('https://checkout.stripe.com/c/pay/cs_test_123', $result->url);
        $this->assertSame(1790000000, $result->expiresAt);

        $this->assertCount(1, $this->http->requests);
        $request = $this->http->requests[0];
        $this->assertSame('post', $request['method']);
        $this->assertStringEndsWith('/v1/checkout/sessions', $request['url']);

        $params = $request['params'];
        $this->assertSame('payment', $params['mode']);
        $this->assertSame(['card'], $params['payment_method_types']);
        $this->assertSame('jpy', $params['line_items'][0]['price_data']['currency']);
        $this->assertSame(15000, $params['line_items'][0]['price_data']['unit_amount']);
        $this->assertSame('腕時計', $params['line_items'][0]['price_data']['product_data']['name']);
        $this->assertSame(1, $params['line_items'][0]['quantity']);
        $this->assertSame('buyer@test.com', $params['customer_email']);
        $this->assertSame('http://localhost/purchase/complete?session_id={CHECKOUT_SESSION_ID}', $params['success_url']);
        $this->assertSame('http://localhost/purchase/3?checkout=canceled', $params['cancel_url']);
        $this->assertSame(1790000000, $params['expires_at']);
        $this->assertSame(['purchase_id' => '12', 'item_id' => '3', 'user_id' => '7'], $params['metadata']);
        $this->assertSame($params['metadata'], $params['payment_intent_data']['metadata']);
        $this->assertArrayNotHasKey('payment_method_options', $params);
    }

    // コンビニ払いでは支払期限（日数）を指定する
    public function testCreateKonbiniCheckoutSession(): void
    {
        $service = $this->service([$this->sessionResponse()]);

        $service->createCheckoutSession($this->params('konbini'));

        $params = $this->http->requests[0]['params'];
        $this->assertSame(['konbini'], $params['payment_method_types']);
        $this->assertSame(3, $params['payment_method_options']['konbini']['expires_after_days']);
    }

    // Stripe が決済画面の URL を返さなかった場合は例外にする
    public function testCreateCheckoutSessionThrowsWhenUrlIsMissing(): void
    {
        $service = $this->service([$this->sessionResponse(['url' => null])]);

        $this->expectException(RuntimeException::class);
        $service->createCheckoutSession($this->params());
    }

    // Stripe がエラーを返した場合は Stripe の例外がそのまま投げられる
    public function testCreateCheckoutSessionPropagatesStripeErrors(): void
    {
        $service = $this->service([[400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Invalid amount']]]]);

        $this->expectException(\Stripe\Exception\InvalidRequestException::class);
        $service->createCheckoutSession($this->params());
    }

    // Checkout Session を期限切れにできた場合は true
    public function testExpireCheckoutSession(): void
    {
        $service = $this->service([$this->sessionResponse(['status' => 'expired'])]);

        $this->assertTrue($service->expireCheckoutSession('cs_test_123'));

        $request = $this->http->requests[0];
        $this->assertSame('post', $request['method']);
        $this->assertStringEndsWith('/v1/checkout/sessions/cs_test_123/expire', $request['url']);
    }

    // すでに完了している Session など、Stripe が受け付けなかった場合は false
    public function testExpireCheckoutSessionReturnsFalseWhenStripeRejects(): void
    {
        $service = $this->service([[400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Only Checkout Sessions with a status of open can be expired.']]]]);

        $this->assertFalse($service->expireCheckoutSession('cs_test_123'));
    }
}
