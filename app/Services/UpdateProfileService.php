<?php

namespace App\Services;

use App\Models\livestock_farmer;
use App\Models\Others;
use App\Models\Pet_Owner;
use App\Models\Profiles;
use App\Models\Role;
use App\Models\Vendor;
use App\Models\VeterinaryClinic;
use App\Models\VeterinaryDoctor;
use App\Models\VeterinaryParaprofessional;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UpdateProfileService
{
    private array $roleMap = [
        'vet' => [
            'model' => VeterinaryDoctor::class,
            'roleName' => 'veterinary_doctor',
            'defaults' => ['role' => 'Veterinarian', 'is_approved' => 0, 'availability' => false, 'view' => 0],
            'fields' => ['practice_license_num', 'specialty', 'list_them', 'address', 'longitude', 'latitude'],
        ],
        'vet_clinic' => [
            'model' => VeterinaryClinic::class,
            'roleName' => 'veterinary_clinic',
            'defaults' => ['role' => 'Veterinary Clinic', 'is_approved' => 0, 'availability' => false, 'view' => 0],
            'fields' => ['practice_license_num', 'clinic_name', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude'],
        ],
        'vet_paraprofessional' => [
            'model' => VeterinaryParaprofessional::class,
            'roleName' => 'veterinary_paraprofessional',
            'defaults' => ['role' => 'Veterinary Paraprofessional', 'is_approved' => 0, 'availability' => false, 'view' => 0],
            'fields' => ['name_of_institution', 'graduation_year', 'expected_year_of_graduation', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude'],
        ],
        'vendor' => [
            'model' => Vendor::class,
            'roleName' => 'vendor',
            'defaults' => ['role' => 'vendor']
        ],
        'pet' => [
            'model' => Pet_Owner::class,
            'roleName' => 'pet_owner',
            'defaults' => ['role' => 'pet_owner']
        ],
        'farmer' => [
            'model' => livestock_farmer::class,
            'roleName' => 'livestock_farmer',
            'defaults' => ['role' => 'livestock_farmer']
        ],
        'others' => [
            'model' => Others::class,
            'roleName' => 'others',
            'defaults' => ['role' => 'others']
        ],
    ];

    public function execute($user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            $this->handlePassword($user, $data);
            $this->handleEmail($user, $data);
            $user->update(Arr::only($data, ['first_name', 'last_name', 'phone_num', 'state', 'country']));

            $profile = Profiles::updateOrCreate(['user_id' => $user->id], Arr::only($data, ['bio']));
            $this->handleImages($user, $profile, $data);

            if ($status = Arr::get($data, 'status')) {
                $this->handleRole($user, $status, $data);
            }

            if (array_key_exists('availability', $data)){
                $this->updateAvailability($user, $data['availability']);
            }

            $this->updateVetDetails($user, $status ?? '', $data);

            // Handles notifications/logging in listener
            $user->logActivity('update', 'Profile Updated', 'User updated their profile');
            $user->sendNotification('profile', 'Profile Updated', 'Your profile was successfully updated.');
        });
    }

    private function handlePassword($user, array &$data): void
    {
        if (!Arr::has($data, ['current_password', 'password'])) {
            return;
        }

        $current = $data['current_password'];
        if (Hash::check($current, $user->password) || $user->password === sha1($current)) {
            $user->update(['password' => Hash::make($data['password']), 'password_algorithm' => 'bcrypt']);
            $user->logActivity(
                type: 'security',
                title: 'Password Changed',
                content: 'Your password has been successfully updated.'
            );
        } else {
            throw new \Exception('Current password is incorrect');
        }
    }

    private function handleEmail($user, array &$data): void
    {
        if (Arr::has($data, 'email') && !$user->provider) {
            $user->update(['email' => $data['email']]);
        }
    }

    private function handleImages($user, $profile, array $data): void
    {
        if ($file = Arr::get($data, 'profile_image')) {
            if($profile->profile_image !== null){
            Storage::disk('public')->delete($profile->profile_image);
            }
            $profile->update(['profile_image' => $file->store('profiles', 'public')]);
            $user->logActivity(
                action: 'update_profile_image',
                title: 'Profile Image Updated',
                detail: "User {$user->first_name} {$user->last_name} updated their profile image",
                meta: ['profile_image' => $profile->profile_image]
            );
        }
        if ($file = Arr::get($data, 'cover_page_image')) {
            if($profile->cover_page_image !== null){
            Storage::disk('public')->delete($profile->cover_page_image);
            }
            $profile->update(['cover_page_image' => $file->store('profiles/coverPage', 'public')]);
            $user->logActivity(
                action: 'update_cover_image',
                title: 'Cover Image Updated',
                detail: "User {$user->first_name} {$user->last_name} updated their cover page image",
                meta: ['cover_page_image' => $profile->cover_page_image]
            );
        }
    }

    private function handleRole($user, string $status, array $data): void
    {
        if (!array_key_exists($status, $this->roleMap)) return;

        $config = $this->roleMap[$status];
        $model = $config['model']::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($config['defaults'], Arr::only($data, $config['fields']))
        );

        $role = Role::where('name', $config['roleName'])->first();
        if ($role) {
            $user->roles()->syncWithoutDetaching([$role->id]);
            $user->update(['active_role_id' => $role->id]);
        }

        // Log (move to event)
        $user->logActivity(
            'role_changed',
            'Role has been changed',
            "Your Role has been changed to {$user->activeRole?->name}"
        );
    }

    // update availability status for vet, vet_clinic, vet_paraprofessional
    private function updateAvailability($user, bool $availability): void
    {
        $activeRole = $user->activeRole?->name;
        if (!$activeRole) Return;

        // use match
        match($activeRole) {
            'veterinary_doctor' => VeterinaryDoctor::where('user_id', $user->id)->update(['availability' => $availability]),
            'veterinary_clinic' => VeterinaryClinic::where('user_id', $user->id)->update(['availability' => $availability]),
            'veterinary_paraprofessional' => VeterinaryParaprofessional::where('user_id', $user->id)->update(['availability' => $availability]),
            default => null,
        };

        $user->logActivity(
            action: 'update_availability',
            title: 'Availability Status Updated',
            detail: "User {$user->first_name} {$user->last_name} updated their availability status to " . ($availability ? 'available' : 'unavailable'),
            meta: ['availability' => $availability]
        );
    }

    // update vet roles details
    private function updateVetDetails($user, string $status, array $data): void{
        if (!array_key_exists($status, $this->roleMap)) return;

        $config = $this->roleMap[$status];
        $model = $config['model']::where('user_id', $user->id)->first();
        if ($model) {
            $model->update(Arr::only($data, $config['fields']));
        }

        $user->logActivity(
            'update_vet_details',
            'Veterinary Details Updated',
            "Your Veterinary Details have been updated.",
            ['details' => Arr::only($data, $config['fields'])]
        );
    }
}
