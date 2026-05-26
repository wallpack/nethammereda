<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::whenTableDoesntHaveColumn('menu_items', 'supplier_name', function (Blueprint $table): void {
            $table->string('supplier_name')->nullable()->after('title');
        });

        Schema::whenTableDoesntHaveColumn('order_items', 'supplier_name_snapshot', function (Blueprint $table): void {
            $table->string('supplier_name_snapshot')->nullable()->after('title_snapshot');
        });
    }

    public function down(): void
    {
        Schema::whenTableHasColumn('order_items', 'supplier_name_snapshot', function (Blueprint $table): void {
            $table->dropColumn('supplier_name_snapshot');
        });

        Schema::whenTableHasColumn('menu_items', 'supplier_name', function (Blueprint $table): void {
            $table->dropColumn('supplier_name');
        });
    }
};
