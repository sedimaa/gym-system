<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per paid period. The member's current membership is the latest row.
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('expiry_date')->index(); // start_date + plan duration_days
            $table->unsignedSmallInteger('sessions_total')->nullable();     // per-session bundles
            $table->unsignedSmallInteger('sessions_remaining')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
