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
        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            // 担当者（未割当の場合は null）
            $table->foreignId('member_id')->nullable()->constrained()->nullOnDelete();
            // 紐付けるエピック（開発作業・PM管理・保守運用すべてで任意）
            $table->foreignId('epic_id')->nullable()->constrained()->nullOnDelete();
            // 紐付けるイシュー（開発作業の場合のタスク/ストーリー）
            $table->foreignId('issue_id')->nullable()->constrained('issues')->nullOnDelete();
            // null=開発作業, pm_estimate/pm_meeting/pm_other, ops_inquiry/ops_fix/ops_incident/ops_other
            $table->string('category')->nullable();
            $table->decimal('hours', 5, 2);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('work_logs');
    }
};
