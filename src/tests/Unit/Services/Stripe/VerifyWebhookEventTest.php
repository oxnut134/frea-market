<?php

namespace Tests\Unit\Services\Stripe;

use App\Services\Stripe\StripeCheckoutService;
use PHPUnit\Framework\TestCase;
use Stripe\StripeClient;

class VerifyWebhookEventTest extends TestCase
{
    private const SECRET = 'whsec_test_secret';

    private function service(string $secret = self::SECRET): StripeCheckoutService
    {
        return new StripeCheckoutService(new StripeClient('sk_test_dummy'), $secret);
    }

    private function payload(): string
    {
        return json_encode([
            'id' => 'evt_test_123',
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test_123',
                    'object' => 'checkout.session',
                ],
            ],
        ]);
    }

    // Stripe と同じ形式の署名ヘッダーを作る（t=タイムスタンプ,v1=HMAC-SHA256）
    private function sign(string $payload, string $secret = self::SECRET, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return 't=' . $timestamp . ',v1=' . hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    // 署名が正しい場合は成功とイベントを返す
    public function testReturnsEventWhenSignatureMatches(): void
    {
        $payload = $this->payload();

        $result = $this->service()->verifyWebhookEvent($payload, $this->sign($payload));

        $this->assertTrue($result->success);
        $this->assertNull($result->error);
        $this->assertSame('evt_test_123', $result->event->id);
        $this->assertSame('checkout.session.completed', $result->event->type);
        $this->assertInstanceOf(\Stripe\Checkout\Session::class, $result->event->data->object);
    }

    // 別のシークレットで署名されている場合は失敗
    public function testFailsWithWrongSecret(): void
    {
        $payload = $this->payload();

        $result = $this->service()->verifyWebhookEvent($payload, $this->sign($payload, 'whsec_other_secret'));

        $this->assertFalse($result->success);
        $this->assertNull($result->event);
        $this->assertNotEmpty($result->error);
    }

    // 署名後に本文が書き換えられた場合は失敗
    public function testFailsWhenPayloadIsTampered(): void
    {
        $payload = $this->payload();
        $signature = $this->sign($payload);

        $result = $this->service()->verifyWebhookEvent(str_replace('cs_test_123', 'cs_test_999', $payload), $signature);

        $this->assertFalse($result->success);
    }

    // 署名ヘッダーがない場合は失敗
    public function testFailsWithoutSignatureHeader(): void
    {
        $result = $this->service()->verifyWebhookEvent($this->payload(), null);

        $this->assertFalse($result->success);
        $this->assertSame('Missing Stripe-Signature header', $result->error);
    }

    // 署名のタイムスタンプが古すぎる場合は失敗（リプレイ攻撃の対策）
    public function testFailsWhenTimestampIsTooOld(): void
    {
        $payload = $this->payload();

        $result = $this->service()->verifyWebhookEvent($payload, $this->sign($payload, self::SECRET, time() - 3600));

        $this->assertFalse($result->success);
    }

    // 本文が JSON でない場合は失敗
    public function testFailsWhenPayloadIsNotJson(): void
    {
        $payload = 'not json';

        $result = $this->service()->verifyWebhookEvent($payload, $this->sign($payload));

        $this->assertFalse($result->success);
    }

    // Webhook シークレットが設定されていない場合は失敗
    public function testFailsWhenSecretIsNotConfigured(): void
    {
        $payload = $this->payload();

        $result = $this->service('')->verifyWebhookEvent($payload, $this->sign($payload, ''));

        $this->assertFalse($result->success);
        $this->assertSame('Webhook secret is not configured', $result->error);
    }
}
