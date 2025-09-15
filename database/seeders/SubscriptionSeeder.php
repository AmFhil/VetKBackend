<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('subscriptions')->insert([
            [
                'subscription_title' => 'Freemium',
                'subscription_code' => 'sp2295',
                'currency' => 'USD',
                'price' => 0,
                'vat' => '0%',
                'date_option' => 'Days',
                'duration' => 30,
                'case_record' => 2,
                'profile_approval' => 'No Approval Badge',
                'contact_info' => false,
                'direct_message' => false,
                'feed_calculator' => true,
                'disease_predictor' => true,
                'store' => 1,
                'no_of_products' => 2,
                'customer_support' => false,
            ],
            [
                'subscription_title' => 'Monthly',
                'subscription_code' => 'sp4175',
                'currency' => 'USD',
                'price' => 4.90,
                'vat' => '2%',
                'date_option' => 'Days',
                'duration' => 30,
                'case_record' => 20,
                'profile_approval' => 'Approved Badge',
                'contact_info' => true,
                'direct_message' => true,
                'feed_calculator' => true,
                'disease_predictor' => true,
                'store' => 6,
                'no_of_products' => 3,
                'customer_support' => true,
            ],
            [
                'subscription_title' => 'Quarterly',
                'subscription_code' => 'sp2354',
                'currency' => 'USD',
                'price' => 14.97,
                'vat' => '2%',
                'date_option' => 'Months',
                'duration' => 3,
                'case_record' => 50,
                'profile_approval' => 'Approved Badge',
                'contact_info' => true,
                'direct_message' => true,
                'feed_calculator' => true,
                'disease_predictor' => true,
                'store' => 10,
                'no_of_products' => 5,
                'customer_support' => true,
            ],
            [
                'subscription_title' => 'Yearly',
                'subscription_code' => 'sp3154',
                'currency' => 'USD',
                'price' => 59.88,
                'vat' => '2%',
                'date_option' => 'Months',
                'duration' => 12,
                'case_record' => -1, // unlimited
                'profile_approval' => 'Approved Badge',
                'contact_info' => true,
                'direct_message' => true,
                'feed_calculator' => true,
                'disease_predictor' => true,
                'store' => -1, // unlimited
                'no_of_products' => -1, // unlimited
                'customer_support' => true,
            ],
        ]);
    }
}
