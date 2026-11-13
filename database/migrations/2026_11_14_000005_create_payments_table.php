<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_no', 20)->unique(); // OR-000001
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('plan_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10); // new (first plan ever) | renewal
            $table->decimal('amount', 10, 2);
            $table->string('method', 20); // App\Enums\PaymentMethod
            $table->string('reference_no', 60)->nullable();
            $table->date('paid_on')->index();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
