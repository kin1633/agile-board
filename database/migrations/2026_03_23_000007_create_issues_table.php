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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('epic_id')->nullable()->constrained()->nullOnDelete();
            // 親issueへの自己参照（サブissue機能）
            $table->foreignId('parent_issue_id')->nullable()->constrained('issues')->nullOnDelete();
            $table->integer('github_issue_number');
            $table->string('title');
            $table->string('state')->default('open');
            $table->string('assignee_login')->nullable();
            // story_points / exclude_velocity は同期時に既存値を保護（上書きしない）
            $table->integer('story_points')->nullable();
            $table->boolean('exclude_velocity')->default(false);
            $table->string('project_status')->nullable(); // GitHub Projects のステータスフィールド値
            $table->decimal('estimated_hours', 8, 2)->nullable(); // 見積もり時間（手動入力）
            $table->decimal('actual_hours', 8, 2)->nullable(); // 実績時間（手動入力）
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['repository_id', 'github_issue_number']);
        });

        // issueとlabelの中間テーブル
        Schema::create('issue_labels', function (Blueprint $table) {
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->primary(['issue_id', 'label_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_labels');
        Schema::dropIfExists('issues');
    }
};
