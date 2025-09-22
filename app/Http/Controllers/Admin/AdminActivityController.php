<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use Illuminate\Http\Request;

class AdminActivityController extends Controller
{
    /**
     * Return all activities for an admin.
     * If admin_id is not provided, falls back to the authenticated admin.
     */
    public function getAdminActivity(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }


        $adminId = $request->input('admin_id', $admin->id);


        $activities = AdminActivity::where('admin_id', $adminId)
            ->orderBy('id', 'desc')
            ->get();


        return response()->json(['activities' => $activities], 200);
    }

    /**
     * Filter activities by title ("name" query param).
     * Returns [] when no admin authenticated.
     */
    public function getAdminActivityByFilter(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }


        $request->validate([
            'admin_id' => 'nullable|integer',
            'name' => 'nullable|string',
        ]);


        $adminId = $request->input('admin_id', $admin->id);


        $query = AdminActivity::where('admin_id', $adminId);


        if ($request->filled('name')) {
            $query->where('title', 'like', '%' . $request->input('name') . '%');
        }


        $activities = $query->orderBy('id', 'desc')->get();


        return response()->json(['activities' => $activities], 200);
    }


    /**
     * Search activities across multiple columns.
     * Accepts `query` string and optional `admin_id`.
     */
    public function searchAdminActivity(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }


        $search = $request->input('query', '');
        if ($search === '') {
            return response()->json(['activities' => []], 200);
        }


        $adminId = $request->input('admin_id', $admin->id);


        $activities = AdminActivity::where('admin_id', $adminId)
            ->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('detail', 'like', "%{$search}%")
                    ->orWhere('action', 'like', "%{$search}%")
                    ->orWhere('meta', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->get();


        return response()->json(['activities' => $activities], 200);
    }
}
