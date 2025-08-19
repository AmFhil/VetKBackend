<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Example public route
Route::get('/hello', function () {
    return response()->json(['message' => 'Hello, API!']);
});

// Example protected route (requires auth:api middleware)
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return response()->json($request->user());
});

// app routes
Route::prefix('api/v3')->group(function () {

});
