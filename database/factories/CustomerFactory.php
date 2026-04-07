<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'username' => $this->faker->unique()->userName(),
            'type' => 'retail',
            'corporate_id' => null,
            'avatar' => null,
            'language' => 'en',
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => null,
            'referal_email_code' => $this->faker->unique()->uuid(),
            'didit_user_id' => null,
            'doc_status' => 'pending',
            'phone' => $this->faker->phoneNumber(),
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
