<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChannelController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\DirectConversationController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\ReactionController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

// Protected Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::prefix('user')->group(function () {
        Route::put('profile', [UserController::class, 'updateProfile']);
        Route::put('status', [UserController::class, 'updateStatus']);
        Route::post('avatar', [UserController::class, 'uploadAvatar']);
        Route::delete('avatar', [UserController::class, 'deleteAvatar']);
    });

    Route::get('users/search', [UserController::class, 'search']);

    // Companies
    Route::get('my-companies', [CompanyController::class, 'myCompanies']);
    Route::get('companies/search', [CompanyController::class, 'search']);

    Route::prefix('companies')->group(function () {
        Route::post('/', [CompanyController::class, 'store']);
        Route::get('{company}', [CompanyController::class, 'show']);
        Route::put('{company}', [CompanyController::class, 'update']);
        Route::post('{company}/join', [CompanyController::class, 'join']);
        Route::post('{company}/leave', [CompanyController::class, 'leave']);
        Route::post('{company}/logo', [CompanyController::class, 'uploadLogo']);
        Route::get('{company}/members', [CompanyController::class, 'members']);
        Route::put('{company}/members/{userId}', [CompanyController::class, 'updateMember']);
        Route::delete('{company}/members/{userId}', [CompanyController::class, 'removeMember']);
        Route::get('{company}/channels', [ChannelController::class, 'index']);
        Route::post('{company}/channels', [ChannelController::class, 'store']);
    });

    // Channels
    Route::prefix('channels')->group(function () {
        Route::get('{channel}', [ChannelController::class, 'show']);
        Route::put('{channel}', [ChannelController::class, 'update']);
        Route::delete('{channel}', [ChannelController::class, 'destroy']);
        Route::get('{channel}/members', [ChannelController::class, 'members']);
        Route::post('{channel}/members', [ChannelController::class, 'addMember']);
        Route::delete('{channel}/members/{userId}', [ChannelController::class, 'removeMember']);
        Route::post('{channel}/join-request', [ChannelController::class, 'requestJoin']);
        Route::get('{channel}/join-requests', [ChannelController::class, 'joinRequests']);
        Route::put('{channel}/join-requests/{joinRequest}', [ChannelController::class, 'handleJoinRequest']);

        // Channel Messages
        Route::get('{channel}/messages', [MessageController::class, 'channelMessages']);
        Route::post('{channel}/messages', [MessageController::class, 'storeChannelMessage']);
        Route::post('{channel}/typing', [MessageController::class, 'typing']);
    });

    // Message CRUD
    Route::prefix('messages')->group(function () {
        Route::put('{message}', [MessageController::class, 'update']);
        Route::delete('{message}', [MessageController::class, 'destroy']);
    });

    // Direct Conversations
    Route::prefix('conversations')->group(function () {
        Route::get('/', [DirectConversationController::class, 'index']);
        Route::post('/', [DirectConversationController::class, 'store']);
        Route::get('{conversation}', [DirectConversationController::class, 'show']);
        Route::delete('{conversation}', [DirectConversationController::class, 'destroy']);
        Route::post('{conversation}/accept', [DirectConversationController::class, 'accept']);
        Route::post('{conversation}/reject', [DirectConversationController::class, 'reject']);
        Route::get('{conversation}/messages', [DirectConversationController::class, 'messages']);
        Route::post('{conversation}/messages', [DirectConversationController::class, 'sendMessage']);
        Route::post('{conversation}/typing', [DirectConversationController::class, 'typing']);
    });

    // Emojis
    Route::get('emojis', [ReactionController::class, 'emojis']);

    // Reactions
    Route::prefix('messages/{message}/reactions')->group(function () {
        Route::get('/', [ReactionController::class, 'index']);
        Route::post('/', [ReactionController::class, 'store']);
        Route::post('/toggle', [ReactionController::class, 'toggle']);
        Route::delete('/{emoji}', [ReactionController::class, 'destroy']);
    });
});
