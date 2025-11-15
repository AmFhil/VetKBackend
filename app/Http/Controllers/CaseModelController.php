<?php

namespace App\Http\Controllers;

use App\Models\CaseModel;
use App\Models\User;
use App\Models\VeterinaryClinic;
use App\Models\VeterinaryDoctor;
use App\Models\VeterinaryParaprofessional;
use App\Traits\BroadcastsAdminEvents;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CaseModelController extends Controller
{
    use BroadcastsAdminEvents;
    //
    private function generateCaseId($userId)
    {
        $farmCount = CaseModel::where('user_id', $userId)->count();
        $randomNumber = mt_rand(100000, 999999);
        $letter = chr(97 + $farmCount);

        return "CS{$randomNumber}{$letter}";
    }

    public function addNewCase(Request $request)
    {
        $request->validate([
            'case_title' => 'required|string|max:255',
            'client_phone_number' => 'required|string|max:20',
            'pet_or_farm' => 'required|string',
            'pet_name' => 'nullable|string|max:100',
            'specie' => 'nullable|string|max:100',
            'breed' => 'nullable|string|max:100',
            'farm_name' => 'nullable|string|max:100',
            'type_of_livestock' => 'nullable|string|max:100',
            'number_of_livestock' => 'nullable|numeric|min:0',
            'number_of_workers' => 'nullable|numeric|min:0',
            'age' => 'required|numeric|min:0',
            'sex' => 'required|string|max:100',
            'location' => 'required|string|max:255',
            'other_details' => 'nullable|string|max:255',
            'date_occurred' => 'required|date',
            'date_presented' => 'required|date|after_or_equal:date_occurred',
            'history' => 'nullable|string|max:1000',
            'clinical_signs' => 'required|array',
            'temperature' => 'required|string|max:50',
            'heart_rate' => 'required|string|max:50',
            'weight' => 'required|string|max:50',
            'tentative_diagnosis' => 'required|string|max:255',
            'differential_diagnosis' => 'required|string|max:255',
            'lab_confirm' => 'required|string|max:100',
            'mortality' => 'required|string|max:100',
            'treatment_regimen' => 'required|array',
            'picture' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $isVeterinaryClinic = $user->activeRole && $user->activeRole->role === 'veterinary_clinic';
        $isVeterinaryDoctor = $user->activeRole && $user->activeRole->role === 'veterinary_doctor';
        $isVeterinaryParaprofessional = $user->activeRole && $user->activeRole->role === 'veterinary_paraprofessional';

        if (!($isVeterinaryClinic || $isVeterinaryDoctor || $isVeterinaryParaprofessional)) {
            return response()->json(['message' => 'You are not authorized to create a case'], 403);
        }

        $caseId = $this->generateCaseId($user->id);

        // handle image upload
        $imagePath = null;
        if ($request->hasFile('picture')) {
            $imagePath = $request->file('picture')->store('cases_pictures', 'public');
        }

        $case = CaseModel::create([
            'user_id' => $user->id,
            'case_id' => $caseId,
            'case_title' => $request->case_title,
            'client_name' => $request->client_name,
            'client_phone_number' => $request->client_phone_number,
            'pet_or_farm' => $request->pet_or_farm,
            'pet_name' => $request->pet_name,
            'specie' => $request->specie,
            'breed' => $request->breed,
            'pet_number' => $request->pet_number,
            'farm_name' => $request->farm_name,
            'type_of_livestock' => $request->type_of_livestock,
            'number_of_livestock' => $request->number_of_livestock,
            'number_of_workers' => $request->number_of_workers,
            'age' => $request->age,
            'sex' => $request->sex,
            'location' => $request->location,
            'other_details' => $request->other_details,
            'date_occurred' => $request->date_occurred,
            "date_presented" => $request->date_presented,
            'history' => $request->history,
            'clinical_signs' => $request->clinical_signs,
            'temperature' => $request->temperature,
            'heart_rate' => $request->heart_rate,
            'weight' => $request->weight,
            'tentative_diagnosis' => $request->tentative_diagnosis,
            'differential_diagnosis' => $request->differential_diagnosis,
            'lab_confirm' => $request->lab_confirm,
            'mortality' => $request->mortality,
            'treatment_regimen' => $request->treatment_regimen,
            'picture' => $imagePath,
        ]);

        Cache::forget("user_cases_{$user->id}");

        $user->logActivity(
            'case_created',
            'Case Created',
            "A new case \"{$case->case_title}\" (ID: {$case->case_id}) was created.",
            ['case_id' => $case->id]
        );

        return response()->json(['message' => 'Case created successfully', 'case' => $case], 200);
    }

    public function updateCases(Request $request, $id)
    {
        $request->validate([
            'case_title' => 'sometimes|string|max:255',
            'client_phone_number' => 'sometimes|string|max:20',
            'pet_or_farm' => 'sometimes|string',
            'pet_name' => 'sometimes|string|max:100',
            'specie' => 'sometimes|string|max:100',
            'breed' => 'sometimes|string|max:100',
            'farm_name' => 'sometimes|string|max:100',
            'type_of_livestock' => 'sometimes|string|max:100',
            'number_of_livestock' => 'sometimes|numeric|min:0',
            'number_of_workers' => 'sometimes|numeric|min:0',
            'age' => 'sometimes|numeric|min:0',
            'sex' => 'sometimes|string|max:100',
            'location' => 'sometimes|string|max:255',
            'other_details' => 'sometimes|string|max:255',
            'date_occurred' => 'sometimes|date',
            'date_presented' => 'sometimes|date|after_or_equal:date_occurred',
            'history' => 'sometimes|string|max:1000',
            'clinical_signs' => 'sometimes|array',
            'temperature' => 'sometimes|string|max:50',
            'heart_rate' => 'sometimes|string|max:50',
            'weight' => 'sometimes|string|max:50',
            'tentative_diagnosis' => 'sometimes|string|max:255',
            'differential_diagnosis' => 'sometimes|string|max:255',
            'lab_confirm' => 'sometimes|string|max:100',
            'mortality' => 'sometimes|string|max:100',
            'treatment_regimen' => 'sometimes|array',
            'picture' => 'sometimes|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        $case = CaseModel::findOrFail($id);

        $user = Auth::user();

        if (!$user || $user->id !== $case->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($request->hasFile('picture')) {
            if ($case->picture) {
                Storage::disk('public')->delete($case->picture);
            }
            $case->picture = $request->file('picture')->store('cases_pictures', 'public');
        }

        $case->fill($request->only([
            'case_title',
            'client_name',
            'client_phone_number',
            'pet_or_farm',
            'pet_name',
            'specie',
            'breed',
            'pet_number',
            'farm_name',
            'type_of_livestock',
            'number_of_livestock',
            'number_of_workers',
            'age',
            'sex',
            'location',
            'other_details',
            'date_occurred',
            'date_presented',
            'history',
            'clinical_signs',
            'temperature',
            'heart_rate',
            'weight',
            'tentative_diagnosis',
            'differential_diagnosis',
            'lab_confirm',
            'mortality',
            'treatment_regimen',
        ]));
        $case->touch();
        $case->save();

        Cache::forget("user_cases_{$user->id}");

        // After saving updates
        $user->logActivity(
            'case_updated',
            'Case Updated',
            "Your case titled \"{$case->case_title}\" (ID: {$case->case_id}) has been updated successfully.",
            ['case_id' => $case->id]
        );


        return response()->json(['message' => 'Case updated successfully', 'case' => $case], 200);
    }

    // Delete case
    public function destroy($id)
    {
        $case = CaseModel::findOrFail($id);

         $user = Auth::user();
        if ($user->id !== $case->user_id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($case->picture) {
            Storage::disk('public')->delete($case->picture);
        }

        $case_title = $case->case_title;
        $case_id = $case->case_id;
        $caseId = $case->id;
        $case->delete();
        Cache::forget("user_cases_{$user->id}");
        Cache::forget("cases_{$id}");


        $user->logActivity(
            'case_deleted',
            'Case Deleted',
            "Case \"{$case_title}\" (ID: {$case_id}) was deleted.",
            ['case_id' => $caseId]
        );


        return response()->json(['message' => 'Case deleted successfully'], 200);
    }

    public function filterByDateForAdmin(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => "Unauthorize"], 403);
        }

        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);

        $cases = CaseModel::whereBetween('date_occurred', [$request->from, $request->to])
            ->with(['user:id,email,first_name,last_name', 'user.profile:id,user_id,profile_image'])
            ->orderBy('date_occurred', 'asc')
            ->get();

        return response()->json(['cases' => $cases], 200);
    }

    public function filterByDateForUser(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 403);
        }

        $request->validate([
            'from' => 'required|date',
            'to'   => 'required|date|after_or_equal:from',
        ]);

        $cases = CaseModel::where('user_id', $user->id)
            ->whereBetween('date_occurred', [$request->from, $request->to])
            ->with([
                'user:id,email,first_name,last_name',
                'user.profile:id,user_id,profile_image'
            ])
            ->orderBy('date_occurred', 'asc')
            ->get();

        if ($cases->isEmpty()) {
            return response()->json(['message' => 'No cases found for this period'], 404);
        }

        return response()->json(['cases' => $cases], 200);
    }


    public function getUserCase()
    {
        $user = auth('user')->user();
        if (!$user) {
            return response()->json(['message' => 'Forbidden Access'], 403);
        }

        $cases = Cache::remember("user_cases_{$user->id}", now()->addMinutes(10), function () use ($user) {
            return CaseModel::where('user_id', $user->id)
                ->with(['user:id,email,first_name,last_name', 'user.profile:id,user_id,profile_image'])
                ->orderBy('date_occurred', 'desc')
                ->paginate(10);
        });


        return response()->json(['cases' => $cases], 200);
    }

    public function adminSeeAllCases()
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => "Unauthorize"], 403);
        }

        $cases = CaseModel::with(['user:id,email,first_name,last_name', 'user.profile:id,user_id,profile_image'])
            ->orderBy('date_occurred', 'desc')
            ->paginate(10);

        return response()->json(['cases' => $cases], 200);
    }

    // Get single case
    public function show($id)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => "Unauthorize"], 403);
        }

        $case = Cache::remember("cases_{$id}", now()->addMinutes(10), fn() => CaseModel::with(['user:id,email,first_name,last_name', 'user.profile:id,user_id,profile_image'])->find($id));

        if (!$case) {
            return response()->json(['message' => 'Case not found'], 404);
        }

        return response()->json(['case' => $case], 200);
    }

    public function disableCase($id)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized'], 403);

        $case = CaseModel::findOrFail($id);
        $user = User::find($case->user_id);

        if ($user) {
            $case->disabled = true;
            $case->save();
            // Notify the user
            $user->sendNotification(
                'case_disabled',
                'Case Disabled',
                "Your case titled \"{$case->case_title}\" (ID: {$case->case_id}) has been disabled by an administrator.",
                ['case_id' => $case->case_id, 'user_id' => $user->id]
            );

            $user->notify(new \App\Notifications\AdminActionNotification(
                'Case Disabled by Administrator',
                'Dear ' . $user->first_name . ', we regret to inform you that your case "' . $case->case_title . '" (Case ID: ' . $case->case_id . ') has been disabled by an administrator. If you believe this is an error, please contact support for further assistance.'
            ));
        }

        // Log admin activity
        $this->createAdminActivity(
            $admin->id,
            'case_disabled',
            'CASE',
            "Admin {$admin->name} disabled {$user->first_name} case (ID: {$case->id}).",
            ['case_id' => $case->case_id, 'user_id' => $case->user_id, 'staff_id' => $admin->staff_id]
        );

        Cache::forget("user_cases_{$user->id}");
        Cache::forget("cases_{$id}");

        return response()->json(['message' => 'Case disabled'], 200);
    }
}
