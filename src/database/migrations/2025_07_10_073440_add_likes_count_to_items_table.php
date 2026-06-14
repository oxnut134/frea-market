<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddLikesCountToItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->unsignedInteger('likes_count')->default(0)->after('status');
        });

        // 既存のlikesレコードからカウントをバックフィル
        DB::statement('UPDATE items SET likes_count = (SELECT COUNT(*) FROM likes WHERE likes.item_id = items.id)');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('likes_count');
        });
    }
}
