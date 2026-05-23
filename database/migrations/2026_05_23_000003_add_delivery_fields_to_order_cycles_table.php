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
        Schema::table('order_cycles', function (Blueprint $table) {
            $table->timestamp('delivered_at')->nullable()->after('sent_to_supplier_by');
            $table->foreignId('delivered_by')
                ->nullable()
                ->after('delivered_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_cycles', function (Blueprint $table) {
            $table->dropForeign(['delivered_by']);
            $table->dropColumn(['delivered_at', 'delivered_by']);
        });
    }
};
