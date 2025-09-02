<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LivestockFarmerController;
use App\Http\Controllers\OthersController;
use App\Http\Controllers\PetOwnerController;
use App\Http\Controllers\SocialLogin;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VeterinaryClinicController;
use App\Http\Controllers\VeterinaryDoctorController;
use App\Http\Controllers\VeterinaryParaprofessionalController;
use App\Models\VeterinaryParaprofessional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Example protected route (requires auth:api middleware)
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return response()->json($request->user());
});

// app routes
Route::prefix('api/v3')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/get-nearest-veterinary-doctors', [UserController::class, 'getNearestVeterinaryDoctors']);

    Route::middleware('auth:sanctum')->group(function (){
        Route::post('/complete-profile', [SocialLogin::class, 'completeUserRegistration']);
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/add-livestock-farmer', [LivestockFarmerController::class, 'AddLivestockFarmer']);
        Route::post('/add-others', [OthersController::class, 'addOthers']);
        Route::post('/add-pet-owner', [PetOwnerController::class, 'addPetOwner']);
        Route::post('/add-vendor', [VendorController::class, 'addVendor']);
        Route::post('/add-veterinary-clinic', [VeterinaryClinicController::class, 'addVeterinaryClinic']);
        Route::post('/add-veterinary-doctor', [VeterinaryDoctorController::class, 'addVeterinaryDoctor']);
        Route::post('/add-veterinary-paraprofessional', [VeterinaryParaprofessionalController::class, 'addVeterinaryParaprofessional']);
        Route::post('/feed-calculator', [UserController::class, 'feedCalculator']);
        Route::post('/disease-predictor', [UserController::class, 'diseasePredictor']);
        Route::post('/get-livestock-disease', [UserController::class, 'getLivestockDisease']);
        Route::get('/get-all-veterinary-doctors', [VeterinaryDoctorController::class, 'getAllVeterinaryDoctors']);
        Route::get('/get-all-veterinary-clinics', [VeterinaryClinicController::class, 'getAllVeterinaryClinics']);
        Route::get('/get-all-veterinary-paraprofessionals', [VeterinaryParaprofessionalController::class, 'getAllVeterinaryParaprofessionals']);
        Route::get('/search-veterinary-doctors', [VeterinaryDoctorController::class, 'searchVeterinaryDoctors']);
        Route::get('/search-veterinary-clinics', [VeterinaryClinicController::class, 'searchVeterinaryClinics']);
        Route::get('/search-veterinary-paraprofessionals', [VeterinaryParaprofessionalController::class, 'searchVeterinaryParaprofessionals']);
        Route::get('/get-veterinary-doctor-by-location', [VeterinaryDoctorController::class, 'getVeterinaryDoctorsByLocation']);
        Route::get('/get-veterinary-clinic-by-location', [VeterinaryClinicController::class, 'getNearbyVeterinaryClinics']);
        Route::get('/get-veterinary-paraprofessional-by-location', [VeterinaryParaprofessional::class, 'getVeterinaryParaprofessionalsByLocation']);
        Route::get('/get-veterinary-doctor/{id}', [VeterinaryDoctorController::class, 'getVeterinaryDoctorById']);
        Route::get('/get-veterinary-clinic/{id}', [VeterinaryClinicController::class, 'getVeterinaryClinicById']);
        Route::get('/get-veterinary-paraprofessional/{id}', [VeterinaryParaprofessionalController::class, 'getVeterinaryParaprofessionalById']);
        
    });

    Route::middleware('auth:admin')->group(function () {
        Route::post('/admin/create-category', [\App\Http\Controllers\CategoryController::class, 'store']);
        Route::get('/admin/categories', [\App\Http\Controllers\CategoryController::class, 'adminCategories']);
    });

});

//social login
//google
Route::prefix('google')->group(function () {
    Route::get('/loginUsingGoogle', [SocialLogin::class, 'redirectToGoogle']);
    Route::any('/callback', [socialLogin::class, 'handleGoogleCallback']);
});

//linkedin
Route::prefix('linkedin')->group(function () {
    Route::get('/loginUsingLinkedin', [socialLogin::class, 'redirectToLinkedin']);
    Route::any('/callback', [socialLogin::class, 'handleLinkedinCallback']);
});
