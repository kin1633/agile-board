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
        Schema::table('milestones', function (Blueprint $table) {
            // Monthly Iteration からのマイルストーンは github_milestone_id を持たないため nullable に変更
            $table->integer('github_milestone_id')->nullable()->change();

            // GitHub Projects の Monthly Iteration ID（文字列型）
            $table->string('github_iteration_id')->nullable()->unique()->after('github_milestone_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('milestones', function (Blueprint $table) {
            $table->dropUnique(['github_iteration_id']);
            $table->dropColumn('github_iteration_id');
            $table->integer('github_milestone_id')->nullable(false)->change();
        });
    }
};
