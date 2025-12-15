# Phase 6: Datei-System (Woche 11-13)

## Ziel dieser Phase
Nach Abschluss dieser Phase haben wir:
- Datei-Upload mit Validierung und Größenlimits
- TinyPNG Komprimierung für Bilder
- Automatische Thumbnail-Generierung
- Datei-Browser pro Channel/Conversation
- Download-Endpoint mit Zugriffsschutz
- Datei-Preview in Nachrichten

---

## 6.1 File Service [BE]

### 6.1.1 FileService erstellen
- [x] **Erledigt**

→ *Abhängig von Phase 5 abgeschlossen*

**Datei:** `app/Services/FileService.php`
```php
<?php

namespace App\Services;

use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\File;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class FileService
{
    private const THUMBNAIL_WIDTH = 300;
    private const THUMBNAIL_HEIGHT = 300;
    private const MAX_IMAGE_SIZE = 10 * 1024 * 1024; // 10MB
    private const MAX_FILE_SIZE = 50 * 1024 * 1024;  // 50MB

    public function __construct(
        private ImageCompressionService $compressionService
    ) {}

    /**
     * Datei hochladen und verarbeiten
     */
    public function upload(
        UploadedFile $file,
        User $uploader,
        string $fileableType,
        int $fileableId,
        ?Message $message = null
    ): File {
        $originalName = $file->getClientOriginalName();
        $mimeType = $file->getMimeType();
        $originalSize = $file->getSize();

        // Eindeutiger Dateiname
        $storedName = Str::uuid() . '.' . $file->extension();

        // Pfad basierend auf Kontext
        $basePath = $this->getBasePath($fileableType, $fileableId);
        $filePath = $basePath . '/' . $storedName;

        // Bild verarbeiten
        $isImage = str_starts_with($mimeType, 'image/');
        $isCompressed = false;
        $thumbnailPath = null;

        if ($isImage && $this->compressionService->isCompressible($mimeType)) {
            // Mit TinyPNG komprimieren
            $result = $this->compressionService->compressUploadedFile(
                $file,
                $filePath,
                'public'
            );

            $filePath = $result['path'];
            $isCompressed = $result['success'];
            $fileSize = $result['compressed_size'];

            // Thumbnail erstellen
            $thumbnailPath = $this->createThumbnail($filePath, $basePath, $storedName);
        } else {
            // Normale Datei speichern
            $filePath = $file->storeAs($basePath, $storedName, 'public');
            $fileSize = $originalSize;

            // Thumbnail für nicht-komprimierbare Bilder
            if ($isImage) {
                $thumbnailPath = $this->createThumbnail($filePath, $basePath, $storedName);
            }
        }

        // DB-Eintrag erstellen
        return File::create([
            'message_id' => $message?->id,
            'uploader_id' => $uploader->id,
            'fileable_type' => $fileableType,
            'fileable_id' => $fileableId,
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_path' => $filePath,
            'thumbnail_path' => $thumbnailPath,
            'is_compressed' => $isCompressed,
            'original_size' => $isCompressed ? $originalSize : null,
        ]);
    }

    /**
     * Thumbnail für Bild erstellen
     */
    private function createThumbnail(string $filePath, string $basePath, string $storedName): ?string
    {
        try {
            $fullPath = Storage::disk('public')->path($filePath);
            
            $image = Image::read($fullPath);
            $image->scaleDown(self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);

            $thumbnailName = 'thumb_' . $storedName;
            $thumbnailPath = $basePath . '/thumbnails/' . $thumbnailName;
            $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

            // Verzeichnis erstellen
            $thumbnailDir = dirname($fullThumbnailPath);
            if (!is_dir($thumbnailDir)) {
                mkdir($thumbnailDir, 0755, true);
            }

            $image->save($fullThumbnailPath);

            return $thumbnailPath;
        } catch (\Exception $e) {
            \Log::error('Thumbnail creation failed', [
                'error' => $e->getMessage(),
                'file' => $filePath,
            ]);
            return null;
        }
    }

    /**
     * Basis-Pfad basierend auf Kontext
     */
    private function getBasePath(string $fileableType, int $fileableId): string
    {
        return match ($fileableType) {
            'channel' => "files/channels/{$fileableId}",
            'direct' => "files/conversations/{$fileableId}",
            default => "files/misc",
        };
    }

    /**
     * Datei löschen
     */
    public function delete(File $file): bool
    {
        // Datei löschen
        if ($file->file_path) {
            Storage::disk('public')->delete($file->file_path);
        }

        // Thumbnail löschen
        if ($file->thumbnail_path) {
            Storage::disk('public')->delete($file->thumbnail_path);
        }

        return $file->delete();
    }

    /**
     * Prüft ob User Zugriff auf Datei hat
     */
    public function canAccess(User $user, File $file): bool
    {
        if ($file->fileable_type === 'channel') {
            $channel = Channel::find($file->fileable_id);
            return $channel && $user->isMemberOfChannel($channel);
        }

        if ($file->fileable_type === 'direct') {
            $conversation = DirectConversation::find($file->fileable_id);
            return $conversation && $conversation->hasUser($user);
        }

        return false;
    }

    /**
     * Erlaubte MIME-Types
     */
    public function getAllowedMimeTypes(): array
    {
        return [
            // Bilder
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
            
            // Dokumente
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            
            // Text
            'text/plain',
            'text/csv',
            'text/html',
            'text/css',
            'text/javascript',
            'application/json',
            'application/xml',
            
            // Archive
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip',
            
            // Medien
            'audio/mpeg',
            'audio/wav',
            'audio/ogg',
            'video/mp4',
            'video/webm',
            'video/quicktime',
        ];
    }

    /**
     * Maximale Dateigröße in Bytes
     */
    public function getMaxFileSize(string $mimeType): int
    {
        if (str_starts_with($mimeType, 'image/')) {
            return self::MAX_IMAGE_SIZE;
        }

        return self::MAX_FILE_SIZE;
    }

    /**
     * Validierungsregeln für Upload
     */
    public function getValidationRules(): array
    {
        $mimes = collect($this->getAllowedMimeTypes())
            ->map(fn ($mime) => explode('/', $mime)[1])
            ->unique()
            ->implode(',');

        return [
            'file' => [
                'required',
                'file',
                'max:51200', // 50MB in KB
                'mimetypes:' . implode(',', $this->getAllowedMimeTypes()),
            ],
        ];
    }
}
```

