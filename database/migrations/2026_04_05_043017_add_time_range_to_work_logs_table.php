<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * work_logs テーブルに開始・終了時刻カラムを追加する。
     * hours はこれらから自動計算するため、手動入力は廃止。
     */
    public function up(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->time('start_time')->after('date');
            $table->time('end_time')->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('work_logs', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
