<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('video_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->uuid('share_token')->unique()->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('is_default');
            $table->index('share_token');
            // Примечание: Проверка на единственность дефолтного каталога выполняется на уровне приложения
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_collections');
    }
};