---

### 6.1.2 FileService registrieren
- [x] **Erledigt**

**Datei:** `app/Providers/AppServiceProvider.php` ergänzen:
```php
use App\Services\FileService;

public function register(): void
{
    $this->app->singleton(ImageCompressionService::class);
    $this->app->singleton(MessageEncryptionService::class);
    $this->app->singleton(EmojiService::class);
    $this->app->singleton(FileService::class);
}
```

---

### 6.1.3 Unit Tests für FileService
- [x] **Erledigt**

**Datei:** `tests/Unit/Services/FileServiceTest.php`
```php
<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Models\Channel;
use App\Models\DirectConversation;
use App\Models\File;
use App\Services\FileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileServiceTest extends TestCase
{
    use RefreshDatabase;

    private FileService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->service = app(FileService::class);
    }

    public function test_uploads_file_to_channel_path(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $uploadedFile = UploadedFile::fake()->create('document.pdf', 100);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id
        );

        $this->assertStringContains("files/channels/{$channel->id}", $file->file_path);
        $this->assertEquals('document.pdf', $file->original_name);
        $this->assertEquals($user->id, $file->uploader_id);
        Storage::disk('public')->assertExists($file->file_path);
    }

    public function test_uploads_file_to_conversation_path(): void
    {
        $user = User::factory()->create();
        $conversation = DirectConversation::factory()->create();

        $uploadedFile = UploadedFile::fake()->create('doc.pdf', 100);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'direct',
            $conversation->id
        );

        $this->assertStringContains("files/conversations/{$conversation->id}", $file->file_path);
    }

    public function test_creates_thumbnail_for_images(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $uploadedFile = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id
        );

        $this->assertNotNull($file->thumbnail_path);
        Storage::disk('public')->assertExists($file->thumbnail_path);
    }

    public function test_does_not_create_thumbnail_for_non_images(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $uploadedFile = UploadedFile::fake()->create('document.pdf', 100);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id
        );

        $this->assertNull($file->thumbnail_path);
    }

    public function test_delete_removes_file_and_thumbnail(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $uploadedFile = UploadedFile::fake()->image('photo.jpg', 400, 300);

        $file = $this->service->upload(
            $uploadedFile,
            $user,
            'channel',
            $channel->id
        );

        $filePath = $file->file_path;
        $thumbnailPath = $file->thumbnail_path;

        $this->service->delete($file);

        Storage::disk('public')->assertMissing($filePath);
        if ($thumbnailPath) {
            Storage::disk('public')->assertMissing($thumbnailPath);
        }
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_can_access_returns_true_for_channel_member(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        $this->assertTrue($this->service->canAccess($user, $file));
    }

    public function test_can_access_returns_false_for_non_member(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        $this->assertFalse($this->service->canAccess($user, $file));
    }

    public function test_can_access_returns_true_for_conversation_participant(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $conversation = DirectConversation::findOrCreateBetween($user, $other);

        $file = File::factory()->create([
            'fileable_type' => 'direct',
            'fileable_id' => $conversation->id,
        ]);

        $this->assertTrue($this->service->canAccess($user, $file));
    }
}
```

