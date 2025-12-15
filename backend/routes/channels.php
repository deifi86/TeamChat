<?php

use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\Company;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Private Channel für User-spezifische Events (Benachrichtigungen, Status)
Broadcast::channel('user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Private Channel für Channel-Chat
Broadcast::channel('channel.{channelId}', function ($user, $channelId) {
    $channel = Channel::find($channelId);
    if (!$channel) {
        return false;
    }
    return $user->isMemberOfChannel($channel);
});

// Private Channel für Direct Conversation
Broadcast::channel('conversation.{conversationId}', function ($user, $conversationId) {
    $conversation = DirectConversation::find($conversationId);
    if (!$conversation) {
        return false;
    }
    return $conversation->hasUser($user);
});

// Presence Channel für Online-Status in einer Firma
Broadcast::channel('company.{companyId}', function ($user, $companyId) {
    $company = Company::find($companyId);
    if (!$company || !$user->isMemberOf($company)) {
        return false;
    }

    return [
        'id' => $user->id,
        'username' => $user->username,
        'avatar_url' => $user->avatar_url,
        'status' => $user->status,
    ];
});
