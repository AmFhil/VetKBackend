<?php

namespace App\Providers;

use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\ServiceProvider;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
        Broadcast::routes(['middleware' => ['auth:sanctum']]); // Use Sanctum

        // For admins (custom guard)
        Broadcast::routes(['middleware' => ['auth:admin'], 'prefix' => 'admin-broadcasting']);

        require base_path('routes/channels.php');
    }
}
