<?php

use App\Http\Controllers\Admin\AdminActivityController;
use App\Http\Controllers\Admin\AdminAnalyticsController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminForumController;
use App\Http\Controllers\Admin\AdminManageUserController;
use App\Http\Controllers\Admin\AdminNotification;
use App\Http\Controllers\Admin\AdminPromotionController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\AdsPromotionController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlogChatCommentController;
use App\Http\Controllers\BlogChatController;
use App\Http\Controllers\BlogChatLikeController;
use App\Http\Controllers\CaseCommentController;
use App\Http\Controllers\CaseModelController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ClinicController;
use App\Http\Controllers\ContactUsController;
use App\Http\Controllers\DirectMessageController;
use App\Http\Controllers\DiseasePredictorController;
use App\Http\Controllers\FarmController;
use App\Http\Controllers\FeedCalculatorController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\ForumChatCommentController;
use App\Http\Controllers\ForumChatController;
use App\Http\Controllers\ForumChatLikeController;
use App\Http\Controllers\LivestockFarmerController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\OrderController;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Test routes
 */
Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return $request->user();
});

/**
 * App routes
 */
Route::prefix('v3')->group(function () {
    // Broadcasting auth route (handles both user and admin guards via Sanctum tokens)
    Broadcast::routes(['middleware' => ['auth:sanctum']]);

    // Public routes
    Route::middleware('throttle:auth')->group(function () {  // Uses your custom 'auth' limiter (10/min)
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/reset-mail', [ForgotPasswordController::class, 'sendResetLink']);
        Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
        Route::post('/resendVerificationEmail', [AuthController::class, 'resendVerificationEmail']);
        Route::get('/endpoint/{email}/secure', [UserController::class, 'secureEndpoint']);
        Route::get('/verify-email/{id}', [AuthController::class, 'verifyEmail'])->name('verify.email');
        Route::get('/get-nearest-veterinary-doctors', [UserController::class, 'getNearestVeterinaryDoctor']);
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
        Route::get('/get-all-vendor', [VendorController::class, 'getVendor']);
        Route::post('/add-veterinary-clinic', [VeterinaryClinicController::class, 'addVeterinaryClinic']);
        Route::post('/add-veterinary-doctor', [VeterinaryDoctorController::class, 'addVeterinaryDoctor']);
        Route::post('/add-veterinary-paraprofessional', [VeterinaryParaprofessionalController::class, 'addVeterinaryParaprofessional']);

        Route::post('/feed-calculator', [FeedCalculatorController::class, 'feedCalculator']);
        Route::post('/disease-predictor', [DiseasePredictorController::class, 'diseasePredictor']);
        // Route::get('/get-livestock-disease', [UserController::class, 'getLivestockDisease']);

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
        Route::match(['post', 'patch'], '/store/{id}/store', [StoreController::class, 'updateStore']);
        Route::get('/vendor-store', [StoreController::class, 'getVendorStores']);
        Route::get('/get-all-stores', [StoreController::class, 'getAllStores']);
        Route::get('/get-my-store', [StoreController::class, 'getMyStore']);
        Route::get('/get-store-by-user-id/{id}/store', [StoreController::class, 'getStoreByUserId']);
        Route::get('/get-store-by-id/{id}/store', [StoreController::class, 'getStoreById']);
        Route::delete('/delete-store/{id}/store', [StoreController::class, 'deleteStore']);
        Route::post('/get-stores-by-location', [StoreController::class, 'getStoresByLocation']);

        Route::post('/add-new-product', [ProductsController::class, 'addNewProduct']);
        Route::match(['post', 'patch'], '/update-product/{id}/product', [ProductsController::class, 'updateProduct']);
        Route::delete('/delete-product/{id}/product', [ProductsController::class, 'deleteProduct']);
        Route::get('/get-products-by-user-id/{id}/product', [ProductsController::class, 'getProductByUserId']);
        Route::get('/get-product-not-on-store', [ProductsController::class, 'getProductNotOnStore']);
        Route::get('/get-product-by-store/{id}/product', [ProductsController::class, 'getProductsByStore']);
        Route::get('/get-product-by-store-for-customer/{id}/product', [ProductsController::class, 'getProductsByStoreForCustomers']);
        Route::get('/get-related-product/{id}/related', [ProductsController::class, 'getRelatedProducts']);
        Route::post('/search-product', [ProductsController::class, 'searchProducts']);
        Route::get('/get-product-by-id/{id}/product', [ProductsController::class, 'getProductById']);

        Route::post('/update-profile', [UserController::class, 'updateProfile']);

        Route::post('/blogs/{id}/like', [BlogChatLikeController::class, 'toggleLike']);
        Route::get('/blogs/{id}/blog', [BlogChatController::class, 'show']);
        Route::get('/blogs/slug/{slug}/slug', [BlogChatController::class, 'showWithSlug']);
        Route::get('/blogs/{id}/share', [BlogChatController::class, 'share']);
        Route::get('/blogs/trending', [BlogChatController::class, 'trending']);
        Route::put('/comments/{id}/flag', [BlogChatCommentController::class, 'flag']);
        Route::post('/add-comment/{blogId}/comment', [BlogChatCommentController::class, 'addComment']);
        Route::get('/fetch-comment/{id}/comment', [BlogChatCommentController::class, 'fetchComments']);
        Route::delete('/delete-comment/{id}/comment', [BlogChatCommentController::class, 'deleteComment']);
        Route::put('/edit-comment/{commentId}/comment', [BlogChatCommentController::class, 'editComment']);
        Route::get('/gets-all-blog', [BlogChatController::class, 'index']);

        Route::post('/add-pet', [PetController::class, 'addNewPet']);
        Route::match(['post', 'patch'], '/update-pet/{id}/pet', [PetController::class, 'updatePet']);
        Route::get('/get-user-pets', [PetController::class, 'getUserPets']);
        Route::get('/get-pet-by-id/{id}/pet', [PetController::class, 'getPetById']);
        Route::delete('/delete-pet/{id}/delete', [PetController::class, 'deletePet']);

        Route::post('/add-farm', [FarmController::class, 'addNewFarm']);
        Route::match(['post', 'patch'], '/update-farm/{id}/farm', [FarmController::class, 'updateFarm']);
        Route::get('/get-user-farms', [FarmController::class, 'getUserFarms']);
        Route::get('/get-farm-by-id/{id}/farm', [FarmController::class, 'getFarmById']);
        Route::delete('/delete-farm/{id}/delete', [FarmController::class, 'deleteFarm']);

        Route::post('/add-clinic', [ClinicController::class, 'addNewClinic']);
        Route::match(['post', 'patch'], '/update-clinic/{id}/clinic', [ClinicController::class, 'updateClinic']);
        Route::get('/get-user-clinics', [ClinicController::class, 'getUserClinics']);
        Route::get('/get-clinic-by-id/{id}/clinic', [ClinicController::class, 'getClinicById']);
        Route::delete('/delete-clinic/{id}/delete', [ClinicController::class, 'deleteClinic']);
        Route::get('/search-clinic', [ClinicController::class, 'searchClinic']);

        Route::post('/add-case', [CaseModelController::class, 'addNewCase']);
        Route::match(['post', 'patch'], '/update-case/{id}/case', [CaseModelController::class, 'updateCases']);
        Route::get('/get-user-cases', [CaseModelController::class, 'getUserCase']);
        Route::get('/get-case_by-id/{id}/case', [CaseModelController::class, 'show']);
        Route::delete('/delete-case/{id}/delete', [CaseModelController::class, 'destroy']);
        Route::get('/filter-case', [CaseModelController::class, 'filterByDateForUser']);

        Route::post('/add-comment', [CaseCommentController::class, 'addComment']);
        Route::get('/get-comments/{caseId}/case', [CaseCommentController::class, 'getComments']);
        Route::delete('/delete-comment/{id}/case', [CaseCommentController::class, 'deleteComment']);
        Route::put('/edit-comment/{id}/case', [CaseCommentController::class, 'updateComment']);

        Route::post('/promote-ad', [AdsPromotionController::class, 'promoteAd']);
        Route::get('/cancel-promotion/{id}/ad', [AdsPromotionController::class, 'cancelPromotion']);
        Route::get('/get-my-promotions', [AdsPromotionController::class, 'myPromotions']);


        Route::get('/get-profile', [ProfileController::class, 'getMyProfile']);

        Route::post('/messages/send', [DirectMessageController::class, 'send']);
        Route::get('/messages/{userId}', [DirectMessageController::class, 'conversation']);
        Route::patch('/messages/{id}/read', [DirectMessageController::class, 'markAsRead']);
        Route::match(['post', 'patch'], '/messages/{id}/edit', [DirectMessageController::class, 'edit']);
        Route::delete('/messages/{id}/delete', [DirectMessageController::class, 'destroy']);
        Route::put('/messages/{id}/flag', [DirectMessageController::class, 'flag']);
        Route::get('/message/sent', [DirectMessageController::class, 'getAllSentMessage']);
        Route::get('/message/received', [DirectMessageController::class, 'getAllReceivedMessage']);
        Route::get('/get-chat-list', [DirectMessageController::class, 'getChatList']);

        Route::get('/get-user-activity', [UserController::class, 'getUserActivity']);
        Route::get('/get-user-notification', [UserController::class, 'getUserNotification']);

        Route::get('/promotion-plans', [PromotionPlanController::class, 'getActivePlans']);


        Route::prefix('forums')->group(function () {
            Route::get('/index', [ForumChatController::class, 'index']);          // List published forums
            Route::get('/trending', [ForumChatController::class, 'trending']); // Trending forums
            Route::get('/{slug}', [ForumChatController::class, 'show']);     // Show single forum
            Route::post('/store', [ForumChatController::class, 'store']);         // Create forum
            Route::match(['post', 'patch'], '/{id}/update', [ForumChatController::class, 'update']);     // Update forum
            Route::delete('/{id}/delete', [ForumChatController::class, 'destroy']); // Delete forum
            Route::get('/{id}/share', [ForumChatController::class, 'share']); // Share forum
            Route::get('/get-visibility-options/{visibility}/forums', [ForumChatController::class, 'getForumsByVisibility']);
            Route::prefix('{forumId}/comments')->group(function () {
                Route::get('/fetch', [ForumChatCommentController::class, 'fetchComments']);  // List comments
                Route::post('/', [ForumChatCommentController::class, 'addComment']);    // Add comment
            });
            Route::prefix('comments')->group(function () {
                Route::put('/{id}', [ForumChatCommentController::class, 'editComment']);   // Edit comment
                Route::delete('/{id}/delete', [ForumChatCommentController::class, 'deleteComment']); // Delete comment
                Route::put('/{id}/flag', [ForumChatCommentController::class, 'flag']);   // User flag
            });
            Route::post('/{id}/like', [ForumChatLikeController::class, 'toggleLike']);   // Like/Unlike
        });

        Route::prefix('appointment')->group(function () {
            Route::post('/', [AppointmentController::class, 'makeAppointment']);
            Route::get('/{id}', [AppointmentController::class, 'getAppointment'])->name('appointments.show');
            Route::get('/{id}/cancel', [AppointmentController::class, 'cancelAppointment'])->name('appointments.cancel');
            Route::get('/{id}/complete', [AppointmentController::class, 'completeAppointment']);
        });

        Route::post('/orders', [OrderController::class, 'createOrder']);
        Route::get('/orders/buyer', [OrderController::class, 'getBuyerOrders']);
        Route::get('/orders/merchant', [OrderController::class, 'getMerchantOrders']);
        Route::get('/orders/{id}', [OrderController::class, 'getOrderDetails'])->name('order.show');
        Route::post('/orders/{id}/confirm', [OrderController::class, 'confirmOrder']);
        Route::post('/orders/{id}/complaint', [OrderController::class, 'fileComplaint']);
        Route::post('/orders/{id}/cancel', [OrderController::class, 'cancelOrder'])->name('order.cancel');

        Route::get('/get-categories', [CategoryController::class, 'index']);
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

    Route::post('/admin/login', [AdminAuthController::class, 'adminLogin']);

    //  Admin routes
    Route::middleware(['auth:admin',  'throttle:api', 'token.expiry'])->prefix('admin')->group(function () {
        Route::post('/create-category', [CategoryController::class, 'store']);
        Route::get('/categories', [CategoryController::class, 'adminCategories']);
        Route::match(['post', 'patch'], '/update-category/{id}/category', [CategoryController::class, 'update']);
        Route::delete('/delete-category/{id}/category', [CategoryController::class, 'destroy']);
        Route::get('/get-category/{id}/category', [CategoryController::class, 'show']);
        Route::post('/blogs', [BlogChatController::class, 'addBlog']);
        Route::match(['post', 'patch'], '/update-blog/{id}', [BlogChatController::class, 'updateBlog']);
        Route::delete('/delete-blog/{id}', [BlogChatController::class, 'delete']);
        Route::get('/blogs/deleted', [BlogChatController::class, 'getDeletedBlogs']);
        Route::get('/blogs/restore/{id}', [BlogChatController::class, 'restore']);
        Route::post('/blogs/{id}/publish', [BlogChatController::class, 'publish']);
        Route::post('/blogs/{id}/archive', [BlogChatController::class, 'archive']);
        Route::put('/blogs/{id}/flag', [BlogChatCommentController::class, 'updateFlag']);
        Route::post('/blogs/{id}/reject', [BlogChatController::class, 'reject']);
        Route::get('/get-all-blog', [BlogChatController::class, 'getAllBlog']);
        Route::patch('/verify-clinic/{id}/clinic', [ClinicController::class, 'verify']);
        Route::patch('/verify-veterinary-clinic/{id}/clinic', [VeterinaryClinicController::class, 'verify']);
        Route::patch('/verify-veterinary-doctor/{id}/doctor', [VeterinaryDoctorController::class, 'verify']);
        Route::patch('/verify-veterinary-paraprofessional/{id}/paraprofessional', [VeterinaryParaprofessionalController::class, 'verify']);
        Route::get('/filter-case', [CaseModelController::class, 'filterByDateForAdmin']);
        Route::get('/get-user-case', [CaseModelController::class, 'adminSeeAllCases']);

        Route::post('/subscriptions', [AdminSubscriptionController::class, 'addSubscription']);
        Route::get('/subscriptions', [AdminSubscriptionController::class, 'listSubscriptions']);
        Route::get('/subscriptions/{id}/view', [AdminSubscriptionController::class, 'viewSubscription']);
        Route::put('/subscriptions/{id}/update', [AdminSubscriptionController::class, 'updateSubscription']);
        Route::delete('/subscriptions/{id}/delete', [AdminSubscriptionController::class, 'deleteSubscription']);
        Route::put('/deactivate-subscription/{id}', [AdminSubscriptionController::class, 'deactivateSubscription']);
        Route::get('/get-subscription-approvals', [AdminSubscriptionController::class, 'getSubscriptionApprovals']);
        Route::get('/get-user-by-subscription-type/{type}', [AdminSubscriptionController::class, 'getUsersBySubscription']);

        Route::get('/promotion-plans', [PromotionPlanController::class, 'getAllPlans']);
        Route::post('/promotion-plans', [PromotionPlanController::class, 'createPlan']);
        Route::put('/promotion-plans/{id}', [PromotionPlanController::class, 'updatePlan']);
        Route::delete('/promotion-plans/{id}', [PromotionPlanController::class, 'deletePlan']);

        // Admin promotion actions (user subscriptions & ads promotions)
        Route::get('/user-promotions', [AdminPromotionController::class, 'listUserPromotions']);
        Route::get('/user-promotions/{id}', [AdminPromotionController::class, 'viewUserPromotion']);
        Route::get('/user-promotions/{id}/expire', [AdminPromotionController::class, 'expirePromotion']);
        Route::get('/promotion-approvals', [AdminPromotionController::class, 'getPromotionApprovals']);
        Route::get('/promotion-users/{title}', [AdminPromotionController::class, 'getUsersByPromotion']);
        Route::post('/ads-promotions/activate', [AdminPromotionController::class, 'activatePromotion']);
        Route::post('/ads-promotions/deactivate', [AdminPromotionController::class, 'deactivatePromotion']);


        Route::prefix('forums')->group(function () {
            Route::get('/', [ForumChatController::class, 'getAllForumChat']); // All forums for admin
            Route::put('/comments/{id}/flag', [ForumChatCommentController::class, 'updateFlag']); // Admin flag
            Route::post('/approve', [AdminForumController::class, 'approveForumChat']); // Approve forum
            Route::post('/reject', [AdminForumController::class, 'rejectForumChat']); // Reject forum
            Route::get('/search', [ForumChatController::class, 'searchForumChats']); // Search forums
            Route::get('/forum-approvals', [AdminForumController::class, 'getForumApprovals']); // pending forums
        });

        Route::patch('/disable/{id}/store', [StoreController::class, 'disableStore']);
        Route::patch('/disable/{id}/case', [CaseModelController::class, 'disableCase']);
        Route::patch('/disable/{id}/clinic', [ClinicController::class, 'disableClinic']);
        Route::patch('/disable/{id}/farm', [FarmController::class, 'disableFarm']);
        Route::patch('/disable/{id}/product', [ProductsController::class, 'disableProduct']);
        Route::patch('/disable/{id}/pet', [PetController::class, 'disablePet']);

        Route::get('/logout', [AdminAuthController::class, 'adminLogout']);
        Route::match(['post', 'patch'], '/update-details', [AdminAuthController::class, 'updateAdminDetails']);

        Route::get('/get-count', [AdminAnalyticsController::class, 'adminGetUserCount']);
        Route::get('/refresh-count-cache', [AdminAnalyticsController::class, 'refreshCountsCache']);

        Route::get('/admin-activity', [AdminActivityController::class, 'getAdminActivity']);
        Route::get('/search-admin-activity', [AdminActivityController::class, "searchAdminActivity"]);

        Route::get('/admin-notification', [AdminNotification::class, 'getAdminNotification']);
        Route::get('/search-admin-notification', [AdminNotification::class, "searchAdminNotification"]);

        Route::get('/get-all-veterinary-paraprofessional', [AdminManageUserController::class, 'getAllVeterinaryParaprofessional']);
        Route::get('/get-all-veterinary-doctor', [AdminManageUserController::class, 'getAllVeterinaryDoctors']);
        Route::get('/get-all-veterinary-clinic', [AdminManageUserController::class, 'getAllVeterinaryClinics']);

        Route::get('/search-veterinary-paraprofessionals', [AdminManageUserController::class, 'searchVeterinaryParaprofessionals']);
        Route::get('/search-veterinary-doctors', [AdminManageUserController::class, 'searchVeterinaryDoctors']);
        Route::get('/search-veterinary-clinics', [AdminManageUserController::class, 'searchVeterinaryClinics']);

        Route::get('/get-vendor', [AdminManageUserController::class, 'getVendor']);
        Route::get('/get-other', [AdminManageUserController::class, 'getOther']);
        Route::get('/get-pet-owner', [AdminManageUserController::class, 'getPetOwner']);
        Route::get('/get-livestock-farmer', [AdminManageUserController::class, 'getLifeStockFarmer']);
        Route::get('/get-stores', [AdminManageUserController::class, 'getStores']);
        Route::get('/get-products', [AdminManageUserController::class, 'getProducts']);
        Route::get('/get-farms', [AdminManageUserController::class, 'getFarms']);
        Route::get('/get-pets', [AdminManageUserController::class, 'getPets']);
        Route::get('/get-clinics', [AdminManageUserController::class, 'getClinics']);
        Route::get('/get-cases', [AdminManageUserController::class, 'getCases']);

        Route::get('/search-stores', [AdminManageUserController::class, 'searchStores']);
        Route::get('/search-products', [AdminManageUserController::class, 'searchProducts']);
        Route::get('/search-clinics', [AdminManageUserController::class, 'searchClinics']);

        // Route::get('get-promotion-approvals', [AdminManageUserController::class, 'getPromotionApprovals']);
        // Route::get('get-subscription-approvals', [AdminManageUserController::class, 'getSubscriptionApprovals']);
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
