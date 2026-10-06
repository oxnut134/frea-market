<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRegisteredInDemoToUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // デモ環境（DEMO_MODE=true）で会員登録したユーザーの印。
            // 毎日の初期化（demo:reset）で削除するのは、この印があるユーザーだけ
            $table->boolean('registered_in_demo')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('registered_in_demo');
        });
    }
}
