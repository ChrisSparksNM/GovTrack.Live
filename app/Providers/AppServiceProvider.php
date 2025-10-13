<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register AI services
        $this->app->singleton(\App\Services\AI\IntelligentQueryService::class, function ($app) {
            return new \App\Services\AI\IntelligentQueryService(
                $app->make(\App\Services\AnthropicService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
