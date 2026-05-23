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
            $table->dateTime('sent_to_supplier_at')->nullable()->after('status');
            $table->foreignId('sent_to_supplier_by')
                ->nullable()
                ->after('sent_to_supplier_at')
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
            $table->dropForeign(['sent_to_supplier_by']);
            $table->dropColumn(['sent_to_supplier_at', 'sent_to_supplier_by']);
        });
    }
};
