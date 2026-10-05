<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Database\Seeders\Concerns\ResetsAutoIncrement;
use Database\Seeders\Concerns\SeedsImages;

class ProfilesTableSeeder extends Seeder
{
    use ResetsAutoIncrement, SeedsImages;

    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $now = now();

        DB::table('profiles')->upsert([
            [
                'id' => 1,
                'user_id' => 1,
                'profile_image' => $this->seedImage('profiles/person-1.png'),
                'post_code' => '111-1111',
                'address' => 'ueno',
                'building' => 'zoo',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'user_id' => 2,
                'profile_image' => $this->seedImage('profiles/person-2.png'),
                'post_code' => '222-2222',
                'address' => 'ueno',
                'building' => 'zoo',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'user_id' => 3,
                'profile_image' => $this->seedImage('profiles/person-3.png'),
                'post_code' => '333-3333',
                'address' => 'ueno',
                'building' => 'zoo',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'user_id' => 4,
                'profile_image' => $this->seedImage('profiles/person-4.png'),
                'post_code' => '444-4444',
                'address' => 'ueno',
                'building' => 'zoo',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            // デモ用アカウント。画像は未設定（デフォルトのアイコンを表示）
            [
                'id' => 5,
                'user_id' => 5,
                'profile_image' => null,
                'post_code' => '555-5555',
                'address' => 'ueno',
                'building' => 'zoo',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['id'], ['user_id', 'profile_image', 'post_code', 'address', 'building', 'updated_at']);

        $this->resetAutoIncrement('profiles');
    }
}
