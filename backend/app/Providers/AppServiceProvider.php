<?php

namespace App\Providers;

use App\Models\Channel;
use App\Models\DirectConversation;
use App\Services\EmojiService;
use App\Services\FileService;
use App\Services\ImageCompressionService;
use App\Services\MessageEncryptionService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->app->singleton(EmojiService::class);
        $this->app->singleton(FileService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Polymorphic Type Mapping
        Relation::morphMap([
            'channel' => Channel::class,
            'direct' => DirectConversation::class,
        ]);

        // Configure Rate Limiting
        $this->configureRateLimiting();
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // Standard API Rate Limit
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Auth Endpoints (strenger)
        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Message Sending
        RateLimiter::for('messages', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
        });

        // File Uploads
        RateLimiter::for('uploads', function (Request $request) {
            return Limit::perHour(50)->by($request->user()?->id ?: $request->ip());
        });
    }
}
