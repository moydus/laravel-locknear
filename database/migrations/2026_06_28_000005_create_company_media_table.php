<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('gallery');
            $table->string('url');
            $table->string('path')->nullable();
            $table->string('disk')->nullable();
            $table->string('source')->default('uploaded');
            $table->boolean('is_public')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'type', 'is_public']);
            $table->index(['company_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_media');
    }
};
