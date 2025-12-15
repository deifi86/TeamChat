<?php

namespace App\Http\Controllers\Api;

use App\Events\NewMessage;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\File;
use App\Models\Message;
use App\Services\FileService;
use App\Services\MessageEncryptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function __construct(
        private FileService $fileService,
        private MessageEncryptionService $encryptionService
    ) {}

    /**
     * Datei in Channel hochladen
     */
    public function uploadToChannel(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOfChannel($channel)) {
            return response()->json([
                'message' => 'You are not a member of this channel',
            ], 403);
        }

        $request->validate($this->fileService->getValidationRules());

        $uploadedFile = $request->file('file');

        // Optionale Nachricht zum Bild
        $messageContent = $request->input('message', '');
        $encrypted = $this->encryptionService->encryptForStorage(
            $messageContent ?: '[Datei]'
        );

        // Nachricht erstellen
        $message = Message::create([
            'messageable_type' => 'channel',
            'messageable_id' => $channel->id,
            'sender_id' => $user->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'content_type' => $this->getContentType($uploadedFile->getMimeType()),
        ]);

        // Datei hochladen
        $file = $this->fileService->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id,
            $message
        );

        // Event broadcasten
        broadcast(new NewMessage($message, $messageContent ?: '[Datei]'))->toOthers();

        return response()->json([
            'message' => 'File uploaded',
            'data' => $this->formatFileWithMessage($file, $message, $user),
        ], 201);
    }

    /**
     * Datei in Conversation hochladen
     */
    public function uploadToConversation(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        if (!$conversation->isAccepted()) {
            return response()->json([
                'message' => 'Conversation not yet accepted',
            ], 403);
        }

        $request->validate($this->fileService->getValidationRules());

        $uploadedFile = $request->file('file');
        $messageContent = $request->input('message', '');

        $encrypted = $this->encryptionService->encryptForStorage(
            $messageContent ?: '[Datei]'
        );

        $message = Message::create([
            'messageable_type' => 'direct',
            'messageable_id' => $conversation->id,
            'sender_id' => $user->id,
            'content' => $encrypted['content'],
            'content_iv' => $encrypted['content_iv'],
            'content_type' => $this->getContentType($uploadedFile->getMimeType()),
        ]);

        $file = $this->fileService->upload(
            $uploadedFile,
            $user,
            'direct',
            $conversation->id,
            $message
        );

        broadcast(new NewMessage($message, $messageContent ?: '[Datei]'))->toOthers();

        return response()->json([
            'message' => 'File uploaded',
            'data' => $this->formatFileWithMessage($file, $message, $user),
        ], 201);
    }

    /**
     * Dateien eines Channels auflisten
     */
    public function channelFiles(Request $request, Channel $channel): JsonResponse
    {
        $user = $request->user();

        if (!$user->isMemberOfChannel($channel)) {
            return response()->json([
                'message' => 'You are not a member of this channel',
            ], 403);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'in:image,document,media,all'],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = File::where('fileable_type', 'channel')
            ->where('fileable_id', $channel->id)
            ->with('uploader:id,username,avatar_path')
            ->orderByDesc('created_at');

        // Typ-Filter
        if (isset($validated['type']) && $validated['type'] !== 'all') {
            $query->where(function ($q) use ($validated) {
                match ($validated['type']) {
                    'image' => $q->where('mime_type', 'like', 'image/%'),
                    'document' => $q->whereIn('mime_type', [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/plain',
                        'text/csv',
                    ]),
                    'media' => $q->where('mime_type', 'like', 'video/%')
                        ->orWhere('mime_type', 'like', 'audio/%'),
                    default => null,
                };
            });
        }

        // Suche
        if (isset($validated['search'])) {
            $query->where('original_name', 'like', '%' . $validated['search'] . '%');
        }

        $perPage = $validated['per_page'] ?? 20;
        $files = $query->paginate($perPage);

        return response()->json([
            'files' => $files->items()
                ? collect($files->items())->map(fn ($f) => $this->formatFile($f))
                : [],
            'pagination' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    /**
     * Dateien einer Conversation auflisten
     */
    public function conversationFiles(Request $request, DirectConversation $conversation): JsonResponse
    {
        $user = $request->user();

        if (!$conversation->hasUser($user)) {
            return response()->json([
                'message' => 'Conversation not found',
            ], 404);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'in:image,document,media,all'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = File::where('fileable_type', 'direct')
            ->where('fileable_id', $conversation->id)
            ->with('uploader:id,username,avatar_path')
            ->orderByDesc('created_at');

        if (isset($validated['type']) && $validated['type'] !== 'all') {
            $query->where(function ($q) use ($validated) {
                match ($validated['type']) {
                    'image' => $q->where('mime_type', 'like', 'image/%'),
                    'document' => $q->whereIn('mime_type', [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ]),
                    'media' => $q->where('mime_type', 'like', 'video/%')
                        ->orWhere('mime_type', 'like', 'audio/%'),
                    default => null,
                };
            });
        }

        $perPage = $validated['per_page'] ?? 20;
        $files = $query->paginate($perPage);

        return response()->json([
            'files' => $files->items()
                ? collect($files->items())->map(fn ($f) => $this->formatFile($f))
                : [],
            'pagination' => [
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
                'per_page' => $files->perPage(),
                'total' => $files->total(),
            ],
        ]);
    }

    /**
     * Einzelne Datei abrufen
     */
    public function show(Request $request, File $file): JsonResponse
    {
        $user = $request->user();

        if (!$this->fileService->canAccess($user, $file)) {
            return response()->json([
                'message' => 'File not found',
            ], 404);
        }

        return response()->json([
            'file' => $this->formatFile($file),
        ]);
    }

    /**
     * Datei herunterladen
     */
    public function download(Request $request, File $file): StreamedResponse|JsonResponse
    {
        $user = $request->user();

        if (!$this->fileService->canAccess($user, $file)) {
            return response()->json([
                'message' => 'File not found',
            ], 404);
        }

        if (!Storage::disk('public')->exists($file->file_path)) {
            return response()->json([
                'message' => 'File not found on disk',
            ], 404);
        }

        return Storage::disk('public')->download(
            $file->file_path,
            $file->original_name
        );
    }

    /**
     * Datei löschen
     */
    public function destroy(Request $request, File $file): JsonResponse
    {
        $user = $request->user();

        // Nur Uploader oder Admin kann löschen
        $canDelete = $file->uploader_id === $user->id;

        if (!$canDelete && $file->fileable_type === 'channel') {
            $channel = Channel::find($file->fileable_id);
            $canDelete = $channel && $user->isAdminOf($channel->company);
        }

        if (!$canDelete) {
            return response()->json([
                'message' => 'You cannot delete this file',
            ], 403);
        }

        $this->fileService->delete($file);

        return response()->json([
            'message' => 'File deleted',
        ]);
    }

    /**
     * Content-Type basierend auf MIME-Type
     */
    private function getContentType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        return 'file';
    }

    /**
     * Datei formatieren
     */
    private function formatFile(File $file): array
    {
        return [
            'id' => $file->id,
            'original_name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'file_size' => $file->file_size,
            'human_size' => $file->human_file_size,
            'url' => $file->url,
            'thumbnail_url' => $file->thumbnail_url,
            'is_image' => $file->isImage(),
            'is_compressed' => $file->is_compressed,
            'original_size' => $file->original_size,
            'uploader' => $file->uploader ? [
                'id' => $file->uploader->id,
                'username' => $file->uploader->username,
                'avatar_url' => $file->uploader->avatar_url,
            ] : null,
            'created_at' => $file->created_at->toIso8601String(),
        ];
    }

    /**
     * Datei mit zugehöriger Nachricht formatieren
     */
    private function formatFileWithMessage(File $file, Message $message, $user): array
    {
        return [
            'file' => $this->formatFile($file),
            'message' => [
                'id' => $message->id,
                'content_type' => $message->content_type,
                'sender' => [
                    'id' => $user->id,
                    'username' => $user->username,
                    'avatar_url' => $user->avatar_url,
                ],
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ];
    }
}
