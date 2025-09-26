<?php

use App\Http\Controllers\Admin\AdminForumController;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\AdminSubscriptionController;
use App\Http\Controllers\AdsPromotionController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogChatCommentController;
use App\Http\Controllers\BlogChatController;
use App\Http\Controllers\BlogChatLikeController;
use App\Http\Controllers\CaseModelController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ForumChatCommentController;
use App\Http\Controllers\ForumChatController;
use App\Http\Controllers\ForumChatLikeController;
use App\Http\Controllers\LivestockFarmerController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\OthersController;
use App\Http\Controllers\PetController;
use App\Http\Controllers\PetOwnerController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromotionPlanController;
use App\Http\Controllers\RateController;
use App\Http\Controllers\SocialLogin;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserPromotionPlanController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\VeterinaryClinicController;
use App\Http\Controllers\VeterinaryDoctorController;
use App\Http\Controllers\VeterinaryParaprofessionalController;
use App\Models\AdsPromotion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * 🔹 Test routes
 */
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});


/**
 * 🔹 App routes
 */
Route::prefix('v3')->group(function () {

    // Public routes
    Route::middleware('throttle:auth')->group(function () {  // Uses your custom 'auth' limiter (10/min)
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/reset-mail', [ForgotPasswordController::class, 'sendResetLink']);
        Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
        Route::post('/resendVerificationEmail', [AuthController::class, 'resendVerificationEmail']);
        Route::get('/endpoint/{email}/secure', [UserController::class, 'secureEndpoint']);
        Route::get('/verify-email/{id}', [AuthController::class, 'verifyEmail'])->name('verify.email');
        Route::post('/get-nearest-veterinary-doctors', [UserController::class, 'getNearestVeterinaryDoctor']);
        Route::get('/search-location', [UserController::class, 'searchLocation']);
        Route::post('/add-to-newsletter', [NewsletterController::class, 'addNewsletter']);
        Route::post('/contact-us', [ContactUsController::class, 'addContactUs']);
    });





    //  Protected routes (token required + idle expiry check)
    Route::middleware(['auth:sanctum', 'token.expiry', 'throttle:api'])->group(function () {
        Route::post('/complete-profile', [SocialLogin::class, 'completeUserRegistration']);
        Route::post('/logout', [AuthController::class, 'logout']);

        Route::post('/add-livestock-farmer', [LivestockFarmerController::class, 'AddLivestockFarmer']);
        Route::get('/get-livestock-farmer/{id}/livestock-farmer', [LivestockFarmerController::class, 'getLivestockFarmerById']);
        Route::post('/add-others', [OthersController::class, 'addOthers']);
        Route::get('/get-other-by-id/{id}/others', [OthersController::class, 'getOtherById']);
        Route::post('/add-pet-owner', [PetOwnerController::class, 'choosePetOwnerRole']);
        Route::get('/get-pet-owner-by-id/{id}/pet-owner', [PetOwnerController::class, 'getPetOwnerById']);
        Route::post('/add-vendor', [VendorController::class, 'addVendor']);
        Route::get('/get-vendor-by-id/{id}/vendor', [VendorController::class, 'getVendorById']);
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
        Route::get('/get-pet-by-id/{id}/pet', [PetController::class, 'getPetById']);
        Route::delete('/delete-pet/{id}/delete', [PetController::class, 'deletePet']);

        Route::post('/add-farm', [FarmController::class, 'addNewFarm']);
        Route::put('/update-farm/{id}/farm', [FarmController::class, 'updateFarm']);
        Route::get('/get-user-farms', [FarmController::class, 'getUserFarms']);
        Route::get('/get-farm-by-id/{id}/farm', [FarmController::class, 'getFarmById']);
        Route::delete('/delete-farm/{id}/delete', [FarmController::class, 'deleteFarm']);

        Route::post('/add-clinic', [ClinicController::class, 'addNewClinic']);
        Route::put('/update-clinic/{id}/clinic', [ClinicController::class, 'updateClinic']);
        Route::get('/get-user-clinics', [ClinicController::class, 'getUserClinics']);
        Route::get('/get-clinic-by-id/{id}/clinic', [ClinicController::class, 'getClinicById']);
        Route::delete('/delete-clinic/{id}/delete', [ClinicController::class, 'deleteClinic']);
        Route::get('/search-clinic', [ClinicController::class, 'searchClinic']);

        Route::post('/add-case', [CaseModelController::class, 'addNewCase']);
        Route::put('/update-case/{id}/case', [CaseModelController::class, 'updateCases']);
        Route::get('/get-user-cases', [CaseModelController::class, 'getUserCase']);
        Route::get('/get-case_by-id/{id}/case', [CaseModelController::class, 'show']);
        Route::delete('/delete-case/{id}/delete', [CaseModelController::class, 'destroy']);
        Route::get('/filter-case/{id}/cases', [CaseModelController::class, 'filterByDateForUser']);

        Route::post('/promote-ad', [AdsPromotionController::class, 'promoteAd']);
        Route::put('/update-ad/{id}/ad', [AdsPromotionController::class, 'updateAd']);
        Route::delete('/delete-ad/{id}/ad', [AdsPromotionController::class, 'deleteAd']);
        Route::get('/toggle_availability/{id}/ad', [AdsPromotionController::class, 'toggleAvailability']);

        Route::post('/buy-plan', [PromotionPlanController::class, 'buyPlan']);

        Route::get('/get-profile', [ProfileController::class, 'getMyProfile']);

        Route::post('/messages/send', [DirectMessageController::class, 'send']);
        Route::get('/messages/{userId}', [DirectMessageController::class, 'conversation']);
        Route::patch('/messages/{id}/read', [DirectMessageController::class, 'markAsRead']);
        Route::patch('/messages/{id}/edit', [DirectMessageController::class, 'edit']);
        Route::delete('/messages/{id}', [DirectMessageController::class, 'destroy']);
        Route::patch('/messages/{id}/flag', [DirectMessageController::class, 'flag']);

        Route::get('/get-user-activity', [UserController::class, 'getUserActivity']);
        Route::get('/get-user-notification', [UserController::class, 'getUserNotification']);

        Route::get('/promotion-plans', [PromotionPlanController::class, 'getActivePlans']);


        Route::get('/my', [UserPromotionPlanController::class, 'myPlans']);
        // buy a plan (dummy activation, Paystack will plug here later)
        Route::post('/buy', [UserPromotionPlanController::class, 'buyPlan']);

        Route::prefix('forums')->group(function () {
            Route::get('/', [ForumChatController::class, 'index']);          // List published forums
            Route::get('/trending', [ForumChatController::class, 'trending']); // Trending forums
            Route::get('/{slug}', [ForumChatController::class, 'show']);     // Show single forum
            Route::post('/', [ForumChatController::class, 'store']);         // Create forum
            Route::put('/{id}', [ForumChatController::class, 'update']);     // Update forum
            Route::delete('/{id}', [ForumChatController::class, 'destroy']); // Delete forum
            Route::post('/{id}/share', [ForumChatController::class, 'share']); // Share forum
            Route::prefix('{forumId}/comments')->group(function () {
                Route::get('/', [ForumChatCommentController::class, 'fetchComments']);  // List comments
                Route::post('/', [ForumChatCommentController::class, 'addComment']);    // Add comment
            });
            Route::prefix('comments')->group(function () {
                Route::put('/{id}', [ForumChatCommentController::class, 'editComment']);   // Edit comment
                Route::delete('/{id}', [ForumChatCommentController::class, 'deleteComment']); // Delete comment
                Route::post('/{id}/flag', [ForumChatCommentController::class, 'flag']);   // User flag
            });
            Route::post('/{id}/like', [ForumChatLikeController::class, 'toggleLike']);   // Like/Unlike
        });

        Route::prefix('appointment')->group(function () {
            Route::post('/', [AppointmentController::class, 'makeAppointment']);
            Route::get('/{id}', [AppointmentController::class, 'getAppointment']);
            Route::get('/{id}/cancel', [AppointmentController::class, 'cancelAppointment']);
            Route::get('/{id}/complete', [AppointmentController::class, 'completeAppointment']);
        });
    });

    // User must have an active subscription
    // Route::middleware(['auth:sanctum', 'subscription'])->group(function () {

    //     // Store limit
    //     Route::post('/store', [StoreController::class, 'create'])->middleware('subscription:store');

    //     // Product limit per store
    //     Route::post('/product', [ProductsController::class, 'create'])->middleware('subscription:product');

    //     // Case records
    //     Route::post('/cases', [CaseModelController::class, 'create'])->middleware('subscription:case_record');

    //     // Direct messaging
    //     Route::post('/messages', [MessageController::class, 'send'])->middleware('subscription:direct_message');

    //     // Contact info
    //     Route::get('/contacts/{id}', [ContactController::class, 'show'])->middleware('subscription:contact_info');
    // });


    //  Admin routes
    Route::middleware(['auth:admin',  'throttle:api'])->prefix('admin')->group(function () {
        Route::post('/create-category', [\App\Http\Controllers\CategoryController::class, 'store']);
        Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'adminCategories']);
        Route::post('/blogs', [BlogChatController::class, 'addBlog']);
        Route::put('/update-blog/{id}', [BlogChatController::class, 'updateBlog']);
        Route::delete('/delete-blog/{id}', [BlogChatController::class, 'delete']);
        Route::get('/blogs/deleted', [BlogChatController::class, 'getDeletedBlogs']);
        Route::get('/blogs/restore/{id}', [BlogChatController::class, 'restore']);
        Route::post('/blogs/{id}/publish', [BlogChatController::class, 'publish']);
        Route::post('/blogs/{id}/archive', [BlogChatController::class, 'archive']);
        Route::post('/blogs/{id}/flag', [BlogChatCommentController::class, 'updateFlag']);
        Route::post('/blogs/{id}/reject', [BlogChatController::class, 'reject']);
        Route::get('/get-all-blog', [BlogChatController::class, 'getAllBlog']);
        Route::get('/verify-clinic/{id}/clinic', [ClinicController::class, 'verify']);
        Route::get('/verify-veterinary-clinic/{id}/clinic', [VeterinaryClinicController::class, 'verify']);
        Route::get('/verify-veterinary-doctor/{id}/doctor', [VeterinaryDoctorController::class, 'verify']);
        Route::get('/verify-veterinary-paraprofessional/{id}/paraprofessional', [VeterinaryParaprofessionalController::class, 'verify']);
        Route::get('/filter-case', [CaseModelController::class, 'filterByDateForAdmin']);
        Route::get('/get-user-case', [CaseModelController::class, 'adminSeeAllCases']);
        Route::post('/subscriptions', [AdminSubscriptionController::class, 'addSubscription']);
        Route::get('/subscriptions', [AdminSubscriptionController::class, 'listSubscriptions']);
        Route::get('/subscriptions/{id}/view', [AdminSubscriptionController::class, 'viewSubscription']);
        Route::put('/subscriptions/{id}/update', [AdminSubscriptionController::class, 'updateSubscription']);
        Route::delete('/subscriptions/{id}/delete', [AdminSubscriptionController::class, 'deleteSubscription']);

        Route::get('/promotion-plans', [PromotionPlanController::class, 'getAllPlans']);
        Route::post('/promotion-plans', [PromotionPlanController::class, 'createPlan']);
        Route::put('/promotion-plans/{id}', [PromotionPlanController::class, 'updatePlan']);
        Route::delete('/promotion-plans/{id}', [PromotionPlanController::class, 'deletePlan']);

        // Admin promotion actions (user subscriptions & ads promotions)
        Route::get('/user-promotions', [AdminPromotionController::class, 'listUserPromotions']);
        Route::get('/user-promotions/{id}', [AdminPromotionController::class, 'viewUserPromotion']);
        Route::post('/user-promotions/{id}/expire', [AdminPromotionController::class, 'expirePromotion']);
        Route::get('/promotion-approvals', [AdminPromotionController::class, 'getPromotionApprovals']);
        Route::get('/promotion-users/{title}', [AdminPromotionController::class, 'getUsersByPromotion']);
        Route::post('/ads-promotions/activate', [AdminPromotionController::class, 'activatePromotion']);
        Route::post('/ads-promotions/deactivate', [AdminPromotionController::class, 'deactivatePromotion']);

        Route::patch('{id}/expire', [UserPromotionPlanController::class, 'expirePlan']);

        Route::prefix('admin/forums')->group(function () {
            Route::get('/', [ForumChatController::class, 'getAllForumChat']); // All forums for admin
            Route::put('/comments/{id}/flag', [ForumChatCommentController::class, 'updateFlag']); // Admin flag
            Route::post('/approve', [AdminForumController::class, 'approveForumChat']); // Approve forum
            Route::post('/reject', [AdminForumController::class, 'rejectForumChat']); // Reject forum
            Route::get('/search', [ForumChatController::class, 'searchForumChats']); // Search forums
            Route::get('forum-approvals', [AdminForumController::class, 'getForumApprovals']); // pending forums
        });

        Route::get('/disable/{id}/store', [StoreController::class, 'disableStore']);
        Route::get('/disable/{id}/case', [CaseModelController::class, 'disableCase']);
        Route::get('/disable/{id}/clinic', [ClinicController::class, 'disableClinic']);
        Route::get('/disable/{id}/farm', [FarmController::class, 'disableFarm']);
        Route::get('/disable/{id}/product', [ProductsController::class, 'disableProduct']);
        Route::get('/disable/{id}/pet', [PetController::class, 'disablePet']);
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
