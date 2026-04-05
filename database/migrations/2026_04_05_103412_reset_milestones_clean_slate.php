<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * マイルストーンを全削除してクリーンな状態にする。
     *
     * 第1月曜日ベースの日付計算に移行するため、既存データを一掃する。
     * スプリントの milestone_id も NULL リセットし、次回 GitHub 同期時に自動紐付けされる。
     */
    public function up(): void
    {
        DB::table('sprints')->update(['milestone_id' => null]);
        // TRUNCATE は FK 制約があると失敗するため、Schema ヘルパーで一時的に無効化する（MySQL/SQLite 両対応）
        Schema::disableForeignKeyConstraints();
        DB::table('milestones')->truncate();
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // データ復元不可のため down は空
    }
};
