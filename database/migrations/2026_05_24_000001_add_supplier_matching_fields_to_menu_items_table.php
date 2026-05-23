<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('image_url')->index();
            $table->string('supplier_code')->nullable()->after('external_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['external_id']);
            $table->dropIndex(['supplier_code']);
            $table->dropColumn(['external_id', 'supplier_code']);
        });
    }
};
