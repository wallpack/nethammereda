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
        Schema::create('fridge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained('menu_items')->nullOnDelete();
            $table->string('title_snapshot');
            $table->unsignedInteger('quantity_total')->default(1);
            $table->unsignedInteger('quantity_remaining')->default(1);
            $table->string('status')->default('in_fridge')->index();
            $table->dateTime('arrived_at')->nullable();
            $table->dateTime('eaten_at')->nullable();
            $table->dateTime('discarded_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['order_item_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fridge_items');
    }
};

