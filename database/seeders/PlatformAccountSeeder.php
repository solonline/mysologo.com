<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformAccountSeeder extends Seeder
{
    /**
     * php artisan db:seed --class=PlatformAccountSeeder
     */
    public function run(): void
    {
        DB::table('customers')->updateOrInsert(
            ['email' => 'master@yoop.com'],
            [
                'name'              => 'Yoop Platform',
                'username'          => 'platform',
                'type'              => 'platform',
                'currency'          => 'eur',
                'status'            => 'active',
                'email_verified_at' => now(),
                'created_at'        => now(),
                'updated_at'        => now(),
            ]
        );
    }
}
