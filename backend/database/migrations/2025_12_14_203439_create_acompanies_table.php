<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('join_password');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('logo_path', 500)->nullable();
            $table->timestamps();

            $table->index('slug');

            // FULLTEXT nur für MySQL
            if (DB::getDriverName() === 'mysql') {
                $table->fullText('name');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
