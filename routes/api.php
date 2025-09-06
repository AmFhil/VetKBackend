<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\LivestockFarmerController;
use App\Http\Controllers\OthersController;
use App\Http\Controllers\PetOwnerController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\SocialLogin;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VeterinaryClinicController;
use App\Http\Controllers\VeterinaryDoctorController;
use App\Http\Controllers\VeterinaryParaprofessionalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

// Example protected route (requires auth:api middleware)
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});


Route::get('/me-test', function (Request $request) {
    $token = PersonalAccessToken::findToken($request->bearerToken());
    return [
        'token' => $token,
        'user'  => $token?->tokenable,
    ];
});



// app routes
Route::prefix('v3')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/get-nearest-veterinary-doctors', [UserController::class, 'getNearestVeterinaryDoctor']);
    Route::get('/search-location', [UserController::class, 'searchLocation']);

    Route::middleware(['auth:sanctum'])->group(function (){
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
        Route::get('/get-livestock-disease', [UserController::class, 'getLivestockDisease']);
        Route::get('/get-all-veterinary-doctors', [VeterinaryDoctorController::class, 'getAllVeterinaryDoctors']);
        Route::get('/get-all-veterinary-clinics', [VeterinaryClinicController::class, 'getAllVeterinaryClinics']);
        Route::get('/get-all-veterinary-paraprofessionals', [VeterinaryParaprofessionalController::class, 'getAllVeterinaryParaprofessionals']);
        Route::get('/search-veterinary-doctors', [VeterinaryDoctorController::class, 'searchVeterinaryDoctors']);
        Route::get('/search-veterinary-clinics', [VeterinaryClinicController::class, 'searchVeterinaryClinics']);
        Route::get('/search-veterinary-paraprofessionals', [VeterinaryParaprofessionalController::class, 'searchVeterinaryParaprofessionals']);
        Route::post('/get-veterinary-doctor-by-location', [VeterinaryDoctorController::class, 'getVeterinaryDoctorsByLocation']);
        Route::post('/get-veterinary-clinic-by-location', [VeterinaryClinicController::class, 'getNearbyVeterinaryClinics']);
        Route::post('/get-veterinary-paraprofessional-by-location', [VeterinaryParaprofessionalController::class, 'getVeterinaryParaprofessionalsByLocation']);
        Route::get('/get-veterinary-doctor/{id}', [VeterinaryDoctorController::class, 'getVeterinaryDoctorById']);
        Route::get('/get-veterinary-clinic/{id}', [VeterinaryClinicController::class, 'getVeterinaryClinicById']);
        Route::get('/get-veterinary-paraprofessional/{id}', [VeterinaryParaprofessionalController::class, 'getVeterinaryParaprofessionalById']);
        Route::post('/rate-entity', [RateController::class, 'rateUser']);
        Route::post('/add-new-store', [StoreController::class, 'addNewStore']);
        Route::put('/update-store/{id}', [StoreController::class, 'updateStore']);
        Route::get('/vendor-store', [StoreController::class, 'getVendorsStore']);
        Route::get('/get-store-by-user-id/{id}', [StoreController::class, 'getStoreByUserId']);
        Route::get('/get-store-by-id/{id}', [StoreController::class, 'getStoreById']);
        Route::get('/delete-store/{id}', [StoreController::class, 'deleteStore']);
        Route::post('/add-new-product', [ProductsController::class, 'addNewProduct']);
        Route::put('/update-product/{id}', [ProductsController::class, 'updateProduct']);
        Route::get('/delete-product/{id}', [ProductsController::class, 'deleteProduct']);
        Route::post('/get-product-by-store/{id}', [ProductsController::class, 'getProductsByStore']);


    });

    Route::middleware(['auth:admin'])->group(function () {
        Route::post('/admin/create-category', [\App\Http\Controllers\CategoryController::class, 'store']);
        Route::get('/admin/categories', [\App\Http\Controllers\CategoryController::class, 'adminCategories']);
    });

});

//social login
//google
Route::prefix('google')->group(function () {
    Route::get('/loginUsingGoogle', [SocialLogin::class, 'redirectToGoogle']);
    Route::any('/callback', [SocialLogin::class, 'handleGoogleCallback']);
});

//linkedin
Route::prefix('linkedin')->group(function () {
    Route::get('/loginUsingLinkedin', [SocialLogin::class, 'redirectToLinkedin']);
    Route::any('/callback', [SocialLogin::class, 'handleLinkedinCallback']);
});
