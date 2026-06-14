<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUniqueIndexToLikesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // (item_id, user_id)の重複行を1件に統合してから一意制約を付与
        DB::statement('
            DELETE FROM likes
            WHERE id NOT IN (
                SELECT id FROM (
                    SELECT MIN(id) AS id FROM likes GROUP BY item_id, user_id
                ) AS keep_ids
            )
        ');

        Schema::table('likes', function (Blueprint $table) {
            $table->unique(['item_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('likes', function (Blueprint $table) {
            $table->dropUnique(['likes_item_id_user_id_unique']);
        });
    }
}
