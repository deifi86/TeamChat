<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploader_id')->constrained('users')->cascadeOnDelete();
            $table->string('fileable_type', 20);
            $table->unsignedBigInteger('fileable_id');
            $table->string('original_name', 500);
            $table->string('stored_name', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->string('file_path', 1000);
            $table->string('thumbnail_path', 1000)->nullable();
            $table->boolean('is_compressed')->default(false);
            $table->unsignedBigInteger('original_size')->nullable();
            $table->timestamps();

            $table->index(['fileable_type', 'fileable_id'], 'idx_fileable');
            $table->index('uploader_id');
            $table->index('mime_type');
            $table->index('created_at');

            // FULLTEXT nur für MySQL
            if (DB::getDriverName() === 'mysql') {
                $table->fullText('original_name');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
