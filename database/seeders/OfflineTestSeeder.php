<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\VeterinaryDoctor;
use App\Models\VeterinaryClinic;
use App\Models\VeterinaryParaprofessional;
use App\Models\Store;
use App\Models\Products;
use App\Models\Rate;
use App\Models\Profiles as Profile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class OfflineTestSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Roles
        $roles = [
            'basic_user',
            'pet_owner',
            'livestock_farmer',
            'others',
            'vendor',
            'veterinary_doctor',
            'veterinary_clinic',
            'veterinary_paraprofessional',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        $vetRole = Role::where('name', 'veterinary_doctor')->first();
        $clinicRole = Role::where('name', 'veterinary_clinic')->first();
        $paraRole = Role::where('name', 'veterinary_paraprofessional')->first();
        $vendorRole = Role::where('name', 'vendor')->first();
        $userRole = Role::where('name', 'basic_user')->first();

        // 2. Default test user
        $testUser = User::firstOrCreate(
            ['email' => 'test@vetkonect.com'],
            [
                'first_name' => 'John',
                'last_name' => 'Farmer',
                'phone_num' => '08012345678',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'password' => Hash::make('password123'),
                'active_role_id' => $userRole->id,
            ]
        );

        // 3. Seed Veterinary Doctors
        $vetsData = [
            [
                'first_name' => 'Dr. Adebayo',
                'last_name' => 'Ogunlesi',
                'email' => 'adebayo.vet@vetkonect.com',
                'phone_num' => '+2348031234567',
                'state' => 'Lagos',
                'country' => 'Nigeria',
                'specialty' => 'Small Animal Surgery & Canine Medicine',
                'list_them' => 'Dogs, Cats, Rabbits',
                'address' => '14 Admiralty Way, Lekki Phase 1, Lagos',
                'latitude' => 6.4474,
                'longitude' => 3.4723,
                'rating' => 4.9,
                'license' => 'VCN/2018/0421',
            ],
            [
                'first_name' => 'Dr. Fatima',
                'last_name' => 'Bello',
                'email' => 'fatima.vet@vetkonect.com',
                'phone_num' => '+2348059876543',
                'state' => 'Abuja',
                'country' => 'Nigeria',
                'specialty' => 'Poultry & Avian Pathology',
                'list_them' => 'Poultry, Broilers, Layers, Turkeys',
                'address' => 'Plot 22 Gana Street, Maitama, Abuja',
                'latitude' => 9.0765,
                'longitude' => 7.3986,
                'rating' => 4.8,
                'license' => 'VCN/2016/1109',
            ],
            [
                'first_name' => 'Dr. Chinedu',
                'last_name' => 'Okoro',
                'email' => 'chinedu.vet@vetkonect.com',
                'phone_num' => '+2348023456789',
                'state' => 'Enugu',
                'country' => 'Nigeria',
                'specialty' => 'Ruminant Health & Cattle Herd Management',
                'list_them' => 'Cattle, Goats, Sheep',
                'address' => '5 Ogui Road, Independence Layout, Enugu',
                'latitude' => 6.4584,
                'longitude' => 7.5464,
                'rating' => 4.7,
                'license' => 'VCN/2019/2281',
            ],
            [
                'first_name' => 'Dr. Olawale',
                'last_name' => 'Balogun',
                'email' => 'olawale.vet@vetkonect.com',
                'phone_num' => '+2348076543210',
                'state' => 'Oyo',
                'country' => 'Nigeria',
                'specialty' => 'Swine Production & Herd Biosecurity',
                'list_them' => 'Pigs, Swine herds',
                'address' => 'Bodija Market Area, Ibadan, Oyo State',
                'latitude' => 7.4216,
                'longitude' => 3.9059,
                'rating' => 4.6,
                'license' => 'VCN/2015/0733',
            ],
            [
                'first_name' => 'Dr. Ngozi',
                'last_name' => 'Eze',
                'email' => 'ngozi.vet@vetkonect.com',
                'phone_num' => '+2348091122334',
                'state' => 'Rivers',
                'country' => 'Nigeria',
                'specialty' => 'General Practice & Emergency Care',
                'list_them' => 'Dogs, Cats, Exotics, Poultry',
                'address' => '28 Stadium Road, Port Harcourt, Rivers State',
                'latitude' => 4.8156,
                'longitude' => 7.0498,
                'rating' => 4.9,
                'license' => 'VCN/2020/3512',
            ],
        ];

        foreach ($vetsData as $vd) {
            $user = User::firstOrCreate(
                ['email' => $vd['email']],
                [
                    'first_name' => $vd['first_name'],
                    'last_name' => $vd['last_name'],
                    'phone_num' => $vd['phone_num'],
                    'state' => $vd['state'],
                    'country' => $vd['country'],
                    'password' => Hash::make('password123'),
                    'active_role_id' => $vetRole->id,
                ]
            );

            // Assign role
            if (!$user->roles()->where('role_id', $vetRole->id)->exists()) {
                $user->roles()->attach($vetRole->id);
            }

            // Create Profile
            Profile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'bio' => "Experienced veterinarian specializing in {$vd['specialty']}. Committed to animal welfare and farmer profitability.",
                ]
            );

            // Create Doctor record
            $doctor = VeterinaryDoctor::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'practice_license_num' => $vd['license'],
                    'specialty' => $vd['specialty'],
                    'list_them' => $vd['list_them'],
                    'address' => $vd['address'],
                    'latitude' => $vd['latitude'],
                    'longitude' => $vd['longitude'],
                    'role' => 'Veterinarian',
                    'is_approved' => 1,
                    'availability' => 1,
                ]
            );

            // Add rating
            Rate::firstOrCreate(
                [
                    'user_id' => $testUser->id,
                    'rateable_id' => $doctor->id,
                    'rateable_type' => VeterinaryDoctor::class,
                ],
                [
                    'rating' => (int) round($vd['rating']),
                    'comment' => 'Excellent service and very knowledgeable doctor.',
                ]
            );
        }

        // 4. Seed Veterinary Clinics
        $clinicsData = [
            [
                'name' => 'Lekki Pet & Livestock Hospital',
                'email' => 'clinic.lekki@vetkonect.com',
                'phone' => '+2348011112222',
                'address' => 'Plot 8 Lekki Expressway, Lagos',
                'state' => 'Lagos',
                'speciality' => 'Surgery, Diagnostics, Emergency, Boarding',
                'latitude' => 6.4380,
                'longitude' => 3.4810,
            ],
            [
                'name' => 'Abuja Central Animal Clinic',
                'email' => 'clinic.abuja@vetkonect.com',
                'phone' => '+2348022223333',
                'address' => '12 Constitution Avenue, Central Area, Abuja',
                'state' => 'Abuja',
                'speciality' => 'Vaccination, Lab Diagnostics, Farm Consultations',
                'latitude' => 9.0579,
                'longitude' => 7.4951,
            ],
        ];

        foreach ($clinicsData as $cd) {
            $user = User::firstOrCreate(
                ['email' => $cd['email']],
                [
                    'first_name' => $cd['name'],
                    'last_name' => 'Clinic',
                    'phone_num' => $cd['phone'],
                    'state' => $cd['state'],
                    'country' => 'Nigeria',
                    'password' => Hash::make('password123'),
                    'active_role_id' => $clinicRole->id,
                ]
            );

            if (!$user->roles()->where('role_id', $clinicRole->id)->exists()) {
                $user->roles()->attach($clinicRole->id);
            }

            VeterinaryClinic::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name_of_clinic' => $cd['name'],
                    'practice_license_num' => 'VCN/CLINIC/' . rand(1000, 9999),
                    'specialty' => $cd['speciality'],
                    'list_them' => 'Dogs, Cats, Cattle, Poultry',
                    'contact_num' => $cd['phone'],
                    'address' => $cd['address'],
                    'latitude' => $cd['latitude'],
                    'longitude' => $cd['longitude'],
                    'role' => 'Vet Clinic',
                    'is_approved' => 1,
                    'availability' => 1,
                ]
            );
        }

        // 5. Seed Paraprofessionals
        $paraData = [
            [
                'first_name' => 'Ibrahim',
                'last_name' => 'Suleiman',
                'email' => 'ibrahim.vpp@vetkonect.com',
                'phone_num' => '+2348044445555',
                'state' => 'Kaduna',
                'specialty' => 'Vaccinations, Artificial Insemination & Dehorning',
                'address' => 'Zaria Road, Kaduna',
                'latitude' => 10.5105,
                'longitude' => 7.4165,
            ]
        ];

        foreach ($paraData as $pd) {
            $user = User::firstOrCreate(
                ['email' => $pd['email']],
                [
                    'first_name' => $pd['first_name'],
                    'last_name' => $pd['last_name'],
                    'phone_num' => $pd['phone_num'],
                    'state' => $pd['state'],
                    'country' => 'Nigeria',
                    'password' => Hash::make('password123'),
                    'active_role_id' => $paraRole->id,
                ]
            );

            if (!$user->roles()->where('role_id', $paraRole->id)->exists()) {
                $user->roles()->attach($paraRole->id);
            }

            VeterinaryParaprofessional::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'name_of_institution' => 'Ahmadu Bello University',
                    'graduation_year' => '2020',
                    'specialty' => $pd['specialty'],
                    'list_them' => 'Ruminants, Small animals',
                    'contact_num' => $pd['phone_num'],
                    'address' => $pd['address'],
                    'latitude' => $pd['latitude'],
                    'longitude' => $pd['longitude'],
                    'role' => 'VPP',
                    'is_approved' => 1,
                    'availability' => 1,
                ]
            );
        }
    }
}
