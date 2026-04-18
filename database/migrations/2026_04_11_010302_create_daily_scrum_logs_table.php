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
        Schema::create('daily_scrum_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date')->comment('記録日');
            $table->foreignId('issue_id')->constrained('issues')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            // 0〜100の進捗率（unsignedTinyInteger は0〜255の範囲）
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->text('memo')->nullable()->comment('実施内容メモ');
            $table->timestamps();

            // 同日・同タスク・同メンバーの重複を防ぐ複合ユニーク制約
            $table->unique(['date', 'issue_id', 'member_id']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_scrum_logs');
    }
};
