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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->unsignedBigInteger('price_paid_in_cents');
            $table->unsignedBigInteger('platform_fee_percent')->default(30);
            $table->string('status');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->string('payment_reference')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->index(['student_id', 'status']);
            $table->index(['start_at', 'end_at']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
