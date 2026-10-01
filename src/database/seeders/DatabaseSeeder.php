<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(UsersTableSeeder::class);
        $this->call(ItemsTableSeeder::class);
        $this->call(CategoriesTableSeeder::class);
        $this->call(ItemCategoryTableSeeder::class);
        $this->call(ProfilesTableSeeder::class);

        // \App\Models\User::factory(10)->create();
    }
}
