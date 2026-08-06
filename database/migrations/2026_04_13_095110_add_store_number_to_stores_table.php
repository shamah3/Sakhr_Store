<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  /**
     * Run the migrations.
     */
    public function up(): void
    {
        // نتحقق أولاً إذا كان العمود غير موجود قبل محاولة إضافته
        if (!Schema::hasColumn('stores', 'store_number')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->string('store_number')->unique()->after('id')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('stores', 'store_number')) {
            Schema::table('stores', function (Blueprint $table) {
                $table->dropColumn('store_number');
            });
        }
    }
};
