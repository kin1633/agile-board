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
        // カラムが既に存在しない場合のみ追加する（同一バッチで別マイグレーションが先走った場合に対応）
        if (! Schema::hasColumn('work_log_categories', 'work_log_category_group_id')) {
            Schema::table('work_log_categories', function (Blueprint $table) {
                $table->foreignId('work_log_category_group_id')
                    ->nullable()
                    ->after('label')
                    ->constrained('work_log_category_groups')
                    ->nullOnDelete();
            });
        } else {
            // カラムは存在するが FK 制約がない場合に FK を追加する
            Schema::table('work_log_categories', function (Blueprint $table) {
                $table->foreign('work_log_category_group_id')
                    ->references('id')
                    ->on('work_log_category_groups')
                    ->nullOnDelete();
            });
        }

        // 既存の group_name 文字列を FK に移行する
        $groups = DB::table('work_log_category_groups')->pluck('id', 'name');
        foreach ($groups as $name => $id) {
            DB::table('work_log_categories')
                ->where('group_name', $name)
                ->update(['work_log_category_group_id' => $id]);
        }

        if (Schema::hasColumn('work_log_categories', 'group_name')) {
            Schema::table('work_log_categories', function (Blueprint $table) {
                $table->dropColumn('group_name');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('work_log_categories', 'group_name')) {
            Schema::table('work_log_categories', function (Blueprint $table) {
                $table->string('group_name', 100)->nullable()->after('label');
            });
        }

        // FK から group_name に戻す
        $groups = DB::table('work_log_category_groups')->pluck('name', 'id');
        foreach ($groups as $id => $name) {
            DB::table('work_log_categories')
                ->where('work_log_category_group_id', $id)
                ->update(['group_name' => $name]);
        }

        Schema::table('work_log_categories', function (Blueprint $table) {
            $table->dropForeign(['work_log_category_group_id']);
            $table->dropColumn('work_log_category_group_id');
        });
    }
};