---

## 6.2 File Controller [BE]

### 6.2.1 FileController erstellen
- [x] **Erledigt**

**Durchführung:**
```bash
php artisan make:controller Api/FileController
```

**Datei:** `app/Http/Controllers/Api/FileController.php`
```php
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
```

---

### 6.2.2 Unit Tests für FileController
- [x] **Erledigt**

**Datei:** `tests/Feature/Api/File/UploadFileTest.php`
```php
<?php

namespace Tests\Feature\Api\File;

use App\Events\NewMessage;
use App\Models\User;
use App\Models\Channel;
use App\Models\DirectConversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_member_can_upload_file_to_channel(): void
    {
        Event::fake([NewMessage::class]);

        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/files", [
                'file' => $file,
                'message' => 'Check this document',
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'file' => ['id', 'original_name', 'url'],
                    'message' => ['id', 'sender'],
                ],
            ]);

        $this->assertDatabaseHas('files', [
            'original_name' => 'document.pdf',
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        Event::assertDispatched(NewMessage::class);
    }

    public function test_non_member_cannot_upload_to_channel(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }

    public function test_uploads_image_with_thumbnail(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        $file = UploadedFile::fake()->image('photo.jpg', 800, 600);

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(201);

        $fileData = $response->json('data.file');
        $this->assertNotNull($fileData['thumbnail_url']);
        $this->assertTrue($fileData['is_image']);
    }

    public function test_validates_file_type(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        // Gefährliche Datei
        $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

        $response = $this->actingAs($user)
            ->postJson("/api/channels/{$channel->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function test_participant_can_upload_to_conversation(): void
    {
        Event::fake([NewMessage::class]);

        $user = User::factory()->create();
        $other = User::factory()->create();
        
        $conversation = DirectConversation::factory()->create([
            'user_one_id' => min($user->id, $other->id),
            'user_two_id' => max($user->id, $other->id),
            'user_one_accepted' => true,
            'user_two_accepted' => true,
        ]);

        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->actingAs($user)
            ->postJson("/api/conversations/{$conversation->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(201);
    }

    public function test_cannot_upload_to_unaccepted_conversation(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        
        $conversation = DirectConversation::findOrCreateBetween($user, $other);

        $file = UploadedFile::fake()->create('document.pdf', 500);

        $response = $this->actingAs($other)
            ->postJson("/api/conversations/{$conversation->id}/files", [
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }
}
```

