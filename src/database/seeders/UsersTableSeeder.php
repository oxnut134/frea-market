<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
                'password' => bcrypt('abc12345'),
                'password_confirmation' => bcrypt('abc12345'),
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
            [
                'id' => 2,
                'name' => 'Dog',
                'email' => 'dog@test.com',
                'password' => bcrypt('abc12345'),
                'password_confirmation' => bcrypt('abc12345'),
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
            [
                'id' => 3,
                'name' => 'Tiger',
                'email' => 'tiger@test.com',
                'password' => bcrypt('abc12345'),
                'password_confirmation' => bcrypt('abc12345'),
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
            [
                'id' => 4,
                'name' => 'Wolf',
                'email' => 'wolf@test.com',
                'password' => 'abc12345',
                'password_confirmation' => 'abc12345',
                'created_at' => $now,
                'updated_at' => $now,
                'email_verified_at' => $now,
            ],
        ], ['id'], ['name', 'email', 'password', 'password_confirmation', 'updated_at', 'email_verified_at']);

        $this->resetAutoIncrement('users');
    }
}
