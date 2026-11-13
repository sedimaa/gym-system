<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('checked_in_at')->index(); // server clock
            $table->string('status', 10);               // allowed | denied | manual (override / admin entry)
            $table->string('source', 10)->default('manual'); // manual now, scanner later
            $table->boolean('session_used')->default(false); // a per-session bundle session was used
            $table->string('note')->nullable();               // required for overrides and admin corrections
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['member_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
