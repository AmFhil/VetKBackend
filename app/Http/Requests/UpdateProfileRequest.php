<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('user')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = auth('user')->user();
        return [
            //
            'email' => 'sometimes|email|unique:users_registration,email,' . $user->id,
            'current_password' => 'sometimes|string',
            'password' => 'sometimes|string|min:8|confirmed',
            'first_name' => 'sometimes|string|max:50|min:3',
            'last_name' => 'sometimes|string|max:50|min:3',
            'phone_num' => 'sometimes|string|max:15',
            'contact_num' => 'sometimes|string|max:15',
            'address' => 'sometimes|string|max:500',
            'practice_license_num' => 'sometimes|string|max:255',
            'clinic_name' => 'sometimes|string|max:100',
            'name_of_institution' => 'sometimes|string|max:255',
            'graduation_year' => 'sometimes|string|max:30',
            'expected_year_of_graduation' => 'sometimes|nullable|string|max:30',
            'specialty' => 'sometimes|string|max:255',
            'list_them' => 'sometimes|string|max:255',
            'longitude' => 'sometimes|numeric',
            'latitude' => 'sometimes|numeric',
            'status' => 'sometimes|string|in:vet,vet_clinic,vet_paraprofessional',
            'bio' => 'sometimes|string|max:150',
            'profile_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:1048',
            'cover_page_image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:1048'
        ];
    }
}
