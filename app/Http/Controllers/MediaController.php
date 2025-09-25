<?php

namespace App\Http\Controllers;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    //
    public function addMedia(Request $request)
    {
        $request->validate([
            'file' => 'required|array',
            'file.*' => 'required|image|mimes:jpeg,png,jpg|max:5120',
        ]);

        // check if user is veterinaryDoctor || veterinaryClinic || veterinaryParaprofessional || vendor
        $user = auth()->user();
        if ($user->activeRole?->name !== 'veterinary_doctor' && $user->activeRole?->name !== 'veterinary_clinic' && $user->activeRole?->name !== 'veterinary_paraprofessional' && $user->activeRole?->name !== 'vendor')
        {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        foreach ($request->file('file') as $file){
            $path = $file->store('user_media', 'public');

            $media = Media::create([
                'user_id' => $user->id,
                'file_path' => $path,
                'type' => 'image'
            ]);
        }

        return response()->json(['message' => 'Media uploaded successfully', 'data' => $media], 201);
    }

    // get media for authenticated user
    public function getMedia(){
        $user = auth()->user();
        $media = Media::where('user_id', $user->id)->get();

        return response()->json(['message' => 'Media fetched successfully', 'data' => $media], 200);
    }

    // delete media by id
    public function deleteMedia($id){
        $user = auth()->user();
        $media = Media::where('id', $id)->where('user_id', $user->id)->first();

        if (!$media){
            return response()->json(['message' => 'Media not found'], 404);
        }

        Storage::disk('public')->delete($media->file_path);
        $media->delete();

        return response()->json(['message' => 'Media deleted successfully'], 200);
    }
}
