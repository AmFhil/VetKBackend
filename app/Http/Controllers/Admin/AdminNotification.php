<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification as ModelsAdminNotification;
use Illuminate\Http\Request;

class AdminNotification extends Controller
{
    //
    public function getAdminNotification(Request $request)
    {
        $admin = auth('admin')->user();
        if (!$admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }


        $adminId = $request->input('admin_id', $admin->id);


        $notification = ModelsAdminNotification::where('admin_id', $adminId)
            ->orderBy('id', 'desc')
            ->get();


        return response()->json(['notification' => $notification], 200);
    }

    public function getAdminNotificationByFilter(Request $request)
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


        $query = ModelsAdminNotification::where('admin_id', $adminId);


        if ($request->filled('name')) {
            $query->where('title', 'like', '%' . $request->input('name') . '%')
                ->orWhere('content', 'like', '%' . $request->input('name') . '%')
                ->orWhere('type', 'like', '%' . $request->input('name') . '%');
        }


        $activities = $query->orderBy('id', 'desc')->get();


        return response()->json(['message' => 'Notification Retrieved', 'activities' => $activities], 200);
    }

    public function searchAdminNotification(Request $request)
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


        $activities = ModelsAdminNotification::where('admin_id', $adminId)
            ->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('type', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%")
                    ->orWhere('meta', 'like', "%{$search}%");
            })
            ->orderBy('id', 'desc')
            ->get();


        return response()->json(['activities' => $activities], 200);
    }
}
