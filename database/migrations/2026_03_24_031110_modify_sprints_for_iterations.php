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
        Schema::table('sprints', function (Blueprint $table) {
            // MySQLでは unique 制約が FK と共有されているため、
            // 先に FK を削除してから unique を外し、nullable FK として再定義する。
            $table->dropForeign(['milestone_id']);
            $table->dropUnique(['milestone_id']);
            $table->foreignId('milestone_id')->nullable()->change();
            $table->foreign('milestone_id')->references('id')->on('milestones')->cascadeOnDelete();

            // GitHub Projects (ProjectV2) の Iteration ID（グローバル Node ID）
            $table->string('github_iteration_id')->nullable()->unique()->after('milestone_id');
            // Iteration の期間（日数）。duration フィールドから取得
            $table->unsignedInteger('iteration_duration_days')->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropUnique(['github_iteration_id']);
            $table->dropColumn(['github_iteration_id', 'iteration_duration_days']);

            // NOT NULL + UNIQUE に戻す
            $table->dropForeign(['milestone_id']);
            $table->foreignId('milestone_id')->nullable(false)->change();
            $table->unique('milestone_id');
            $table->foreign('milestone_id')->references('id')->on('milestones')->cascadeOnDelete();
        });
    }
};
