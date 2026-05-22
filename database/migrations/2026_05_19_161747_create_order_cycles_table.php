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
        Schema::create('order_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->dateTime('starts_at');
            $table->dateTime('closes_at');
            $table->string('status')->default('draft')->index();
            $table->timestamps();

            $table->index(['starts_at', 'closes_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_cycles');
    }
};
