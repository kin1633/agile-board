<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GitHub 同期用の既存データは全廃棄（アプリ独自管理に移行するため）
        // MySQL: sprints.milestone_id FK があるため truncate 前に FK チェックを一時無効化
        // SQLite: PRAGMA で外部キー制約を制御（テスト環境用）
        if (DB::getDriverName() === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
            DB::table('milestones')->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        } else {
            DB::statement('PRAGMA foreign_keys = OFF');
            DB::table('milestones')->truncate();
            DB::statement('PRAGMA foreign_keys = ON');
        }

        Schema::table('milestones', function (Blueprint $table) {
            // GitHub 同期カラムを削除（repository_id FK 含む）
            $table->dropForeign(['repository_id']);
            $table->dropUnique(['repository_id', 'github_milestone_id']);
            $table->dropColumn(['repository_id', 'github_milestone_id', 'due_on', 'state', 'synced_at']);

            // github_iteration_id の unique 制約と列を削除
            $table->dropUnique(['github_iteration_id']);
            $table->dropColumn('github_iteration_id');

            // アプリ独自管理カラムを追加
            $table->unsignedSmallInteger('year')->after('id');
            $table->unsignedTinyInteger('month')->after('year');
            $table->text('goal')->nullable()->after('title');
            // planning / in_progress / done の3ステータスで運用
            $table->string('status')->default('planning')->after('goal');
            $table->date('started_at')->nullable()->after('status');
            $table->date('due_date')->nullable()->after('started_at');

            // 同じ年月のマイルストーンは1件のみ許可
            $table->unique(['year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropUnique(['year', 'month']);
            $table->dropColumn(['year', 'month', 'goal', 'status', 'started_at', 'due_date']);

            // GitHub 同期カラムを復元
            $table->foreignId('repository_id')->after('id')->constrained()->cascadeOnDelete();
            $table->integer('github_milestone_id')->nullable()->after('repository_id');
            $table->string('github_iteration_id')->nullable()->unique()->after('github_milestone_id');
            $table->date('due_on')->nullable()->after('title');
            $table->string('state')->default('open')->after('due_on');
            $table->timestamp('synced_at')->nullable()->after('state');

            $table->unique(['repository_id', 'github_milestone_id']);
        });
    }
};
