<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CashierRemovedTest extends TestCase
{
    use RefreshDatabase;

    // Cashier が作っていた列とテーブルがない
    public function testCashierColumnsAndTablesDoNotExist(): void
    {
        foreach (['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at'] as $column) {
            $this->assertFalse(Schema::hasColumn('users', $column), "users.{$column} が残っています");
        }
        $this->assertFalse(Schema::hasTable('subscriptions'));
        $this->assertFalse(Schema::hasTable('subscription_items'));
    }

    // Cashier が登録していたルートがない
    public function testCashierRoutesDoNotExist(): void
    {
        $this->get('/stripe/payment/pi_test')->assertNotFound();
        // /stripe/webhook は自前のエンドポイントに置き換えた。署名のないリクエストは受け付けない
        $this->post('/stripe/webhook')->assertStatus(400);
    }
}
