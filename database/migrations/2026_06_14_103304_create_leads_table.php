<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('zip');
            $table->string('service_type');
            $table->string('phone');
            $table->text('description')->nullable();
            $table->string('status')->default('new'); // new, assigned, completed, cancelled
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
