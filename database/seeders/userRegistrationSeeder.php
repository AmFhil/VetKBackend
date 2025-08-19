<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class userRegistrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        DB::table('users_registration')->insertUsing(
            ['email', 'phone_num', 'state', 'country', 'password', 'old_id', 'old_table'],
            DB::table('animal_owner')->select('email', 'phone_number', 'state', 'country', 'password', 'id as old_id', DB::raw("'animal_owner' as old_table"))
        );

        DB::table('users_registration')->insertUsing(
            ['email', 'phone_num', 'state', 'country', 'password', 'old_id', 'old_table'],
            DB::table('veterinarian')->select('email', 'phone_number', 'state', 'country', 'password', 'id as old_id', DB::raw("'veterinarian' as old_table"))
        );
    }
}
