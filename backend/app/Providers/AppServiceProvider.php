<?php

namespace App\Providers;

use App\Services\ImageCompressionService;
use App\Services\MessageEncryptionService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ImageCompressionService::class);
        $this->app->singleton(MessageEncryptionService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
