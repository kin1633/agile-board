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
        Schema::create('sprint_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sprint_id')->constrained()->cascadeOnDelete();
            // デモしたストーリーのIssue ID（任意）
            $table->foreignId('issue_id')->nullable()->constrained()->nullOnDelete();
            // demo: デモ記録 / feedback: フィードバック / decision: 受入/持越決定
            $table->string('type');
            $table->text('content');
            // accepted: 受入完了 / carried_over: 次スプリントへ持越
            $table->string('outcome')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprint_reviews');
    }
};
