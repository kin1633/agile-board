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
            $table->foreignId('milestone_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('title');
            // start_dateは新規作成時にdue_onの8日前を自動計算。既存レコードは同期時に上書きしない
            $table->date('start_date');
            $table->date('end_date'); // milestones.due_on と同値
            $table->integer('working_days')->default(5); // 稼働日数（祝日週は手動変更可）
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