**Datei:** `tests/Feature/Api/File/ListFilesTest.php`
```php
<?php

namespace Tests\Feature\Api\File;

use App\Models\User;
use App\Models\Channel;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_channel_files(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        File::factory()->count(3)->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'uploader_id' => $user->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files");

        $response->assertOk()
            ->assertJsonCount(3, 'files')
            ->assertJsonStructure([
                'files' => [
                    '*' => ['id', 'original_name', 'mime_type', 'url'],
                ],
                'pagination',
            ]);
    }

    public function test_can_filter_by_type(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        // Bilder
        File::factory()->count(2)->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'mime_type' => 'image/jpeg',
        ]);

        // PDFs
        File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files?type=image");

        $response->assertOk()
            ->assertJsonCount(2, 'files');
    }

    public function test_can_search_by_name(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'original_name' => 'important-report.pdf',
        ]);

        File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'original_name' => 'random.jpg',
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files?search=report");

        $response->assertOk()
            ->assertJsonCount(1, 'files')
            ->assertJsonFragment(['original_name' => 'important-report.pdf']);
    }

    public function test_pagination_works(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        File::factory()->count(25)->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files?per_page=10");

        $response->assertOk()
            ->assertJsonCount(10, 'files')
            ->assertJsonPath('pagination.total', 25)
            ->assertJsonPath('pagination.last_page', 3);
    }

    public function test_non_member_cannot_list_files(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $response = $this->actingAs($user)
            ->getJson("/api/channels/{$channel->id}/files");

        $response->assertStatus(403);
    }
}
```

**Datei:** `tests/Feature/Api/File/DownloadFileTest.php`
```php
<?php

namespace Tests\Feature\Api\File;

use App\Models\User;
use App\Models\Channel;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DownloadFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_member_can_download_file(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        // Echte Datei erstellen
        Storage::disk('public')->put('files/test.pdf', 'PDF content');

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'file_path' => 'files/test.pdf',
            'original_name' => 'document.pdf',
        ]);

        $response = $this->actingAs($user)
            ->get("/api/files/{$file->id}/download");

        $response->assertOk();
        $response->assertHeader('content-disposition', 'attachment; filename=document.pdf');
    }

    public function test_non_member_cannot_download(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
        ]);

        $response = $this->actingAs($user)
            ->get("/api/files/{$file->id}/download");

        $response->assertStatus(404);
    }
}
```

**Datei:** `tests/Feature/Api/File/DeleteFileTest.php`
```php
<?php

namespace Tests\Feature\Api\File;

use App\Models\User;
use App\Models\Company;
use App\Models\Channel;
use App\Models\File;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeleteFileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_uploader_can_delete_own_file(): void
    {
        $user = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach($user->id);

        Storage::disk('public')->put('files/test.pdf', 'content');

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'uploader_id' => $user->id,
            'file_path' => 'files/test.pdf',
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/files/{$file->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
    }

    public function test_admin_can_delete_any_file_in_channel(): void
    {
        $admin = User::factory()->create();
        $uploader = User::factory()->create();
        $company = Company::factory()->create();
        $company->members()->attach($admin->id, ['role' => 'admin']);

        $channel = Channel::factory()->create(['company_id' => $company->id]);

        Storage::disk('public')->put('files/test.pdf', 'content');

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'uploader_id' => $uploader->id,
            'file_path' => 'files/test.pdf',
        ]);

        $response = $this->actingAs($admin)
            ->deleteJson("/api/files/{$file->id}");

        $response->assertOk();
    }

    public function test_other_user_cannot_delete_file(): void
    {
        $user = User::factory()->create();
        $uploader = User::factory()->create();
        $channel = Channel::factory()->create();
        $channel->members()->attach([$user->id, $uploader->id]);

        $file = File::factory()->create([
            'fileable_type' => 'channel',
            'fileable_id' => $channel->id,
            'uploader_id' => $uploader->id,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson("/api/files/{$file->id}");

        $response->assertStatus(403);
    }
}
```

---

## 6.3 File Factory erstellen
- [x] **Erledigt**

**Datei:** `database/factories/FileFactory.php`
```php
<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FileFactory extends Factory
{
    public function definition(): array
    {
        $mimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
        ];

        $mimeType = fake()->randomElement(array_keys($mimeTypes));
        $extension = $mimeTypes[$mimeType];
        $storedName = Str::uuid() . '.' . $extension;

        return [
            'message_id' => null,
            'uploader_id' => User::factory(),
            'fileable_type' => 'channel',
            'fileable_id' => Channel::factory(),
            'original_name' => fake()->word() . '.' . $extension,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => fake()->numberBetween(1000, 5000000),
            'file_path' => 'files/' . $storedName,
            'thumbnail_path' => null,
            'is_compressed' => false,
            'original_size' => null,
        ];
    }

    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'image/jpeg',
            'original_name' => fake()->word() . '.jpg',
            'thumbnail_path' => 'files/thumbnails/thumb_' . Str::uuid() . '.jpg',
        ]);
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'mime_type' => 'application/pdf',
            'original_name' => fake()->word() . '.pdf',
        ]);
    }

    public function compressed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_compressed' => true,
            'original_size' => fake()->numberBetween(2000000, 10000000),
        ]);
    }
}
```

