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
        Schema::create('pull_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repository_id')->constrained()->cascadeOnDelete();
            // 関連するIssue（1PRにつき1ストーリーを想定、任意）
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            $table->integer('github_pr_number');
            $table->string('title');
            // open / closed / merged
            $table->string('state')->default('open');
            // PR作成者のGitHubログイン名
            $table->string('author_login')->nullable();
            // none / approved / changes_requested / dismissed
            $table->string('review_state')->nullable();
            $table->timestamp('merged_at')->nullable();
            $table->string('head_branch')->nullable();
            $table->string('base_branch')->nullable();
            $table->string('github_url')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['repository_id', 'github_pr_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pull_requests');
    }
};
