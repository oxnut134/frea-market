
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreatePurchasesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('payment_method', 20);
            $table->unsignedInteger('amount');
            $table->string('delivery_address', 255)->nullable();
            $table->string('stripe_checkout_session_id')->nullable()->unique();
            $table->text('stripe_checkout_url')->nullable();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE purchases ADD CONSTRAINT purchases_status_check CHECK (status IN ('pending', 'paid', 'expired', 'failed'))");
        DB::statement("ALTER TABLE purchases ADD CONSTRAINT purchases_payment_method_check CHECK (payment_method IN ('card', 'konbini'))");
        // PostgreSQL には unsigned がないため、金額の下限は CHECK 制約で守る
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_amount_check CHECK (amount > 0)');

        // 1 つの商品につき、確保中（pending）または支払い済み（paid）の購入は 1 件だけ
        DB::statement("CREATE UNIQUE INDEX purchases_item_id_active_unique ON purchases (item_id) WHERE status IN ('pending', 'paid')");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('purchases');
    }
}