---

## 6.4 Routes & Tests [BE]

### 6.4.1 File Routes definieren
- [x] **Erledigt**

**Datei:** `routes/api.php` ergänzen:
```php
use App\Http\Controllers\Api\FileController;

Route::middleware('auth:sanctum')->group(function () {
    // ... bestehende Routes ...

    // File Upload
    Route::post('channels/{channel}/files', [FileController::class, 'uploadToChannel']);
    Route::post('conversations/{conversation}/files', [FileController::class, 'uploadToConversation']);

    // File Browser
    Route::get('channels/{channel}/files', [FileController::class, 'channelFiles']);
    Route::get('conversations/{conversation}/files', [FileController::class, 'conversationFiles']);

    // File Operations
    Route::prefix('files')->group(function () {
        Route::get('{file}', [FileController::class, 'show']);
        Route::get('{file}/download', [FileController::class, 'download']);
        Route::delete('{file}', [FileController::class, 'destroy']);
    });
});
```

---

### 6.4.2 Alle Phase 6 Tests ausführen
- [x] **Erledigt**

**Durchführung:**
```bash
php artisan test --filter=File
php artisan test
```

**Akzeptanzkriterien:**
- [x] Alle Tests grün
- [x] 104 Tests insgesamt (24 File-Tests, 80 andere Tests)

---

### 6.4.3 Storage Verzeichnisse vorbereiten
- [x] **Erledigt**

**Durchführung:**
```bash
# Verzeichnisse erstellen
mkdir -p storage/app/public/files/channels
mkdir -p storage/app/public/files/conversations
mkdir -p storage/app/public/avatars
mkdir -p storage/app/public/company-logos

# Berechtigungen
chmod -R 775 storage/app/public
```

---

### 6.4.4 Git Commit & Tag
- [x] **Erledigt**

**Durchführung:**
```bash
git add .
git commit -m "Phase 6: File System - Upload, Download, Thumbnails"
git tag v0.6.0
```

---

## Phase 6 Zusammenfassung

### Erstellte Dateien
- 1 Service (FileService)
- 1 Controller (FileController)
- 1 Factory (FileFactory)
- ~6 neue Test-Dateien

### Neue API Endpoints
| Methode | Endpoint | Beschreibung |
|---------|----------|--------------|
| POST | /api/channels/{id}/files | Datei in Channel hochladen |
| POST | /api/conversations/{id}/files | Datei in DM hochladen |
| GET | /api/channels/{id}/files | Channel-Dateien auflisten |
| GET | /api/conversations/{id}/files | DM-Dateien auflisten |
| GET | /api/files/{id} | Datei-Details |
| GET | /api/files/{id}/download | Datei herunterladen |
| DELETE | /api/files/{id} | Datei löschen |

### Features
- **Upload:**
  - Validierung von MIME-Types und Größe
  - Automatische TinyPNG Komprimierung für Bilder
  - UUID-basierte Dateinamen (Sicherheit)
  - Verknüpfung mit Nachricht

- **Thumbnails:**
  - Automatische Generierung für Bilder
  - 300x300px maximale Größe
  - Proportionale Skalierung

- **File Browser:**
  - Filter nach Typ (image, document, media)
  - Suche nach Dateinamen
  - Pagination

- **Sicherheit:**
  - Zugriffsprüfung auf Channel/Conversation-Basis
  - Nur Uploader oder Admin kann löschen
  - Keine direkten Dateipfade exponiert

### Unterstützte Dateitypen
| Kategorie | Formate |
|-----------|---------|
| Bilder | JPEG, PNG, GIF, WebP, SVG |
| Dokumente | PDF, Word, Excel, PowerPoint, Text, CSV |
| Archive | ZIP, RAR, 7z, GZip |
| Medien | MP3, WAV, OGG, MP4, WebM, MOV |

### Größenlimits
- Bilder: 10 MB
- Andere Dateien: 50 MB

### Nächste Phase
→ Weiter mit `phase-7-frontend-setup.md`
