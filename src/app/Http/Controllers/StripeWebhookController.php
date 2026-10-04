<?php

namespace App\Http\Controllers;

use App\Services\Purchase\PurchaseWebhookHandlers;
use App\Services\Stripe\StripeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeWebhookController extends Controller
{
    // Stripe からの Webhook を受け取る。購入の確定・失敗・期限切れは、ここでだけ反映する
    public function handle(Request $request, StripeCheckoutService $stripe, PurchaseWebhookHandlers $handlers)
    {
        // 署名の検証には、パース前の生のボディが必要
        $verified = $stripe->verifyWebhookEvent($request->getContent(), $request->header('Stripe-Signature'));
        if (!$verified->success) {
            Log::warning('Stripe webhook: signature verification failed', ['error' => $verified->error]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $event = $verified->event;
        try {
            $result = $stripe->handleWebhookEvent($event, $handlers);
        } catch (\Throwable $e) {
            // 500 を返すと Stripe が再送する
            Log::error('Stripe webhook: handling failed', [
                'event_id' => $event->id,
                'type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['error' => 'Webhook handling failed'], 500);
        }

        return response()->json(['received' => true, 'handled' => $result->handled]);
    }
}
