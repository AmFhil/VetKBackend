<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(Request $request): void
    {
        //
        Relation::morphMap([
            'users' => \App\Models\User::class, // Sanctum will use this
        ]);

        if (!empty(env('NGROK_URL')) && $request->server->has('HTTP_X_ORIGINAL_HOST')) {
        $this->app['url']->forceRootUrl(env('NGROK_URL'));

        if (env('APP_ENV') === 'production') {
            URL::forceScheme('https');
        }
    }
    }
}
