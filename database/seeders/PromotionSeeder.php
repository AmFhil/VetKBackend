<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PromotionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        DB::table('promotion_plan')->insert([
            [
                'title' => 'Basic Plan',
                'promotion_code' => 'pp1001',
                'currency' => 'USD',
                'price' => 9.99,
                'vat' => 5,
                'date_option' => 'days',
                'duration' => 7,
                'no_of_products' => 5,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Standard Plan',
                'promotion_code' => 'pp1002',
                'currency' => 'USD',
                'price' => 19.99,
                'vat' => 10,
                'date_option' => 'days',
                'duration' => 14,
                'no_of_products' => 10,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Premium Plan',
                'promotion_code' => 'pp1003',
                'currency' => 'USD',
                'price' => 29.99,
                'vat' => 15,
                'date_option' => 'days',
                'duration' => 30,
                'no_of_products' => 20,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}
