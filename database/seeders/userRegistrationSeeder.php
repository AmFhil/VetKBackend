<?php

namespace Database\Seeders;

use Carbon\Carbon;
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
        // Reset auto-increment and clear table before seeding
        //DB::table('users_registration')->truncate();

        // Get all records from animal_owner and filter unique emails (case-insensitive)
        $animalOwners = collect(DB::table('animal_owner')
            ->select('email', 'password', 'id as old_id', DB::raw("'animal_owner' as old_table"), DB::raw("'sha1' as password_algorithm"))
            ->get())->unique(function ($item) {
            return strtolower(trim($item->email));
        });

        // Get all records from veterinarian and filter unique emails (case-insensitive)
        $veterinarians = collect(DB::table('veterinarian')
            ->select('email', 'password', 'id as old_id', DB::raw("'veterinarian' as old_table"), DB::raw("'sha1' as password_algorithm"))
            ->get())->unique(function ($item) {
            return strtolower(trim($item->email));
        });

        // Merge and filter unique emails across both tables (case-insensitive)
        $allUsers = $animalOwners->concat($veterinarians)->unique(function ($item) {
            return strtolower(trim($item->email));
        });

        // Insert only one record per unique email (preserving the original email case and choosing the first occurrence if duplicates exist across tables)
        foreach ($allUsers as $user) {
            DB::table('users_registration')->insert([
                'email' => $user->email,
                'password' => $user->password,
                'old_id' => $user->old_id,
                'old_table' => $user->old_table,
                'password_algorithm' => $user->password_algorithm,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }
}
