<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogChatCommentController;
use App\Http\Controllers\BlogChatController;
use App\Http\Controllers\BlogChatLikeController;
use App\Http\Controllers\CaseModelController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\LivestockFarmerController;
use App\Http\Controllers\OthersController;
use App\Http\Controllers\PetController;
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
use App\Http\Middleware\CheckTokenExpiry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 🔹 Test routes
 */
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

/**
 * 🔹 App routes
 */
Route::prefix('v3')->group(function () {
    // Public routes
    Route::middleware('throttle:auth')->group(function () {  // Uses your custom 'auth' limiter (10/min)
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    Route::get('/verify-email/{id}', [AuthController::class, 'verifyEmail'])->name('verify.email');

    Route::post('/get-nearest-veterinary-doctors', [UserController::class, 'getNearestVeterinaryDoctor']);
    Route::get('/search-location', [UserController::class, 'searchLocation']);

    //  Protected routes (token required + idle expiry check)
    Route::middleware(['auth:sanctum', 'token.expiry', 'throttle:api'])->group(function () {
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

        Route::get('/get-veterinary-doctor/{id}/doctor', [VeterinaryDoctorController::class, 'getVeterinaryDoctorById']);
        Route::get('/get-veterinary-clinic/{id}/clinic', [VeterinaryClinicController::class, 'getVeterinaryClinicById']);
        Route::get('/get-veterinary-paraprofessional/{id}/paraprofessional', [VeterinaryParaprofessionalController::class, 'getVeterinaryParaprofessionalById']);

        Route::post('/rate-entity', [RateController::class, 'rateUser']);

        Route::post('/add-new-store', [StoreController::class, 'addNewStore']);
        Route::put('/store/{id}/store', [StoreController::class, 'updateStore']);
        Route::get('/vendor-store', [StoreController::class, 'getVendorsStore']);
        Route::get('/get-store-by-user-id/{id}/store', [StoreController::class, 'getStoreByUserId']);
        Route::get('/get-store-by-id/{id}/store', [StoreController::class, 'getStoreById']);
        Route::delete('/delete-store/{id}/store', [StoreController::class, 'deleteStore']);

        Route::post('/add-new-product', [ProductsController::class, 'addNewProduct']);
        Route::put('/update-product/{id}/product', [ProductsController::class, 'updateProduct']);
        Route::delete('/delete-product/{id}/product', [ProductsController::class, 'deleteProduct']);
        Route::get('/get-products-by-user-id/{id}/product', [ProductsController::class, 'getProductByUserId']);
        Route::get('/get-product-not-on-store', [ProductsController::class, 'getProductNotOnStore']);
        Route::post('/get-product-by-store/{id}/product', [ProductsController::class, 'getProductsByStore']);
        Route::get('/get-related-product/{id}/related', [ProductsController::class, 'getRelatedProducts']);

        Route::post('/update-profile', [UserController::class, 'updateProfile']);

        Route::post('/blogs/{id}/like', [BlogChatLikeController::class, 'toggleLike']);
        Route::get('/blogs/{id}/blog', [BlogChatController::class, 'show']);
        Route::get('/blogs/slug/{id}/slug', [BlogChatController::class, 'showWithSlug']);
        Route::get('/blogs/{id}/share', [BlogChatController::class, 'share']);
        Route::get('/blogs/trending', [BlogChatController::class, 'trending']);
        Route::post('/comments/{id}/flag', [BlogChatCommentController::class, 'flag']);
        Route::post('/add-comment/{blogId}/comment', [BlogChatCommentController::class, 'addComment']);
        Route::get('/fetch-comment/{id}/comment', [BlogChatCommentController::class, 'fetchComments']);
        Route::delete('/delete-comment/{id}/comment', [BlogChatCommentController::class, 'deleteComment']);
        Route::put('/edit-comment/{commentId}/comment', [BlogChatCommentController::class, 'editComment']);
        Route::get('/gets-all-blog', [BlogChatController::class, 'index']);

        Route::post('/add-pet', [PetController::class, 'addNewPet']);
        Route::put('/update-pet/{id}/pet', [PetController::class, 'updatePet']);
        Route::get('/get-user-pets', [PetController::class, 'getUserPets']);
        Route::get('/get-pet_by-id/{id}/pet', [PetController::class, 'getPetById']);
        Route::delete('/delete-pet/{id}/delete', [PetController::class, 'deletePet']);

        Route::post('/add-farm', [FarmController::class, 'addNewFarm']);
        Route::put('/update-farm/{id}/farm', [FarmController::class, 'updateFarm']);
        Route::get('/get-user-farms', [FarmController::class, 'getUserFarms']);
        Route::get('/get-farm_by-id/{id}/farm', [FarmController::class, 'getFarmById']);
        Route::delete('/delete-farm/{id}/delete', [FarmController::class, 'deleteFarm']);

        Route::post('/add-clinic', [ClinicController::class, 'addNewClinic']);
        Route::put('/update-clinic/{id}/clinic', [ClinicController::class, 'updateClinic']);
        Route::get('/get-user-clinics', [ClinicController::class, 'getUserClinics']);
        Route::get('/get-clinic_by-id/{id}/clinic', [ClinicController::class, 'getClinicById']);
        Route::delete('/delete-clinic/{id}/delete', [ClinicController::class, 'deleteClinic']);
        Route::get('/search-clinic', [ClinicController::class, 'searchClinic']);

        Route::post('/add-case', [CaseModelController::class, 'addNewCase']);
        Route::put('/update-case/{id}/case', [CaseModelController::class, 'updateCases']);
        Route::get('/get-user-cases', [CaseModelController::class, 'getUserCase']);
        Route::get('/get-case_by-id/{id}/case', [CaseModelController::class, 'show']);
        Route::delete('/delete-case/{id}/delete', [CaseModelController::class, 'destroy']);
        Route::get('/filter-case/{id}/cases', [CaseModelController::class, 'filterByDateForUser']);
    });

    //  Admin routes
    Route::middleware(['auth:admin'])->group(function () {
        Route::post('/admin/create-category', [\App\Http\Controllers\CategoryController::class, 'store']);
        Route::get('/admin/categories', [\App\Http\Controllers\CategoryController::class, 'adminCategories']);
        Route::post('/admin/blogs', [BlogChatController::class, 'addBlog']);
        Route::put('/admin/update-blog/{id}', [BlogChatController::class, 'updateBlog']);
        Route::delete('/admin/delete-blog/{id}', [BlogChatController::class, 'deleteBlog']);
        Route::post('/admin/blogs/{id}/publish', [BlogChatController::class, 'publish']);
        Route::post('/admin/blogs/{id}/archive', [BlogChatController::class, 'archive']);
        Route::post('/admin/blogs/{id}/flag', [BlogChatCommentController::class, 'updateFlag']);
        Route::post('/admin/blogs/{id}/reject', [BlogChatController::class, 'reject']);
        Route::get('/admin/get-all-blog', [BlogChatController::class, 'getAllBlog']);
        Route::get('/admin/verify-clinic/{id}/clinic', [ClinicController::class, 'verify']);
        Route::get('/filter-case', [CaseModelController::class, 'filterByDateForAdmin']);
        Route::get('/get-user-case', [CaseModelController::class, 'adminSeeAllCases']);
    });
});

/**
 * 🔹 Social login routes
 */
Route::prefix('google')->group(function () {
    Route::get('/loginUsingGoogle', [SocialLogin::class, 'redirectToGoogle']);
    Route::any('/callback', [SocialLogin::class, 'handleGoogleCallback']);
});

Route::prefix('linkedin')->group(function () {
    Route::get('/loginUsingLinkedin', [SocialLogin::class, 'redirectToLinkedin']);
    Route::any('/callback', [SocialLogin::class, 'handleLinkedinCallback']);
});
