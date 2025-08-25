<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Categories;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CategoriesSeeder extends Seeder
{
    public function run()
    {
        // Reset auto-increment and clear table before seeding
        DB::table('categories')->truncate();

        $categories = [
            'Pet Owner',
            'Veterinarian',
            'Livestock Farmer',
            'Veterinary Paraprofessional',
            'Vendor',
            'Veterinary Clinic',
            'Others',
        ];

        foreach ($categories as $name) {
            Categories::create([
                'name' => $name,
                'image' => null,
                'created_at' => Carbon::now()
            ]);
        }
    }
}
