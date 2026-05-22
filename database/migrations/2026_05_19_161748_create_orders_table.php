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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_cycle_id')->constrained('order_cycles')->cascadeOnDelete();
            $table->string('status')->default('draft')->index();
            $table->decimal('total_price', 10, 2)->default(0);
            $table->dateTime('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'order_cycle_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
