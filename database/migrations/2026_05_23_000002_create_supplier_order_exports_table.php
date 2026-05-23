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
        Schema::create('supplier_order_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_cycle_id')->constrained('order_cycles')->cascadeOnDelete();
            $table->foreignId('exported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('exported_at')->index();
            $table->unsignedInteger('rows_count')->default(0);
            $table->unsignedInteger('total_quantity')->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->string('format', 32)->default('csv');
            $table->string('file_path')->nullable();
            $table->json('snapshot_json')->nullable();
            $table->timestamps();

            $table->index(['order_cycle_id', 'exported_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_order_exports');
    }
};
