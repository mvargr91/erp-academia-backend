<?php

namespace Database\Seeders;

use Database\Seeders\UserSeed;
use Illuminate\Database\Seeder;
use Database\Seeders\SeguridadSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory(10)->create();
        $this->call(UserSeed::class);
        $this->call(SeguridadSeeder::class);
    }
}
