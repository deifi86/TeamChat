<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelJoinRequest;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOf($company)) {
            return response()->json([
                'message' => 'You are not a member of this company',
            ], 403);
        }

        $channels = $company->channels()
            ->withCount('members')
            ->get()
            ->map(fn ($channel) => [
                'id' => $channel->id,
                'name' => $channel->name,
                'description' => $channel->description,
                'is_private' => $channel->is_private,
                'is_member' => $user->isMemberOfChannel($channel),
                'members_count' => $channel->members_count,
                'has_pending_request' => $channel->pendingJoinRequests()
                    ->where('user_id', $user->id)
                    ->exists(),
            ]);

        return response()->json(['channels' => $channels]);
    }

    public function store(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($company)) {
            return response()->json([
                'message' => 'Only admins can create channels',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_private' => ['boolean'],
        ]);

        $channel = $company->channels()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_private' => $validated['is_private'] ?? true,
            'created_by' => $user->id,
        ]);

        // Ersteller hinzufügen
        $channel->addMember($user);

        // Bei öffentlichem Channel: Alle Firmenmitglieder hinzufügen
        if (!$channel->is_private) {
            $company->users->each(fn ($member) => $channel->addMember($member, $user));
        }

        return response()->json([
            'message' => 'Channel created',
            'channel' => [
                'id' => $channel->id,
                'name' => $channel->name,
                'description' => $channel->description,
                'is_private' => $channel->is_private,
                'members_count' => $channel->users()->count(),
            ],
        ], 201);
    }

    public function show(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOf($channel->company)) {
            return response()->json([
                'message' => 'You are not a member of this company',
            ], 403);
        }

        return response()->json([
            'channel' => [
                'id' => $channel->id,
                'company_id' => $channel->company_id,
                'name' => $channel->name,
                'description' => $channel->description,
                'is_private' => $channel->is_private,
                'members_count' => $channel->users()->count(),
                'created_at' => $channel->created_at->toIso8601String(),
            ],
            'is_member' => $user->isMemberOfChannel($channel),
        ]);
    }

    public function update(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($channel->company)) {
            return response()->json([
                'message' => 'Only admins can update channels',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_private' => ['sometimes', 'boolean'],
        ]);

        $wasPrivate = $channel->is_private;
        $channel->update($validated);

        // Wenn von privat zu öffentlich gewechselt: Alle Firmenmitglieder hinzufügen
        if ($wasPrivate && isset($validated['is_private']) && !$validated['is_private']) {
            $channel->company->users->each(fn ($member) => $channel->addMember($member, $user));
        }

        return response()->json([
            'message' => 'Channel updated',
            'channel' => [
                'id' => $channel->id,
                'name' => $channel->name,
                'description' => $channel->description,
                'is_private' => $channel->is_private,
            ],
        ]);
    }

    public function destroy(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($channel->company)) {
            return response()->json([
                'message' => 'Only admins can delete channels',
            ], 403);
        }

        // Prüfen ob letzter Channel
        if ($channel->company->channels()->count() === 1) {
            return response()->json([
                'message' => 'Cannot delete the last channel of a company',
            ], 422);
        }

        $channel->delete();

        return response()->json([
            'message' => 'Channel deleted',
        ]);
    }

    public function members(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOfChannel($channel)) {
            return response()->json([
                'message' => 'You are not a member of this channel',
            ], 403);
        }

        $members = $channel->users()
            ->get()
            ->map(fn ($member) => [
                'id' => $member->id,
                'username' => $member->username,
                'avatar_url' => $member->avatar_url,
                'status' => $member->status,
                'joined_at' => $member->pivot->joined_at,
            ]);

        return response()->json(['members' => $members]);
    }

    public function addMember(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($channel->company)) {
            return response()->json([
                'message' => 'Only admins can add members',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $userToAdd = User::find($validated['user_id']);

        if (!$userToAdd->isMemberOf($channel->company)) {
            return response()->json([
                'message' => 'User must be a member of the company first',
            ], 422);
        }

        if ($userToAdd->isMemberOfChannel($channel)) {
            return response()->json([
                'message' => 'User is already a member of this channel',
            ], 422);
        }

        $channel->addMember($userToAdd, $user);

        return response()->json([
            'message' => 'Member added to channel',
        ]);
    }

    public function removeMember(Request $request, Channel $channel, int $userId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($channel->company)) {
            return response()->json([
                'message' => 'Only admins can remove members',
            ], 403);
        }

        $channel->users()->detach($userId);

        return response()->json([
            'message' => 'Member removed from channel',
        ]);
    }

    public function requestJoin(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOf($channel->company)) {
            return response()->json([
                'message' => 'You must be a member of the company first',
            ], 403);
        }

        if ($user->isMemberOfChannel($channel)) {
            return response()->json([
                'message' => 'You are already a member of this channel',
            ], 422);
        }

        if ($channel->pendingJoinRequests()->where('user_id', $user->id)->exists()) {
            return response()->json([
                'message' => 'You already have a pending request',
            ], 422);
        }

        $validated = $request->validate([
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $joinRequest = ChannelJoinRequest::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'message' => $validated['message'] ?? null,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Join request submitted',
            'request' => [
                'id' => $joinRequest->id,
                'status' => $joinRequest->status,
                'created_at' => $joinRequest->created_at->toIso8601String(),
            ],
        ], 201);
    }

    public function joinRequests(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($channel->company)) {
            return response()->json([
                'message' => 'Only admins can view join requests',
            ], 403);
        }

        $requests = $channel->pendingJoinRequests()
            ->with('user:id,username,email,avatar_path')
            ->get()
            ->map(fn ($req) => [
                'id' => $req->id,
                'user' => [
                    'id' => $req->user->id,
                    'username' => $req->user->username,
                    'email' => $req->user->email,
                    'avatar_url' => $req->user->avatar_url,
                ],
                'message' => $req->message,
                'created_at' => $req->created_at->toIso8601String(),
            ]);

        return response()->json(['requests' => $requests]);
    }

    public function handleJoinRequest(Request $request, Channel $channel, ChannelJoinRequest $joinRequest): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($channel->company)) {
            return response()->json([
                'message' => 'Only admins can handle join requests',
            ], 403);
        }

        if ($joinRequest->channel_id !== $channel->id) {
            return response()->json([
                'message' => 'Request does not belong to this channel',
            ], 404);
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
        ]);

        if ($validated['action'] === 'approve') {
            $joinRequest->approve($user);
            $message = 'Request approved';
        } else {
            $joinRequest->reject($user);
            $message = 'Request rejected';
        }

        return response()->json(['message' => $message]);
    }
}
