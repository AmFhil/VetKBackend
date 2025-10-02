<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdsPromotion;
use App\Models\CaseModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Models\VeterinaryParaprofessional;
use App\Models\VeterinaryDoctor;
use App\Models\VeterinaryClinic;
use App\Models\Vendor;
use App\Models\Others;
use App\Models\Pet_Owner;
use App\Models\livestock_farmer;
use App\Models\Store;
use App\Models\Products;
use App\Models\Clinic;
use App\Models\Farm;
use App\Models\Pet;
use App\Models\User;
use App\Models\PromotionPlan;
use App\Models\UserSubscriptions;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AdminManageUserController extends Controller
{
    //
     // require admin for management endpoints
    protected function guardAdmin()
    {
        $admin = auth('admin')->user();
        if (!$admin) abort(response()->json(['message' => 'Unauthorized'], 403));
        return $admin;
    }

    // paginated list of veterinary paraprofessionals
    public function getAllVeterinaryParaprofessional()
    {
        $this->guardAdmin();

        $veterinaryParaprofessionals = VeterinaryParaprofessional::with([
                'user:id,email,first_name,last_name,phone_num,state,country',
                'ratings:id,rateable_id,rating,comment',
                'user.profile:id,user_id,profile_image'
            ])
            ->select('id','user_id','name_of_institution','specialty','list_them','contact_num','address','longitude','latitude','role','availability','created_at')
            ->latest()
            ->paginate(10);

        $veterinaryParaprofessionals->getCollection()->transform(function ($item) {
            $item->average_rating = method_exists($item, 'averageRating') ? $item->averageRating() : null;
            return $item;
        });

        return response()->json(['veterinary_paraprofessionals' => $veterinaryParapessionals ?? $veterinaryParaprofessionals], 200);
    }

    public function getAllVeterinaryDoctors()
    {
        $this->guardAdmin();

        $veterinaryDoctors = VeterinaryDoctor::with([
                'user:id,email,first_name,last_name,phone_num,state,country',
                'ratings:id,rateable_id,rating,comment',
                'user.profile:id,user_id,profile_image'
            ])
            ->select('id','user_id','specialty','list_them','address','longitude','latitude','role','availability','created_at')
            ->latest()
            ->paginate(10);

        $veterinaryDoctors->getCollection()->transform(function ($d) {
            $d->average_rating = method_exists($d, 'averageRating') ? $d->averageRating() : null;
            return $d;
        });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    public function getAllVeterinaryClinics()
    {
        $this->guardAdmin();

        $clinics = VeterinaryClinic::with([
                'user:id,email,first_name,last_name,phone_num,state,country',
                'ratings:id,rateable_id,rating,comment',
                'user.profile:id,user_id,profile_image'
            ])
            ->select('id','user_id','name_of_clinic','specialty','list_them','contact_num','address','longitude','latitude','role','availability','created_at')
            ->inRandomOrder()
            ->paginate(10);

        $clinics->getCollection()->transform(function ($c) {
            $c->average_rating = method_exists($c, 'averageRating') ? $c->averageRating() : null;
            return $c;
        });

        return response()->json(['clinics' => $clinics], 200);
    }

    public function searchVeterinaryParaprofessionals(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);

        $query = $request->input('query');

        if (!$query) {
            return response()->json(['veterinary_paraprofessionals' => []], 200);
        }

        $veterinaryParaprofessionals = VeterinaryParaprofessional::where('specialty', 'like', "%$query%")
            ->orWhere('name_of_institution', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_institution', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

        // add average rating to each veterinary paraprofessional
        $veterinaryParaprofessionals->getCollection()->transform(function ($veterinaryParaprofessional) {
            $veterinaryParaprofessional->average_rating = $veterinaryParaprofessional->averageRating();
            return $veterinaryParaprofessional;
        });

        return response()->json(['veterinary_paraprofessionals' => $veterinaryParaprofessionals], 200);
    }

    public function searchVeterinaryDoctors(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);

        $query = $request->input('query');
        if (!$query) {
            return response()->json(['veterinary_doctors' => []], 200);
        }

        $veterinaryDoctors = VeterinaryDoctor::where('specialty', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'specialty', 'list_them', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

        // add average rating to each veterinary doctor
        $veterinaryDoctors->getCollection()->transform(function ($doctor) {
            $doctor->average_rating = $doctor->averageRating();
            return $doctor;
        });

        return response()->json(['veterinary_doctors' => $veterinaryDoctors], 200);
    }

    public function searchVeterinaryClinics(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) return response()->json(['message' => 'Unauthorized']);

        $query = $request->input('query', '');

        if (!$query) {
            return response()->json(['clinics' => []], 200);
        }

        $clinics = VeterinaryClinic::where('name_of_clinic', 'like', "%$query%")
            ->orWhere('specialty', 'like', "%$query%")
            ->orWhere('list_them', 'like', "%$query%")
            ->orWhere('address', 'like', "%$query%")
            ->with(['user:id,email,first_name,last_name,phone_num,state,country', 'ratings:id,rateable_id,rating,comment', 'user.profile:id,user_id,profile_image'])
            ->select('id', 'user_id', 'name_of_clinic', 'specialty', 'list_them', 'contact_num', 'address', 'longitude', 'latitude', 'role', 'availability')
            ->paginate(10);

        // add average rating to each veterinary clinic
        $clinics->getCollection()->transform(function ($clinic) {
            $clinic->average_rating = $clinic->averageRating();
            return $clinic;
        });

        return response()->json(['clinics' => $clinics], 200);
    }

    public function getVendor()
    {
        $this->guardAdmin();
        $vendor = Vendor::with(['user:id,email,first_name,last_name,phone_num,state,country','user.profile:id,user_id,profile_image'])
            ->select('id','user_id','role','created_at')
            ->paginate(20);

        return response()->json($vendor, 200);
    }

    public function getOther()
    {
        $this->guardAdmin();
        $other = Others::with(['user:id,email,first_name,last_name,phone_num,state,country','user.profile:id,user_id,profile_image'])
            ->select('id','user_id','role','created_at')
            ->paginate(20);

        return response()->json($other, 200);
    }

    public function getPetOwner()
    {
        $this->guardAdmin();
        $pet_owner = Pet_Owner::with(['user:id,email,first_name,last_name,phone_num,state,country','user.profile:id,user_id,profile_image'])
            ->select('id','user_id','role','created_at')
            ->paginate(20);

        return response()->json($pet_owner, 200);
    }

    public function getLifeStockFarmer()
    {
        $this->guardAdmin();
        $lifeStock = livestock_farmer::with(['user:id,email,first_name,last_name,phone_num,state,country','user.profile:id,user_id,profile_image'])
            ->select('id','user_id','role','created_at')
            ->paginate(20);

        return response()->json($lifeStock, 200);
    }

    public function getStores()
    {
        $this->guardAdmin();
        $stores = Store::with('ratings:id,rating,comment,rateable_id')->paginate(10);

        $stores->getCollection()->transform(function ($store) {
            $store->average_rating = method_exists($store, 'averageRating') ? $store->averageRating() : null;
            return $store;
        });

        return response()->json(['stores' => $stores], 200);
    }

    public function searchStores(Request $request)
    {
        $this->guardAdmin();
        $query = $request->input('query');
        if (!$query) return response()->json(['stores' => []], 200);

        $stores = Store::where('store_name','like',"%$query%")
            ->orWhere('location','like',"%$query%")
            ->with('ratings:id,rating,comment,rateable_id')
            ->paginate(10);

        $stores->getCollection()->transform(function ($store) {
            $store->average_rating = method_exists($store, 'averageRating') ? $store->averageRating() : null;
            return $store;
        });

        return response()->json(['stores' => $stores], 200);
    }

    public function getProducts()
    {
        $this->guardAdmin();
        $products = Products::with('ratings:id,rating,comment,rateable_id','tags','user','user.profile','user.activeRoleData')
            ->latest()
            ->paginate(10);

        $products->getCollection()->transform(function ($p) {
            $p->average_rating = method_exists($p, 'averageRating') ? $p->averageRating() : null;
            return $p;
        });

        return response()->json(['products' => $products], 200);
    }

    public function searchProducts(Request $request)
    {
        $this->guardAdmin();
        $keyword = $request->input('keyword', '');
        $category = $request->input('category', '');

        if (!$keyword && !$category) return response()->json(['products' => []], 200);

        $query = Products::with('ratings:id,rating,comment,rateable_id','tags');

        if ($category) {
            $query->where('category','like',"%{$category}%");
        }

        if ($keyword) {
            $query->where(function ($q) use ($keyword) {
                $q->where('description','like',"%{$keyword}%")
                  ->orWhere('product_name','like',"%{$keyword}%");
            });
        }

        $products = $query->paginate(10);

        $products->getCollection()->transform(function ($p) {
            $p->average_rating = method_exists($p, 'averageRating') ? $p->averageRating() : null;
            return $p;
        });

        return response()->json(['products' => $products], 200);
    }

    public function getFarms()
    {
        $this->guardAdmin();
        $farms = Farm::with('user')->latest()->paginate(10);
        return response()->json($farms);
    }

    public function getPets()
    {
        $this->guardAdmin();
        $pets = Pet::with('user')->latest()->paginate(10);
        return response()->json($pets);
    }

     // search clinic
    public function searchClinics(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        $query = $request->input('query', '');

        if (!$query) {
            return response()->json(['clinics' => []], 200);
        }

        $clinics = Clinic::where('clinic_name', 'like', "%$query%")
            ->orWhereJsonContains('clinic_speciality', $query)
            ->orWhere('location', 'like', "%$query%")
            ->paginate(10);

        return response()->json(['clinics' => $clinics], 200);
    }

    // get clinic
    public function getClinic()
    {
        // check if user is authenticated
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        };

        //get clinic
        $clinics = Clinic::latest()
            ->paginate(10);

        return response()->json(['clinics' => $clinics], 200);
    }

    // get cases
    public function getCases()
    {
        $this->guardAdmin();

        $cases = CaseModel::with('user', 'user.activeRole', 'user.profile', 'comment.replies', 'comment')
            ->latest()
            ->paginate(10);

        return response()->json($cases);
    }


    public function getPromotionApprovals()
    {
        $this->guardAdmin();

        $promotions = AdsPromotion::with('user','promotionPlan','user.profile','user.activeRole','user.activeRoleData','product.ratings', 'product',)
            ->latest()
            ->paginate(10);

        $promotions->getCollection()->transform(function ($promo) {
            $promo->status = ($promo->end_date && Carbon::parse($promo->end_date)->isFuture()) ? 'active' : 'expired';
            return $promo;
        });

        return response()->json($promotions);
    }

    public function getSubscriptionApprovals()
    {
        $this->guardAdmin();

        $subscriptions = UserSubscriptions::with('user','subscription','user.profile','user.activeRole','user.activeRoleData','ratings')
            ->latest()
            ->paginate(10);

        $subscriptions->getCollection()->transform(function ($sub) {
            $sub->status = ($sub->end_date && Carbon::parse($sub->end_date)->isFuture()) ? 'active' : 'expired';
            return $sub;
        });

        return response()->json($subscriptions);
    }
}
