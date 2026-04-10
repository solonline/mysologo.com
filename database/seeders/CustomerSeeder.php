<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customer = DB::table('customers')->create( [
                'name' => 'Cliente Exemplo',
                'username' => 'cliente1',
                'email' => 'cliente@example.com',
                'phone' => '+351912345678',
                'type' => 'retail',
            ]);

        DB::table('customer_stripe_accounts')->create([
            'customer_id' => $customer->id,
            'stripe_customer_id' => 'cus_UIsEDNJxG4sCEF',
            'currency' => 'eur',
            'status' => 'active',
        ]);
    }
}
