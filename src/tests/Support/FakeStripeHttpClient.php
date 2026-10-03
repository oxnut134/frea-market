<?php

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Stripe に実際のリクエストを送らずに、送信内容を記録して決まった応答を返す HTTP クライアント
 *
 * 使い方：\Stripe\ApiRequestor::setHttpClient(new FakeStripeHttpClient(...));
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{method: string, url: string, params: array}> */
    public array $requests = [];

    /**
     * @param array<int, array{0: int, 1: array}|\Throwable> $responses 返す応答（ステータスコードと本文）を順番に並べたもの。
     *                                                                  例外を入れると、その順番で例外を投げる（通信エラーの再現など）
     */
    public function __construct(private array $responses)
    {
    }

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        $response = array_shift($this->responses) ?? [500, ['error' => ['message' => 'No fake response']]];
        if ($response instanceof \Throwable) {
            throw $response;
        }

        [$status, $body] = $response;

        return [json_encode($body), $status, []];
    }
}
