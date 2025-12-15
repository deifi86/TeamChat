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
