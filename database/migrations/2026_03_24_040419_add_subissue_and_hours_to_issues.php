<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * サブイシュー（親子関係）と工数管理フィールドを追加する。
     *
     * parent_issue_id: GitHub Sub-issues に対応するための自己参照FK。
     * estimated_hours / actual_hours: クライアント向け工数報告のための手動入力項目。
     */
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // 自己参照FK: Task（サブイシュー）→ Story（親イシュー）の親子関係
            $table->foreignId('parent_issue_id')
                ->nullable()
                ->after('epic_id')
                ->constrained('issues')
                ->nullOnDelete();

            // 予定工数・実績工数（GitHub 同期では上書きしない手動設定項目）
            $table->decimal('estimated_hours', 8, 2)->nullable()->after('story_points');
            $table->decimal('actual_hours', 8, 2)->nullable()->after('estimated_hours');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropForeign(['parent_issue_id']);
            $table->dropColumn(['parent_issue_id', 'estimated_hours', 'actual_hours']);
        });
    }
};
