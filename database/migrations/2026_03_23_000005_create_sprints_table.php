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
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            // milestone と iteration の両方に対応するため nullable + nullOnDelete
            $table->foreignId('milestone_id')->nullable()->constrained()->nullOnDelete();
            $table->string('github_iteration_id')->nullable()->unique(); // GitHub Projects iteration ID
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('working_days')->default(5); // 稼働日数（祝日週は手動変更可）
            $table->unsignedInteger('iteration_duration_days')->nullable(); // GitHub iteration の日数
            $table->string('state')->default('open');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sprints');
    }
};
