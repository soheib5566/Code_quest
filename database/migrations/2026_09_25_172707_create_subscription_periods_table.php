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
        Schema::create('subscription_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('period_number');
            $table->timestamp('start_at');
            $table->timestamp('end_at');
            $table->unsignedBigInteger('gross_amount_in_cents');
            $table->unsignedBigInteger('platform_amount_in_cents');
            $table->unsignedBigInteger('instructor_pool_in_cents');
            $table->string('status')->default('pending');
            $table->timestamp('allocated_at')->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'period_number']);
            $table->index(['status', 'end_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_periods');
    }
};
