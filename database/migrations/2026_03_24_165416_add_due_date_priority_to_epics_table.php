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
        Schema::table('epics', function (Blueprint $table) {
            // リリース予定日（未定の案件は NULL）
            $table->date('due_date')->nullable()->after('status');
            // 優先度: high（高）/ medium（中）/ low（低）
            $table->string('priority')->default('medium')->after('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('epics', function (Blueprint $table) {
            $table->dropColumn(['due_date', 'priority']);
        });
    }
};
