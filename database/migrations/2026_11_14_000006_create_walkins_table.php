<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Day-pass guests (not members). Count toward income and the daily attendance total.
        Schema::create('walkins', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable(); // optional guest name
            $table->string('phone', 20)->nullable();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete(); // the day-pass plan sold
            $table->decimal('amount', 10, 2);
            $table->string('method', 20); // App\Enums\PaymentMethod
            $table->string('reference_no', 60)->nullable();
            $table->dateTime('visited_at')->index(); // server clock
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walkins');
    }
};
