<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Database\Seeders\Concerns\ResetsAutoIncrement;

class UsersTableSeeder extends Seeder
{
    use ResetsAutoIncrement;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        DB::table('users')->upsert([
            [
                'id' => 1,
                'name' => 'Cat',
                'email' => 'cat@test.com',
                'password' => Hash::make('abc12345'),
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Dog',
                'email' => 'dog@test.com',
                'password' => Hash::make('abc12345'),
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Tiger',
                'email' => 'tiger@test.com',
                'password' => Hash::make('abc12345'),
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'Wolf',
                'email' => 'wolf@test.com',
                'password' => Hash::make('abc12345'),
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
            // デモ用アカウント（config/demo.php）。商品は持たない
            [
                'id' => 5,
                'name' => config('demo.name'),
                'email' => config('demo.email'),
                'password' => Hash::make(config('demo.password')),
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
        ], ['id'], ['name', 'email', 'password', 'updated_at', 'email_verified_at']);

        $this->resetAutoIncrement('users');
    }
}
