<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('read_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('messageable_type', 20);
            $table->unsignedBigInteger('messageable_id');
            $table->foreignId('last_read_message_id')->constrained('messages')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['user_id', 'messageable_type', 'messageable_id'], 'unique_read_receipt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('read_receipts');
    }
};
