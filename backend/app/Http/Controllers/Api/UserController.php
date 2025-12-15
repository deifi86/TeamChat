<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct(
        private ImageCompressionService $imageService
    ) {}

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => ['sometimes', 'string', 'min:3', 'max:100'],
            'status_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    public function updateStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'status' => ['required', 'in:available,busy,away,offline'],
            'status_text' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $user->update($validated);

        return response()->json([
            'message' => 'Status updated',
            'status' => $user->status,
            'status_text' => $user->status_text,
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $user = $request->user();
        $file = $request->file('avatar');

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $filename = 'avatars/' . $user->id . '_' . Str::random(10) . '.' . $file->extension();

        if ($this->imageService->isCompressible($file->getMimeType())) {
            $result = $this->imageService->compressUploadedFile($file, $filename, 'public');
            $path = $result['path'];
        } else {
            $path = $file->storeAs('avatars', basename($filename), 'public');
        }

        $user->update(['avatar_path' => $path]);

        return response()->json([
            'message' => 'Avatar uploaded',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return response()->json([
            'message' => 'Avatar deleted',
            'avatar_url' => $user->fresh()->avatar_url,
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $query = $validated['q'];
        $currentUserId = $request->user()->id;

        $users = User::where('id', '!=', $currentUserId)
            ->where(function ($q) use ($query) {
                $q->where('username', 'LIKE', "%{$query}%")
                    ->orWhere('email', 'LIKE', "%{$query}%");
            })
            ->limit(20)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'status' => $user->status,
            ]);

        return response()->json([
            'users' => $users,
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'avatar_url' => $user->avatar_url,
            'status' => $user->status,
            'status_text' => $user->status_text,
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
