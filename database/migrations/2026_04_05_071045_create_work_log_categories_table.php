<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('work_log_categories', function (Blueprint $table) {
            $table->id();
            /** work_logs.category に保存する値。デフォルト（開発作業）は空文字 */
            $table->string('value', 50)->unique();
            $table->string('label', 100);
            /** ドロップダウンのグループヘッダー */
            $table->string('group_name', 100)->nullable();
            /** FullCalendar のイベント背景色（HEX） */
            $table->string('color', 7)->default('#6b7280');
            /** false = 工数管理外（休憩・社内研修など） */
            $table->boolean('is_billable')->default(true);
            /** true = 「開発作業」デフォルト種別（value='' に対応） */
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 初期カテゴリデータを投入する
        DB::table('work_log_categories')->insert([
            ['value' => '',            'label' => '開発作業',              'group_name' => null,          'color' => '#3b82f6', 'is_billable' => true,  'is_default' => true,  'sort_order' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['value' => 'pm_estimate', 'label' => '作業工数見積',           'group_name' => 'PJ管理工数',  'color' => '#f97316', 'is_billable' => true,  'is_default' => false, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['value' => 'pm_meeting',  'label' => '内部打ち合わせ時間',     'group_name' => 'PJ管理工数',  'color' => '#f97316', 'is_billable' => true,  'is_default' => false, 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['value' => 'pm_other',    'label' => 'その他PJ管理',           'group_name' => 'PJ管理工数',  'color' => '#f97316', 'is_billable' => true,  'is_default' => false, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['value' => 'ops_inquiry', 'label' => '問い合わせ対応',         'group_name' => '保守・運用工数', 'color' => '#8b5cf6', 'is_billable' => true,  'is_default' => false, 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['value' => 'ops_fix',     'label' => 'リリース後改修',          'group_name' => '保守・運用工数', 'color' => '#8b5cf6', 'is_billable' => true,  'is_default' => false, 'sort_order' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['value' => 'ops_incident', 'label' => '障害対応',               'group_name' => '保守・運用工数', 'color' => '#ef4444', 'is_billable' => true,  'is_default' => false, 'sort_order' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['value' => 'ops_other',   'label' => 'その他運用',              'group_name' => '保守・運用工数', 'color' => '#8b5cf6', 'is_billable' => true,  'is_default' => false, 'sort_order' => 7, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['value' => 'break',       'label' => '休憩',                   'group_name' => '工数管理外',  'color' => '#9ca3af', 'is_billable' => false, 'is_default' => false, 'sort_order' => 8, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_log_categories');
    }
};
