<?php

namespace App\Providers;

use App\Models\Channel;
use App\Models\DirectConversation;
use App\Services\EmojiService;
use App\Services\FileService;
use App\Services\ImageCompressionService;
use App\Services\MessageEncryptionService;
use Illuminate\Database\Eloquent\Relations\Relation;
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
    }
}
