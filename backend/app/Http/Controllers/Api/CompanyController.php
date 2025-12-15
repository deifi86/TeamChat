<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Services\ImageCompressionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function __construct(
        private ImageCompressionService $imageService
    ) {}

    public function myCompanies(Request $request): JsonResponse
    {
        $user = $request->user();

        $companies = $user->companies()
            ->withCount('members')
            ->with('owner:id,username,avatar_path')
            ->get()
            ->map(fn ($company) => $this->formatCompany($company, $user));

        return response()->json(['companies' => $companies]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $query = $validated['q'];

        $companies = Company::where('name', 'LIKE', "%{$query}%")
            ->orWhere('slug', 'LIKE', "%{$query}%")
            ->withCount('members')
            ->limit(20)
            ->get()
            ->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'slug' => $company->slug,
                'logo_url' => $company->logo_url,
                'members_count' => $company->members_count,
            ]);

        return response()->json(['companies' => $companies]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'join_password' => ['required', 'string', 'min:6', 'max:100'],
        ]);

        $user = $request->user();

        $company = Company::create([
            'name' => $validated['name'],
            'join_password' => Hash::make($validated['join_password']),
            'owner_id' => $user->id,
        ]);

        // Ersteller als Admin hinzufügen
        $company->addMember($user, 'admin');

        // Default-Channel erstellen
        $channel = $company->channels()->create([
            'name' => 'Allgemein',
            'description' => 'Allgemeiner Kanal für alle Mitglieder',
            'is_private' => false,
            'created_by' => $user->id,
        ]);
        $channel->addMember($user);

        return response()->json([
            'message' => 'Company created',
            'company' => $this->formatCompany($company->fresh()->load('owner'), $user),
        ], 201);
    }

    public function show(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOf($company)) {
            return response()->json([
                'message' => 'You are not a member of this company',
            ], 403);
        }

        $company->load('owner:id,username,avatar_path');
        $company->loadCount('members');

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
            ]);

        return response()->json([
            'company' => $this->formatCompany($company, $user),
            'channels' => $channels,
        ]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if ($company->owner_id !== $user->id) {
            return response()->json([
                'message' => 'Only the owner can update this company',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'min:2', 'max:255'],
            'join_password' => ['sometimes', 'string', 'min:6', 'max:100'],
        ]);

        if (isset($validated['join_password'])) {
            $validated['join_password'] = Hash::make($validated['join_password']);
        }

        $company->update($validated);

        return response()->json([
            'message' => 'Company updated',
            'company' => $this->formatCompany($company->fresh()->load('owner'), $user),
        ]);
    }

    public function join(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if ($user->isMemberOf($company)) {
            return response()->json([
                'message' => 'You are already a member of this company',
            ], 422);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (!$company->checkJoinPassword($validated['password'])) {
            return response()->json([
                'message' => 'Invalid password',
            ], 422);
        }

        $company->addMember($user, 'user');

        // Zu allen öffentlichen Channels hinzufügen
        $company->channels()
            ->where('is_private', false)
            ->each(fn ($channel) => $channel->addMember($user));

        return response()->json([
            'message' => 'Successfully joined company',
            'company' => $this->formatCompany($company->fresh()->load('owner'), $user),
        ]);
    }

    public function leave(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOf($company)) {
            return response()->json([
                'message' => 'You are not a member of this company',
            ], 422);
        }

        if ($company->owner_id === $user->id) {
            return response()->json([
                'message' => 'Owner cannot leave the company. Transfer ownership first.',
            ], 422);
        }

        $company->removeMember($user);

        return response()->json([
            'message' => 'Successfully left company',
        ]);
    }

    public function members(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOf($company)) {
            return response()->json([
                'message' => 'You are not a member of this company',
            ], 403);
        }

        $members = $company->users()
            ->get()
            ->map(fn ($member) => [
                'id' => $member->id,
                'username' => $member->username,
                'email' => $member->email,
                'avatar_url' => $member->avatar_url,
                'status' => $member->status,
                'role' => $member->pivot->role,
                'joined_at' => $member->pivot->joined_at,
                'is_owner' => $member->id === $company->owner_id,
            ]);

        return response()->json(['members' => $members]);
    }

    public function updateMember(Request $request, Company $company, int $userId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($company)) {
            return response()->json([
                'message' => 'Only admins can change member roles',
            ], 403);
        }

        if ($userId === $company->owner_id) {
            return response()->json([
                'message' => 'Owner role cannot be changed',
            ], 422);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:admin,user'],
        ]);

        $company->users()->updateExistingPivot($userId, [
            'role' => $validated['role'],
        ]);

        return response()->json([
            'message' => 'Member role updated',
        ]);
    }

    public function removeMember(Request $request, Company $company, int $userId): JsonResponse
    {
        $user = $request->user();

        if (!$user->isAdminOf($company)) {
            return response()->json([
                'message' => 'Only admins can remove members',
            ], 403);
        }

        if ($userId === $company->owner_id) {
            return response()->json([
                'message' => 'Owner cannot be removed',
            ], 422);
        }

        $memberToRemove = User::find($userId);
        if (!$memberToRemove || !$memberToRemove->isMemberOf($company)) {
            return response()->json([
                'message' => 'User is not a member of this company',
            ], 404);
        }

        $company->removeMember($memberToRemove);

        return response()->json([
            'message' => 'Member removed',
        ]);
    }

    public function uploadLogo(Request $request, Company $company): JsonResponse
    {
        $user = $request->user();

        if ($company->owner_id !== $user->id) {
            return response()->json([
                'message' => 'Only the owner can update the logo',
            ], 403);
        }

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $file = $request->file('logo');

        if ($company->logo_path) {
            Storage::disk('public')->delete($company->logo_path);
        }

        $filename = 'company-logos/' . $company->id . '_' . Str::random(10) . '.' . $file->extension();

        if ($this->imageService->isCompressible($file->getMimeType())) {
            $result = $this->imageService->compressUploadedFile($file, $filename, 'public');
            $path = $result['path'];
        } else {
            $path = $file->storeAs('company-logos', basename($filename), 'public');
        }

        $company->update(['logo_path' => $path]);

        return response()->json([
            'message' => 'Logo uploaded',
            'logo_url' => $company->fresh()->logo_url,
        ]);
    }

    private function formatCompany(Company $company, User $user): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'logo_url' => $company->logo_url,
            'owner' => [
                'id' => $company->owner->id,
                'username' => $company->owner->username,
                'avatar_url' => $company->owner->avatar_url,
            ],
            'members_count' => $company->members_count,
            'my_role' => $company->pivot->role ?? null,
            'is_owner' => $company->owner_id === $user->id,
            'created_at' => $company->created_at->toIso8601String(),
        ];
    }
}
