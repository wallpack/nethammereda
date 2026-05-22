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
        Schema::table('menu_items', function (Blueprint $table) {
            $table->decimal('proteins', 8, 2)->nullable()->after('calories');
            $table->decimal('fats', 8, 2)->nullable()->after('proteins');
            $table->decimal('carbs', 8, 2)->nullable()->after('fats');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['proteins', 'fats', 'carbs']);
        });
    }
};

