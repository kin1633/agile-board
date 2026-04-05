<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * グループに工数フラグを追加し、種別側から削除する。
     * 「工数管理外」グループは is_billable = false に設定する。
     */
    public function up(): void
    {
        Schema::table('work_log_category_groups', function (Blueprint $table) {
            $table->boolean('is_billable')->default(true)->after('sort_order');
        });

        // 工数管理外グループのみ false に設定
        DB::table('work_log_category_groups')
            ->where('name', '工数管理外')
            ->update(['is_billable' => false]);

        Schema::table('work_log_categories', function (Blueprint $table) {
            $table->dropColumn('is_billable');
        });
    }

    /**
     * ロールバック時はカテゴリ側に is_billable を復元し、グループ側から削除する。
     * 復元値はグループの is_billable を継承する（グループなしは true）。
     */
    public function down(): void
    {
        Schema::table('work_log_categories', function (Blueprint $table) {
            $table->boolean('is_billable')->default(true)->after('color');
        });

        // グループの is_billable をカテゴリに反映
        DB::statement('
            UPDATE work_log_categories c
            LEFT JOIN work_log_category_groups g ON g.id = c.work_log_category_group_id
            SET c.is_billable = COALESCE(g.is_billable, 1)
        ');

        Schema::table('work_log_category_groups', function (Blueprint $table) {
            $table->dropColumn('is_billable');
        });
    }
};
