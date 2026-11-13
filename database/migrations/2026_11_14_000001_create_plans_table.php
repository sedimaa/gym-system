<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 20); // App\Enums\PlanType
            $table->decimal('price', 10, 2);
            $table->unsignedSmallInteger('duration_days'); // validity: expiry = start + duration_days
            $table->unsignedSmallInteger('session_count')->nullable(); // per-session bundles only
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true); // inactive plans can't be sold
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
